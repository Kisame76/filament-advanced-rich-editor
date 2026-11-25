<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Export\RichEditorExport;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Smalot\PdfParser\Parser;

/**
 * Several documents as one PDF: put together as HTML and printed once, each part on a page
 * of its own, the page numbers running on across all of them, and a table of contents that
 * says where each part starts.
 */
beforeEach(function (): void {
    config()->set('filament-advanced-rich-editor.pdf.dompdf.font_directory', sys_get_temp_dir().'/arte-pdf-fonts');

    $this->long = str_repeat('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.</p>', 70);
});

/**
 * @param  array<int, string|null>  $titles
 * @return array<int, RichEditorPdf>
 */
function parts(array $titles, string $content = '<p>Body</p>'): array
{
    return array_map(
        static fn (?string $title): RichEditorPdf => RichEditorPdf::make($content)->title($title),
        $titles,
    );
}

describe('assembling', function (): void {
    it('puts each part in a section of its own, under its title, every one but the first on a new page', function (): void {
        $html = RichEditorPdf::combine([
            RichEditorPdf::make('<p>First body</p>')->title('First'),
            RichEditorPdf::make('<p>Second body</p>')->title('Second <draft>'),
        ])->toBodyHtml();

        expect($html)
            ->toContain('<section id="fi-arte-pdf-part-1" class="fi-arte-pdf-part"><h1 class="fi-arte-pdf-part-title">First</h1><p>First body</p></section>')
            ->toContain('<section id="fi-arte-pdf-part-2" class="fi-arte-pdf-part fi-arte-pdf-new-page"><h1 class="fi-arte-pdf-part-title">Second &lt;draft&gt;</h1><p>Second body</p></section>');
    });

    it('prints the document\'s own content first, and starts the first part on a page after it', function (): void {
        $html = RichEditorPdf::make('<p>Preface</p>')->append(parts(['First']))->toBodyHtml();

        expect($html)->toStartWith('<p>Preface</p>')
            ->toContain('<section id="fi-arte-pdf-part-1" class="fi-arte-pdf-part fi-arte-pdf-new-page">');
    });

    it('takes parts one at a time, from a collection, or both', function (): void {
        $pdf = RichEditorPdf::combine(collect(parts(['One', 'Two'])))->append(RichEditorPdf::make('<p>x</p>')->title('Three'));

        expect(array_map(fn (RichEditorExport $part): ?string => (fn () => $this->title)->call($part), $pdf->getParts()))
            ->toBe(['One', 'Two', 'Three']);
    });

    it('leaves the titles out where the content carries its own', function (): void {
        expect(RichEditorPdf::combine(parts(['First']))->partTitles(false)->toBodyHtml())
            ->not->toContain('fi-arte-pdf-part-title')
            ->toContain('<p>Body</p>');
    });

    it('gives every part ids of its own, and the links in it with them', function (): void {
        $part = fn (string $title): RichEditorPdf => RichEditorPdf::make('<h2>Introduction</h2><p>See the <a href="#introduction">introduction</a> or <a href="https://example.com/#top">elsewhere</a>.</p>')
            ->title($title)
            ->renderer(fn (AdvancedRichContentRenderer $renderer) => $renderer->anchorHeadings([2]));

        $html = RichEditorPdf::combine([$part('One'), $part('Two')])->toBodyHtml();

        expect($html)
            ->toContain('id="part-1-introduction"')
            ->toContain('href="#part-1-introduction"')
            ->toContain('id="part-2-introduction"')
            ->toContain('href="#part-2-introduction"')
            ->not->toContain('id="introduction"')
            // An address elsewhere is no anchor of the document's.
            ->toContain('href="https://example.com/#top"');
    });

    it('marks a part written in another language than the document', function (): void {
        $html = RichEditorPdf::combine([
            RichEditorPdf::make('<p>Hallo</p>')->title('Deutsch')->language('de'),
            RichEditorPdf::make('<p>Hello</p>')->title('English')->language('en'),
            RichEditorPdf::make('<p>Hi</p>')->title('Unsaid'),
        ])->language('en')->toBodyHtml();

        expect($html)
            ->toContain('<section id="fi-arte-pdf-part-1" class="fi-arte-pdf-part" lang="de">')
            ->toContain('<section id="fi-arte-pdf-part-2" class="fi-arte-pdf-part fi-arte-pdf-new-page">')
            ->toContain('<section id="fi-arte-pdf-part-3" class="fi-arte-pdf-part fi-arte-pdf-new-page">');
    });

    it('prints with the stylesheets the parts brought, each once', function (): void {
        $pdf = RichEditorPdf::combine([
            RichEditorPdf::make('<p>x</p>')->stylesheet('.brand { color: red; }'),
            RichEditorPdf::make('<p>y</p>')->stylesheet('.brand { color: red; }')->stylesheet('.other { color: blue; }'),
        ])->stylesheet('.own { color: green; }');

        expect($pdf->getStylesheets())->toBe(['.own { color: green; }', '.brand { color: red; }', '.other { color: blue; }'])
            ->and(substr_count($pdf->toHtml(), '.brand { color: red; }'))->toBe(1);
    });

    it('refuses a part that has parts of its own, or is the document itself', function (): void {
        $combined = RichEditorPdf::combine(parts(['One']));

        expect(fn () => RichEditorPdf::combine([$combined]))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $combined->append($combined))->toThrow(InvalidArgumentException::class);
    });
});

