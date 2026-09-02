<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

it('renders a captioned image as a figure', function (): void {
    // `<figure>` and `<figcaption>` are the markup a caption means, and both survive
    // Filament's sanitiser. The paragraph around the image is replaced rather than kept:
    // a figure inside a paragraph is markup no browser agrees on.
    $stored = '<p><img src="/hafen.jpg" alt="Kräne im Nebel" data-caption="Hamburger Hafen, 1962"></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toBe('<figure class="fi-arte-figure" style="margin-inline: 0; inline-size: fit-content; max-inline-size: 100%;"><img src="/hafen.jpg" alt="Kräne im Nebel" /><figcaption>Hamburger Hafen, 1962</figcaption></figure>');
});

it('wraps an image that stands on its own', function (): void {
    $stored = '<img src="/a.jpg" data-caption="Eine Bildunterschrift">';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toContain('<figure class="fi-arte-figure" style="margin-inline: 0; inline-size: fit-content; max-inline-size: 100%;">')
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
        ->toContain('style="margin-inline: 0; inline-size: fit-content; max-inline-size: 100%;"');
});

it('boxes the figure to the picture rather than to the column', function (): void {
    // Reported off a preview, where it was plain to see: over a picture somebody had made
    // narrower, the caption sat out to the right of it. A `<figure>` is a block, so it fills
    // the column whatever the picture measures, and a centred caption then centres on the
    // column instead of under the picture.
    //
    // Written on the element rather than only in the stylesheet, because the page a rendered
    // figure lands on does not load this package's stylesheet - the same reason the indent
    // repair beside it is inline.
    $stored = '<p><img src="/a.jpg" style="width: 240px" data-caption="Bildunterschrift"></p>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->toContain('inline-size: fit-content')
        // Never past the column it sits in, whatever the picture's own width claims.
        ->toContain('max-inline-size: 100%')
        // And the picture keeps its own width: the box is the figure's business, the size
        // is the picture's.
        ->toContain('width: 240px');
});

it('lets a centred picture actually centre, which the box is what makes possible', function (): void {
    // The second thing the full-width figure broke, and the one nothing reported because it
    // fails silently: `moveFloat()` centres a picture by moving `margin-inline: auto` onto
    // the figure, and auto margins do nothing to a block that already fills its container.
    $stored = '<p><img src="/a.jpg" style="margin-inline: auto" data-caption="Mitte"></p>';

    $rendered = AdvancedRichContentRenderer::make($stored)->toHtml();

    // Read off the markup rather than through the helper in `ImageFloatTest`, so this file
    // still answers when it is the only one running.
    preg_match('/<figure[^>]*style="([^"]*)"/', $rendered, $figure);

    expect($figure[1] ?? '')->toContain('margin-inline: auto')
        ->and($figure[1] ?? '')->toContain('inline-size: fit-content')
        // The centring is written after the zero it replaces, which is what makes it win.
        ->and(strpos($figure[1], 'margin-inline: auto'))->toBeGreaterThan((int) strpos($figure[1], 'margin-inline: 0'))
        // And it did not stay behind on the picture as well.
        ->and($rendered)->not->toContain('<img src="/a.jpg" style="margin-inline: auto"');
});
