<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Numbers;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CharacterCountPlugin;

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

it('reads as full rather than nearly full where the field refuses more', function (): void {
    // On a field that blocks, the count can never pass the limit - so a line that only
    // turns red *above* it would never turn red at all, and somebody would sit at "almost
    // full" wondering why the keyboard stopped answering.
    expect(CharacterCount::make()->characters(100)->limit(100)->enforced()->toEmbeddedHtml())
        ->toContain('fi-arte-character-count-danger')
        // The browser is handed the same threshold the first render used, so the state
        // cannot flip on the first keystroke that changes nothing.
        ->toContain('danger\\u0022:100')
        // And a field that only warns keeps the rule it had: full is not over.
        ->and(CharacterCount::make()->characters(100)->limit(100)->toEmbeddedHtml())
        ->not->toContain('fi-arte-character-count-danger');
});

it('hands the counter the field\'s answer about refusing', function (): void {
    $enforcing = belowContent(editor()->maxLength(100)->enforceMaxLength());

    // Asserted on the number handed to the browser rather than on generated JavaScript:
    // the rule itself lives in one place now, so what a test can check is which thresholds
    // came out of it.
    expect($enforcing->toEmbeddedHtml())->toContain('danger\\u0022:100')
        ->and(belowContent(editor()->maxLength(100))->toEmbeddedHtml())
        ->toContain('danger\\u0022:101');
});

it('refuses nothing until it is asked to', function (): void {
    // `maxLength()` is a rule the save is checked against, and it stays one. Refusing the
    // keystroke as well is a separate decision - a comment box blocks, an article warns.
    expect(editor()->maxLength(100)->enforcesMaxLength())->toBeFalse()
        ->and(editor()->maxLength(100)->getCharacterCountSettingsForJs())->toBeNull();
});

it('hands the browser the limit the save is refused over', function (): void {
    // `getMaxLength()` and not `getCharacterCountLimit()`: the second falls back to the
    // first but may be set on its own, and its own docblock calls it a number with no rule
    // behind it. Enforcing a number nothing validates would refuse a keystroke the server
    // would have accepted.
    $editor = editor()->maxLength(100)->characterCountLimit(50)->enforceMaxLength();

    expect($editor->getCharacterCountSettingsForJs())->toBe(['limit' => 100, 'enforce' => true]);
});

it('says nothing to the browser without a limit to enforce', function (): void {
    // Nothing to refuse over, so nothing is sent and the extension stays out of the way.
    expect(editor()->enforceMaxLength()->getCharacterCountSettingsForJs())->toBeNull();
});

it('reads whether it enforces from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.character_count.enforce', true);

    expect(editor()->maxLength(100)->enforcesMaxLength())->toBeTrue()
        // And a field still overrules it.
        ->and(editor()->maxLength(100)->enforceMaxLength(false)->enforcesMaxLength())->toBeFalse();
});

it('loads the extension for a field that enforces without showing the counter', function (): void {
    // The rule lives in the same extension the counter announces from, so a field that
    // switched the line off and asked for the limit to be held would otherwise be given
    // neither.
    expect(pluginNames(editor()->characterCount(false)->maxLength(100)->enforceMaxLength()))
        ->toContain(CharacterCountPlugin::class);
});

it('renders the same number shape the browser will render', function (): void {
    // `Number::format()` reads its own locale, which is English until something sets it,
    // while the browser half builds an `Intl.NumberFormat` from `app()->getLocale()`. Left
    // apart, the line says 1,234 until the first keystroke and 1.234 after it - the number
    // changes shape while somebody is looking at it.
    app()->setLocale('de');

    $html = CharacterCount::make()->characters(1234)->limit(9999)->toEmbeddedHtml();

    expect($html)->toContain('1.234')
        ->and($html)->toContain('de');
});

it('writes its numbers through the one helper that knows the locale', function (): void {
    app()->setLocale('de');

    expect(Numbers::format(1234))->toBe('1.234')
        ->and(CharacterCount::make()->characters(1234)->limit(9999)->toEmbeddedHtml())
        ->toContain('1.234');
});

