<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\MissingPdfLibrary;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;

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

    public function render(string $html, PdfPage $page): string
    {
        if (! static::isInstalled()) {
            throw MissingPdfLibrary::for('dompdf', 'dompdf/dompdf');
        }

        $dompdf = new Dompdf(new Options([...$this->defaultOptions(), ...$this->options]));

        $dompdf->setPaper([0.0, 0.0, ...$page->sizeInPoints()]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
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
