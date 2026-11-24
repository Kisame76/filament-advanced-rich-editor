<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\TakesHeaderAndFooter;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\BrowsershotDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\DomPdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stored rich content as a PDF, from anywhere: a controller, a job, a command, tinker.
 *
 *     RichEditorPdf::make($article->content)->page('a4')->save('exports/article.pdf');
 *
 * Built on the renderer and not on the editor, which is the point of it: an export that is
 * a button in a form cannot be queued, attached to a mail or put together with others. The
 * markup comes from `AdvancedRichContentRenderer::toHtml()` - out of its cache where that
 * render was told to keep one - so a document prints with the same nodes, captions, column
 * widths and mention labels it has on the page. The page format is deliberately not part
 * of that cache: it changes the sheet, not the markup.
 *
 * What is printed is the document as it is stored. Nothing here writes to it; the page view
 * is an output, never content.
 */
class RichEditorPdf
{
    protected AdvancedRichContentRenderer $renderer;

    protected ?PageFormat $format = null;

    /**
     * @var array{0: float, 1: float}|null
     */
    protected ?array $size = null;

    protected ?bool $isLandscape = null;

    /**
     * @var array{0: float, 1: float, 2: float, 3: float}|null
     */
    protected ?array $margins = null;

    protected ?string $title = null;

    protected ?string $language = null;

    protected ?string $baseUrl = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $imageHosts = null;

    /**
     * @var array<int, string>
     */
    protected array $stylesheets = [];

    protected PdfDriver|string|null $driver = null;

    protected string|Htmlable|View|bool $cover = false;

    protected string|Htmlable|View|null $header = null;

    protected string|Htmlable|View|null $footer = null;

    protected DateTimeInterface|string|null $date = null;

