<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

it('renders a captioned image as a figure', function (): void {
    // `<figure>` and `<figcaption>` are the markup a caption means, and both survive
    // Filament's sanitiser. The paragraph around the image is replaced rather than kept:
    // a figure inside a paragraph is markup no browser agrees on.
    $stored = '<p><img src="/hafen.jpg" alt="Kräne im Nebel" data-caption="Hamburger Hafen, 1962"></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toBe('<figure class="fi-arte-figure" style="margin-inline: 0;"><img src="/hafen.jpg" alt="Kräne im Nebel" /><figcaption>Hamburger Hafen, 1962</figcaption></figure>');
});

it('wraps an image that stands on its own', function (): void {
    $stored = '<img src="/a.jpg" data-caption="Eine Bildunterschrift">';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toContain('<figure class="fi-arte-figure" style="margin-inline: 0;">')
        ->toContain('<figcaption>Eine Bildunterschrift</figcaption>');
});

it('leaves an image without a caption exactly as it was', function (): void {
    $stored = '<p><img src="/a.jpg" alt="A" /></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())->toBe($stored);
});

it('leaves an image inside a sentence where it is', function (): void {
    // A figure is a block that stands apart from the text. An image between two words is
    // not one, and lifting it out would rewrite the sentence around it.
    $stored = '<p>Vorher <img src="/a.jpg" data-caption="Ignoriert"> nachher</p>';

    $html = AdvancedRichContentRenderer::make($stored)->toHtml();

    expect($html)->toContain('Vorher')
        ->toContain('nachher')
        ->not->toContain('<figure')
        // The attribute is not left behind either: it says something the page does not show.
        ->not->toContain('data-caption');
});

it('escapes what somebody typed into the caption', function (): void {
    $stored = '<p><img src="/a.jpg" data-caption="Tom &amp; Jerry &lt;3"></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toContain('<figcaption>Tom &amp; Jerry &lt;3</figcaption>')
        ->not->toContain('<3<');
});

it('keeps a caption through the round trip a save goes through', function (): void {
    // Content is parsed on hydration and again on dehydration. A caption the schema does
    // not declare is gone the first time the record is reopened.
    $stored = '<p><img src="/a.jpg" data-caption="Bleibt"></p>';

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect($once)->toContain('data-caption="Bleibt"')
        ->and($twice)->toBe($once);
});

it('drops a caption that is only whitespace', function (): void {
    $stored = '<p><img src="/a.jpg" data-caption="   "></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->not->toContain('<figure')
        ->not->toContain('<figcaption');
});

it('does not indent the figure the way a browser would', function (): void {
    // Browsers give `<figure>` a default `margin: 1em 40px`, which pushes a captioned image
    // out of line with every paragraph and heading around it. That is a user agent default
    // rather than a design decision, so it is taken off the markup this package writes -
    // the page it lands on does not load this package's stylesheet.
    $stored = '<p><img src="/a.jpg" data-caption="Bildunterschrift"></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toContain('style="margin-inline: 0;"');
});