describe('the table of contents', function (): void {
    it('lists the parts with a title, each linked to where it starts, after the cover', function (): void {
        $html = RichEditorPdf::combine(parts(['First', null, 'Third']))->cover()->tableOfContents()->language('en')->toHtml();

        expect($html)
            ->toContain('<h1 class="fi-arte-pdf-toc-heading">Contents</h1>')
            ->toContain('<a href="#fi-arte-pdf-part-1">First</a>')
            ->toContain('<td class="fi-arte-pdf-toc-page">'.PdfDocument::pageOf('fi-arte-pdf-part-1').'</td>')
            ->toContain('<a href="#fi-arte-pdf-part-3">Third</a>')
            ->not->toContain('#fi-arte-pdf-part-2"')
            ->and(strpos($html, 'class="fi-arte-pdf-cover"'))->toBeLessThan(strpos($html, 'class="fi-arte-pdf-toc"'))
            ->and(strpos($html, 'class="fi-arte-pdf-toc"'))->toBeLessThan(strpos($html, 'id="fi-arte-pdf-part-1"'));
    });

    it('heads it in the document\'s language, a regional one included, or as it is told', function (): void {
        $heading = fn (RichEditorPdf $pdf): string => str($pdf->toHtml())->betweenFirst('<h1 class="fi-arte-pdf-toc-heading">', '</h1>')->toString();

        expect($heading(RichEditorPdf::combine(parts(['A']))->tableOfContents()->language('de')))->toBe('Inhalt')
            ->and($heading(RichEditorPdf::combine(parts(['A']))->tableOfContents()->language('de-AT')))->toBe('Inhalt')
            ->and($heading(RichEditorPdf::combine(parts(['A']))->tableOfContents()->language('pt-BR')))->toBe('Sumário')
            ->and($heading(RichEditorPdf::combine(parts(['A']))->tableOfContents('Articles')))->toBe('Articles');
    });

    it('is not printed unless asked', function (): void {
        expect(RichEditorPdf::combine(parts(['A']))->toHtml())->not->toContain('class="fi-arte-pdf-toc"');
    });

    it('says so where there is nothing to list', function (): void {
        expect(fn () => RichEditorPdf::make('<p>x</p>')->tableOfContents()->toHtml())->toThrow(LogicException::class, 'has none')
            ->and(fn () => RichEditorPdf::combine(parts([null, null]))->tableOfContents()->toHtml())->toThrow(LogicException::class, 'none of them has one');
    });

    it('leaves the page numbers to the driver', function (): void {
        $document = RichEditorPdf::combine(parts(['A', 'B']))->tableOfContents()->toDocument(new GotenbergDriver);

        expect($document->pageReferences())->toBe(['fi-arte-pdf-part-1', 'fi-arte-pdf-part-2']);
    });
});

