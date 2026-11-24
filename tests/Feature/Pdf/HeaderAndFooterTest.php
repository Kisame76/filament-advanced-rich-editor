<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\BrowsershotDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\DomPdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

/**
 * The cover, the header, the footer and the page numbers: what turns text printed on paper
 * into a document. Assembled once, printed by DomPDF as part of the document and by Chromium
 * from templates of its own.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-11-24 10:00:00');

    config()->set('filament-advanced-rich-editor.pdf.dompdf.font_directory', sys_get_temp_dir().'/arte-pdf-fonts');

    $this->long = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.</p>', 70);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('assembling', function (): void {
    it('prints no cover, header or footer unless asked', function (): void {
        $html = RichEditorPdf::make('<p>x</p>')->toHtml();

        expect($html)
            ->not->toContain('class="fi-arte-pdf-cover"')
            ->not->toContain('class="fi-arte-pdf-header"')
            ->not->toContain('class="fi-arte-pdf-footer"')
            ->not->toContain('@page :first {');
    });

    it('puts the title and the date on the package\'s cover, in the document\'s language', function (): void {
        $html = RichEditorPdf::make('<p>x</p>')->title('Quartalsbericht')->language('de')->cover()->toHtml();

        expect($html)
            ->toContain('<h1 class="fi-arte-pdf-cover-title">Quartalsbericht</h1>')
            ->toContain('<p class="fi-arte-pdf-cover-date">24. November 2026</p>')
            // No margins on the cover's page, so the header and the footer have nowhere to stand.
            ->toContain('@page :first { margin: 0; } .fi-arte-pdf-cover { padding: 20mm 20mm 20mm 20mm; }');
    });

    it('takes a cover of the project\'s, with the same words a header uses', function (): void {
        $html = RichEditorPdf::make('<p>x</p>')
            ->title('Q3 <draft>')
            ->date('Herbst 2026')
            ->cover(new HtmlString('<div class="brand">{title} · {date}</div>'))
            ->toHtml();

        expect($html)->toContain('<div class="brand">Q3 &lt;draft&gt; · Herbst 2026</div>')
            ->not->toContain('<h1 class="fi-arte-pdf-cover-title"');
    });

    it('takes the cover away again', function (): void {
        expect(RichEditorPdf::make('<p>x</p>')->cover()->cover(false)->toHtml())->not->toContain('class="fi-arte-pdf-cover"');
    });

    it('writes the page number and the number of pages where the markup asks for them', function (): void {
        $html = RichEditorPdf::make('<p>x</p>')
            ->title('Report')
            ->header('<p>{title}</p>')
            ->footer('<p>{page} of {pages}</p>')
            ->toHtml();

        expect($html)
            ->toContain('<div class="fi-arte-pdf-header"><p>Report</p></div>')
            ->toContain('<div class="fi-arte-pdf-footer"><p>'.PdfDocument::PAGE_NUMBER.' of '.PdfDocument::PAGE_COUNT.'</p></div>')
            // Where they stand: in the margins, measured from the sheet.
            ->toContain('.fi-arte-pdf-header { top: -12mm; } .fi-arte-pdf-footer { bottom: -12mm; }');
    });

    it('takes a view as the header', function (): void {
        $html = RichEditorPdf::make('<p>x</p>')
            ->header(view()->file(__DIR__.'/../../Fixtures/Documents/header.blade.php', ['brand' => 'Acme']))
            ->toHtml();

        expect($html)->toContain('<div class="fi-arte-pdf-header"><p class="brand">Acme · '.PdfDocument::PAGE_NUMBER.'</p>');
    });

    it('sets the page numbers as the footer, in the format it is given', function (): void {
        expect(RichEditorPdf::make('<p>x</p>')->pageNumbers()->toHtml())
            ->toContain('<p class="fi-arte-pdf-page-numbers">'.PdfDocument::PAGE_NUMBER.' / '.PdfDocument::PAGE_COUNT.'</p>')
            ->and(RichEditorPdf::make('<p>x</p>')->pageNumbers('Seite {page} von {pages} <b>')->toHtml())
            ->toContain('<p class="fi-arte-pdf-page-numbers">Seite '.PdfDocument::PAGE_NUMBER.' von '.PdfDocument::PAGE_COUNT.' &lt;b&gt;</p>');
    });

    it('writes a date the long way in the document\'s language, and text as it is', function (): void {
        expect(RichEditorPdf::make('<p>x</p>')->language('fr')->getDate())->toBe('24 novembre 2026')
            ->and(RichEditorPdf::make('<p>x</p>')->language('en')->date(Carbon::parse('2026-03-01'))->getDate())->toBe('March 1, 2026')
            ->and(RichEditorPdf::make('<p>x</p>')->date('Q1')->getDate())->toBe('Q1');
    });

    it('leaves the words alone in the content, which is somebody\'s text', function (): void {
        expect(RichEditorPdf::make('<p>Write {page} or {title} here.</p>')->title('T')->pageNumbers()->toBodyHtml())
            ->toBe('<p>Write {page} or {title} here.</p>');
    });

    it('hands a driver that prints them itself the header and the footer apart from the document', function (): void {
        $pdf = RichEditorPdf::make('<p>x</p>')->header('<p>Head</p>')->footer('<p>{page}</p>');

        $document = $pdf->toDocument(new GotenbergDriver('http://gotenberg'));

        expect($document->header)->toBe('<p>Head</p>')
            ->and($document->footer)->toBe('<p>'.PdfDocument::PAGE_NUMBER.'</p>')
            ->and($document->html)->not->toContain('class="fi-arte-pdf-header"')
            ->not->toContain('.fi-arte-pdf-header { top:')
            // DomPDF's, by contrast, carries them inside.
            ->and($pdf->toDocument()->header)->toBeNull()
            ->and($pdf->toDocument()->html)->toContain('<div class="fi-arte-pdf-header"><p>Head</p></div>');
    });
});

describe('printed by DomPDF', function (): void {
    beforeEach(function (): void {
        requires('dompdf/dompdf');
        requires('smalot/pdfparser');
    });

    /**
     * The text of each page, whitespace folded.
     *
     * @return array<int, string>
     */
    function pagesOf(string $pdf): array
    {
        return array_map(
            static fn ($page): string => trim((string) preg_replace('/\s+/', ' ', $page->getText())),
            (new Parser)->parseContent($pdf)->getPages(),
        );
    }

    it('numbers every page and counts them', function (): void {
        $pages = pagesOf(RichEditorPdf::make($this->long)->pageNumbers('Page {page} of {pages}')->toString());

        expect(count($pages))->toBeGreaterThanOrEqual(3);

        foreach ($pages as $index => $text) {
            expect($text)->toContain('Page '.($index + 1).' of '.count($pages));
        }
    });

    it('prints once where nothing asks for the number of pages', function (): void {
        $printed = 0;

        $driver = new class($printed) extends DomPdfDriver
        {
            public function __construct(public int &$printed)
            {
                parent::__construct();
            }

            protected function print(PdfDocument $document): Dompdf\Dompdf
            {
                $this->printed++;

                return parent::print($document);
            }
        };

        RichEditorPdf::make($this->long)->footer('<p>{page}</p>')->driver($driver)->toString();
        expect($printed)->toBe(1);

        RichEditorPdf::make($this->long)->footer('<p>{page} / {pages}</p>')->driver($driver)->toString();
        expect($printed)->toBe(3);
    });

    it('keeps the header and the footer off the cover, and counts the cover as a page', function (): void {
        $pdf = RichEditorPdf::make($this->long)
            ->title('Quarterly report')
            ->cover()
            ->header('<p>Running head</p>')
            ->pageNumbers()
            ->toString();

        $document = (new Parser)->parseContent($pdf);
        $height = (float) ((array) $document->getPages()[0]->getDetails()['MediaBox'])[3];

        // On the cover they are still in the document - DomPDF repeats a fixed element on
        // every page - but off the sheet, where nothing draws them.
        $onSheet = static function (Document $document, int $page, string $needle) use ($height): bool {
            foreach ($document->getPages()[$page]->getDataTm() as [$matrix, $text]) {
                if (str_contains($text, $needle) && ((float) $matrix[5] >= 0.0) && ((float) $matrix[5] <= $height)) {
                    return true;
                }
            }

            return false;
        };

        $pages = count($document->getPages());

        expect($onSheet($document, 0, 'Quarterly report'))->toBeTrue()
            ->and($onSheet($document, 0, 'Running head'))->toBeFalse()
            ->and($onSheet($document, 1, 'Running head'))->toBeTrue()
            ->and($onSheet($document, $pages - 1, 'Running head'))->toBeTrue()
            ->and(pagesOf($pdf)[1])->toContain('2 / '.$pages);
    });

    it('sets the header in the top margin and the footer in the bottom one', function (): void {
        $document = (new Parser)->parseContent(RichEditorPdf::make($this->long)->header('<p>Head</p>')->footer('<p>Foot</p>')->toString());
        $page = $document->getPages()[1];
        $height = (float) ((array) $page->getDetails()['MediaBox'])[3];

        $y = static function (string $needle) use ($page): ?float {
            foreach ($page->getDataTm() as [$matrix, $text]) {
                if (str_contains($text, $needle)) {
                    return (float) $matrix[5];
                }
            }

            return null;
        };

        // 20mm margins are 56.7pt: the header's line above the text's top edge, the footer's
        // below its bottom one, both on the sheet.
        expect($y('Head'))->toBeGreaterThan($height - 56.7)->toBeLessThan($height)
            ->and($y('Foot'))->toBeGreaterThan(0.0)->toBeLessThan(56.7)
            ->and($y('Lorem'))->toBeLessThan($height - 56.7);
    });
});

