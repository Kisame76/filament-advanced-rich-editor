<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\BrowsershotDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\DomPdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Drivers\GotenbergDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PageFormat;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AnchorPosition;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

/**
 * A driver that prints nothing and remembers what it was handed - the document and the sheet
 * are this class's whole output, and the engine's part is tested on its own.
 */
class RecordingPdfDriver implements PdfDriver
{
    public ?string $html = null;

    public ?PdfPage $page = null;

    public function render(string $html, PdfPage $page): string
    {
        $this->html = $html;
        $this->page = $page;

        return '%PDF-1.7 recorded';
    }
}

beforeEach(function (): void {
    $this->driver = new RecordingPdfDriver;
});

it('prints on the configured sheet by default', function (): void {
    $page = RichEditorPdf::make('<p>x</p>')->getPage();

    expect([$page->width, $page->height])->toBe([210.0, 297.0])
        ->and([$page->marginTop, $page->marginRight, $page->marginBottom, $page->marginLeft])->toBe([20.0, 20.0, 20.0, 20.0]);
});

it('takes the sheet from the configuration file', function (): void {
    config()->set('filament-advanced-rich-editor.pdf.page', 'letter');
    config()->set('filament-advanced-rich-editor.pdf.orientation', 'landscape');
    config()->set('filament-advanced-rich-editor.pdf.margins', [10, 15]);

    $page = RichEditorPdf::make('<p>x</p>')->getPage();

    expect([$page->width, $page->height])->toBe([279.4, 215.9])
        ->and([$page->marginTop, $page->marginRight, $page->marginBottom, $page->marginLeft])->toBe([10.0, 15.0, 10.0, 15.0]);
});

it('knows every format by name, whatever its case', function (string $name, array $dimensions): void {
    $page = RichEditorPdf::make('<p>x</p>')->page($name)->getPage();

    expect([$page->width, $page->height])->toBe($dimensions);
})->with([
    'a3' => ['A3', [297.0, 420.0]],
    'a4' => ['a4', [210.0, 297.0]],
    'a5' => ['a5', [148.0, 210.0]],
    'letter' => ['Letter', [215.9, 279.4]],
    'legal' => ['legal', [215.9, 355.6]],
]);

it('takes a format as the enum too', function (): void {
    expect(RichEditorPdf::make('<p>x</p>')->page(PageFormat::A5)->getPage()->width)->toBe(148.0);
});

it('refuses a format it does not know, and says which it does', function (): void {
    RichEditorPdf::make('<p>x</p>')->page('a6');
})->throws(InvalidArgumentException::class, 'Unknown page format [a6]. Use one of: a3, a4, a5, letter, legal, or size().');

it('turns the sheet for landscape, and back', function (): void {
    $pdf = RichEditorPdf::make('<p>x</p>')->page('a4')->landscape();

    expect([$pdf->getPage()->width, $pdf->getPage()->height])->toBe([297.0, 210.0])
        ->and($pdf->getPage()->isLandscape())->toBeTrue();

    $pdf->portrait();

    expect([$pdf->getPage()->width, $pdf->getPage()->height])->toBe([210.0, 297.0]);
});

it('takes a sheet of any size as given, and turns it only when asked', function (): void {
    $pdf = RichEditorPdf::make('<p>x</p>')->size(150, 100);

    expect([$pdf->getPage()->width, $pdf->getPage()->height])->toBe([150.0, 100.0]);

    $pdf->portrait();

    expect([$pdf->getPage()->width, $pdf->getPage()->height])->toBe([100.0, 150.0]);
});

it('refuses a sheet with no size', function (): void {
    RichEditorPdf::make('<p>x</p>')->size(0, 100);
})->throws(InvalidArgumentException::class);

it('reads margins the way the CSS shorthand does', function (array $given, array $expected): void {
    $page = RichEditorPdf::make('<p>x</p>')->margins(...$given)->getPage();

    expect([$page->marginTop, $page->marginRight, $page->marginBottom, $page->marginLeft])->toBe($expected);
})->with([
    'one' => [[12], [12.0, 12.0, 12.0, 12.0]],
    'two' => [[10, 25], [10.0, 25.0, 10.0, 25.0]],
    'three' => [[10, 25, 30], [10.0, 25.0, 30.0, 25.0]],
    'four' => [[1, 2, 3, 4], [1.0, 2.0, 3.0, 4.0]],
]);