describe('page references', function (): void {
    it('writes in the pages it is told, and leaves the rest empty', function (): void {
        $document = new PdfDocument(
            '<p>'.PdfDocument::pageOf('a').' '.PdfDocument::pageOf('b').' '.PdfDocument::pageOf('a').'</p>',
            new PdfPage(210, 297, 20, 20, 20, 20),
        );

        expect($document->pageReferences())->toBe(['a', 'b'])
            ->and($document->withPageNumbers(['a' => 3])->html)->toBe(
                '<p>'.str_replace('></span>', '>3</span>', PdfDocument::pageOf('a')).' '.PdfDocument::pageOf('b').' '.str_replace('></span>', '>3</span>', PdfDocument::pageOf('a')).'</p>',
            );
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
    function combinedPages(string $pdf): array
    {
        return array_map(
            static fn ($page): string => trim((string) preg_replace('/\s+/', ' ', $page->getText())),
            (new Parser)->parseContent($pdf)->getPages(),
        );
    }

    it('starts every part on a new page, without a blank one in front of the first', function (): void {
        $pages = combinedPages(RichEditorPdf::combine([
            RichEditorPdf::make('<p>Alpha body</p>')->title('Alpha'),
            RichEditorPdf::make('<p>Beta body</p>')->title('Beta'),
            RichEditorPdf::make('<p>Gamma body</p>')->title('Gamma'),
        ])->toString());

        expect($pages)->toHaveCount(3)
            ->and($pages[0])->toStartWith('Alpha')
            ->and($pages[1])->toStartWith('Beta')
            ->and($pages[2])->toStartWith('Gamma');
    });

    it('numbers the pages on across the parts, and lists where each part starts', function (): void {
        $pdf = RichEditorPdf::combine([
            RichEditorPdf::make($this->long)->title('Alpha'),
            RichEditorPdf::make('<p>Beta body</p>')->title('Beta'),
            RichEditorPdf::make($this->long)->title('Gamma'),
            RichEditorPdf::make('<p>Delta body</p>')->title('Delta'),
        ])
            ->title('Collected')
            ->cover()
            ->tableOfContents('Contents')
            ->pageNumbers('Page {page} of {pages}')
            ->toString();

        $pages = combinedPages($pdf);
        $total = count($pages);

        // Where each part's title first stands at the top of a page.
        $startOf = static function (string $title) use ($pages): int {
            foreach ($pages as $index => $text) {
                if ($index > 1 && str_starts_with($text, $title)) {
                    return $index + 1;
                }
            }

            return 0;
        };

        expect($pages[0])->toContain('Collected')
            ->and($pages[1])->toContain('Contents')
            ->and($startOf('Alpha'))->toBe(3)
            ->and($startOf('Beta'))->toBeGreaterThan(4)
            ->and($startOf('Gamma'))->toBe($startOf('Beta') + 1)
            ->and($startOf('Delta'))->toBeGreaterThan($startOf('Gamma') + 1)
            ->and($startOf('Delta'))->toBe($total);

        foreach (['Alpha', 'Beta', 'Gamma', 'Delta'] as $title) {
            expect($pages[1])->toContain($title.' '.$startOf($title));
        }

        foreach (array_slice($pages, 1, preserve_keys: true) as $index => $text) {
            expect($text)->toContain('Page '.($index + 1).' of '.$total);
        }
    });

    it('writes the pages into the table of contents where nothing else asks for a second pass', function (): void {
        $pages = combinedPages(RichEditorPdf::combine([
            RichEditorPdf::make($this->long)->title('Alpha'),
            RichEditorPdf::make('<p>Beta body</p>')->title('Beta'),
        ])->tableOfContents('Contents')->toString());

        expect($pages[0])->toContain('Alpha 2')
            ->toContain('Beta '.count($pages));
    });

    it('links each entry to the page its part starts on', function (): void {
        $pdf = RichEditorPdf::combine(parts(['Alpha', 'Beta']))->tableOfContents()->toString();

        // DomPDF writes its objects uncompressed: a link's action names a destination, the
        // destination names a page, and the page tree lists the pages in order.
        $object = static fn (string $id): string => preg_match('/(?:^|\n)'.$id.' 0 obj\s*(.*?)endobj/s', $pdf, $match) ? $match[1] : '';

        preg_match('/\/Type \/Pages\s*\/Kids \[([^\]]*)\]/', $pdf, $kids);
        preg_match_all('/(\d+) 0 R/', $kids[1] ?? '', $pageIds);
        preg_match_all('/\/S \/GoTo\s*\/D (\d+) 0 R/', $pdf, $destinations);

        $targets = array_map(static function (string $destination) use ($object, $pageIds): int|false {
            preg_match('/\[\s*(\d+) 0 R/', $object($destination), $page);

            return array_search($page[1] ?? '', $pageIds[1], strict: true);
        }, $destinations[1]);

        expect(combinedPages($pdf))->toHaveCount(3)
            ->and($targets)->toBe([1, 2]);
    });
});
