<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFullscreen;

it('ends the toolbar with the pinned group the fullscreen button sits in', function (): void {
    // Three buttons and no divider: the group is pinned to an edge, and the gap between it
    // and the rest of the bar is the separation a rule would otherwise draw.
    expect(editor()->getPinnedToolbarButtons())->toHaveCount(1)
        ->and(array_slice(toolbarShape(editor()), -1))->toBe([
            [toolsShape(), 'fullscreen'],
        ]);
});

it('empties the pinned half when the button is turned off', function (): void {
    // Nothing is left to pin, so the bar goes back to the plain row of groups it was
    // before - and the insert group is the last of them again.
    expect(array_slice(toolbarShape(editor()->fullscreen(false)->moreTools([])->sourceCode(false)->help(false)->find(false)->statistics(false)), -1))->toBe([
        ['dropdown:bulletList,orderedList,taskList', 'mediaBrowser', 'table', calloutsShape()],
    ]);
});

it('ships the styles the button switches on', function (): void {
    // The button only toggles two class names; without the rules behind them it toggles
    // its own icon and nothing else happens, which is exactly how this once shipped.
    $css = file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css');

    expect($css)->toContain('.fi-fo-rich-editor.fi-arte-fullscreen')
        ->and($css)->toContain('body.fi-arte-fullscreen-lock')
        // The overlay has to clear the panel's topbar and sidebar and stay under
        // Filament's modals, which is the reason it is not the browser's own fullscreen.
        ->and($css)->toMatch('/\.fi-fo-rich-editor\.fi-arte-fullscreen\s*\{[^}]*z-index:\s*3[0-9];/')
        // The published stylesheet is the one the browser gets, so it must not drift.
        ->and($css)->toBe(file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor.css'));
});

it('reads the default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.fullscreen', false);

    expect(editor()->hasFullscreen())->toBeFalse();
});

it('toggles a class on the field rather than calling the fullscreen api', function (): void {
    $html = ToolbarFullscreen::make()->toEmbeddedHtml();

    // The browser's own fullscreen would promote the editor into the top layer, where
    // Filament's modals - rendered at the end of the body - could no longer be seen.
    expect($html)->not->toContain('requestFullscreen')
        ->and($html)->toContain('fi-arte-fullscreen')
        ->toContain('fi-arte-fullscreen-lock')
        // Escape is the way out everyone tries first.
        ->toContain('x-on:keydown.escape.window')
        ->toContain('aria-pressed');
});
