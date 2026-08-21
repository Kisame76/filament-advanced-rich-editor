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

    // The pointer is rotated back out of the picture's frame, and the handle is renamed to
    // the edge of the element the pointer is really pulling. The node view then does its own
    // arithmetic unchanged and gets it right at every angle - corners and edges alike.
    expect($js)->toContain('originalHandleResize(...rotateDelta(deltaX, deltaY))')
        ->and($js)->toContain('nodeView.activeHandle = renamedHandle')
        // Restored even when the node view throws, or the next drag inherits the wrong edge.
        ->and($js)->toContain('nodeView.activeHandle = realHandle')
        ->and($js)->toContain('90: { right:');
});

it('corrects the edge handles too, not only the corners', function (): void {
    // Filament draws only corners today. If it ever draws the edges as well, they go through
    // the same rename and the same rotation rather than falling out of the correction.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('right: [1, 0]')
        ->and($js)->toContain('bottom: [0, 1]');
});

it('ignores a mouse button that cannot drag', function (): void {
    // A right click on a handle opens a context menu and never starts a resize, so arming
    // the drag would leave the floating bar held open over the menu.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('if (event.button !== undefined && event.button !== 0)');
});

it('pins the size of a turned picture as soon as its file arrives', function (): void {
    // Turning normally pins the size from what is on screen, but an image that had not
    // loaded could not be measured - and a turned picture with no size of its own keeps an
    // unturned box, lying across the lines around it.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('pinTurnedSize(image)')
        ->and($js)->toContain('if (!attrs?.rotate)');
});

it('grows from any corner while the ratio is kept', function (): void {
    // For a corner the node view reads the width and discards the other delta, so dragging
    // straight down did nothing at all - and on a quarter turned picture it was dragging
    // sideways that did nothing. The pointer is measured along the direction the corner
    // points in instead, and that one distance drives the resize.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('Math.abs(byX) >= Math.abs(byY) ? byX : byY')
        ->and($js)->toContain('nodeView.preserveAspectRatio || nodeView.isShiftKeyPressed');
});

it('does not let a stuck shift key keep the ratio', function (): void {
    // The node view listens for the shift key only while a drag runs, so a shift released
    // after the mouse button left the flag standing - and every later drag kept the ratio
    // however the lock was set.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('nodeView.isShiftKeyPressed = event.shiftKey === true');
});

it('pins the size of a picture that is turned before it is resized', function (): void {
    // The margins that make a turned image's box match what is drawn need a width and a
    // height, and an image that has never been resized carries neither - so turning it left
    // the box unturned, the picture lying across the lines around it and the handles off its
    // corners.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-rotate.js');

    expect($js)->toContain('measure(view, position, node.attrs)')
        ->and($js)->toContain('return { width: image.offsetWidth, height: image.offsetHeight }');
});

it('keeps the toolbar open while the picture is being resized', function (): void {
    // Grabbing a corner never selected the picture - the node view swallows that mousedown
    // before ProseMirror sees it - and the transaction that commits a finished drag leaves a
    // caret beside it. Both closed the bar under the pointer that was using it.
    $js = file_get_contents(dirname(__DIR__, 2).'/resources/dist/js/image-resize.js');

    expect($js)->toContain('reselect(editorView, nodeView)')
        ->and($js)->toContain('storage.resizing = true')
        // A timeout rather than a frame: frames do not come while the tab is in the
        // background, and the selection would stay dropped until it was looked at again.
        ->and($js)->toContain('setTimeout(() => {')
        // Nothing may outlive a drag whose mouseup was never heard.
        ->and($js)->toContain("window.addEventListener('blur', finish)");
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
