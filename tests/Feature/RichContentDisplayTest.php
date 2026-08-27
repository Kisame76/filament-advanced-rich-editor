<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\Infolists\Components\AdvancedRichEntry;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\Tables\Columns\AdvancedRichColumn;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

/**
 * A document whose stored mention label is out of date, which is the case only a provider
 * can put right.
 */
function staleMention(): string
{
    return '<p>Ping <span data-type="mention" data-id="2" data-label="Ada L." data-char="@"></span></p>';
}

function richEntry(Model $record, string $name = 'content'): AdvancedRichEntry
{
    return AdvancedRichEntry::make($name)->container(
        Schema::make(new TestSchemaComponent)->record($record),
    );
}

function richColumn(Model $record, string $name = 'content'): AdvancedRichColumn
{
    return AdvancedRichColumn::make($name)->record($record);
}

/**
 * The entry's markup with the indentation squeezed out.
 */
function entryHtml(AdvancedRichEntry $entry): string
{
    return trim((string) preg_replace('/\s+/', ' ', $entry->toEmbeddedHtml()));
}

it('renders a stored document on a view page', function (): void {
    $entry = richEntry(new Post(['content' => '<p>Ein Absatz</p>']));

    expect(entryHtml($entry))->toContain('<p>Ein Absatz</p>')
        ->toContain('fi-arte-entry')
        ->toContain('fi-prose');
});

it('sanitises what the entry prints', function (): void {
    $entry = richEntry(new Post(['content' => '<p>Text</p><script>alert(1)</script>']));

    expect(entryHtml($entry))->not->toContain('<script');
});

it('shows the placeholder where nobody has typed anything', function (): void {
    $entry = richEntry(new Post(['content' => null]))->placeholder('Noch nichts');

    expect(entryHtml($entry))->toContain('Noch nichts');
});

it('anchors the headings the entry was told to anchor', function (): void {
    $entry = richEntry(new Post(['content' => '<h2>Über uns</h2>']))->anchorHeadings();

    expect(entryHtml($entry))->toContain('id="uber-uns"');
});

it('reads what the model declares about the attribute', function (): void {
    // The provider is registered on the model and nowhere else, and the label in the
    // document is the stale copy. An entry that does not read the declaration prints it.
    expect(entryHtml(richEntry(new RichPost(['content' => staleMention()]))))
        ->toContain('Ada Lovelace')
        ->not->toContain('Ada L.');
});

it('lets the entry override what the model declares', function (): void {
    $entry = richEntry(new RichPost(['content' => staleMention()]))
        ->mentions([MentionProvider::make('@')->items(['2' => 'Grace Hopper'])]);

    expect(entryHtml($entry))->toContain('Grace Hopper')
        ->not->toContain('Ada Lovelace');
});

it('takes anything else through a renderer it is handed', function (): void {
    $entry = richEntry(new Post(['content' => '<p>Text</p>']))
        ->configureRenderer(fn (AdvancedRichContentRenderer $renderer) => $renderer->processNodesUsing(
            function (object &$node): void {
                if (($node->type ?? null) === 'paragraph') {
                    $node->content = [(object) ['type' => 'text', 'text' => 'Ersetzt']];
                }
            },
        ));

    expect(entryHtml($entry))->toContain('Ersetzt');
});

it('shortens a document to fit a table cell', function (): void {
    $column = richColumn(new Post)->excerptLength(20);

    expect($column->formatState('<p>Ein Absatz, der deutlich länger ist als zwanzig Zeichen.</p>'))
        ->toBe('Ein Absatz, der…');
});

it('shortens to the configured length where the column says nothing', function (): void {
    config()->set('filament-advanced-rich-editor.excerpt.characters', 12);

    expect(richColumn(new Post)->formatState('<p>Ein Absatz mit mehr Text darin</p>'))
        ->toBe('Ein Absatz…');
});

it('hands back the whole text where the column asks for it', function (): void {
    $column = richColumn(new Post)->excerptLength(null);

    expect($column->formatState('<p>Ein Absatz</p><p>Und noch einer</p>'))
        ->toBe("Ein Absatz\n\nUnd noch einer");
});

it('renders the markup where the column asks for HTML', function (): void {
    $column = richColumn(new Post)->html();

    $state = $column->formatState('<p>Ein Absatz</p>');

    expect($state)->toBeInstanceOf(HtmlString::class)
        ->and($state->toHtml())->toBe('<p>Ein Absatz</p>');
});

it('resolves a mention in a table cell instead of dropping it', function (): void {
    // This is what a plain text column cannot do: the mention is an atom, and everything
    // that reads the column as a string reads straight past it.
    $content = '<p>Ping <span data-type="mention" data-id="2" data-label="Ada Lovelace" data-char="@"></span> bitte.</p>';

    expect(richColumn(new Post)->formatState($content))->toBe('Ping @Ada Lovelace bitte.');
});

it('reads the model declaration in a table cell too', function (): void {
    expect(richColumn(new RichPost)->formatState(staleMention()))->toBe('Ping @Ada Lovelace');
});

it('says nothing for a row that holds nothing', function (): void {
    expect(richColumn(new Post)->formatState(null))->toBe('');
});
