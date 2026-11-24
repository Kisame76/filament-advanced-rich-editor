<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\BrowsershotDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\DomPdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\MissingPdfLibrary;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;
use Smalot\PdfParser\Parser;

/**
 * Each engine, given a document and a sheet. DomPDF prints for real and the result is read
 * back; the two Chromium drivers are checked at the point where they hand over to a service
 * or a browser, which is all of their code there is.
 */
function a4(float $margin = 20): PdfPage
{
    return new PdfPage(210, 297, $margin, $margin, $margin, $margin);
}

describe('DomPDF', function (): void {
    beforeEach(function (): void {
        requires('dompdf/dompdf');
        requires('smalot/pdfparser');

        config()->set('filament-advanced-rich-editor.pdf.dompdf.font_directory', sys_get_temp_dir().'/arte-pdf-fonts');
    });

    it('prints a PDF on the sheet it is given', function (PdfPage $page, array $points): void {
        $pdf = (new DomPdfDriver)->render('<p>Hello</p>', $page);

        $details = (new Parser)->parseContent($pdf)->getPages()[0]->getDetails();

        expect($pdf)->toStartWith('%PDF-')
            ->and(array_map(static fn (mixed $value): float => round((float) $value, 1), array_slice((array) $details['MediaBox'], 2)))->toBe($points);
    })->with([
        'a4' => [new PdfPage(210, 297, 20, 20, 20, 20), [595.3, 841.9]],
        'a4 landscape' => [new PdfPage(297, 210, 20, 20, 20, 20), [841.9, 595.3]],
        'letter' => [new PdfPage(215.9, 279.4, 20, 20, 20, 20), [612.0, 792.0]],
    ]);

    it('sets the text inside the margins the document asks for', function (): void {
        $html = static fn (float $margin): string => '<style>'.(a4($margin))->toCss().'</style><p>Hello</p>';

        $x = static function (string $pdf): float {
            foreach ((new Parser)->parseContent($pdf)->getPages()[0]->getDataTm() as [$matrix, $text]) {
                if (str_contains($text, 'Hello')) {
                    return (float) $matrix[4];
                }
            }

            return -1.0;
        };

        // 20mm is 56.7pt, 40mm is 113.4pt: the line starts at the margin, give or take the
        // glyph's own side bearing.
        expect($x((new DomPdfDriver)->render($html(20), a4(20))))->toBeGreaterThan(56.0)->toBeLessThan(58.0)
            ->and($x((new DomPdfDriver)->render($html(40), a4(40))))->toBeGreaterThan(112.5)->toBeLessThan(114.5);
    });

    it('prints letters beyond Latin-1 rather than question marks', function (): void {
        $pdf = (new DomPdfDriver)->render('<p>Zażółć gęślą jaźń, Ağaç, 5 €, ☑ ☐</p>', a4());

        expect((new Parser)->parseContent($pdf)->getText())->toContain('Zażółć gęślą jaźń, Ağaç, 5 €, ☑ ☐');
    });

    it('fetches nothing a document points at', function (): void {
        // Remote fetching is off: the picture is not drawn, and the attempt is not made.
        Http::preventStrayRequests();

        $pdf = (new DomPdfDriver)->render('<p><img src="http://127.0.0.1:1/probe.png" alt="x"></p><p>After</p>', a4());

        expect((new Parser)->parseContent($pdf)->getText())->toContain('After');
    });

    it('passes DomPDF options through', function (): void {
        $indent = static function (array $options): float {
            $pdf = (new DomPdfDriver($options))->render('<style>@page { margin: 0; }</style><p style="margin: 0 0 0 96px;">Hello</p>', a4(0));

            foreach ((new Parser)->parseContent($pdf)->getPages()[0]->getDataTm() as [$matrix, $text]) {
                if (str_contains($text, 'Hello')) {
                    return round((float) $matrix[4]);
                }
            }

            return -1.0;
        };

        // 96 CSS pixels are an inch, 72pt, at DomPDF's own resolution; told 72 dots to the
        // inch, the same 96 pixels are 96pt.
        expect($indent([]))->toBe(72.0)
            ->and($indent(['dpi' => 72]))->toBe(96.0);
    });

    it('says how to install DomPDF where it is missing', function (): void {
        $driver = new class extends DomPdfDriver
        {
            public static function isInstalled(): bool
            {
                return false;
            }
        };

        $driver->render('<p>x</p>', a4());
    })->throws(MissingPdfLibrary::class, 'The [dompdf] PDF driver needs [dompdf/dompdf]. Install it with `composer require dompdf/dompdf`.');
});

