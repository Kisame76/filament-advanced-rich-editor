<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\HelpAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\StatisticsAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StatisticsTable;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * The statistics dialog, and the numbers behind it.
 *
 * Counted in one place with the character counter under the field, because two answers to
 * "how long is this" on one screen that disagree are worse than one answer.
 */
it('leaves the counter under the field measuring only what it shows', function (): void {
    // The line shows two numbers, so it asks for two. Blocks and reading time cost a second
    // walk of the document and a regex over the text, and the line would throw both away on
    // every render of every field that has one.
    expect(array_keys(editor()->measureCharacterCount('<p>ab</p>')))->toBe(['characters', 'words']);
});

it('counts the same characters and words the counter under the field counts', function (): void {
    // Filament measures `maxLength` with `Str::length($editor->getText())`, and that
    // serialiser escapes the text - so a single `&` costs the five of `&amp;`. The dialog
    // has to say the number the save is refused over, not a friendlier one.
    $counted = editor()->measureDocument('<p>Fish &amp; chips</p>');

    expect($counted['characters'])->toBe(16)
        ->and($counted['words'])->toBe(3);
});

it('counts characters without spaces off the same string', function (): void {
    // Off the same string, or the dialog shows two numbers called characters that were
    // measured differently and cannot be told apart.
    $counted = editor()->measureDocument('<p>ab cd</p><p>ef</p>');

    expect($counted['characters'])->toBe(9)
        ->and($counted['charactersWithoutSpaces'])->toBe(6);
});

it('counts the blocks a reader would call paragraphs', function (): void {
    $counted = editor()->measureDocument(
        '<h2>A heading</h2><p>One.</p><p>Two.</p><ul><li>An item</li></ul>',
    );

    expect($counted['paragraphs'])->toBe(4);
});

it('counts nothing in an empty document', function (): void {
    expect(editor()->measureDocument(null))
        // Same keys in the same order an occupied document reports, so a caller reading
        // them positionally cannot be surprised by an empty field.
        ->toBe([
            'characters' => 0,
            'words' => 0,
            'charactersWithoutSpaces' => 0,
            'paragraphs' => 0,
            'readingMinutes' => 0,
        ]);
});

it('estimates the reading time from the words', function (): void {
    config()->set('filament-advanced-rich-editor.statistics.words_per_minute', 10);

    // Rounded up: a text that takes a minute and a bit takes two.
    expect(editor()->measureDocument('<p>'.implode(' ', array_fill(0, 11, 'wort')).'</p>')['readingMinutes'])
        ->toBe(2)
        // Anything at all is at least a minute; the dialog says "under a minute" itself.
        ->and(editor()->measureDocument('<p>wort</p>')['readingMinutes'])->toBe(1);
});

it('offers the dialog through the tools menu', function (): void {
    // Not on the bar: inside a dropdown a switched-off tool is dropped, and this is the
    // place the config file already reserved for it.
    expect(editor()->getToolsMenu())->toContain('statistics')
        ->and(array_keys(ToolbarLayout::tokens()))->toContain('statistics')
        ->and(editor()->getTools())->toHaveKey('statistics');
});

it('takes the tool away with the switch', function (): void {
    expect(editor()->statistics(false)->hasStatistics())->toBeFalse()
        ->and(editor()->statistics(false)->getTools())->not->toHaveKey('statistics');
});

it('reads whether it is offered from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.statistics.enabled', false);

    expect(editor()->hasStatistics())->toBeFalse()
        // And a field still overrules it.
        ->and(editor()->statistics()->hasStatistics())->toBeTrue();
});

it('is a dialog to read rather than one to answer', function (): void {
    // Nothing to submit and nothing to cancel: a footer holding one button called Close is
    // furniture next to the cross that already closes the dialog. What has to stay is a way
    // out that does not need a mouse - the cross, Escape, and the click beside it.
    $action = StatisticsAction::make();

    expect($action->getModalSubmitAction())->toBeNull()
        ->and($action->getModalCancelAction())->toBeNull()
        ->and($action->hasModalCloseButton())->toBeTrue()
        ->and($action->isModalClosedByClickingAway())->toBeTrue()
        ->and($action->isModalClosedByEscaping())->toBeTrue();
});

it('reads the same way the shortcut list does', function (): void {
    // The two dialogs this package ships are both read and never answered, so they close
    // the same way. One of them behaving differently is a difference nobody meant.
    $help = HelpAction::make();

    expect($help->getModalSubmitAction())->toBeNull()
        ->and($help->getModalCancelAction())->toBeNull()
        ->and($help->hasModalCloseButton())->toBeTrue()
        ->and($help->isModalClosedByClickingAway())->toBeTrue();
});

it('formats its numbers the way the line under the field formats them', function (): void {
    // Two numbers for one document, one screen apart: the counter uses `Number::format()`
    // and an `Intl.NumberFormat` built from the app's locale, so a dialog on `number_format`
    // would read 1,234 beside a line reading 1.234.
    app()->setLocale('de');

    $rows = StatisticsAction::rowsFor(editor()->maxLength(9999));
    $values = array_column($rows, 'value');

    expect(CharacterCount::number(1234))->toBe('1.234')
        ->and($values)->toContain(CharacterCount::number(0));
});

it('escapes what a project put in its translation file', function (): void {
    // The labels are translatable, so they are a project's text rather than this package's.
    $html = StatisticsTable::make()
        ->rows([['label' => '<b>Wörter</b>', 'value' => '<i>7</i>']])
        ->toEmbeddedHtml();

    expect($html)->toContain('&lt;b&gt;')
        ->and($html)->toContain('&lt;i&gt;')
        ->and($html)->not->toContain('<b>');
});
