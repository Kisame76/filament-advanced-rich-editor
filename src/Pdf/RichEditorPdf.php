<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Kisame76\FilamentAdvancedRichEditor\Export\RichEditorExport;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\TakesHeaderAndFooter;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\BrowsershotDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\DomPdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;

/**
 * Stored rich content as a PDF, from anywhere: a controller, a job, a command, tinker.
 *
 *     RichEditorPdf::make($article->content)->page('a4')->save('exports/article.pdf');
 *
 * Or several documents as one, each on a page of its own:
 *
 *     RichEditorPdf::combine($articles->map(
 *         fn (Article $article) => RichEditorPdf::forRecord($article, 'content')->title($article->title),
 *     ))->cover()->tableOfContents()->pageNumbers()->save('exports/articles.pdf');
 *
 * Everything but the printing is `RichEditorExport`'s, which `RichEditorDocx` shares: the
 * same document, configured the same way, comes out as a Word file there.
 */
class RichEditorPdf extends RichEditorExport
{
    /**
     * @var array<int, string>
     */
    protected array $stylesheets = [];

    protected PdfDriver|string|null $driver = null;

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
     * The engine that prints: `dompdf`, `gotenberg`, `browsershot`, the class name of a
     * driver, or a driver itself.
     */
    public function driver(PdfDriver|string|null $driver): static
    {
        $this->driver = $driver;

        return $this;
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
            'tableOfContents' => $this->tableOfContentsHtml(),
            'language' => $this->getLanguage(),
            'stylesheet' => implode("\n", [...$stylesheet, static::packageStylesheet(), ...$this->getStylesheets()]),
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

    /**
     * The document's stylesheets, then those its parts brought, each once.
     *
     * @return list<string>
     */
    public function getStylesheets(): array
    {
        return array_values(array_unique([
            ...$this->stylesheets,
            ...array_merge(...array_map(static fn (RichEditorExport $part): array => ($part instanceof RichEditorPdf) ? $part->stylesheets : [], $this->parts)),
        ]));
    }

    protected function tableOfContentsHtml(): ?string
    {
        $entries = $this->tableOfContentsEntries();

        if ($entries === null) {
            return null;
        }

        return view('filament-advanced-rich-editor::pdf.table-of-contents', [
            'heading' => $this->tableOfContentsTitle(),
            'entries' => array_map(static fn (array $entry): array => [
                ...$entry,
                'page' => new HtmlString(PdfDocument::pageOf($entry['target'])),
            ], $entries),
        ])->render();
    }

    public static function packageStylesheet(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/pdf/document.css');
    }

    protected static function extension(): string
    {
        return 'pdf';
    }

    protected static function contentType(): string
    {
        return 'application/pdf';
    }
}
