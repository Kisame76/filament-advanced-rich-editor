<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\PasteCleanupPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;

it('cleans a paste unless a project says otherwise', function (): void {
    expect(pluginNames(editor()))->toContain(PasteCleanupPlugin::class);
});

it('stores nothing and draws nothing', function (): void {
    // By the time a paste reaches the server it is an ordinary document: what this decides
    // was decided in the browser, before ProseMirror parsed the markup. So there is no PHP
    // extension to parse it back, no tool on the bar and no action behind one.
    $plugin = PasteCleanupPlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorTools())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('paste-cleanup');
});

it('hands the extension the one thing the browser cannot know', function (): void {
    // Everything else about the cleaning is a fact about Word and Google Docs. Which style
    // properties a paste keeps is a decision, and only a project can make it.
    expect(editor()->getPasteSettingsForJs())->toBe(['keepStyles' => ['text-align', 'aspect-ratio']]);
});

it('carries them into the markup, which is the only way the extension has to them', function (): void {
    // A TipTap extension has no channel to anything the field knows except the element it
    // is mounted on, so an attribute quietly dropped out of the forked view is a feature
    // that stays mounted and does nothing.
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    expect($compiled)->toContain('data-arte-paste')
        ->and($compiled)->toContain('getPasteSettingsForJs');
});

it('keeps the structural properties and no typography until a project names more', function (): void {
    // Both of the two are structure wearing a style attribute: the alignment, and the shape
    // of an embed. The font, the size and the colour are parsed into marks of this package's
    // own, so one left standing is not noise the next save drops - it is in the document for
    // good.
    expect(editor()->getPasteKeepStyles())->toBe(PasteCleanupPlugin::DEFAULT_KEEP_STYLES)
        ->and(PasteCleanupPlugin::DEFAULT_KEEP_STYLES)->toBe(['text-align', 'aspect-ratio'])
        ->and(editor()->pasteKeepStyles(['Color', ' font-size '])->getPasteKeepStyles())
        // Read back out of a config file and compared against a CSS property, so the case
        // and the spacing are settled here rather than in JavaScript.
        ->toBe(['color', 'font-size']);
});

it('keeps nothing where a project asked for nothing', function (): void {
    expect(editor()->pasteKeepStyles([])->getPasteSettingsForJs())->toBe(['keepStyles' => []]);
});

it('takes the extension away with the setting', function (): void {
    $editor = editor()->pasteCleanup(false);

    // Without the settings the editor element carries no `data-arte-paste`, and the
    // extension that reads them was never registered either - so a paste arrives the way
    // Filament takes it rather than the way this package would have taken it.
    expect($editor->getPasteSettingsForJs())->toBeNull()
        ->and(pluginNames($editor))->not->toContain(PasteCleanupPlugin::class);
});

it('drops a typo in a hand-written list rather than dying on it', function (): void {
    // The list is read out of a published config file. A stray null or number in it is a
    // typo and not a property, and the alternative to dropping it is a TypeError out of a
    // form that was only being rendered.
    config()->set('filament-advanced-rich-editor.paste.keep_styles', ['text-align', null, 42, '  COLOR  ', '']);

    expect(editor()->getPasteKeepStyles())->toBe(['text-align', 'color']);
});

it('reads both halves out of the config file', function (): void {
    config()->set('filament-advanced-rich-editor.paste.keep_styles', ['color']);

    expect(editor()->getPasteKeepStyles())->toBe(['color']);

    config()->set('filament-advanced-rich-editor.paste.cleanup', false);

    expect(editor()->hasPasteCleanup())->toBeFalse();
});

it('lets a field decide for itself', function (): void {
    config()->set('filament-advanced-rich-editor.paste.cleanup', false);

    // A comment field takes whatever it is given; the article beside it does not.
    expect(editor()->pasteCleanup()->hasPasteCleanup())->toBeTrue();
});

it('says how to paste without any of it, which is a key nothing here binds', function (): void {
    // Mod+Shift+V is the browser's own paste-and-match-style, and where a browser keeps
    // that key for itself ProseMirror still sees the shift and takes the text half of the
    // clipboard. Listing it is the whole of what this package adds: the way out of a paste
    // that arrived wearing more than it should was until now something people guessed.
    $keys = array_column(Shortcuts::for(editor()), 'keys', 'label');

    expect($keys)->toHaveKey('Paste as plain text')
        ->and($keys['Paste as plain text'])->toBe(['Mod', 'Shift', 'V']);
});

it('says it whether or not this field cleans a paste', function (): void {
    // The two are not the same feature: one decides what a paste is made of and the other
    // is the browser handing over the text half instead.
    $keys = array_column(Shortcuts::for(editor()->pasteCleanup(false)), 'keys', 'label');

    expect($keys)->toHaveKey('Paste as plain text');
});