it('lets a project decide what a number looks like', function (): void {
    // `Numbers` is where this package decided, not the last word on it: the line reaches it
    // through an overridable `number()`, and the component is resolved from the container,
    // so binding a subclass changes the count and the limit together. Naming `Numbers` at
    // both places instead would let a project change one of the two numbers on one line.
    app()->bind(CharacterCount::class, BracketedCharacterCount::class);

    expect(CharacterCount::make()->characters(1234)->limit(9999)->toEmbeddedHtml())
        ->toContain('[1234]')
        ->and(CharacterCount::make()->characters(1234)->limit(9999)->toEmbeddedHtml())
        ->toContain('[9999]');
});

/** A project that decided its own answer. Declared here because it exists for one test. */
final class BracketedCharacterCount extends CharacterCount
{
    protected static function number(int $value): string
    {
        return "[{$value}]";
    }
}

it('treats a limit below one as no limit at all', function (): void {
    // A `maxLength(0)` - from a config value that arrived empty, or a computed limit that
    // came back zero - would otherwise paint the line red on an empty document, and with
    // the limit held it would refuse every character: a field nobody can type into.
    $line = CharacterCount::make()->characters(0)->limit(0)->enforced()->toEmbeddedHtml();

    expect($line)->not->toContain('fi-arte-character-count-danger')
        // And the wording drops the limit with the colour, rather than announcing one of
        // nought: the three answers the line gives about a limit all have to say the same.
        ->and($line)->not->toContain('0 / 0')
        ->and($line)->toContain('limit: null')
        ->and(CharacterCount::make()->limit(0)->getStateThresholds())->toBeNull();
});

it('takes a limit below one as none wherever it is read', function (): void {
    expect(CharacterCount::make()->limit(0)->getLimit())->toBeNull()
        ->and(CharacterCount::make()->limit(-5)->getLimit())->toBeNull()
        // Answered where the number comes from, so the counter, the browser's copy of the
        // rule and the field's own accessor cannot disagree about the same zero.
        ->and(editor()->maxLength(0)->getCharacterCountLimit())->toBeNull()
        ->and(editor()->characterCountLimit(0)->getCharacterCountLimit())->toBeNull()
        ->and(editor()->characterCountLimit(-5)->getCharacterCountLimit())->toBeNull()
        ->and(belowContent(editor()->maxLength(0))->getLimit())->toBeNull()
        ->and(editor()->maxLength(0)->enforceMaxLength()->getCharacterCountSettingsForJs())->toBeNull()
        // While one is a limit, and is left alone.
        ->and(editor()->maxLength(1)->getCharacterCountLimit())->toBe(1)
        ->and(editor()->maxLength(1)->enforceMaxLength()->getCharacterCountSettingsForJs())
        ->toBe(['limit' => 1, 'enforce' => true]);
});

it('takes a target of nought as no target rather than as no limit', function (): void {
    // A field that names both, where the target arrived empty: nought is not a number to
    // count towards, so the counter falls through to the limit the record is validated
    // against - exactly as it does when no target was named at all. Reading it the other
    // way would let an empty config value hide `maxLength()` from the reader.
    expect(editor()->maxLength(500)->characterCountLimit(0)->getCharacterCountLimit())->toBe(500)
        ->and(editor()->maxLength(500)->characterCountLimit(null)->getCharacterCountLimit())->toBe(500)
        ->and(editor()->maxLength(500)->characterCountLimit(fn (): int => 0)->getCharacterCountLimit())->toBe(500)
        // And with nothing behind it there is nothing to fall through to.
        ->and(editor()->characterCountLimit(0)->getCharacterCountLimit())->toBeNull()
        // A target that is a target is still preferred to the validated limit.
        ->and(editor()->maxLength(500)->characterCountLimit(160)->getCharacterCountLimit())->toBe(160);
});
