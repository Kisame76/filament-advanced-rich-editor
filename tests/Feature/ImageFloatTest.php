<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageFloatPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageFloat;

/**
 * The style attribute of the first `<img>` in a fragment, as a sorted list of
 * declarations. Read back rather than matched as a string: the order the schema writes
 * them in carries no meaning, and a test spelling it out would fail on a reordering that
 * changed nothing.
 *
 * @return array<int, string>
 */
function imageStyle(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $style = $document->getElementsByTagName('img')->item(0)?->getAttribute('style') ?? '';

    $declarations = array_values(array_filter(array_map(
        static fn (string $declaration): string => trim($declaration),
        explode(';', $style),
    )));

    sort($declarations);

    return $declarations;
}

/**
 * The width on the first `<img>`, without its unit.
 *
 * Filament writes it as `width: 200px` on current releases and as `width: 200` on the
 * oldest this package still supports, and neither spelling says anything about a float.
 * Asking for the number is the same decision `imageStyle()` makes when it sorts instead of
 * matching a string: a test that pins what carries no meaning fails on a change that moved
 * nothing.
 */
function imageWidth(string $html): ?string
{
    foreach (imageStyle($html) as $declaration) {
        if (preg_match('/^width:\s*(\d+(?:\.\d+)?)/', $declaration, $matches) === 1) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * @return array<int, string>
 */
function figureStyle(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $style = $document->getElementsByTagName('figure')->item(0)?->getAttribute('style') ?? '';

    $declarations = array_values(array_filter(array_map(
        static fn (string $declaration): string => trim($declaration),
        explode(';', $style),
    )));

    sort($declarations);

    return $declarations;
}

function floatedImage(string $side = 'left'): string
{
    return '<p><img src="/cat.jpg" style="float: '.$side.'" /></p>';
}

it('keeps the side a picture was floated to', function (): void {
    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toContain('float: left');
});

it('writes a gap beside the picture, so the text does not touch it', function (): void {
    // The page a document lands on has not loaded this package's stylesheet, so a bare
    // `float` would have the words against the frame.
    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toBe(['float: left', 'margin-block-end: 1rem', 'margin-inline-end: 1rem']);
});

it('puts the gap on the other side of a picture floated right', function (): void {
    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage('right'))->toHtml()))
        ->toBe(['float: right', 'margin-block-end: 1rem', 'margin-inline-start: 1rem']);
});

it('takes the gap from the config', function (): void {
    config()->set('filament-advanced-rich-editor.images.float_gap', '2.5rem');

    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toContain('margin-inline-end: 2.5rem');
});

it('reads a bare number as pixels', function (): void {
    config()->set('filament-advanced-rich-editor.images.float_gap', 24);

    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toContain('margin-inline-end: 24px');
});

it('writes the bare float where the project would rather draw the gap itself', function (): void {
    config()->set('filament-advanced-rich-editor.images.float_gap', null);

    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toBe(['float: left']);
});

it('refuses a gap that is not a length', function (): void {
    // It is interpolated into a `style` attribute that nothing downstream inspects, and
    // there is no correct escaping for "this was meant to be a number".
    config()->set('filament-advanced-rich-editor.images.float_gap', '1rem; position: fixed');

    expect(imageStyle(AdvancedRichContentRenderer::make(floatedImage())->toHtml()))
        ->toBe(['float: left']);
});