it('refuses a negative margin', function (): void {
    RichEditorPdf::make('<p>x</p>')->margins(10, -1);
})->throws(InvalidArgumentException::class);

it('describes the sheet in the document it prints', function (): void {
    $html = RichEditorPdf::make('<p>x</p>')->page('a5')->landscape()->margins(12.5, 10)->toHtml();

    expect($html)->toContain('@page { size: 210mm 148mm; margin: 12.5mm 10mm 12.5mm 10mm; }');
});

it('writes lengths with a full stop under a locale that writes a comma', function (): void {
    $locale = setlocale(LC_NUMERIC, '0');
    setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'de');

    try {
        expect((new PdfPage(215.9, 279.4, 12.5, 0, 0, 0))->toCss())->toContain('size: 215.9mm 279.4mm; margin: 12.5mm 0mm 0mm 0mm;');
    } finally {
        setlocale(LC_NUMERIC, (string) $locale);
    }
});

it('wraps the content in a document with its title, language and stylesheets', function (): void {
    app()->setLocale('de_DE');

    $html = RichEditorPdf::make('<p>Hallo</p>')
        ->title('Bericht <Q3> & mehr')
        ->stylesheet('body { font-family: "Inter"; }')
        ->stylesheet('h1 { color: red; }')
        ->toHtml();

    expect($html)
        ->toStartWith('<!DOCTYPE html>')
        ->toContain('<html lang="de-DE">')
        ->toContain('<title>Bericht &lt;Q3&gt; &amp; mehr</title>')
        ->toContain('<main class="fi-arte-pdf">')
        ->toContain('<p>Hallo</p>')
        ->toContain(RichEditorPdf::packageStylesheet())
        // The project's CSS after the package's, so it wins.
        ->and(strpos($html, 'font-family: "Inter"'))->toBeGreaterThan(strpos($html, '.fi-arte-callout-note'))
        ->and(strpos($html, 'h1 { color: red; }'))->toBeGreaterThan(strpos($html, 'font-family: "Inter"'));
});

it('takes the language it is told over the application\'s', function (): void {
    expect(RichEditorPdf::make('<p>x</p>')->language('fr')->toHtml())->toContain('<html lang="fr">');
});

it('leaves the title out where there is none', function (): void {
    expect(RichEditorPdf::make('<p>x</p>')->toHtml())->not->toContain('<title>');
});

it('escapes nothing in the content twice, and lets nothing past the sanitiser', function (): void {
    $html = RichEditorPdf::make('<p>Fish &amp; chips</p><script>alert(1)</script><p onclick="steal()">Text</p>')->toBodyHtml();

    expect($html)->toBe('<p>Fish &amp; chips</p><p>Text</p>');
});

it('prints the content through the renderer it was given, configured as the page is', function (): void {
    $renderer = AdvancedRichContentRenderer::make('<h2>Section</h2>')->anchorHeadings(position: AnchorPosition::After);

    $html = RichEditorPdf::fromRenderer($renderer)->toBodyHtml();

    // The anchor's id comes from the page's configuration; its `#` is for a screen.
    expect($html)->toBe('<h2 id="section">Section</h2>');
});

it('prints a model\'s attribute with what the model registered for it', function (): void {
    $post = RichPost::create(['title' => 'Post', 'content' => '<p>Ping <span data-type="mention" data-id="2" data-label="Old name" data-char="@"></span></p>']);

    // The label comes from the provider the model registered, not from the copy the
    // document was saved with.
    expect(RichEditorPdf::forRecord($post, 'content')->toBodyHtml())->toContain('@Ada Lovelace')
        ->not->toContain('Old name');
});

it('prints an attribute the model never registered with nothing but the defaults', function (): void {
    $post = RichPost::create(['title' => 'Post <b>bold</b>', 'content' => '<p>x</p>']);

    expect(RichEditorPdf::forRecord($post, 'title')->toBodyHtml())->toBe('<p>Post <strong>bold</strong></p>');
});