describe('Gotenberg', function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();
    });

    it('uploads the document with the sheet in inches', function (): void {
        Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 gotenberg')]);

        $pdf = (new GotenbergDriver('http://gotenberg:3000/'))->render('<p>Hello</p>', new PdfPage(210, 297, 25.4, 12.7, 25.4, 12.7));

        expect($pdf)->toBe('%PDF-1.7 gotenberg');

        Http::assertSent(function (Request $request): bool {
            $parts = collect($request->data())->keyBy('name');

            return ($request->url() === 'http://gotenberg:3000/forms/chromium/convert/html')
                && $request->isMultipart()
                && ($parts['files']['filename'] === 'index.html')
                && ($parts['files']['contents'] === '<p>Hello</p>')
                && ($parts['paperWidth']['contents'] === '8.2677')
                && ($parts['paperHeight']['contents'] === '11.6929')
                && ($parts['marginTop']['contents'] === '1')
                && ($parts['marginRight']['contents'] === '0.5')
                && ($parts['printBackground']['contents'] === 'true');
        });
    });

    it('reads the service\'s address from the configuration, and sends further fields', function (): void {
        config()->set('filament-advanced-rich-editor.pdf.gotenberg.url', 'https://pdf.internal');

        Http::fake(['pdf.internal/*' => Http::response('%PDF')]);

        (new GotenbergDriver(fields: ['emulatedMediaType' => 'screen']))->render('<p>x</p>', a4());

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://pdf.internal/forms/')
            && collect($request->data())->contains(fn (array $part): bool => ($part['name'] === 'emulatedMediaType') && ($part['contents'] === 'screen')));
    });

    it('fails loudly where the service refuses', function (): void {
        Http::fake(['gotenberg:3000/*' => Http::response('Invalid form data', 400)]);

        (new GotenbergDriver('http://gotenberg:3000'))->render('<p>x</p>', a4());
    })->throws(RequestException::class);

    it('says what is missing where no address was given', function (): void {
        config()->set('filament-advanced-rich-editor.pdf.gotenberg.url', null);

        (new GotenbergDriver)->render('<p>x</p>', a4());
    })->throws(InvalidArgumentException::class, 'The Gotenberg PDF driver needs the address of a Gotenberg service.');
});

describe('Browsershot', function (): void {
    beforeEach(function (): void {
        requires('spatie/browsershot');
    });

    it('asks Chromium for the sheet and the margins in millimetres, backgrounds included', function (): void {
        $command = (new BrowsershotDriver)->browsershot('<p>x</p>', new PdfPage(297, 210, 10, 12, 14, 16))->createPdfCommand();

        expect($command['action'])->toBe('pdf')
            ->and($command['options']['width'])->toBe('297mm')
            ->and($command['options']['height'])->toBe('210mm')
            ->and($command['options']['margin'])->toBe(['top' => '10mm', 'right' => '12mm', 'bottom' => '14mm', 'left' => '16mm'])
            ->and($command['options']['printBackground'])->toBeTrue();
    });

    it('takes the binaries from the configuration and anything else from the closure', function (): void {
        config()->set('filament-advanced-rich-editor.pdf.browsershot', [
            'node' => '/opt/node/bin/node',
            'npm' => null,
            'chrome' => '/opt/chrome/chrome',
        ]);

        $browsershot = (new BrowsershotDriver(fn ($browsershot) => $browsershot->noSandbox()))->browsershot('<p>x</p>', a4());

        $command = $browsershot->createPdfCommand();

        expect($command['options']['executablePath'])->toBe('/opt/chrome/chrome')
            ->and($command['options']['args'])->toContain('--no-sandbox')
            ->and((fn () => $this->nodeBinary)->call($browsershot))->toBe('/opt/node/bin/node');
    });

    it('says how to install Browsershot where it is missing', function (): void {
        $driver = new class extends BrowsershotDriver
        {
            public static function isInstalled(): bool
            {
                return false;
            }
        };

        $driver->render('<p>x</p>', a4());
    })->throws(MissingPdfLibrary::class, 'composer require spatie/browsershot');
});
