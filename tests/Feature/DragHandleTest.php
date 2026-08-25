<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DragHandlePlugin;

it('gives every field a grip unless a project says otherwise', function (): void {
    expect(pluginNames(editor()))->toContain(DragHandlePlugin::class);
});

it('stores nothing and puts nothing on the bar', function (): void {
    // Rearranging a document changes the order of what is in it and leaves no trace of how,
    // so there is no PHP extension to parse anything back, and no tool: the controls are
    // built in the browser for a block that only exists once somebody hovers it.
    $plugin = DragHandlePlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorTools())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('drag-handle');
});

it('hands over the two things the browser cannot draw for itself', function (): void {
    $settings = editor()->getDragHandleSettingsForJs();

    expect($settings)->toHaveKeys(['insert', 'labels', 'icons'])
        ->and($settings['insert'])->toBeTrue()
        // What a screen reader is given, since neither control has a word on it.
        ->and($settings['labels']['drag'])->toBe('Drag to move this block, click to select it')
        ->and($settings['labels']['insert'])->toBe('Add a block below')
        // Through the icon registry, so a project can swap either of them.
        ->and($settings['icons']['grip'])->toContain('<svg')
        ->and($settings['icons']['insert'])->toContain('<svg');
});

it('carries them into the markup, which is the only way the extension has to them', function (): void {
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    expect($compiled)->toContain('data-arte-drag-handle')
        ->and($compiled)->toContain('getDragHandleSettingsForJs');
});

it('keeps the grip when a field wants no plus', function (): void {
    // Two switches, because they are two things: one rearranges what is there and the other
    // adds to it, and a field that offers only reordering is a reasonable field.
    $editor = editor()->dragHandleInsert(false);

    expect($editor->getDragHandleSettingsForJs()['insert'])->toBeFalse()
        ->and(pluginNames($editor))->toContain(DragHandlePlugin::class);
});

it('takes the extension away with the setting', function (): void {
    $editor = editor()->dragHandle(false);

    // Without the settings the editor element carries no `data-arte-drag-handle`, and the
    // extension that reads them was never registered either.
    expect($editor->getDragHandleSettingsForJs())->toBeNull()
        ->and(pluginNames($editor))->not->toContain(DragHandlePlugin::class);
});

it('reads both switches out of the config file', function (): void {
    config()->set('filament-advanced-rich-editor.drag_handle.insert', false);

    expect(editor()->hasDragHandleInsert())->toBeFalse();

    config()->set('filament-advanced-rich-editor.drag_handle.enabled', false);

    expect(editor()->hasDragHandle())->toBeFalse();
});

it('lets a field decide for itself', function (): void {
    config()->set('filament-advanced-rich-editor.drag_handle.enabled', false);

    expect(editor()->dragHandle()->hasDragHandle())->toBeTrue();
});
