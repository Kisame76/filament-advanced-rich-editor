<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;

$render = fn (string $html): string => RichContentRenderer::make($html)
    ->plugins([ImageResizePlugin::make()])
    ->toHtml();

it('keeps a rotation across the php round trip', function () use ($render): void {
    $html = '<p><img src="/a.png" width="300" height="200" style="width: 300px; height: 200px; transform: rotate(90deg)"></p>';

    expect($render($html))->toContain('rotate(90deg)');
});

it('loses the rotation without the plugin, which is why the extension exists', function (): void {
    $html = '<p><img src="/a.png" style="transform: rotate(90deg)"></p>';

    expect(RichContentRenderer::make($html)->toHtml())->not->toContain('rotate');
});

it('makes the layout box match the turned picture', function () use ($render): void {
    $html = '<p><img src="/a.png" width="300" height="200" style="transform: rotate(90deg)"></p>';

    // 300x200 turned on its side occupies 200x300, so the box gains 50px above and below
    // and loses 50px either side. Without it the image would overlap its neighbours.
    expect($render($html))->toContain('margin-block: 50px')
        ->toContain('margin-inline: -50px');
});

it('leaves a half turn alone, since the box already fits', function () use ($render): void {
    $html = '<p><img src="/a.png" width="300" height="200" style="transform: rotate(180deg)"></p>';

    expect($render($html))->toContain('rotate(180deg)')
        ->not->toContain('margin-block');
});

it('accepts quarter turns only', function () use ($render): void {
    // Security: the angle lands in a style attribute that Filament's sanitiser passes
    // through, so anything but a quarter turn is refused rather than trusted.
    expect($render('<p><img src="/a.png" style="transform: rotate(46deg)"></p>'))->toContain('rotate(90deg)')
        // Rounded to the nearest quarter turn, and a full turn is no turn at all.
        ->and($render('<p><img src="/a.png" style="transform: rotate(37deg)"></p>'))->not->toContain('transform')
        ->and($render('<p><img src="/a.png" style="transform: rotate(360deg)"></p>'))->not->toContain('transform')
        ->and($render('<p><img src="/a.png" style="transform: rotate(-90deg)"></p>'))->toContain('rotate(270deg)');
});

it('offers a rotation in both directions', function (): void {
    $tools = editor()->getTools();

    // No `focus()`: it would collapse the node selection the command reads the angle from.
    expect($tools['imageRotateLeft']->getJsHandler())->toBe('$getEditor()?.commands.rotateImage(-90)')
        ->and($tools['imageRotateRight']->getJsHandler())->toBe('$getEditor()?.commands.rotateImage(90)');
});

it('writes the size the way a drag does', function (): void {
    $html = ToolbarImagePanel::size()->toEmbeddedHtml();

    expect($html)->toContain("updateAttributes('image', attributes)")
        // The node selection is restored first, because focusing collapses it to a caret.
        ->toContain('setNodeSelection(position)')
        ->toContain('arteImageResize?.unlocked')
        ->toContain('type="number"');
});

it('applies both sizes at once rather than on every keystroke', function (): void {
    $html = ToolbarImagePanel::size()->toEmbeddedHtml();

    // With the ratio locked, committing each field on its own would undo the other one
    // before the second number had been typed.
    expect($html)->toContain('fi-arte-image-panel-apply')
        ->toContain('x-bind:disabled="! isDirty()"')
        ->not->toContain('x-on:change="commit')
        ->not->toContain('x-on:blur="commit');
});

it('carries the aspect ratio lock between the fields', function (): void {
    $html = ToolbarImagePanel::size()->toEmbeddedHtml();

    expect($html)->toContain('fi-arte-image-panel-lock')
        ->toContain('toggleLock()')
        // One state in two places: the toolbar switch and this one follow each other.
        ->toContain('arte-image-lock')
        ->and(ToolbarImageLock::make()->toEmbeddedHtml())
        ->toContain('arte-image-lock');
});

it('removes an alt text rather than storing an empty one', function (): void {
    $html = ToolbarImagePanel::alt()->toEmbeddedHtml();

    // The renderer drops falsy attributes on both sides, so an empty alt cannot be stored.
    expect($html)->toContain("this.alt.trim() === '' ? null : this.alt")
        ->toContain('type="text"');
});

it('leaves a turned image draggable', function (): void {
    // The handles used to be hidden the moment an image was turned, on the assumption that
    // they rotate with the picture. They do not: Filament positions them on the resize
    // wrapper, which keeps its unrotated box, so `bottom-right` is the bottom right corner
    // at every angle and the drag needs no correction at all.
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-advanced-rich-editor.css');

    expect($css)->not->toContain('data-resize-handle');
});

it('lets a turned image use its full width', function (): void {
    // The compensating margins make the layout box narrower than the image itself at a
    // quarter turn, and `max-width: 100%` reads that shrunken box as the limit - a
    // landscape picture turned on its side lost everything past its own height.
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-advanced-rich-editor.css');

    expect($css)->toContain("[data-resize-wrapper] > img[style*='rotate(']")
        ->and($css)->toContain('max-width: none');
});

it('drags a turned image along the axes the pointer is moving in', function (): void {
    // The wrapper carries the picture's on-screen box, so `bottom-right` is the corner the
    // pointer grabbed - but the node view reads the pointer in the element's own axes, which
    // a quarter turn has swapped. Swapping the deltas back is right for the handles on the
    // turn's diagonal; the other two need the signs as well, or dragging out shrinks.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain("'bottom-right': 1, 'top-left': 1, 'bottom-left': -1, 'top-right': -1")
        ->and($js)->toContain('originalHandleResize(sign * deltaY, sign * deltaX)')
        // A half turn leaves both the box and the axes the same way round.
        ->and($js)->toContain('quarterTurned');
});

it('keeps the picture selected through a turn', function (): void {
    // `setNodeMarkup` writes a new node over the old one and the selection that survives it
    // is a caret beside the image rather than a selection of it. The floating toolbar is
    // shown while the image is active, so without putting the node selection back the bar
    // vanishes on the first turn - taking the button that was just pressed with it.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-rotate.js');

    expect($js)->toContain('tr.setSelection(NodeSelection.create(tr.doc, position))')
        // Read off ProseMirror's own state module, which Filament exposes.
        ->and($js)->toContain('window.FilamentRichEditor?.tiptap?.pmState?.NodeSelection');
});
