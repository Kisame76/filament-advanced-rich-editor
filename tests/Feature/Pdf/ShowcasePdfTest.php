<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfMarkup;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;

/**
 * Every node the editor writes, printed by DomPDF and read back.
 *
 * Compared by what is on the page and where, not byte for byte: two prints of one document
 * differ in their creation date, their font subset names and the order of their objects, so a
 * reference file would fail on every run for nothing. Text and its position are what a reader
 * sees, and they do not move unless the layout does.
 */
beforeEach(function (): void {
    requires('dompdf/dompdf');
    requires('smalot/pdfparser');

    config()->set('app.url', 'https://site.test');
    config()->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => 'https://site.test/storage', 'visibility' => 'public']);
    config()->set('filament-advanced-rich-editor.pdf.dompdf.font_directory', sys_get_temp_dir().'/arte-pdf-fonts');

    Storage::fake('public');
    Storage::disk('public')->put('harbour.png', (string) file_get_contents(__DIR__.'/../../Fixtures/media/harbour.png'));

    $this->pdf = RichEditorPdf::make((string) file_get_contents(__DIR__.'/../../Fixtures/Documents/showcase.html'))
        ->renderer(fn (AdvancedRichContentRenderer $renderer) => $renderer
            ->plugins([
                Plugins\TaskListPlugin::make(),
                Plugins\CalloutPlugin::make(),
                Plugins\FileCardPlugin::make(),
                Plugins\EmbedPlugin::make(),
                Plugins\MediaPlugin::make(),
                Plugins\LinkPreviewPlugin::make(),
                Plugins\DescriptionListPlugin::make(),
                Plugins\LockedPlugin::make(),
            ])
            ->anchorHeadings())
        ->title('Quarterly report')
        ->toString();

    $this->document = (new Parser)->parseContent($this->pdf);

    /**
     * Where a piece of text starts on its page, in points from the left edge.
     */
    $this->x = function (string $needle): ?float {
        assert($this->document instanceof Document);

        foreach ($this->document->getPages() as $page) {
            foreach ($page->getDataTm() as [$matrix, $text]) {
                if (str_contains($text, $needle)) {
                    return (float) $matrix[4];
                }
            }
        }

        return null;
    };
});

it('prints every kind of block the editor writes', function (): void {
    $text = $this->document->getText();

    expect($text)
        ->toContain('Quarterly report')
        ->toContain('Everything the editor can put in a document')
        ->toContain('Highlighted')
        ->toContain('Small print.')
        ->toContain('Two point one')
        ->toContain(PdfMarkup::CHECKED.' Done')
        ->toContain(PdfMarkup::UNCHECKED.' Open')
        ->toContain('What the term means.')
        ->toContain('A quotation')
        ->toContain("echo 'Hello';")
        ->toContain('A note.')
        ->toContain('A danger.')
        ->toContain('Details summary')
        ->toContain('Details content.')
        ->toContain('Two thirds')
        ->toContain('A locked clause.')
        ->toContain('1,200')
        ->toContain('The harbour at dawn')
        ->toContain('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->toContain("\u{25B6} tiny.mp4")
        ->toContain('report.pdf')
        ->toContain('88 KB')
        ->toContain('Laravel documentation')
        ->toContain('[A picture from elsewhere]')
        ->toContain('@Ada Lovelace');
});

it('prints no heading with the marker a screen needs to copy its link', function (): void {
    expect($this->document->getText())
        ->not->toContain('Text#')
        ->not->toContain('Lists#');
});

it('carries the title into the PDF', function (): void {
    expect($this->document->getDetails()['Title'] ?? null)->toBe('Quarterly report');
});

it('sets text where the page view puts it', function (): void {
    // A4 is 595.3pt wide with 20mm (56.7pt) margins: 481.9pt of line between 56.7 and 538.6.
    expect(($this->x)('Plain,'))->toBeGreaterThan(56.0)->toBeLessThan(58.0)
        // Centred: the line's middle on the page's.
        ->and(($this->x)('Centred.'))->toBeGreaterThan(260.0)->toBeLessThan(290.0)
        // Right aligned: ending at the right margin.
        ->and(($this->x)('Right aligned.'))->toBeGreaterThan(450.0)->toBeLessThan(500.0)
        // The grid's second track starts a third of the line in.
        ->and(($this->x)('Two thirds'))->toBeGreaterThan(212.0)->toBeLessThan(222.0)
        // The resized column is 200 pixels, 150pt, of content: the next one's text starts
        // after that, two paddings of 6pt and the rule between them.
        ->and(($this->x)('Revenue'))->toBeGreaterThan(222.0)->toBeLessThan(230.0);
});

it('embeds the pictures it could read, and only those', function (): void {
    $images = $this->document->getObjectsByType('XObject', 'Image');

    // The captioned one, the floated one and the centred one: the same file three times,
    // which DomPDF stores once. The picture from elsewhere was never fetched.
    expect(count($images))->toBeGreaterThanOrEqual(1)
        ->and($this->pdf)->not->toContain('elsewhere.example');
});

it('keeps links clickable, with every address absolute', function (): void {
    expect($this->pdf)
        ->toContain('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->toContain('https://site.test/storage/report.pdf')
        ->toContain('https://site.test/terms')
        ->toContain('https://laravel.com/docs');
});