describe('printed by Chromium', function (): void {
    it('uploads the header and the footer to Gotenberg as pages of their own', function (): void {
        Http::preventStrayRequests();
        Http::fake(['gotenberg/*' => Http::response('%PDF')]);

        RichEditorPdf::make('<p>x</p>')
            ->title('Report')
            ->margins(20, 15)
            ->footer('<p>{title}: {page} / {pages}</p>')
            ->driver(new GotenbergDriver('http://gotenberg'))
            ->toString();

        Http::assertSent(function (Request $request): bool {
            $files = collect($request->data())->where('name', 'files')->keyBy('filename');

            $footer = (string) ($files['footer.html']['contents'] ?? '');

            return $files->has(['index.html', 'header.html', 'footer.html'])
                && ! str_contains((string) $files['index.html']['contents'], 'fi-arte-pdf-footer"')
                && str_contains($footer, 'Report: <span class="fi-arte-pdf-page-number pageNumber"></span> / <span class="fi-arte-pdf-page-count totalPages"></span>')
                // Lined up with the text: the side margins as padding.
                && str_contains($footer, 'padding: 0 15mm 0 15mm;')
                // A side with nothing is empty, not Chromium's own date and title.
                && ((string) $files['header.html']['contents'] === '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><span></span></body></html>');
        });
    });

    it('uploads neither where the document has none', function (): void {
        Http::preventStrayRequests();
        Http::fake(['gotenberg/*' => Http::response('%PDF')]);

        RichEditorPdf::make('<p>x</p>')->driver(new GotenbergDriver('http://gotenberg'))->toString();

        Http::assertSent(fn (Request $request): bool => collect($request->data())->where('name', 'files')->pluck('filename')->all() === ['index.html']);
    });

    it('hands Browsershot the header and the footer as Chromium templates', function (): void {
        requires('spatie/browsershot');

        $driver = new BrowsershotDriver;

        $options = $driver->browsershot(
            RichEditorPdf::make('<p>x</p>')->header('<p>Head {page}</p>')->toDocument($driver),
        )->createPdfCommand()['options'];

        expect($options['displayHeaderFooter'])->toBeTrue()
            ->and($options['headerTemplate'])->toContain('<div class="fi-arte-pdf-running" style="padding: 0 20mm 0 20mm;"><p>Head <span class="fi-arte-pdf-page-number pageNumber"></span></p></div>')
            ->toContain('.fi-arte-pdf-running {')
            ->and($options['footerTemplate'])->toBe('<span></span>');
    });

    it('leaves Chromium\'s own header and footer off where the document has none', function (): void {
        requires('spatie/browsershot');

        $options = (new BrowsershotDriver)->browsershot(new PdfDocument('<p>x</p>', RichEditorPdf::make('<p>x</p>')->getPage()))->createPdfCommand()['options'];

        expect($options['displayHeaderFooter'] ?? false)->toBeFalse()
            ->and($options)->not->toHaveKey('headerTemplate');
    });
});
