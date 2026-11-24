<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers;

use DOMElement;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\MissingPdfLibrary;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;

/**
 * Prints with DomPDF: plain PHP, nothing to install on the server, so an export runs on
 * every host this package runs on. That is the reason it is the default, and also its
 * limit - it reads CSS 2.1 and a little more, which is why the document it is handed comes
 * with a stylesheet of its own rather than the one the editor is drawn with.
 *
 * Not suitable for right-to-left scripts: DomPDF lays text out left to right, one glyph
 * after another, without shaping. Arabic arrives in the wrong order with its letters
 * unjoined. A Chromium driver prints it correctly.
 */
class DomPdfDriver implements PdfDriver
{
    /**
     * @param  array<string, mixed>  $options  DomPDF's own options, over the ones set here.
     */
    public function __construct(
        protected array $options = [],
    ) {}

    public function render(PdfDocument $document): string
    {
        if (! static::isInstalled()) {
            throw MissingPdfLibrary::for('dompdf', 'dompdf/dompdf');
        }

        $references = array_fill_keys($document->pageReferences(), true);
        $pages = [];

        // Where each element a table of contents points at starts, noted as the pages are
        // drawn: the first time a frame of it is, since an element split across pages is
        // drawn once on each.
        $dompdf = $this->print($document, ($references === []) ? [] : [[
            'event' => 'begin_frame',
            'f' => static function (Frame $frame, Canvas $canvas) use ($references, &$pages): void {
                $node = $frame->get_node();

                if (($node instanceof DOMElement) && isset($references[$id = $node->getAttribute('id')]) && (! isset($pages[$id]))) {
                    $pages[$id] = $canvas->get_page_number();
                }
            },
        ]]);

        // The number of pages, where a header or a footer shows it, and the pages a table of
        // contents shows. DomPDF knows them only once it has laid the document out, and CSS's
        // `counter(pages)` comes out as nought - so it prints once to count and again with the
        // numbers written in. The header and the footer stand in the margins, and a number in
        // a table of contents sits in a column of its own, so nothing in the text moves and
        // the numbers hold.
        if ($document->countsPages() || ($references !== [])) {
            $dompdf = $this->print($document
                ->withPageCount($dompdf->getCanvas()->get_page_count())
                ->withPageNumbers($pages));
        }

        return (string) $dompdf->output();
    }

    /**
     * @param  array<int, array{event: string, f: callable}>  $callbacks
     */
    protected function print(PdfDocument $document, array $callbacks = []): Dompdf
    {
        $dompdf = new Dompdf(new Options([...$this->defaultOptions(), ...$this->options]));

        if ($callbacks !== []) {
            $dompdf->setCallbacks($callbacks);
        }

        $dompdf->setPaper([0.0, 0.0, ...$document->page->sizeInPoints()]);
        $dompdf->loadHtml($document->html, 'UTF-8');
        $dompdf->render();

        return $dompdf;
    }

    public static function isInstalled(): bool
    {
        return class_exists(Dompdf::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultOptions(): array
    {
        return [
            // Every picture arrives inlined, so there is nothing to fetch - and a document is
            // text somebody typed. Nothing in it should be able to make the server request an
            // address, which is what a remote-enabled DomPDF does for every `src` and `url()`.
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            // The face DomPDF ships that covers more than Latin-1. Its own default is
            // Helvetica, one of the PDF base fonts, which prints `ł`, `ğ` or `€` as `?`.
            'defaultFont' => 'DejaVu Sans',
            // Somewhere the application can write, and that is a cache: DomPDF writes the
            // metrics of every face it uses there on first use, and converts a project's
            // `@font-face`. Its own default is inside `vendor`.
            'fontDir' => $this->fontDirectory(),
            'fontCache' => $this->fontDirectory(),
        ];
    }

    protected function fontDirectory(): string
    {
        $directory = (string) config('filament-advanced-rich-editor.pdf.dompdf.font_directory', storage_path('framework/cache/dompdf-fonts'));

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, recursive: true);
        }

        return $directory;
    }
}
