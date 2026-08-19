<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;

it('offers the aspect ratio switch on a selected image', function (): void {
    $toolbars = editor()->getFloatingToolbars();

    expect($toolbars)->toHaveKey('image')
        ->and($toolbars['image'][0])->toBeInstanceOf(ToolbarImageLock::class)
        // Filament's own table toolbar has to survive the addition.
        ->and($toolbars)->toHaveKey('table');
});

it('accepts a widget in a floating toolbar', function (): void {
    // The parent implementation type-hints every item as `string | ToolbarButtonGroup`,
    // which is why the getter is reimplemented; this pins that it stays reimplemented.
    expect(editor()->floatingToolbars(['image' => [ToolbarImageLock::make()]])->getFloatingToolbars()['image'][0])
        ->toBeInstanceOf(ToolbarImageLock::class);
});

it('leaves images alone when they cannot be resized', function (): void {
    $editor = editor()->resizableImages(false);

    expect($editor->getFloatingToolbars())->not->toHaveKey('image')
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof ImageResizePlugin))->toBe([]);
});

it('registers the resize assist alongside the resizing itself', function (): void {
    $plugins = array_filter(editor()->getPlugins(), fn (object $plugin): bool => $plugin instanceof ImageResizePlugin);

    expect($plugins)->toHaveCount(1)
        // No PHP side: the assist only changes how a drag behaves in the browser.
        ->and(ImageResizePlugin::make()->getTipTapPhpExtensions())->toBe([])
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
