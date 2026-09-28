<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\HelpAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\StatisticsAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Numbers;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StatisticsTable;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor\RomanStatisticsAction;

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

    // The line shows its word count too, so the same 1,234 has to be written the same way
    // in both places - which is the whole point of there being one helper.
    $field = editor()->characterCountWords();
    $field->state('<p>'.trim(str_repeat('wort ', 1234)).'</p>');

    // Read out of the dialog the tools menu opens rather than off the source: the rows are
    // built inside the action's own schema, and a number only proves anything where it
    // arrives the way a reader gets it.
    $dialog = statisticsDialogText($field);
    $line = (string) belowContent($field)->toEmbeddedHtml();

    expect(Numbers::format(1234))->toBe('1.234')
        ->and($dialog)->toContain('1.234')
        ->and($dialog)->not->toContain('1,234')
        // The same document, the same shape, on the line underneath it.
        ->and($line)->toContain('1.234');
});

it('says how long the document is, row by row', function (): void {
    // The five rows are built where nothing else can reach them - `rowsFor()` only works on
    // a field inside a schema - so they are read back through the dialog itself. Without
    // this the labels, their order and the reading time are covered by nothing.
    $field = editor();
    $field->state('<p>eins zwei</p><p>drei</p>');

    $counted = $field->measureDocument($field->getState());
    $text = statisticsDialogText($field);
    $row = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.statistics.{$key}");

    // The numbers are read off the field rather than written down again: showing the field's
    // own measurement is the dialog's whole job, and counting a second time here would be a
    // test that agrees with itself while the screen says something else. What is asserted is
    // that each number arrives, in order, beside the label it belongs to.
    $expected = [
        $row('words').' '.Numbers::format($counted['words']),
        $row('characters').' '.Numbers::format($counted['characters']),
        $row('characters_without_spaces').' '.Numbers::format($counted['charactersWithoutSpaces']),
        $row('paragraphs').' '.Numbers::format($counted['paragraphs']),
        // Anything under a minute is said in words, because "1" reads as a rounded number.
        $row('reading_time').' '.$row('reading_time_under'),
    ];

    $found = array_map(static fn (string $needle): int|false => mb_strpos($text, $needle), $expected);

    expect($counted['readingMinutes'])->toBe(1)
        ->and($found)->not->toContain(false, "A row is missing from: {$text}")
        ->and($found)->toBe(array_values(collect($found)->sort()->all()), "The rows are out of order in: {$text}");
});

it('says an empty document takes no time at all', function (): void {
    $field = editor();
    $field->state(null);

    $row = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.statistics.{$key}");

    expect(statisticsDialogText($field))
        ->toContain($row('words').' 0')
        ->and(statisticsDialogText($field))
        ->toContain($row('reading_time').' '.$row('reading_time_none'));
});

it('lets a project decide what its numbers look like', function (): void {
    // `Numbers` is where this package decided, not the last word on it. The rows go through
    // an overridable `number()` so that a project changing the shape changes all five at
    // once - four calls naming `Numbers` outright would leave a subclass with a dialog that
    // formatted four of its numbers one way and the fifth another.
    $field = editor();
    $field->state('<p>eins zwei</p>');

    expect(array_column(RomanStatisticsAction::rows($field), 'value'))
        ->toContain('[2]')
        ->and(array_column(RomanStatisticsAction::rows($field), 'value'))
        ->toContain('[9]');
});

it('answers an empty document in the same shape as an occupied one', function (): void {
    // The empty answer is written out separately from the counted one, so nothing in the
    // language holds their names or their order together. This does - a sixth number added
    // to one side and not the other stops here rather than at a caller reading positionally.
    $field = editor();

    expect(array_keys($field->measureDocument(null)))
        ->toBe(array_keys($field->measureDocument('<p>wort</p>')))
        ->and(array_keys($field->measureCharacterCount(null)))
        ->toBe(array_keys($field->measureCharacterCount('<p>wort</p>')));
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