it('configures its own renderer in place', function (): void {
    $pdf = RichEditorPdf::make('<h2>Section</h2>')
        ->renderer(fn (AdvancedRichContentRenderer $renderer) => $renderer->anchorHeadings());

    expect($pdf->toBodyHtml())->toBe('<h2 id="section">Section</h2>');
});

it('prints an empty document as an empty page rather than failing', function (mixed $content): void {
    expect(RichEditorPdf::make($content)->toBodyHtml())->toBe('');
})->with([
    'nothing' => [null],
    'empty markup' => [''],
]);

it('hands the driver the document and the sheet, and returns what it printed', function (): void {
    $pdf = RichEditorPdf::make('<p>x</p>')->page('letter')->driver($this->driver);

    expect($pdf->toString())->toBe('%PDF-1.7 recorded')
        ->and($this->driver->html)->toBe($pdf->toHtml())
        ->and($this->driver->page?->width)->toBe(215.9);
});

it('finds a driver by name, by class, or from the configuration', function (?string $name, string $class): void {
    if ($name === null) {
        config()->set('filament-advanced-rich-editor.pdf.driver', 'gotenberg');
    }

    expect(RichEditorPdf::make('<p>x</p>')->driver($name)->getDriver())->toBeInstanceOf($class);
})->with([
    'dompdf' => ['dompdf', DomPdfDriver::class],
    'gotenberg' => ['gotenberg', GotenbergDriver::class],
    'browsershot' => ['browsershot', BrowsershotDriver::class],
    'a class' => [RecordingPdfDriver::class, RecordingPdfDriver::class],
    'the configuration' => [null, GotenbergDriver::class],
]);

it('refuses a driver that is not one', function (string $driver): void {
    RichEditorPdf::make('<p>x</p>')->driver($driver)->getDriver();
})->with([
    'an unknown name' => ['wkhtmltopdf'],
    'a class that prints nothing' => [stdClass::class],
])->throws(InvalidArgumentException::class);

it('saves the PDF to a disk', function (): void {
    Storage::fake('exports');

    $saved = RichEditorPdf::make('<p>x</p>')->driver($this->driver)->save('reports/q3.pdf', 'exports');

    expect($saved)->toBeTrue()
        ->and(Storage::disk('exports')->get('reports/q3.pdf'))->toBe('%PDF-1.7 recorded');
});

it('saves the PDF to a path, making the directory it needs', function (): void {
    $directory = sys_get_temp_dir().'/arte-pdf-'.bin2hex(random_bytes(4));

    try {
        expect(RichEditorPdf::make('<p>x</p>')->driver($this->driver)->save($directory.'/nested/q3.pdf'))->toBeTrue()
            ->and(file_get_contents($directory.'/nested/q3.pdf'))->toBe('%PDF-1.7 recorded');
    } finally {
        @unlink($directory.'/nested/q3.pdf');
        @rmdir($directory.'/nested');
        @rmdir($directory);
    }
});

it('answers a download with the PDF under its title', function (): void {
    $response = RichEditorPdf::make('<p>x</p>')->title('Quarterly report')->driver($this->driver)->download();

    ob_start();
    $response->sendContent();
    $body = (string) ob_get_clean();

    expect($body)->toBe('%PDF-1.7 recorded')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen('%PDF-1.7 recorded'))
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="Quarterly report.pdf"');
});

it('opens inline where asked, under the name it is given', function (): void {
    $response = RichEditorPdf::make('<p>x</p>')->driver($this->driver)->inline('summary.PDF');

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename=summary.PDF');
});

it('names a download for every client, a title that is not ASCII included', function (): void {
    $response = RichEditorPdf::make('<p>x</p>')->title('Grüße/Bericht "Q3"')->driver($this->driver)->download();

    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="Grusse-Bericht Q3.pdf"; filename*=utf-8\'\'Gr%C3%BC%C3%9Fe-Bericht%20%22Q3%22.pdf');
});

it('names a download that has nothing to be named after', function (): void {
    expect(RichEditorPdf::make('<p>x</p>')->driver($this->driver)->download()->headers->get('Content-Disposition'))
        ->toBe('attachment; filename=document.pdf')
        ->and(RichEditorPdf::make('<p>x</p>')->title('日本語')->driver($this->driver)->download()->headers->get('Content-Disposition'))
        ->toContain('filename=document.pdf; filename*=utf-8\'\'');
});