it('centres a picture as a block instead of floating it', function (): void {
    // Not a float, and it cannot be one: CSS has no way to run text down both sides of a
    // block. A block with automatic margins is what every editor means by centred.
    $html = '<p><img src="/cat.jpg" style="display: block; margin-inline: auto" /></p>';

    expect(imageStyle(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toBe(['display: block', 'margin-inline: auto']);
});

it('reads a centred picture back off the margin that centres it', function (): void {
    // The round trip through the schema is the thing being asserted: the attribute is
    // parsed out of the style and written back into it.
    $html = '<p><img src="/cat.jpg" style="margin-inline: auto; display: block" /></p>';

    expect(imageStyle(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toContain('margin-inline: auto');
});

it('moves the centring out to the figure a caption builds', function (): void {
    $html = '<p><img src="/cat.jpg" data-caption="Eine Katze" style="display: block; margin-inline: auto" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect(figureStyle($rendered))->toContain('margin-inline: auto')
        ->and(imageStyle($rendered))->toBe([]);
});

it('refuses a side that is not a side', function (): void {
    $html = '<p><img src="/cat.jpg" style="float: inline-start" /></p>';

    expect(imageStyle(AdvancedRichContentRenderer::make($html)->toHtml()))->toBe([]);
});

it('leaves a picture nobody floated alone', function (): void {
    expect(imageStyle(AdvancedRichContentRenderer::make('<p><img src="/cat.jpg" /></p>')->toHtml()))
        ->toBe([]);
});

it('keeps the float beside a size and a rotation', function (): void {
    // Three extensions write into one `style`, and tiptap-php merges rather than
    // overwrites. If it ever stopped doing so, this is where it would show.
    $html = '<p><img src="/cat.jpg" width="200" height="100" style="float: left; transform: rotate(90deg)" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect(imageStyle($rendered))
        ->toContain('float: left')
        ->toContain('transform: rotate(90deg)')
        ->and(imageWidth($rendered))->toBe('200');
});

it('moves the float out to the figure a caption builds', function (): void {
    // A figure is a block, and a float on a picture inside one floats the picture within
    // the block rather than the block itself.
    $html = '<p><img src="/cat.jpg" data-caption="Eine Katze" style="float: left" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect(figureStyle($rendered))
        ->toContain('float: left')
        ->toContain('margin-inline-end: 1rem')
        ->and(imageStyle($rendered))->toBe([]);
});

it('leaves the picture its own styles when the float moves out', function (): void {
    $html = '<p><img src="/cat.jpg" width="200" data-caption="Eine Katze" style="float: right" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    // Nothing but the width stayed behind on the picture.
    expect(figureStyle($rendered))->toContain('float: right')
        ->and(imageStyle($rendered))->toHaveCount(1)
        ->and(imageWidth($rendered))->toBe('200');
});

it('registers the extension whether or not a field asked for the buttons', function (): void {
    // A picture somebody floated is one that should still float, and a renderer that has
    // to be told is one that drops it the day somebody forgets to say so.
    $names = array_map(
        static fn (object $extension): mixed => $extension::$name,
        AdvancedRichContentRenderer::make()->getTipTapPhpExtensions(),
    );

    expect($names)->toContain(ImageFloat::$name);
});

it('puts the plugin on a field that offers the buttons, and takes it off one that does not', function (): void {
    expect(pluginNames(editor()))->toContain(ImageFloatPlugin::class)
        ->and(pluginNames(editor()->imageFloat(false)))->not->toContain(ImageFloatPlugin::class);
});

it('drops the buttons from the image toolbar when the switch is off', function (): void {
    $buttons = editor()->imageFloat(false)->getFloatingToolbars()['image'];

    expect($buttons)->not->toContain('imageFloatLeft')
        ->and($buttons)->not->toContain('imageFloatRight');
});

it('lets the project switch it off for every field at once', function (): void {
    config()->set('filament-advanced-rich-editor.images.float', false);

    expect(editor()->hasImageFloat())->toBeFalse()
        ->and(editor()->imageFloat()->hasImageFloat())->toBeTrue();
});

it('leaves a turned picture its own margins when a caption wraps it', function (): void {
    // The turn writes `margin-block` and `margin-inline` together to make the layout box
    // match what is drawn. Moving one half of that pair out to the figure and leaving the
    // other on the image splits the compensation across two elements, and the picture lies
    // across the lines around it.
    $html = '<p><img src="/cat.jpg" width="300" height="200" data-caption="Untertitel" style="transform: rotate(90deg)" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect(imageStyle($rendered))
        ->toContain('margin-block: 50px')
        ->toContain('margin-inline: -50px')
        ->and(figureStyle($rendered))->toBe(['inline-size: fit-content', 'margin-inline: 0', 'max-inline-size: 100%']);
});

it('still moves the centring of a turned picture that is also centred', function (): void {
    // Both write `margin-inline`, and only the automatic one places the block.
    $html = '<p><img src="/cat.jpg" width="300" height="200" data-caption="Untertitel" '
        .'style="transform: rotate(90deg); display: block; margin-inline: auto" /></p>';

    $rendered = AdvancedRichContentRenderer::make($html)->toHtml();

    expect(figureStyle($rendered))->toContain('margin-inline: auto')
        ->toContain('display: block')
        ->and(imageStyle($rendered))->toContain('margin-block: 50px');
});
