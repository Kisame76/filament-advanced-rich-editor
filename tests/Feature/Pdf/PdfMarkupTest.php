<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfImages;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfMarkup;

/**
 * What the renderer's markup becomes on its way to a PDF engine. Each input is markup the
 * renderer actually writes - the shapes are copied from its output, not made up.
 */
beforeEach(function (): void {
    config()->set('app.url', 'https://site.test');
    config()->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => 'https://site.test/storage', 'visibility' => 'public']);

    Storage::fake('public');
    Storage::disk('public')->put('harbour.png', (string) file_get_contents(__DIR__.'/../../Fixtures/media/harbour.png'));

    $this->print = static fn (string $html): string => (new PdfMarkup(new PdfImages))->apply($html);
});

it('leaves nothing to print as nothing', function (): void {
    expect(($this->print)(''))->toBe('')
        ->and(($this->print)("  \n "))->toBe('');
});

it('takes the marker a heading links itself with out, and keeps the heading id', function (): void {
    $html = ($this->print)('<h2 id="text">Text<a href="#text" class="fi-arte-anchor">#</a></h2>');

    expect($html)->toBe('<h2 id="text">Text</h2>');
});

it('keeps the text of a heading that is its own link', function (): void {
    $html = ($this->print)('<h2 id="text"><a href="#text" class="fi-arte-anchor">Text</a></h2>');

    expect($html)->toBe('<h2 id="text">Text</h2>');
});

it('leaves a link elsewhere in the document pointing at a heading', function (): void {
    expect(($this->print)('<p><a href="#text">See above</a></p>'))->toBe('<p><a href="#text">See above</a></p>');
});

it('prints a framed video as a link to the page the service shares', function (): void {
    $html = ($this->print)('<div class="fi-arte-embed" data-type="embed" style="aspect-ratio: 16 / 9; width: 100%;"><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=30" style="width: 100%;"></iframe></div>');

    expect($html)->toBe('<p class="fi-arte-pdf-embed"><a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;t=30">https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;t=30</a></p>');
});

it('names a frame by its title where it has one, and links its own address where no service claims it', function (): void {
    $html = ($this->print)('<iframe src="https://maps.example/embed?q=harbour" title="The harbour"></iframe>');

    expect($html)->toBe('<p class="fi-arte-pdf-embed"><a href="https://maps.example/embed?q=harbour">The harbour</a></p>');
});

it('prints a video as a link to its file, absolute, under its name', function (): void {
    $html = ($this->print)('<video src="/storage/clips/harbour%20tour.mp4" controls="controls" class="fi-arte-media fi-arte-media-video" style="width: 100%; height: auto;"></video>');

    expect($html)->toBe('<div class="fi-arte-pdf-media"><p><a href="https://site.test/storage/clips/harbour%20tour.mp4">'."\u{25B6}".' harbour tour.mp4</a></p></div>');
});

it('puts a video\'s still above its link', function (): void {
    $html = ($this->print)('<video src="/storage/tour.mp4" poster="/storage/harbour.png" title="Harbour tour"></video>');

    expect($html)->toMatch('#^<div class="fi-arte-pdf-media"><img src="data:image/png;base64,[^"]+" alt="Harbour tour"><p><a href="https://site.test/storage/tour.mp4">'."\u{25B6}".' Harbour tour</a></p></div>$#');
});

it('prints a sound by the address of its first source', function (): void {
    $html = ($this->print)('<audio controls><source src="https://cdn.example/a.mp3"></audio>');

    expect($html)->toContain('<a href="https://cdn.example/a.mp3">'."\u{25B6}".' a.mp3</a>');
});

it('draws a task\'s box as a ballot box character, ticked where it is done', function (): void {
    $html = ($this->print)(
        '<ul class="fi-arte-task-list" data-type="taskList">'
        .'<li class="fi-arte-task-item fi-arte-task-item-checked" data-type="taskItem"><label class="fi-arte-task-item-control"><span class="fi-arte-task-item-box"></span></label><div class="fi-arte-task-item-content"><p>Done</p></div></li>'
        .'<li class="fi-arte-task-item" data-type="taskItem"><label class="fi-arte-task-item-control"><span class="fi-arte-task-item-box"></span></label><div class="fi-arte-task-item-content"><p>Open</p></div></li>'
        .'</ul>',
    );

    expect($html)
        ->not->toContain('<label')
        ->toContain('<p><span class="fi-arte-pdf-task-box">'.PdfMarkup::CHECKED.'</span> Done</p>')
        ->toContain('<p><span class="fi-arte-pdf-task-box">'.PdfMarkup::UNCHECKED.'</span> Open</p>');
});

it('reads a task stored the way the editor writes it, with no class to go by', function (): void {
    $html = ($this->print)('<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>Done</p></li></ul>');

    expect($html)->toContain('<span class="fi-arte-pdf-task-box">'.PdfMarkup::CHECKED.'</span> Done');
});

it('prints a disclosure open, its summary as a title', function (): void {
    $html = ($this->print)('<details><summary>More</summary><div data-type="detailsContent"><p>Hidden.</p></div></details>');

    expect($html)->toBe('<div class="fi-arte-pdf-details"><p class="fi-arte-pdf-details-summary">More</p><div data-type="detailsContent"><p>Hidden.</p></div></div>');
});

it('lays a grid out as a table, each column at its share of the width', function (): void {
    $html = ($this->print)(
        '<div class="grid-layout" data-cols="3" style="--cols: repeat(3, minmax(0, 1fr));">'
        .'<div class="grid-layout-col" data-col-span="1" style="--col-span: span 1 / span 1;"><p>One</p></div>'
        .'<div class="grid-layout-col" data-col-span="2" style="--col-span: span 2 / span 2;"><p>Two</p></div>'
        .'</div>',
    );

    expect($html)->toBe('<table class="fi-arte-pdf-grid"><tr><td style="width: 33.33%;"><p>One</p></td><td style="width: 66.67%;" colspan="2"><p>Two</p></td></tr></table>');
});

