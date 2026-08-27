<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionRow;

/**
 * A mention row that says more than a name.
 *
 * Filament's provider normalises everything to `id => label`, which is the shape its own
 * menu draws and the reason a picture has nowhere to travel in. This one carries the row
 * whole - and carries the map alongside it, because Filament reads that map to fill in a
 * label the document is missing.
 */
it('carries a picture and a line of context', function (): void {
    $row = MentionRow::make(7, 'Ada Lovelace')
        ->avatar('/avatars/ada.jpg')
        ->hint('Mathematician');

    expect($row->toArray())->toBe([
        'id' => '7',
        'label' => 'Ada Lovelace',
        'avatar' => '/avatars/ada.jpg',
        'hint' => 'Mathematician',
    ]);
});

it('leaves out what it was not given rather than sending empty keys', function (): void {
    // The menu draws initials where there is no picture and one line where there is no
    // second one. A null crossing into JavaScript would say the same thing at more expense.
    expect(MentionRow::make(7, 'Ada')->toArray())->toBe(['id' => '7', 'label' => 'Ada']);
});

it('hands the rows to the menu, and the labels to Filament', function (): void {
    $provider = MentionProvider::make('@')->rows([
        MentionRow::make(2, 'Ada Lovelace')->avatar('/ada.jpg')->hint('Mathematician'),
        MentionRow::make(3, 'Grace Hopper')->hint('Rear admiral'),
    ]);

    expect($provider->getRows())->toBe([
        ['id' => '2', 'label' => 'Ada Lovelace', 'avatar' => '/ada.jpg', 'hint' => 'Mathematician'],
        ['id' => '3', 'label' => 'Grace Hopper', 'hint' => 'Rear admiral'],
    ])
        // Filament reads this to fill in a label a stored document does not carry, and to
        // draw its own menu where this package's one is switched off.
        ->and($provider->getItems())->toBe(['2' => 'Ada Lovelace', '3' => 'Grace Hopper'])
        ->and($provider->getLabels(['2']))->toBe(['2' => 'Ada Lovelace']);
});

it('takes rows from a closure, so they can be a query', function (): void {
    $provider = MentionProvider::make('@')->rows(fn (): array => [
        MentionRow::make(2, 'Ada Lovelace')->avatar('/ada.jpg'),
    ]);

    expect($provider->getRows())->toBe([['id' => '2', 'label' => 'Ada Lovelace', 'avatar' => '/ada.jpg']]);
});

it('takes a plain array as a row, so a query can build one without the class', function (): void {
    $provider = MentionProvider::make('@')->rows([
        ['id' => 2, 'label' => 'Ada Lovelace', 'hint' => 'Mathematician'],
    ]);

    expect($provider->getRows())->toBe([['id' => '2', 'label' => 'Ada Lovelace', 'hint' => 'Mathematician']]);
});

it('searches its own rows where nothing else was said', function (): void {
    $provider = MentionProvider::make('@')->rows([
        MentionRow::make(2, 'Ada Lovelace')->hint('Mathematician'),
        MentionRow::make(3, 'Grace Hopper')->hint('Rear admiral'),
    ]);

    expect($provider->getSearchResults('grace'))
        ->toBe([['id' => '3', 'label' => 'Grace Hopper', 'hint' => 'Rear admiral']])
        // The second line is searched too: somebody typing what a person does does not
        // always know how the name is spelled.
        ->and($provider->getSearchResults('admiral'))->toHaveCount(1)
        ->and($provider->getSearchResults(''))->toHaveCount(2);
});

it('hands a searching closure its own answer, rows or labels', function (): void {
    $rows = MentionProvider::make('@')->getSearchResultsUsing(fn (string $search): array => [
        MentionRow::make(2, 'Ada Lovelace')->avatar('/ada.jpg'),
    ]);

    expect($rows->getSearchResults('ada'))->toBe([['id' => '2', 'label' => 'Ada Lovelace', 'avatar' => '/ada.jpg']]);

    // And a provider written against Filament keeps working untouched, which is the whole
    // reason this extends theirs rather than replacing it.
    $labels = MentionProvider::make('@')->getSearchResultsUsing(fn (): array => ['2' => 'Ada Lovelace']);

    expect($labels->getSearchResults('ada'))->toBe(['2' => 'Ada Lovelace']);
});

it('tells the field it has something to search', function (): void {
    // Rows alone are a list the browser filters itself; a closure is a question for the
    // server. The menu asks only where there is somebody to ask.
    expect(MentionProvider::make('@')->rows([MentionRow::make(2, 'Ada')])->hasSearchResultsUsing())->toBeFalse()
        ->and(MentionProvider::make('@')->getSearchResultsUsing(fn (): array => [])->hasSearchResultsUsing())->toBeTrue();
});

it('puts the rows in front of the menu the field hands to the script', function (): void {
    $editor = editor()->mentions([
        MentionProvider::make('@')->rows([MentionRow::make(2, 'Ada Lovelace')->avatar('/ada.jpg')]),
    ]);

    expect($editor->getMentionMenuForJs()['triggers'][0]['items'])
        ->toBe([['id' => '2', 'label' => 'Ada Lovelace', 'avatar' => '/ada.jpg']]);
});
