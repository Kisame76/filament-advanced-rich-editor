<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageRotate;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;

it('offers the whole image toolbar on a selected image', function (): void {
    $toolbars = editor()->getFloatingToolbars();

    expect($toolbars)->toHaveKey('image')
        ->and($toolbars['image'][0])->toBeInstanceOf(ToolbarImageLock::class)
        ->and($toolbars['image'][1])->toBeInstanceOf(ToolbarImagePanel::class)
        ->and($toolbars['image'][1]->getMode())->toBe(ToolbarImagePanel::MODE_SIZE)
        // Tool names stay strings: the view resolves them against the editor's tools,
        // the same way the main toolbar does.
        ->and($toolbars['image'][2])->toBe('imageRotateLeft')
        ->and($toolbars['image'][3])->toBe('imageRotateRight')
        ->and($toolbars['image'][4])->toBeInstanceOf(ToolbarDivider::class)
        // Where the picture sits, beside how it is turned: both are about placing it
        // rather than about what it says.
        ->and($toolbars['image'][5])->toBe('imageFloatLeft')
        ->and($toolbars['image'][6])->toBe('imageFloatCenter')
        ->and($toolbars['image'][7])->toBe('imageFloatRight')
        ->and($toolbars['image'][8])->toBeInstanceOf(ToolbarDivider::class)
        ->and($toolbars['image'][9])->toBeInstanceOf(ToolbarImagePanel::class)
        ->and($toolbars['image'][9]->getMode())->toBe(ToolbarImagePanel::MODE_ALT)
        ->and($toolbars['image'][10])->toBe('imageDownload')
        ->and($toolbars['image'][11])->toBe('imageDelete')
        ->and(editor()->getTools())->toHaveKeys(['imageRotateLeft', 'imageRotateRight', 'imageFloatLeft', 'imageFloatCenter', 'imageFloatRight', 'imageDownload', 'imageDelete'])
        // Filament's own table toolbar has to survive the addition.
        ->and($toolbars)->toHaveKey('table');
});

it('keeps only the size independent controls when resizing is off', function (): void {
    $buttons = editor()->resizableImages(false)->getFloatingToolbars()['image'];

    // No switch, no size panel, no rotation - all three write what a drag writes - and
    // therefore no divider to separate them from the rest. The float stays: it is not a
    // size, and a field may well allow one without the other.
    expect($buttons[0])->toBe('imageFloatLeft')
        ->and($buttons[1])->toBe('imageFloatCenter')
        ->and($buttons[2])->toBe('imageFloatRight')
        ->and($buttons[3])->toBeInstanceOf(ToolbarDivider::class)
        ->and($buttons[4])->toBeInstanceOf(ToolbarImagePanel::class)
        ->and($buttons[4]->getMode())->toBe(ToolbarImagePanel::MODE_ALT)
        ->and(array_slice($buttons, 5))->toBe(['imageDownload', 'imageDelete']);
});

it('drops the image toolbar entirely when asked', function (): void {
    expect(editor()->imageToolbar(false)->getFloatingToolbars())->not->toHaveKey('image');
});

it('acts on the selected image', function (): void {
    $tools = editor()->getTools();

    expect($tools['imageDelete']->getJsHandler())->toBe('$getEditor()?.chain().focus().deleteSelection().run()')
        // The download reads the source off the node rather than off the DOM, so it also
        // works while the node view is mid-resize.
        ->and($tools['imageDownload']->getJsHandler())->toContain("getAttributes('image')?.src")
        ->and($tools['imageDownload']->getJsHandler())->toContain('link.download');
});

it('accepts a widget in a floating toolbar', function (): void {
    // The parent implementation type-hints every item as `string | ToolbarButtonGroup`,
    // which is why the getter is reimplemented; this pins that it stays reimplemented.
    expect(editor()->floatingToolbars(['image' => [ToolbarImageLock::make()]])->getFloatingToolbars()['image'][0])
        ->toBeInstanceOf(ToolbarImageLock::class);
});

it('loads no resize assist when images cannot be resized', function (): void {
    $editor = editor()->resizableImages(false);

    expect(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof ImageResizePlugin))->toBe([]);
});

it('registers the resize assist alongside the resizing itself', function (): void {
    $plugins = array_filter(editor()->getPlugins(), fn (object $plugin): bool => $plugin instanceof ImageResizePlugin);

    expect($plugins)->toHaveCount(1)
        // The rotation is the one part that needs a counterpart on the PHP side; the size
        // itself travels as Filament's own width and height attributes.
        ->and(ImageResizePlugin::make()->getTipTapPhpExtensions()[0])->toBeInstanceOf(ImageRotate::class)
        ->and(ImageResizePlugin::make()->getEditorTools())->toBe([]);
});

it('renders a switch that mirrors the editor state', function (): void {
    $html = ToolbarImageLock::make()->toEmbeddedHtml();

    expect($html)->toContain('fi-arte-image-lock')
        // The state is read back from the editor on init, because the bubble menu drops
        // its element - and any Alpine state inside it - every time it hides.
        ->toContain('storage()?.unlocked')
        ->toContain('fi-active')
        ->toContain('aria-pressed');
});
