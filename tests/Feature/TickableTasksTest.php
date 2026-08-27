<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\Infolists\Components\AdvancedRichEntry;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentTasks;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

function taskDocument(): string
{
    return '<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="true"><p>Eins</p></li>'
        .'<li data-type="taskItem" data-checked="false"><p>Zwei</p></li>'
        .'<li data-type="taskItem" data-checked="false"><p>Drei</p></li>'
        .'</ul>';
}

function tickableEntry(Post $record, bool|Closure $condition = true): AdvancedRichEntry
{
    return AdvancedRichEntry::make('content')
        ->tickableTasks($condition)
        ->container(Schema::make(new TestSchemaComponent)->record($record));
}

it('counts the task items in a document', function (): void {
    expect(DocumentTasks::count(taskDocument()))->toBe(3)
        ->and(DocumentTasks::count('<p>Nichts</p>'))->toBe(0)
        ->and(DocumentTasks::count(null))->toBe(0);
});

it('turns one tick over and leaves the rest of the document alone', function (): void {
    $toggled = DocumentTasks::toggle(taskDocument(), 1);

    expect($toggled)->toContain('<li data-type="taskItem" data-checked="true"><p>Zwei</p></li>')
        // The first was ticked and stays ticked, the third untouched.
        ->toContain('<li data-type="taskItem" data-checked="true"><p>Eins</p></li>')
        ->toContain('<li data-type="taskItem" data-checked="false"><p>Drei</p></li>');
});

it('unticks one that was ticked', function (): void {
    expect(DocumentTasks::toggle(taskDocument(), 0))
        ->toContain('<li data-type="taskItem" data-checked="false"><p>Eins</p></li>');
});

it('answers nothing for an index that names no item', function (): void {
    expect(DocumentTasks::toggle(taskDocument(), 9))->toBeNull()
        ->and(DocumentTasks::toggle(taskDocument(), -1))->toBeNull()
        ->and(DocumentTasks::toggle('<p>Nichts</p>', 0))->toBeNull()
        ->and(DocumentTasks::toggle(null, 0))->toBeNull();
});

it('turns a tick over in a document held as an array', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'taskList',
            'content' => [
                ['type' => 'taskItem', 'attrs' => ['checked' => false], 'content' => []],
                ['type' => 'taskItem', 'attrs' => ['checked' => true], 'content' => []],
            ],
        ]],
    ];

    $toggled = DocumentTasks::toggle($document, 1);

    expect($toggled['content'][0]['content'][0]['attrs']['checked'])->toBeFalse()
        ->and($toggled['content'][0]['content'][1]['attrs']['checked'])->toBeFalse();
});

it('leaves the boxes alone on an entry that was not asked', function (): void {
    $html = AdvancedRichEntry::make('content')
        ->container(Schema::make(new TestSchemaComponent)->record(new Post(['content' => taskDocument()])))
        ->toEmbeddedHtml();

    expect($html)->not->toContain('wire:click')
        ->and($html)->toContain('fi-arte-task-item-box');
});

it('makes every box a button when it is asked', function (): void {
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    expect(substr_count($html, 'wire:click'))->toBe(3)
        ->and($html)->toContain('toggleTask')
        // Counted from the same document the page was drawn from.
        ->toContain('index: 0')
        ->toContain('index: 1')
        ->toContain('index: 2')
        ->and($html)->toContain('fi-arte-task-item-tickable');
});

it('says which boxes are pressed, for anything that cannot see them', function (): void {
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1)
        ->and(substr_count($html, 'aria-pressed="false"'))->toBe(2);
});

it('asks the callback rather than assuming', function (): void {
    $record = new Post(['content' => taskDocument()]);

    expect(tickableEntry($record, fn (): bool => false)->canTickTasks())->toBeFalse()
        ->and(tickableEntry($record, fn (): bool => true)->canTickTasks())->toBeTrue();
});

it('hands the record to the callback', function (): void {
    $seen = null;

    tickableEntry(new Post(['content' => taskDocument(), 'title' => 'Titel']), function (Post $record) use (&$seen): bool {
        $seen = $record->title;

        return true;
    })->canTickTasks();

    expect($seen)->toBe('Titel');
});

