<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\EmojiPlugin;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Livewire\Livewire;

it('offers the picker from the overflow dropdown', function (): void {
    expect(resolvedButtonNames(toolbarDropdown(editor(), 'emoji')))->toContain('emoji');
});

it('opens the picker against the button that was clicked', function (): void {
    $handler = editor()->getTools()['emoji']->getJsHandler();

    // The button hands over its own element because the dropdown it sits in hides itself
    // on the same click - a popup that looked the anchor up afterwards would find nothing.
    // Nothing else travels with the call: the strings are on the editor element.
    expect($handler)->toBe('$getEditor()?.chain().focus().openEmojiPicker($event.currentTarget).run()')
        ->and(EmojiPlugin::getLabels())->toHaveKeys(['label', 'search', 'empty', 'emptyRecent', 'close', 'closeIcon', 'tabs']);
});

it('writes the strings and icons once, on the element the editor is mounted on', function (): void {
    // The picker draws them in the browser, and only PHP knows the locale. They used to
    // travel inside the button's click handler - and the slash menu copies every handler,
    // so each editor carried them twice, nine kilobytes a copy.
    $html = Livewire::test(EditorFormComponent::class)->html();

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $element = (new DOMXPath($document))->query('//*[@data-arte-emoji]')->item(0);

    expect($element)->toBeInstanceOf(DOMElement::class)
        ->and($element->getAttribute('x-ref'))->toBe('editor')
        ->and(json_decode($element->getAttribute('data-arte-emoji'), true))->toBe(editor()->getEmojiSettingsForJs())
        ->and(editor()->getEmojiSettingsForJs())->toBe(EmojiPlugin::getLabels())
        ->and(substr_count($html, 'The emoji you pick collect here.'))->toBe(1);
});

it('draws its tabs with icons from the registry, not with emoji', function (): void {
    $tabs = EmojiPlugin::getTabs();

    // A row of nine coloured faces reads as things to pick rather than as the chrome
    // around them - and an icon here is swapped like every other one this package draws.
    expect($tabs[0])->toMatchArray(['key' => 'recent'])
        ->and($tabs[0]['icon'])->toContain('<svg')
        ->and($tabs[0]['label'])->toBe('Frequently used')
        ->and(array_column($tabs, 'key'))->toBe(EmojiPlugin::TABS);

    config()->set('filament-advanced-rich-editor.icons.emoji_flags', 'heroicon-o-map');

    expect(Icons::get('emoji_flags'))->toBe('heroicon-o-map');
});

it('inserts an emoji as plain text, so nothing has to render it back', function (): void {
    $plugin = EmojiPlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([]);

    $html = '<p>ship it 🚀</p>';

    // No plugin passed: an emoji needs no help surviving, which is the whole point of
    // inserting characters instead of nodes.
    expect(RichContentRenderer::make($html)->toHtml())->toContain('🚀');
});

it('drops the tool when a field turns the picker off', function (): void {
    $editor = editor()->emoji(false);

    expect($editor->getTools())->not->toHaveKey('emoji')
        ->and(resolvedButtonNames(toolbarDropdown($editor, 'subscript')))->not->toContain('emoji')
        // No strings either: nothing is left to read them.
        ->and($editor->getEmojiSettingsForJs())->toBeNull();

    config()->set('filament-advanced-rich-editor.emoji', false);

    expect(editor()->hasEmoji())->toBeFalse();
});

it('ships a list the picker can group and search', function (): void {
    $data = file_get_contents(__DIR__.'/../../resources/js/emoji-data.js');

    // Every tab but the first has a group in the data file, and every group has a tab: the
    // picker draws the tabs it is handed and reads the emoji out of the file by key.
    expect(EmojiPlugin::TABS)->toBe(['recent', 'smileys', 'nature', 'food', 'activities', 'travel', 'objects', 'symbols', 'flags'])
        // The phone keyboard grouping, not Unicode's: its "Smileys & Emotion" and
        // "People & Body" are one tab here.
        ->and(substr_count($data, "', ["))->toBe(count(EmojiPlugin::TABS) - 1);

    foreach (array_slice(EmojiPlugin::TABS, 1) as $group) {
        expect($data)->toContain("['{$group}', [");
    }

    // One pair per emoji, character and Unicode name.
    expect(substr_count($data, '", "'))->toBe(1906)
        // Skin tone variants are five near-duplicates of every gesture. Matched on the end
        // of an entry, since the file's own header says they were left out.
        ->and($data)->not->toContain('skin tone"]');
});
