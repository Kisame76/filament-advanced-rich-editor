<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * A picture on the page that opens large when it is clicked.
 *
 * What the renderer writes is a link from the picture to its own address, so the page works
 * without the script: the link opens the picture. The script turns that click into a box over
 * the page - see `tests/js/lightbox.test.js`.
 */
beforeEach(function (): void {
    $this->rendered = fn (string $html, bool $lightbox = true): string => AdvancedRichContentRenderer::make($html)
        ->lightbox($lightbox)
        ->toHtml();

    $this->page = fn (string $template, array $data): string => Blade::render($template, $data);
});

it('leaves pictures alone unless it is asked', function (): void {
    expect(($this->rendered)('<p><img src="/storage/harbour.png" alt="Harbour"></p>', lightbox: false))
        ->not->toContain('fi-arte-lightbox')
        ->not->toContain('<a');
});

it('links a picture to itself', function (): void {
    $html = ($this->rendered)('<p><img src="/storage/harbour.png" alt="Harbour"></p>');

    expect($html)->toBe('<p><a href="/storage/harbour.png" class="fi-arte-lightbox"><img src="/storage/harbour.png" alt="Harbour" /></a></p>');
});

it('leaves a picture that points somewhere to the link it has', function (): void {
    $html = ($this->rendered)('<p><img src="/storage/harbour.png" alt="Harbour" data-href="https://example.com/port"></p>');

    expect($html)->toContain('<a href="https://example.com/port"><img')
        ->and($html)->not->toContain('fi-arte-lightbox');
});

it('leaves a picture inside a link alone', function (): void {
    // Written in the source view; a second link around it would nest one inside the other.
    $html = ($this->rendered)('<p><a href="https://example.com"><img src="/storage/harbour.png" alt="Harbour"></a></p>');

    expect($html)->not->toContain('fi-arte-lightbox')
        ->and(substr_count($html, '<a'))->toBe(1);
});

it('leaves a decorative picture out', function (): void {
    // A link has to be called something, and a picture that says it carries nothing gives it
    // nothing to be called: an empty link is worse than no lightbox.
    $html = ($this->rendered)('<p><img src="/storage/flourish.png" alt="" role="presentation"></p>');

    expect($html)->not->toContain('fi-arte-lightbox')
        ->and($html)->not->toContain('<a');
});

it('leaves a picture whose source is no address alone', function (): void {
    $html = ($this->rendered)('<p><img src="data:image/png;base64,iVBORw0KGgo=" alt="Inline"></p>');

    expect($html)->not->toContain('fi-arte-lightbox');
});

it('keeps the caption out of the link', function (): void {
    // The caption is text about the picture, not part of what is clicked - the same rule a
    // picture that points somewhere follows.
    $html = ($this->rendered)('<p><img src="/storage/harbour.png" alt="Harbour" data-caption="The harbour at dawn"></p>');

    expect($html)->toMatch('#<figure[^>]*><a href="/storage/harbour.png" class="fi-arte-lightbox"[^>]*><img[^>]*></a><figcaption[^>]*>The harbour at dawn</figcaption></figure>#');
});

it('remembers a render with and without it apart', function (): void {
    $html = '<p><img src="/storage/harbour.png" alt="Harbour"></p>';

    expect(AdvancedRichContentRenderer::make($html)->lightbox()->getRenderFingerprint())
        ->not->toBe(AdvancedRichContentRenderer::make($html)->getRenderFingerprint());
});

// On a page.

it('brings the script and its stylesheet along with the content tag', function (): void {
    $html = ($this->page)(
        '<x-arte-content :content="$body" lightbox /><x-arte-content :content="$body" lightbox />',
        ['body' => '<p><img src="/storage/harbour.png" alt="Harbour"></p>'],
    );

    $script = FilamentAsset::getScriptSrc('advanced-rich-editor/lightbox', 'kisame76/filament-advanced-rich-editor');
    $style = FilamentAsset::getStyleHref('filament-advanced-rich-editor-lightbox', 'kisame76/filament-advanced-rich-editor');

    expect(substr_count($html, 'class="fi-arte-lightbox"'))->toBe(2)
        // Once a page, however many documents are on it.
        ->and(substr_count($html, '<script type="module" src="'.e($script).'"'))->toBe(1)
        ->and(substr_count($html, '<link rel="stylesheet" href="'.e($style).'"'))->toBe(1);
});

it('brings them for a page that renders by itself', function (): void {
    $html = ($this->page)('<x-arte-lightbox />', []);

    expect($html)->toContain('<script type="module"')
        ->and($html)->toContain('data-close="Close"');
});

it('names the close button in the language of the page', function (): void {
    app()->setLocale('de');

    expect(($this->page)('<x-arte-lightbox />', []))->toContain('data-close="Schließen"');
});

it('follows a renderer handed in that was told to', function (): void {
    $html = ($this->page)('<x-arte-content :renderer="$renderer" />', [
        'renderer' => AdvancedRichContentRenderer::make('<p><img src="/storage/harbour.png" alt="Harbour"></p>')->lightbox(),
    ]);

    expect($html)->toContain('class="fi-arte-lightbox"')
        ->and($html)->toContain('<script type="module"');
});

it('brings nothing along where it is not asked for', function (): void {
    $html = ($this->page)('<x-arte-content :content="$body" />', ['body' => '<p><img src="/storage/harbour.png" alt="Harbour"></p>']);

    expect($html)->not->toContain('lightbox');
});
