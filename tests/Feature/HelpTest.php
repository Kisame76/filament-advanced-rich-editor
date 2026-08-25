<?php

declare(strict_types=1);

use Filament\Schemas\Components\Tabs;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ShortcutTable;

it('puts the button at the end, after fullscreen', function (): void {
    expect(toolbarGroup(editor(), 'fullscreen'))->toBe(['find', 'fullscreen', 'help']);
});

it('lists the shortcuts this field actually has', function (): void {
    $rows = Shortcuts::for(editor());

    $labels = array_column($rows, 'label');

    expect($labels)->toContain('Bold')
        ->and($labels)->toContain('Task list')
        // Every row carries the keys as tokens, not as a finished string: which glyphs
        // those are is a question only the browser can answer.
        ->and($rows[0]['keys'])->toBeArray();
});

it('follows the field rather than a fixed list', function (): void {
    $withoutTaskList = array_column(Shortcuts::for(editor()->taskList(false)), 'label');

    expect($withoutTaskList)->not->toContain('Task list');

    // Only the heading levels the field offers, in the field's own order.
    $headings = array_values(array_filter(
        Shortcuts::for(editor()->headingLevels([2, 3])),
        fn (array $row): bool => str_starts_with($row['label'], 'Heading'),
    ));

    expect($headings)->toHaveCount(2)
        ->and($headings[0]['keys'])->toBe(['Mod', 'Alt', '2']);
});

it('shows one plain list until there is something to put beside it', function (): void {
    $schema = editor()->getAction('help')->getSchema(testSchema())->getComponents();

    expect($schema[0])->not->toBeInstanceOf(Tabs::class);

    $withMore = editor()->helpMore('Ask the editorial team before publishing.')
        ->getAction('help')
        ->getSchema(testSchema())
        ->getComponents();

    // Two things to read means two tabs; one thing does not need a tab bar around it.
    expect($withMore[0])->toBeInstanceOf(Tabs::class)
        ->and($withMore[0]->getChildSchema()->getComponents())->toHaveCount(2);
});

it('takes its second tab as text or as markup, and escapes the text', function (): void {
    expect(editor()->helpMore('a < b')->getHelpMore()?->toHtml())->toContain('a &lt; b')
        ->and(editor()->helpMore(new HtmlString('<em>read me</em>'))->getHelpMore()?->toHtml())
        ->toContain('<em>read me</em>')
        ->and(editor()->getHelpMore())->toBeNull();
});

it('titles the dialog after what it is, not after where it opened from', function (): void {
    expect(editor()->getAction('help')->getModalHeading())->toBe('Help');
});

it('draws the list as pairs, and ships the styles that lay them out', function (): void {
    $html = ShortcutTable::make()->rows(Shortcuts::for(editor()))->toEmbeddedHtml();

    // A name and the keys that mean it - which is a description list, and is also what
    // lets the list run in two columns without the reading order coming apart.
    expect($html)->toContain('<dl class="fi-arte-shortcuts-list">')
        ->and($html)->toContain('fi-arte-shortcut-label')
        ->and($html)->toContain('fi-arte-shortcut-keys')
        ->and($html)->toContain('<kbd');

    $css = file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css');

    // Without these the browser draws a bare `dl`: the names and the keys stacked, which
    // is exactly how this once shipped.
    expect($css)->toContain('.fi-arte-shortcuts-list')
        ->and($css)->toContain('.fi-arte-shortcut ')
        ->and($css)->toContain('.fi-arte-shortcuts kbd');
});

it('drops the button when a field or the config says so', function (): void {
    expect(editor()->help(false)->getTools())->not->toHaveKey('help')
        ->and(toolbarGroup(editor()->help(false), 'fullscreen'))->toBe(['find', 'fullscreen']);

    config()->set('filament-advanced-rich-editor.help', false);

    expect(editor()->hasHelp())->toBeFalse();
});