    final public function __construct(AdvancedRichContentRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * @param  string | array<string, mixed> | null  $content
     */
    public static function make(string|array|null $content = null): static
    {
        return new static(AdvancedRichContentRenderer::make($content));
    }

    /**
     * Prints one rich content attribute of a model, with everything the model registered
     * for it: its plugins, its mentions, its merge tags, and the disk or provider its
     * pictures are read from. A job needs nothing more than the record and the name.
     */
    public static function forRecord(Model&HasRichContent $record, string $attribute): static
    {
        $content = $record->getAttribute($attribute);

        $renderer = AdvancedRichContentRenderer::make(is_array($content) || is_string($content) ? $content : null);

        if (($registered = $record->getRichContentAttribute($attribute)) !== null) {
            $renderer->attribute($registered);
        }

        return new static($renderer);
    }

    /**
     * Prints a renderer a project already configured - the one its pages render with, so
     * the PDF and the page cannot drift apart.
     */
    public static function fromRenderer(AdvancedRichContentRenderer $renderer): static
    {
        return new static($renderer);
    }

    /**
     * Configures the renderer this PDF prints, in place: plugins, mentions, the attachment
     * disk, anything the renderer takes.
     *
     * @param  Closure(AdvancedRichContentRenderer): mixed  $configure
     */
    public function renderer(Closure $configure): static
    {
        $configure($this->renderer);

        return $this;
    }

    public function getRenderer(): AdvancedRichContentRenderer
    {
        return $this->renderer;
    }

    public function page(string|PageFormat $format): static
    {
        $this->format = is_string($format)
            ? (PageFormat::tryFrom(strtolower($format)) ?? throw new InvalidArgumentException("Unknown page format [{$format}]. Use one of: ".implode(', ', array_column(PageFormat::cases(), 'value')).', or size().'))
            : $format;
        $this->size = null;

        return $this;
    }

    /**
     * A sheet of any size, in millimetres, taken as given - wider than tall is landscape.
     */
    public function size(float $width, float $height): static
    {
        if (($width <= 0) || ($height <= 0)) {
            throw new InvalidArgumentException('A page needs a width and a height above zero.');
        }

        $this->size = [$width, $height];
        $this->format = null;

        return $this;
    }

    public function landscape(bool $condition = true): static
    {
        $this->isLandscape = $condition;

        return $this;
    }

    public function portrait(): static
    {
        return $this->landscape(false);
    }

    /**
     * The margins in millimetres, read the way CSS reads its shorthand: one value for all
     * four sides, two for top and bottom then the sides, four clockwise from the top.
     */
    public function margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
    {
        $right ??= $top;
        $bottom ??= $top;
        $left ??= $right;

        foreach ([$top, $right, $bottom, $left] as $margin) {
            if ($margin < 0) {
                throw new InvalidArgumentException('A margin cannot be negative.');
            }
        }

        $this->margins = [$top, $right, $bottom, $left];

        return $this;
    }

    /**
     * The document's title, which a PDF reader shows in its window and its tab.
     */
    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The language the document is written in, for the `lang` a Chromium driver hyphenates
     * by. The application's locale where none is given.
     */
    public function language(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * The address relative links and pictures are read against. The application's URL where
     * none is given - which is wrong in a queue worker whose `APP_URL` is not the site's.
     */
    public function baseUrl(?string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Hosts whose pictures may be fetched for the PDF, on top of the application's own
     * disks. None by default: a picture somebody linked from elsewhere is a request the
     * server makes on their behalf, and that is the project's decision to allow.
     *
     * @param  array<int, string>  $hosts
     */
    public function imageHosts(array $hosts): static
    {
        $this->imageHosts = array_values($hosts);

        return $this;
    }

    /**
     * Further CSS, after the package's own: a project's typeface, its brand colours, the
     * classes its styles put on blocks. Called again, it adds.
     */
    public function stylesheet(string $css): static
    {
        $this->stylesheets[] = $css;

        return $this;
    }

    /**
     * A first page of its own, in front of the content: the package's - the title and the
     * date - where none is given, or markup of the project's. Neither the header nor the
     * footer is printed on it.
     *
     * Markup may be a string, a view or anything `Htmlable`, and may use the same
     * `{title}` and `{date}` the header and the footer do.
     */
    public function cover(string|Htmlable|View|bool $cover = true): static
    {
        $this->cover = $cover;

        return $this;
    }

    /**
     * Markup printed at the top of every page but the cover, in the top margin.
     *
     * A string, a view or anything `Htmlable`. `{page}` and `{pages}` become the
     * page's number and the number of pages, `{title}` and `{date}` the document's - write
     * them literally, in a Blade view as well. The margin has to be deep enough to hold it.
     */
    public function header(string|Htmlable|View|null $header): static
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Markup printed at the bottom of every page but the cover, in the bottom margin. See
     * `header()` for what it may hold.
     */
    public function footer(string|Htmlable|View|null $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    /**
     * The page numbers as the footer, centred: `2 / 7` unless the format says otherwise -
     * `->pageNumbers('Seite {page} von {pages}')`. Pages are counted from the first sheet,
     * the cover included.
     */
    public function pageNumbers(string $format = '{page} / {pages}'): static
    {
        return $this->footer(new HtmlString('<p class="fi-arte-pdf-page-numbers">'.e($format).'</p>'));
    }

    /**
     * The date `{date}` stands for: a date, written the long way in the document's language,
     * or text taken as it is. Today where none is given.
     */
    public function date(DateTimeInterface|string|null $date): static
    {
        $this->date = $date;

        return $this;
    }

    /**
     * The engine that prints: `dompdf`, `gotenberg`, `browsershot`, the class name of a
     * driver, or a driver itself.
     */
    public function driver(PdfDriver|string|null $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getPage(): PdfPage
    {
        [$width, $height] = $this->size ?? ($this->format ?? $this->configuredFormat())->dimensions();

        // A size given by hand is taken the way it was given, unless the orientation was
        // asked for as well; a named format is upright and turned by the configuration.
        $isLandscape = $this->isLandscape ?? (($this->size === null)
            ? (config('filament-advanced-rich-editor.pdf.orientation', 'portrait') === 'landscape')
            : null);

        if (($isLandscape !== null) && ($isLandscape === ($width < $height))) {
            [$width, $height] = [$height, $width];
        }

        [$top, $right, $bottom, $left] = $this->margins ?? $this->configuredMargins();

        return new PdfPage($width, $height, $top, $right, $bottom, $left);
    }

    public function getDriver(): PdfDriver
    {
        $driver = $this->driver ?? config('filament-advanced-rich-editor.pdf.driver', 'dompdf');

        if ($driver instanceof PdfDriver) {
            return $driver;
        }

        $driver = (string) $driver;

        $resolved = match ($driver) {
            'dompdf' => app(DomPdfDriver::class),
            'gotenberg' => app(GotenbergDriver::class),
            'browsershot' => app(BrowsershotDriver::class),
            default => class_exists($driver) ? app($driver) : null,
        };

        if (! $resolved instanceof PdfDriver) {
            throw new InvalidArgumentException("[{$driver}] is not a PDF driver. Use dompdf, gotenberg, browsershot or a class implementing ".PdfDriver::class.'.');
        }

        return $resolved;
    }

    /**
     * The content as the engine receives it, without the document around it.
     */
    public function toBodyHtml(): string
    {
        $images = new PdfImages(
            renderer: $this->renderer,
            baseUrl: $this->baseUrl,
            hosts: $this->imageHosts ?? (array) config('filament-advanced-rich-editor.pdf.images.hosts', []),
            maxBytes: (int) config('filament-advanced-rich-editor.pdf.images.max_bytes', 10_485_760),
            timeout: (int) config('filament-advanced-rich-editor.pdf.images.timeout', 10),
        );

        return (new PdfMarkup($images, $this->baseUrl))->apply($this->renderer->toHtml());
    }

    /**
     * The whole HTML document the engine prints: the sheet, the stylesheet, the cover, the
     * header, the footer and the content - as DomPDF receives it.
     */
    public function toHtml(): string
    {
        return $this->toDocument()->html;
    }

    /**
     * What a driver is handed. A driver that prints the header and the footer itself gets
     * them apart from the document; any other finds them in it.
     */
    public function toDocument(?PdfDriver $driver = null): PdfDocument
    {
        $page = $this->getPage();
        $apart = $driver instanceof TakesHeaderAndFooter;

        $header = $this->runningElement($this->header);
        $footer = $this->runningElement($this->footer);
        $cover = $this->coverHtml();

        $stylesheet = [$page->toCss()];

        if ($cover !== null) {
            $stylesheet[] = $page->coverCss();
        }

        if ((! $apart) && (($header !== null) || ($footer !== null))) {
            $stylesheet[] = $page->runningElementsCss();
        }

        $html = view('filament-advanced-rich-editor::pdf.document', [
            'body' => $this->toBodyHtml(),
            'cover' => $cover,
            'header' => $apart ? null : $header,
            'footer' => $apart ? null : $footer,
            'language' => $this->getLanguage(),
            'stylesheet' => implode("\n", [...$stylesheet, static::packageStylesheet(), ...$this->stylesheets]),
            'title' => $this->title,
        ])->render();

        return new PdfDocument(
            html: $html,
            page: $page,
            header: $apart ? $header : null,
            footer: $apart ? $footer : null,
        );
    }

    /**
     * The PDF, as bytes.
     */
    public function toString(): string
    {
        $driver = $this->getDriver();

        return $driver->render($this->toDocument($driver));
    }

    public function getLanguage(): string
    {
        return $this->language ?? str_replace('_', '-', app()->getLocale());
    }

    /**
     * The text `{date}` stands for.
     */
    public function getDate(): string
    {
        if (is_string($this->date)) {
            return $this->date;
        }

        return Carbon::instance($this->date ?? now())
            ->locale(str_replace('-', '_', $this->getLanguage()))
            ->isoFormat('LL');
    }

    protected function coverHtml(): ?string
    {
        if ($this->cover === false) {
            return null;
        }

        $cover = ($this->cover === true)
            ? view('filament-advanced-rich-editor::pdf.cover', ['title' => $this->title, 'date' => $this->getDate()])
            : $this->cover;

        return $this->resolveTokens(static::markup($cover));
    }

    protected function runningElement(string|Htmlable|View|null $markup): ?string
    {
        if ($markup === null) {
            return null;
        }

        return $this->resolveTokens(static::markup($markup));
    }

    protected static function markup(string|Htmlable|View $markup): string
    {
        return match (true) {
            $markup instanceof View => $markup->render(),
            $markup instanceof Htmlable => $markup->toHtml(),
            default => $markup,
        };
    }

    /**
     * The four words a cover, a header and a footer may use. The title and the date are
     * escaped: they come from the record, not from the project's markup.
     */
    protected function resolveTokens(string $markup): string
    {
        return strtr($markup, [
            '{page}' => PdfDocument::PAGE_NUMBER,
            '{pages}' => PdfDocument::PAGE_COUNT,
            '{title}' => e((string) $this->title),
            '{date}' => e($this->getDate()),
        ]);
    }

    /**
     * Writes the PDF to a disk, or to a path on this machine where no disk is named.
     */
    public function save(string $path, ?string $disk = null): bool
    {
        $pdf = $this->toString();

        if ($disk !== null) {
            return Storage::disk($disk)->put($path, $pdf);
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        return file_put_contents($path, $pdf) !== false;
    }

    /**
     * A response that saves the PDF under the given name. Returned from a Filament action, it
     * downloads; Livewire passes a streamed response through.
     */
    public function download(?string $filename = null): StreamedResponse
    {
        return $this->respond($filename, 'attachment');
    }

    /**
     * A response that opens the PDF in the browser.
     */
    public function inline(?string $filename = null): StreamedResponse
    {
        return $this->respond($filename, 'inline');
    }

    protected function respond(?string $filename, string $disposition): StreamedResponse
    {
        $filename = $this->filename($filename);
        $pdf = $this->toString();

        $response = new StreamedResponse(static function () use ($pdf): void {
            echo $pdf;
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($pdf),
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $disposition,
            $filename,
            static::asciiFallback($filename),
        ));

        return $response;
    }

    protected function filename(?string $filename): string
    {
        $filename = trim((string) ($filename ?? $this->title ?? 'document'));
        $filename = trim(str_replace(['/', '\\', "\0"], '-', $filename));

        if ($filename === '') {
            $filename = 'document';
        }

        return str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';
    }

    /**
     * The name for a client that cannot read the UTF-8 one beside it. Symfony refuses a
     * fallback that is not ASCII, and a title is very often not.
     */
    protected static function asciiFallback(string $filename): string
    {
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%"]/', '', Str::ascii($filename));

        return (trim(pathinfo($fallback, PATHINFO_FILENAME)) === '') ? 'document.pdf' : $fallback;
    }

    public static function packageStylesheet(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/pdf/document.css');
    }

    protected function configuredFormat(): PageFormat
    {
        $format = (string) config('filament-advanced-rich-editor.pdf.page', 'a4');

        return PageFormat::tryFrom(strtolower($format)) ?? PageFormat::A4;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function configuredMargins(): array
    {
        $margins = array_values(array_map('floatval', (array) config('filament-advanced-rich-editor.pdf.margins', [20, 20, 20, 20])));

        return match (count($margins)) {
            1 => [$margins[0], $margins[0], $margins[0], $margins[0]],
            2 => [$margins[0], $margins[1], $margins[0], $margins[1]],
            3 => [$margins[0], $margins[1], $margins[2], $margins[1]],
            4 => [$margins[0], $margins[1], $margins[2], $margins[3]],
            default => [20.0, 20.0, 20.0, 20.0],
        };
    }
}
