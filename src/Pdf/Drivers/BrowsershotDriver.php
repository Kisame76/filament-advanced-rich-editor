<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\TakesHeaderAndFooter;
use Kisame76\FilamentAdvancedRichEditor\Pdf\MissingPdfLibrary;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;
use Spatie\Browsershot\Browsershot;

/**
 * Prints with a local Chromium through Spatie's Browsershot.
 *
 * The same fidelity as Gotenberg without a service to run, paid for with Node, Puppeteer and
 * a Chromium on the application server. The paths to them are read from the configuration;
 * anything else Browsershot can be told goes through the closure.
 */
class BrowsershotDriver implements PdfDriver, TakesHeaderAndFooter
{
    /**
     * @param  (Closure(Browsershot): mixed) | null  $configure
     */
    public function __construct(
        protected ?Closure $configure = null,
    ) {}

    public function render(PdfDocument $document): string
    {
        return $this->browsershot($document)->pdf();
    }

    /**
     * The Browsershot that will print, configured but not yet run.
     */
    public function browsershot(PdfDocument $document): Browsershot
    {
        if (! static::isInstalled()) {
            throw MissingPdfLibrary::for('browsershot', 'spatie/browsershot');
        }

        $page = $document->page;

        $browsershot = Browsershot::html($document->html)
            ->paperSize($page->width, $page->height, 'mm')
            ->margins($page->marginTop, $page->marginRight, $page->marginBottom, $page->marginLeft, 'mm')
            // As for Gotenberg: a callout's wash or a table header's fill is content.
            ->showBackground();

        if (($document->header !== null) || ($document->footer !== null)) {
            $browsershot
                ->showBrowserHeaderAndFooter()
                ->headerHtml($document->headerTemplate())
                ->footerHtml($document->footerTemplate());
        }

        $config = (array) config('filament-advanced-rich-editor.pdf.browsershot', []);

        if (filled($config['node'] ?? null)) {
            $browsershot->setNodeBinary((string) $config['node']);
        }

        if (filled($config['npm'] ?? null)) {
            $browsershot->setNpmBinary((string) $config['npm']);
        }

        if (filled($config['chrome'] ?? null)) {
            $browsershot->setChromePath((string) $config['chrome']);
        }

        if ($this->configure !== null) {
            ($this->configure)($browsershot);
        }

        return $browsershot;
    }

    public static function isInstalled(): bool
    {
        return class_exists(Browsershot::class);
    }
}
