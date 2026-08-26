<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CharactersPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

it('registers the tool and the extension behind it', function (): void {
    $tools = editor()->getTools();

    expect($tools)->toHaveKey('characters')
        ->and($tools['characters']->getJsHandler())->toStartWith('$getEditor()?.chain().focus().openCharacterPicker($event.currentTarget, ')
        ->and($tools['characters']->getIcon())->toBe(Icons::get('characters'))
        ->and(pluginNames(editor()))->toContain(CharactersPlugin::class);
});

it('declares nothing on the PHP side, because a dash is a character', function (): void {
    // No node, no mark, no attribute. What the picker inserts is text, so it travels
    // through the sanitiser, the save and the renderer like any other letter - and switching
    // the picker off later leaves every character already written where it is.
    expect(CharactersPlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(AdvancedRichContentRenderer::make('<p>Bis 2030 – längstens.</p>')->toHtml())
        ->toContain('–');
});

it('hands the picker its strings and its icons, because it is built in the browser', function (): void {
    $labels = CharactersPlugin::getLabels();

    expect($labels)->toHaveKeys(['label', 'search', 'empty', 'emptyRecent', 'close', 'closeIcon', 'tabs'])
        ->and($labels['label'])->toBe('Special character')
        ->and($labels['closeIcon'])->toContain('<svg');
});

it('opens on the tab holding what was picked last, then the groups', function (): void {
    // `recent` is not a group of the list but of the reader's own history, which is why it
    // is first: a document that needs an en dash usually needs a second one.
    expect(array_column(CharactersPlugin::getTabs(), 'key'))
        ->toBe(['recent', 'punctuation', 'currency', 'math', 'arrows', 'symbols', 'latin', 'greek'])
        ->and(CharactersPlugin::getTabs()[0]['label'])->toBe('Recent')
        ->and(CharactersPlugin::getTabs()[1]['icon'])->toContain('<svg');
});

it('gives every tab an icon of its own in the registry', function (): void {
    // Drawn icons rather than a representative glyph, for the reason the emoji tabs use
    // them - and every one has to be swappable like the rest.
    foreach (CharactersPlugin::TABS as $tab) {
        expect(Icons::get("characters_{$tab}"))->not->toBeEmpty();
    }
});

it('sits in the overflow menu next to the emoji picker', function (): void {
    // The two do the same job - a character the keyboard cannot type - and the popup behind
    // them is literally the same one, so they belong in the same place.
    $more = editor()->getMoreTools();

    expect($more)->toContain('emoji', 'characters')
        ->and(array_slice($more, -2))->toBe(['emoji', 'characters']);
});

it('is listed in the slash menu under what gets added to the document', function (): void {
    $groups = collect(SlashMenu::for(editor())['groups'])->keyBy('key');

    expect(array_column($groups['insert']['items'], 'name'))->toContain('characters')
        ->and(array_column($groups['style']['items'], 'name'))->not->toContain('characters');
});

it('answers to the words somebody types instead of the label', function (): void {
    $item = collect(SlashMenu::for(editor())['groups'])
        ->pluck('items')
        ->flatten(1)
        ->firstWhere('name', 'characters');

    expect($item['aliases'])->toContain('symbol', 'dash', 'arrow');
});

it('drops the tool and the extension when the field switched them off', function (): void {
    $editor = editor()->characters(false);

    expect(pluginNames($editor))->not->toContain(CharactersPlugin::class)
        ->and(array_keys($editor->getTools()))->not->toContain('characters')
        // And the name disappears from the overflow menu on its own, because an
        // unregistered name is dropped where a dropdown resolves it.
        ->and(resolvedButtonNames(toolbarDropdown($editor, 'subscript')))->not->toContain('characters');
});

it('reads its default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.characters', false);

    expect(editor()->hasCharacters())->toBeFalse()
        ->and(editor()->characters()->hasCharacters())->toBeTrue();
});