it('writes the tick back to the record', function (): void {
    $record = Post::create(['title' => 'Titel', 'content' => taskDocument()]);

    tickableEntry($record)->toggleTask(1);

    expect($record->fresh()->content)
        ->toContain('<li data-type="taskItem" data-checked="true"><p>Zwei</p></li>');
});

it('refuses to write where the callback says no', function (): void {
    // The button was drawn for somebody who may write; a request is not a button, so the
    // question is asked again on the way in.
    $record = Post::create(['title' => 'Titel', 'content' => taskDocument()]);

    tickableEntry($record, fn (): bool => false)->toggleTask(1);

    expect($record->fresh()->content)->toBe(taskDocument());
});

it('writes nothing for an index that names no item', function (): void {
    $record = Post::create(['title' => 'Titel', 'content' => taskDocument()]);

    tickableEntry($record)->toggleTask(99);

    expect($record->fresh()->content)->toBe(taskDocument());
});

it('touches only the one attribute', function (): void {
    $record = Post::create(['title' => 'Titel', 'content' => taskDocument()]);

    tickableEntry($record)->toggleTask(0);

    expect($record->getDirty())->toBe([])
        ->and($record->fresh()->title)->toBe('Titel');
});

it('draws no buttons where the markup and the document disagree', function (): void {
    // A merge tag or a custom block renders markup of its own, after parsing and past the
    // sanitiser. An `li` in it shaped like a task item would shift every index after it -
    // and an index that has shifted does not fail loudly, it ticks the neighbour. So the
    // count is checked first, and a mismatch leaves the boxes as they were.
    $stored = '<p><span data-type="mergeTag" data-id="box"></span></p>'
        .'<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="false"><p>Echt</p></li>'
        .'</ul>';

    $foreign = '<ul><li data-type="taskItem" class="fi-arte-task-item">'
        .'<label class="fi-arte-task-item-control"><span class="fi-arte-task-item-box"></span></label>'
        .'<div class="fi-arte-task-item-content">Fremd</div></li></ul>';

    $html = AdvancedRichEntry::make('content')
        ->tickableTasks()
        ->mergeTags(['box' => new HtmlString($foreign)])
        ->container(Schema::make(new TestSchemaComponent)->record(new Post(['content' => $stored])))
        ->toEmbeddedHtml();

    expect($html)->not->toContain('wire:click')
        // The document is still shown, only not as something to press.
        ->and($html)->toContain('Echt');
});

it('numbers the buttons off the attribute the parser keys on', function (): void {
    // Not off the class: a class is decoration, and the toggle walks `data-type`.
    $stored = '<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="false"><p>Eins</p></li>'
        .'<li data-type="taskItem" data-checked="false"><p>Zwei</p></li>'
        .'</ul>';

    $html = tickableEntry(new Post(['content' => $stored]))->toEmbeddedHtml();

    expect(substr_count($html, 'wire:click'))->toBe(2)
        ->and($html)->toContain('index: 0')
        ->toContain('index: 1');
});

it('scopes the loading state to its own call', function (): void {
    // Without a target, any Livewire request anywhere on the page greys out every checkbox.
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    expect($html)->toContain('wire:target="toggleTask"');
});

it('draws the buttons on a record that declares its rich content', function (): void {
    // `getState()` answers with a `RichContentAttribute` rather than with the column on a
    // model implementing `HasRichContent`, so anything reading the state as a document has
    // to resolve it first. Reading it as a string gave null, null counts as no task items,
    // and the count guard then refused to draw a single button - on exactly the models this
    // package is built for.
    $record = RichPost::create(['title' => 'Titel', 'content' => taskDocument()]);

    $html = AdvancedRichEntry::make('content')
        ->tickableTasks()
        ->container(Schema::make(new TestSchemaComponent)->record($record))
        ->toEmbeddedHtml();

    expect(substr_count($html, 'wire:click'))->toBe(3);
});

it('writes the tick back on a record that declares its rich content', function (): void {
    $record = RichPost::create(['title' => 'Titel', 'content' => taskDocument()]);

    AdvancedRichEntry::make('content')
        ->tickableTasks()
        ->container(Schema::make(new TestSchemaComponent)->record($record))
        ->toggleTask(2);

    expect($record->fresh()->content)
        ->toContain('<li data-type="taskItem" data-checked="true"><p>Drei</p></li>');
});
