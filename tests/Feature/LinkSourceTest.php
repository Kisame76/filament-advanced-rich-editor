<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\LinkSource;

/**
 * Picking a record instead of typing a URL.
 *
 * The whole design turns on one thing the parser decides rather than this package:
 * `tiptap-php`'s link mark matches `a[href]` and returns `false` for an empty one, so a link
 * carrying only a reference is not a link the next hydration recognises - the markup survives
 * and the linking silently does not. A resolved URL is therefore always what is stored, and
 * that is why a source answers with URLs rather than with ids to be resolved later.
 */
it('answers with what it was given', function (): void {
    $source = LinkSource::make('articles')
        ->label('Artikel')
        ->using(fn (string $search): array => ['/preise' => 'Preise', '/agb' => 'AGB']);

    expect($source->getName())->toBe('articles')
        ->and($source->getLabel())->toBe('Artikel')
        ->and($source->getOptions(''))->toBe(['/preise' => 'Preise', '/agb' => 'AGB']);
});

it('falls back to its own name for a label nobody gave it', function (): void {
    expect(LinkSource::make('articles')->getLabel())->toBe('Articles');
});

it('hands the search term on, because the query belongs to the project', function (): void {
    $seen = null;

    $source = LinkSource::make('a')->using(function (string $search) use (&$seen): array {
        $seen = $search;

        return [];
    });

    $source->getOptions('preis');

    expect($seen)->toBe('preis');
});

it('answers with nothing where nobody said how to search', function (): void {
    // A source with no closure is a misconfiguration, not a crash: the dialog still opens
    // and the URL field still works.
    expect(LinkSource::make('a')->getOptions(''))->toBe([]);
});

it('keeps only what can be a url and a label', function (): void {
    // What a project's closure returns is whatever its query produced. A blank URL is the one
    // value that must never reach the mark - the parser drops a link that carries one, and
    // the linking disappears without a word.
    $source = LinkSource::make('a')->using(fn (string $search): array => [
        '/keep' => 'Behalten',
        '' => 'Ohne URL',
        '/no-label' => '',
        '  /trimmed  ' => '  Getrimmt  ',
    ]);

    expect($source->getOptions(''))->toBe([
        '/keep' => 'Behalten',
        '/trimmed' => 'Getrimmt',
    ]);
});

it('casts what a query gave it, since a label is rarely a string already', function (): void {
    $source = LinkSource::make('a')->using(fn (string $search): array => ['/x' => 42]);

    expect($source->getOptions(''))->toBe(['/x' => '42']);
});

/**
 * What the dialog does with them. The merging is a pure function so the rules that matter -
 * when a heading appears, what happens to two sources offering the same URL - can be read
 * without mounting a modal, the same way `attributesFrom()` can.
 */
function articleSource(): LinkSource
{
    return LinkSource::make('articles')->label('Artikel')
        ->using(fn (string $search): array => ['/preise' => 'Preise', '/agb' => 'AGB']);
}

function categorySource(): LinkSource
{
    return LinkSource::make('categories')->label('Kategorien')
        ->using(fn (string $search): array => ['/kategorie/news' => 'News']);
}

it('lists one source flat, because a single heading is a heading over everything', function (): void {
    expect(LinkAction::internalOptions([articleSource()], ''))
        ->toBe(['/preise' => 'Preise', '/agb' => 'AGB']);
});

it('groups several under their own headings', function (): void {
    expect(LinkAction::internalOptions([articleSource(), categorySource()], ''))
        ->toBe([
            'Artikel' => ['/preise' => 'Preise', '/agb' => 'AGB'],
            'Kategorien' => ['/kategorie/news' => 'News'],
        ]);
});

it('leaves out a source that found nothing, rather than an empty heading', function (): void {
    $empty = LinkSource::make('empty')->label('Leer')->using(fn (string $search): array => []);

    expect(LinkAction::internalOptions([articleSource(), $empty], ''))
        ->toBe(['/preise' => 'Preise', '/agb' => 'AGB']);
});

it('answers with nothing at all where no source found anything', function (): void {
    $empty = LinkSource::make('empty')->using(fn (string $search): array => []);

    expect(LinkAction::internalOptions([$empty], ''))->toBe([]);
});

it('asks every source with the same search term', function (): void {
    $seen = [];

    // A closure rather than an arrow function: an arrow function captures by value, so the
    // `&$seen` inside one would be a reference to a copy and this would pass on an empty array.
    $spy = function (string $name) use (&$seen): LinkSource {
        return LinkSource::make($name)->using(function (string $search) use (&$seen, $name): array {
            $seen[$name] = $search;

            return ["/{$name}" => $name];
        });
    };

    LinkAction::internalOptions([$spy('a'), $spy('b')], 'preis');

    expect($seen)->toBe(['a' => 'preis', 'b' => 'preis']);
});

it('gives the field the sources and nothing by default', function (): void {
    expect(editor()->getLinkSources())->toBe([])
        ->and(editor()->hasLinkSources())->toBeFalse();

    $field = editor()->linkSources([articleSource()]);

    expect($field->hasLinkSources())->toBeTrue()
        ->and($field->getLinkSources())->toHaveCount(1)
        ->and($field->getLinkSources()[0]->getName())->toBe('articles');
});

it('takes a closure, because what is linkable can depend on who is looking', function (): void {
    $field = editor()->linkSources(fn (): array => [articleSource(), categorySource()]);

    expect($field->getLinkSources())->toHaveCount(2);
});

it('puts a picker in the dialog only where the field has somewhere to pick from', function (): void {
    expect(linkDialogFields(editor()))->not->toContain('internal')
        ->and(linkDialogFields(editor()->linkSources([articleSource()])))->toContain('internal')
        // And the URL field stays either way: the picker fills it rather than replacing it,
        // because the resolved URL is what a link has to carry.
        ->and(linkDialogFields(editor()->linkSources([articleSource()])))->toContain('href');
});
