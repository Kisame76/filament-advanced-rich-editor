<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Excerpt;

function excerptRenderer(string $html): AdvancedRichContentRenderer
{
    return AdvancedRichContentRenderer::make($html);
}

it('hands back a text that already fits, with nothing appended', function (): void {
    expect(Excerpt::from('Kurz genug.', 160))->toBe('Kurz genug.');
});

it('cuts on a word boundary rather than through a word', function (): void {
    // Twenty characters lands inside "Wörterbuch"; the excerpt backs up to the space.
    expect(Excerpt::from('Ein langes Wörterbuch liegt hier', 20))->toBe('Ein langes…');
});

it('keeps a word that ends exactly on the limit', function (): void {
    // The character after the cut is a space, so the cut is already a boundary and
    // backing up would throw away a word that fit.
    expect(Excerpt::from('Ein langes Wort hier', 15))->toBe('Ein langes Wort…');
});

it('cuts through a single word too long to have a boundary', function (): void {
    // Half of it says more than none of it.
    expect(Excerpt::from('Donaudampfschifffahrtsgesellschaft', 10))->toBe('Donaudampf…');
});

it('leaves off the ellipsis where the cut lands on a finished sentence', function (): void {
    // The cut falls inside "Der", backs up to the space, and lands on the full stop.
    expect(Excerpt::from('Der erste Satz. Der zweite Satz folgt.', 16))->toBe('Der erste Satz.');
});

it('drops punctuation that cannot end a sentence', function (): void {
    expect(Excerpt::from('Erstens, zweitens und drittens', 12))->toBe('Erstens…')
        ->and(Excerpt::from('Ein Bindestrich - und mehr', 18))->toBe('Ein Bindestrich…');
});

it('counts characters and not bytes', function (): void {
    expect(Excerpt::from('äöüäöüäöüäöü', 6))->toBe('äöüäöü…');
});

it('says nothing where there is nothing, or no room to say it', function (): void {
    expect(Excerpt::from('', 160))->toBe('')
        ->and(Excerpt::from('   ', 160))->toBe('')
        ->and(Excerpt::from('Etwas steht hier', 0))->toBe('');
});

it('collapses every kind of whitespace into single spaces', function (): void {
    // The non-breaking space among them: this package ships a button that inserts one,
    // and `\s` does not match it.
    expect(Excerpt::from("Zwei\n\nAbsätze\tund\u{00A0}ein Leerzeichen", 160))
        ->toBe('Zwei Absätze und ein Leerzeichen');
});

it('builds the excerpt out of a stored document', function (): void {
    $html = '<h2>Überschrift</h2><p>Der erste Absatz ist lang genug, um abgeschnitten zu werden, '
        .'und läuft noch ein Stück weiter.</p><p>Der zweite Absatz.</p>';

    expect(excerptRenderer($html)->toExcerpt(40))
        ->toBe('Überschrift Der erste Absatz ist lang…');
});

it('does not break a sentence apart at a link or a bold word', function (): void {
    // The serialiser separates any two children, and a sentence with a link in it is
    // three text nodes. Without the joining pass this reads as three lines.
    $html = '<p>Hallo <a href="https://example.com">Welt</a>, alles <strong>gut</strong>?</p>';

    expect(excerptRenderer($html)->toExcerpt(160))->toBe('Hallo Welt, alles gut?')
        ->and(excerptRenderer($html)->toText())->toBe('Hallo Welt, alles gut?');
});

it('spells out an ampersand instead of the entity it was serialised as', function (): void {
    expect(excerptRenderer('<p>Tom &amp; Jerry sagen "Hallo".</p>')->toExcerpt(160))
        ->toBe('Tom & Jerry sagen "Hallo".');
});

it('keeps a mention in the excerpt, the way it was typed', function (): void {
    $html = '<p>Ping <span data-type="mention" data-id="2" data-label="Ada Lovelace" data-char="@"></span> bitte.</p>';

    expect(excerptRenderer($html)->toExcerpt(160))->toBe('Ping @Ada Lovelace bitte.');
});

it('has nothing to say about a record nobody has typed into', function (): void {
    expect(AdvancedRichContentRenderer::make(null)->toExcerpt())->toBe('')
        ->and(AdvancedRichContentRenderer::make('')->toExcerpt())->toBe('');
});

it('takes its length and its marker from the config', function (): void {
    config()->set('filament-advanced-rich-editor.excerpt.characters', 12);
    config()->set('filament-advanced-rich-editor.excerpt.end', ' [mehr]');

    expect(excerptRenderer('<p>Ein Absatz mit mehr Text darin</p>')->toExcerpt())
        ->toBe('Ein Absatz [mehr]');
});

it('lets the call override the config', function (): void {
    config()->set('filament-advanced-rich-editor.excerpt.characters', 12);

    expect(excerptRenderer('<p>Ein Absatz mit mehr Text darin</p>')->toExcerpt(20))
        ->toBe('Ein Absatz mit mehr…');
});
