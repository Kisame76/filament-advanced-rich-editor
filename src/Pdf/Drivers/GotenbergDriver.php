<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\TakesHeaderAndFooter;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;

/**
 * Prints through a Gotenberg service: Chromium, without Chromium on the application server.
 *
 * What the page looks like on screen is what it looks like on paper, right-to-left scripts
 * and modern CSS included - in exchange for a container to run beside the application.
 * Nothing to install in PHP: the request is an ordinary multipart upload through Laravel's
 * HTTP client, which is also what makes it testable with `Http::fake()`.
 *
 * Sizes are sent in inches, the unit every Gotenberg release reads without a suffix.
 */
class GotenbergDriver implements PdfDriver, TakesHeaderAndFooter
{
    public const ROUTE = '/forms/chromium/convert/html';

    /**
     * @param  array<string, string>  $fields  Further form fields, over the ones set here.
     */
    public function __construct(
        protected ?string $url = null,
        protected ?int $timeout = null,
        protected array $fields = [],
    ) {}

    public function render(PdfDocument $document): string
    {
        $request = Http::timeout($this->timeout ?? (int) config('filament-advanced-rich-editor.pdf.gotenberg.timeout', 60))
            ->attach('files', $document->html, 'index.html', ['Content-Type' => 'text/html; charset=UTF-8']);

        // Gotenberg reads a header and a footer as two more files of the upload, each a page of
        // its own, and fills Chromium's `pageNumber` and `totalPages` in them.
        if (($document->header !== null) || ($document->footer !== null)) {
            $request = $request
                ->attach('files', static::page($document->headerTemplate()), 'header.html', ['Content-Type' => 'text/html; charset=UTF-8'])
                ->attach('files', static::page($document->footerTemplate()), 'footer.html', ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $request
            ->post($this->endpoint(), [...$this->pageFields($document->page), ...$this->fields])
            ->throw()
            ->body();
    }

    protected static function page(string $template): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$template.'</body></html>';
    }

    public function endpoint(): string
    {
        $url = rtrim((string) ($this->url ?? config('filament-advanced-rich-editor.pdf.gotenberg.url')), '/');

        if ($url === '') {
            throw new InvalidArgumentException('The Gotenberg PDF driver needs the address of a Gotenberg service. Set [filament-advanced-rich-editor.pdf.gotenberg.url] or pass one to the driver.');
        }

        return $url.self::ROUTE;
    }

    /**
     * @return array<string, string>
     */
    public function pageFields(PdfPage $page): array
    {
        $inches = static fn (float $millimetres): string => rtrim(rtrim(sprintf('%.4F', $millimetres / 25.4), '0'), '.');

        return [
            'paperWidth' => $inches($page->width),
            'paperHeight' => $inches($page->height),
            'marginTop' => $inches($page->marginTop),
            'marginRight' => $inches($page->marginRight),
            'marginBottom' => $inches($page->marginBottom),
            'marginLeft' => $inches($page->marginLeft),
            // Chromium leaves backgrounds off a printout unless asked, and a callout, a
            // highlighted word or a coloured table header is content rather than decoration.
            'printBackground' => 'true',
        ];
    }
}
