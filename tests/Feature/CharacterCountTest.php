<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;

it('hangs the counter under the editor', function (): void {
    expect(belowContent(editor()))->toBeInstanceOf(CharacterCount::class);
});

it('counts the characters filament validates, not the ones on screen', function (): void {
    // Filament measures `maxLength` with `Str::length($tiptapEditor->getText())`, and that
    // serialiser escapes text and separates every nesting level with a blank line. A
    // counter that read the rendered text would show a smaller number than the one the
    // save is rejected over, which is worse than no counter.
    $html = '<p>Ben &amp; Jerry</p><ul><li><p>one</p></li><li><p>two</p></li></ul>';

    $field = editor();
    $expected = Str::length($field->getTipTapEditor()->setContent($html)->getText());

    expect($field->measureCharacterCount($html)['characters'])->toBe($expected)
        // The escaped ampersand is the point: five characters where the screen shows one.
        ->and($expected)->toBeGreaterThan(Str::length('Ben & Jerryonetwo'));
});

it('counts words the way a reader would', function (): void {
    expect(editor()->measureCharacterCount('<p>one two</p><p>three</p>')['words'])->toBe(3)
        ->and(editor()->measureCharacterCount('')['words'])->toBe(0)
        ->and(editor()->measureCharacterCount(null)['characters'])->toBe(0);
});

it('takes its limit from the one filament already validates', function (): void {
    expect(belowContent(editor())->getLimit())->toBeNull()
        ->and(belowContent(editor()->maxLength(500))->getLimit())->toBe(500)
        // A display-only limit for the fields that want a target rather than a rule.
        ->and(belowContent(editor()->characterCountLimit(160))->getLimit())->toBe(160)
        ->and(belowContent(editor()->maxLength(500)->characterCountLimit(160))->getLimit())->toBe(160);
});

it('leaves the words out until they are asked for', function (): void {
    expect(belowContent(editor())->getWords())->toBeNull()
        ->and(belowContent(editor()->characterCountWords())->getWords())->toBeInt();
});

it('drops the counter when a field or the config says so', function (): void {
    expect(belowContent(editor()->characterCount(false)))->toBeNull();

    config()->set('filament-advanced-rich-editor.character_count.enabled', false);

    expect(editor()->hasCharacterCount())->toBeFalse()
        ->and(belowContent(editor()))->toBeNull()
        ->and(belowContent(editor()->characterCount()))->toBeInstanceOf(CharacterCount::class);
});

it('reads its default for the words from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.character_count.words', true);

    expect(editor()->hasCharacterCountWords())->toBeTrue();
});

it('renders a number that is already right before the first keystroke', function (): void {
    $html = CharacterCount::make()->characters(42)->limit(100)->toEmbeddedHtml();

    expect($html)->toContain('42 / 100 characters')
        // The live half: the editor announces its counts, the line listens.
        ->toContain('arte-character-count')
        ->toContain('x-text="phrase(')
        // Nothing to warn about at 42 of 100.
        ->not->toContain('fi-arte-character-count-warning');

    // A state the field is already in, not one it enters on the next keystroke.
    expect(CharacterCount::make()->characters(120)->limit(100)->toEmbeddedHtml())
        ->toContain('fi-arte-character-count-danger')
        ->and(CharacterCount::make()->characters(95)->limit(100)->toEmbeddedHtml())
        ->toContain('fi-arte-character-count-warning');
});
