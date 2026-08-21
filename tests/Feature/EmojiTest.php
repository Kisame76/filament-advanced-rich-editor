<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\EmojiPlugin;

it('offers the picker from the overflow dropdown', function (): void {
    expect(resolvedButtonNames(toolbarDropdown(editor(), 'emoji')))->toContain('emoji');
});

it('opens the picker against the button that was clicked', function (): void {
    $handler = editor()->getTools()['emoji']->getJsHandler();

    // The button hands over its own element because the dropdown it sits in hides itself
    // on the same click - a popup that looked the anchor up afterwards would find nothing.
    // The picker draws its own strings, and this is the only place that knows the locale,
    // so they travel with the call - as a `JSON.parse`, which is what `Js::from` renders.
    expect($handler)->toContain('openEmojiPicker($event.currentTarget')
        ->toContain('JSON.parse(')
        ->and(EmojiPlugin::getLabels())->toHaveKeys(['label', 'search', 'empty', 'emptyRecent', 'close', 'closeIcon', 'tabs']);
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
        ->and(resolvedButtonNames(toolbarDropdown($editor, 'subscript')))->not->toContain('emoji');

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
