<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TouchToolbarPlugin;

/**
 * The toolbar on a touch screen. Whether a field is worked with a finger is only the browser's
 * to know, so the PHP half is that every field carries the script and the stylesheet carries
 * the rules; what they do is tested in `tests/js/touch-toolbar.test.js`,
 * `tests/js/toolbar-layout.test.js` and `tests/js/menu-position.test.js`.
 */
it('carries the script on every field, since only the browser knows what holds it', function (): void {
    $script = FilamentAsset::getScriptSrc('advanced-rich-editor/touch-toolbar', 'kisame76/filament-advanced-rich-editor');

    expect(editor()->getTipTapJsExtensions())->toContain($script)
        ->and(editor()->preset('minimal')->stickyToolbar(false)->getTipTapJsExtensions())->toContain($script)
        // Nothing is stored: no node, no mark.
        ->and(TouchToolbarPlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(TouchToolbarPlugin::make()->getEditorTools())->toBe([]);
});

it('ships the rules for a finger, and for a bar on the keyboard', function (): void {
    $css = file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css');

    expect($css)->toContain('@media (pointer: coarse)')
        ->and($css)->toContain('.fi-arte .fi-fo-rich-editor-toolbar.fi-arte-docked')
        ->and($css)->toContain('.fi-arte.fi-arte-docking');
});