it('starts a new row where a grid\'s columns wrap, and pads a short row out', function (): void {
    $html = ($this->print)(
        '<div class="grid-layout" data-cols="2">'
        .'<div class="grid-layout-col" data-col-span="1"><p>A</p></div>'
        .'<div class="grid-layout-col" data-col-span="1"><p>B</p></div>'
        .'<div class="grid-layout-col" data-col-span="1"><p>C</p></div>'
        .'</div>',
    );

    expect($html)->toBe(
        '<table class="fi-arte-pdf-grid">'
        .'<tr><td style="width: 50%;"><p>A</p></td><td style="width: 50%;"><p>B</p></td></tr>'
        .'<tr><td style="width: 50%;"><p>C</p></td><td style="width: 50%;"></td></tr>'
        .'</table>',
    );
});

it('turns a grid inside a grid into a table inside a table', function (): void {
    $html = ($this->print)(
        '<div class="grid-layout" data-cols="2">'
        .'<div class="grid-layout-col" data-col-span="1"><div class="grid-layout" data-cols="2"><div class="grid-layout-col" data-col-span="1"><p>Inner</p></div></div></div>'
        .'<div class="grid-layout-col" data-col-span="1"><p>Outer</p></div>'
        .'</div>',
    );

    expect(substr_count($html, '<table class="fi-arte-pdf-grid">'))->toBe(2)
        ->and($html)->not->toContain('grid-layout');
});

it('sets the widths of a resized table on its first row, where DomPDF reads them', function (): void {
    $html = ($this->print)('<table style="table-layout: fixed;"><colgroup><col style="width: 200px;"><col></colgroup><tbody><tr><th><p>Region</p></th><th><p>Revenue</p></th></tr><tr><td><p>North</p></td><td><p>1</p></td></tr></tbody></table>');

    expect($html)->toBe('<table><colgroup><col style="width: 200px;"><col></colgroup><tbody><tr><th style="width: 200px;"><p>Region</p></th><th><p>Revenue</p></th></tr><tr><td><p>North</p></td><td><p>1</p></td></tr></tbody></table>');
});

it('counts a merged cell as the columns it covers', function (): void {
    $html = ($this->print)('<table><colgroup><col style="width: 100px;"><col style="width: 50px;"><col style="width: 70px;"></colgroup><tr><td colspan="2">A</td><td>B</td></tr></table>');

    expect($html)->toContain('<td colspan="2" style="width: 100px;">A</td><td style="width: 70px;">B</td>');
});

it('embeds a picture from the application\'s public disk', function (): void {
    $html = ($this->print)('<p><img src="/storage/harbour.png" alt="Harbour" srcset="/storage/harbour.png 120w" sizes="100vw" loading="lazy"></p>');

    expect($html)->toMatch('#^<p><img src="data:image/png;base64,[A-Za-z0-9+/=]+" alt="Harbour"></p>$#');
});

it('prints the description of a picture it cannot embed, in brackets', function (): void {
    $html = ($this->print)('<p><img src="https://elsewhere.example/a.png" alt="A chart"></p>');

    expect($html)->toBe('<p><span class="fi-arte-pdf-missing-image">[A chart]</span></p>');
});

it('leaves out a picture it cannot embed and that has nothing to say', function (string $image): void {
    expect(($this->print)("<p>Text{$image}</p>"))->toBe('<p>Text</p>');
})->with([
    'no description' => ['<img src="https://elsewhere.example/a.png">'],
    'decorative' => ['<img src="https://elsewhere.example/icon.png" role="presentation" alt="Site icon">'],
]);

it('makes a link relative to the site absolute, and leaves every other kind alone', function (): void {
    $html = ($this->print)('<p><a href="/terms">Terms</a> <a href="pricing">Pricing</a> <a href="https://laravel.com">Laravel</a> <a href="mailto:a@b.test">Mail</a> <a href="#top">Top</a></p>');

    expect($html)
        ->toContain('href="https://site.test/terms"')
        ->toContain('href="https://site.test/pricing"')
        ->toContain('href="https://laravel.com"')
        ->toContain('href="mailto:a@b.test"')
        ->toContain('href="#top"');
});

it('centres a picture with automatic margins on a line of its own', function (): void {
    $html = ($this->print)('<p><img src="/storage/harbour.png" alt="Centred" style="display: block; margin-inline: auto; width: 200px;"></p>');

    expect($html)->toMatch('#^<p><div style="text-align: center;"><img src="data:image/png;base64,[^"]+" alt="Centred" style="width: 200px;"></div></p>$#');
});

it('centres a figure with automatic margins by its alignment', function (): void {
    $html = ($this->print)('<figure class="fi-arte-figure" style="margin-inline: auto; inline-size: fit-content;"><figcaption>Caption</figcaption></figure>');

    expect($html)->toBe('<figure class="fi-arte-figure" style="margin-left: auto; margin-right: auto; text-align: center;"><figcaption>Caption</figcaption></figure>');
});

it('takes a style off an element whose every declaration had to go', function (): void {
    expect(($this->print)('<div style="--cols: 2; gap: 1rem"><p>A</p></div>'))->toBe('<div><p>A</p></div>');
});

it('keeps text it does not know what to do with as it was', function (): void {
    $html = ($this->print)('<p>Grüße, € und 中文 — <strong>fett</strong></p>');

    expect($html)->toBe('<p>Grüße, € und 中文 — <strong>fett</strong></p>');
});
