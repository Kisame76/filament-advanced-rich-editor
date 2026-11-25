<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Export;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PageFormat;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfImages;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfMarkup;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stored rich content as a document of its own, from anywhere: a controller, a job, a
 * command, tinker. `RichEditorPdf` prints it, `RichEditorDocx` writes it for Word; what they
 * share is here - the content, the sheet, the title and language, the cover, the header and
 * the footer, the parts of a combined document, and handing the file over.
 *
 * Built on the renderer and not on the editor, which is the point of it: an export that is
 * a button in a form cannot be queued, attached to a mail or put together with others. The
 * markup comes from `AdvancedRichContentRenderer::toHtml()` - out of its cache where that
 * render was told to keep one - so a document comes out with the same nodes, captions,
 * column widths and mention labels it has on the page. The page format is deliberately not
 * part of that cache: it changes the sheet, not the markup.
 *
 * What is exported is the document as it is stored. Nothing here writes to it; the page view
 * is an output, never content.
 */
abstract class RichEditorExport
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

    protected string|Htmlable|View|bool $cover = false;

    protected string|Htmlable|View|null $header = null;

    protected string|Htmlable|View|null $footer = null;

    protected DateTimeInterface|string|null $date = null;

    /**
     * @var list<RichEditorExport>
     */
    protected array $parts = [];

    protected string|bool $tableOfContents = false;

    protected bool $hasPartTitles = true;

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
     * Several documents as one, each starting on a new page, with page numbers that run on
     * across all of them and a table of contents over all of them.
     *
     * Put together as HTML and written once - not written one by one and merged, which would
     * need a library on top, number every part from one and leave nothing to build a table of
     * contents from.
     *
     * Each part is an export too and brings what belongs to its content: the renderer, the
     * title it is listed and headed with, its language, the address its links and pictures
     * are read against, the hosts its pictures may come from, and - for a PDF - its
     * stylesheets. The sheet, the cover, the header, the footer, the title `{title}` stands
     * for and the output are the combined document's, and the same settings on a part are not
     * read.
     *
     * @param  iterable<RichEditorExport>  $parts
     */
    public static function combine(iterable $parts = []): static
    {
        return (new static(AdvancedRichContentRenderer::make(null)))->append($parts);
    }

    /**
     * Adds parts after the ones there are. Content of the document's own - from `make()`
     * rather than `combine()` - comes before all of them, as a preface.
     *
     * @param  RichEditorExport | iterable<RichEditorExport>  $parts
     */
    public function append(RichEditorExport|iterable $parts): static
    {
        foreach (($parts instanceof RichEditorExport) ? [$parts] : $parts as $part) {
            // A part prints its own content and nothing else, so parts of parts would be
            // dropped without a word - and a document that is its own part would never end.
            if (($part === $this) || ($part->parts !== [])) {
                throw new InvalidArgumentException('A part of a combined document cannot have parts of its own. Append its parts instead.');
            }

            $this->parts[] = $part;
        }

        return $this;
    }

    /**
     * @return list<RichEditorExport>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * A list of the parts on a page of its own, after the cover: each part's title, linked
     * to where it starts, and the page it starts on.
     *
     * A part without a title is not listed. The heading is "Contents" in the document's
     * language unless it is given. In a PDF, DomPDF writes in the page numbers and a Chromium
     * driver prints the list without them; in a Word file it is Word's own table of contents,
     * which Word fills in when the file is opened. For a combined document only - a single one
     * has its headings, and `TableOfContents` lists those.
     */
    public function tableOfContents(string|bool $heading = true): static
    {
        $this->tableOfContents = $heading;

        return $this;
    }

    /**
     * Whether each part opens with its title as a heading. On by default; off for content
     * that carries its own.
     */
    public function partTitles(bool $condition = true): static
    {
        $this->hasPartTitles = $condition;

        return $this;
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

    public function getPage(): PdfPage
    {
        if ($this->size === null) {
            return PdfPage::configured($this->format, $this->isLandscape, $this->margins);
        }

        // A size given by hand is taken the way it was given, unless the orientation was
        // asked for as well.
        [$width, $height] = $this->size;

        if (($this->isLandscape !== null) && ($this->isLandscape === ($width < $height))) {
            [$width, $height] = [$height, $width];
        }

        [$top, $right, $bottom, $left] = $this->margins ?? PdfPage::configuredMargins();

        return new PdfPage($width, $height, $top, $right, $bottom, $left);
    }

    /**
     * The content as the engine receives it, without the document around it: the document's
     * own, then each part in a section of its own.
     */
    public function toBodyHtml(): string
    {
        $sections = [];

        if (trim($content = $this->contentHtml()) !== '') {
            $sections[] = $content;
        }

        foreach ($this->parts as $index => $part) {
            $sections[] = $this->partHtml($part, $index + 1, startsPage: $sections !== []);
        }

        return implode("\n", $sections);
    }

    /**
     * One part: its content, under its title, in a section the table of contents links to.
     *
     * The ids in it are made its own. Every part hands its headings and footnotes ids from
     * one, so two articles with a heading "Introduction" or a first footnote would share an
     * id, and a link in the second would land in the first.
     */
    protected function partHtml(RichEditorExport $part, int $number, bool $startsPage): string
    {
        $id = static::partId($number);
        $prefix = "part-{$number}-";

        $content = (string) preg_replace_callback(
            '/(?<=\s)(id="|href="#)([^"]+)"/',
            static fn (array $match): string => $match[1].$prefix.$match[2].'"',
            $part->contentHtml(),
        );

        $title = ($this->hasPartTitles && filled($part->title))
            ? '<h1 class="fi-arte-pdf-part-title">'.e((string) $part->title).'</h1>'
            : '';

        // A part written in another language than the document around it says so, for the
        // hyphenation a Chromium driver does by it.
        $language = (filled($part->language) && ($part->language !== $this->getLanguage()))
            ? ' lang="'.e((string) $part->language).'"'
            : '';

        // The first part follows the cover or the table of contents, which end their page
        // themselves; a break before it as well would leave a page blank.
        $class = 'fi-arte-pdf-part'.($startsPage ? ' fi-arte-pdf-new-page' : '');

        return "<section id=\"{$id}\" class=\"{$class}\"{$language}>{$title}{$content}</section>";
    }

    protected static function partId(int $number): string
    {
        return "fi-arte-pdf-part-{$number}";
    }

    /**
     * The document's own content, finished for the engine.
     */
    protected function contentHtml(): string
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

    /**
     * "Contents" in the document's language rather than the application's: a German export
     * from an English panel is German throughout. A regional language falls back to its
     * language - `de-AT` reads the German line.
     */
    protected function tableOfContentsHeading(): string
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.pdf.table_of_contents';
        $language = str_replace('-', '_', $this->getLanguage());

        foreach (array_unique([$language, Str::before($language, '_')]) as $locale) {
            if (Lang::hasForLocale($key, $locale)) {
                return (string) __($key, [], $locale);
            }
        }

        return (string) __($key);
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
        $bytes = $this->toString();

        if ($disk !== null) {
            return Storage::disk($disk)->put($path, $bytes);
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        return file_put_contents($path, $bytes) !== false;
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
        $bytes = $this->toString();

        $response = new StreamedResponse(static function () use ($bytes): void {
            echo $bytes;
        }, Response::HTTP_OK, [
            'Content-Type' => static::contentType(),
            'Content-Length' => (string) strlen($bytes),
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

        $extension = '.'.static::extension();

        return str_ends_with(strtolower($filename), $extension) ? $filename : $filename.$extension;
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

    /**
     * What a table of contents lists: every part with a title, and where it starts. Null where
     * none was asked for.
     *
     * @return list<array{title: string, target: string}>|null
     */
    protected function tableOfContentsEntries(): ?array
    {
        if ($this->tableOfContents === false) {
            return null;
        }

        if ($this->parts === []) {
            throw new LogicException('A table of contents lists the parts of a combined document, and this one has none. Use combine(), or TableOfContents for the headings of a single document.');
        }

        $entries = [];

        foreach ($this->parts as $index => $part) {
            if (blank($part->title)) {
                continue;
            }

            $entries[] = [
                'title' => (string) $part->title,
                'target' => static::partId($index + 1),
            ];
        }

        if ($entries === []) {
            throw new LogicException('A table of contents lists the parts by their titles, and none of them has one. Give each part a title().');
        }

        return $entries;
    }

    /**
     * The heading over the table of contents: the one given, or "Contents" in the document's
     * language.
     */
    protected function tableOfContentsTitle(): string
    {
        return is_string($this->tableOfContents) ? $this->tableOfContents : $this->tableOfContentsHeading();
    }

    /**
     * The document, as the bytes of its file.
     */
    abstract public function toString(): string;

    /**
     * The extension of the file, without the dot.
     */
    abstract protected static function extension(): string;

    abstract protected static function contentType(): string;
}
