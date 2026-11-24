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

it('scopes the loading state to the call its own button makes', function (): void {
    // The request Livewire sends is `callSchemaComponentMethod`, so a target naming the
    // method behind it never matched and the button was never disabled: a double click
    // ticked and unticked. The bare request name would match every checkbox at once, and
    // anything else on the page calling into a schema component. Livewire compares a
    // target's arguments with the call's, so the target is the call itself.
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    preg_match_all('/wire:click="([^"]+)"/', $html, $clicks);
    preg_match_all('/wire:target="([^"]+)"/', $html, $targets);

    expect($targets[1])->toHaveCount(3)
        ->and($targets[1])->toBe($clicks[1])
        ->and($html)->not->toContain('wire:target="toggleTask"');
});

it('asks for the state a click wants rather than for a turn', function (): void {
    // So a second click that arrives before the first one's answer asks for what the first
    // already wrote, instead of undoing it.
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    expect($html)->toContain('{ index: 0, checked: false }')
        ->toContain('{ index: 1, checked: true }')
        ->toContain('{ index: 2, checked: true }');
});

it('names every button after the first line of its item', function (): void {
    $html = tickableEntry(new Post(['content' => taskDocument()]))->toEmbeddedHtml();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $names = [];

    foreach ($document->getElementsByTagName('button') as $button) {
        $names[] = trim((string) $document->getElementById($button->getAttribute('aria-labelledby'))?->textContent);
    }

    expect($names)->toBe(['Eins', 'Zwei', 'Drei']);
});

it('names a button after its own line and not after the items beneath it', function (): void {
    $stored = '<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="false"><p>Oben</p>'
        .'<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="false"><p>Unten</p></li>'
        .'</ul></li></ul>';

    $html = tickableEntry(new Post(['content' => $stored]))->toEmbeddedHtml();

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $names = [];

    foreach ($document->getElementsByTagName('button') as $button) {
        $names[] = trim((string) $document->getElementById($button->getAttribute('aria-labelledby'))?->textContent);
    }

    expect($names)->toBe(['Oben', 'Unten']);
});

it('keeps the ids it hands out apart between two entries on one page', function (): void {
    $record = new Post(['content' => taskDocument()]);

    $first = AdvancedRichEntry::make('content')->tickableTasks()
        ->container(Schema::make(new TestSchemaComponent)->record($record))->toEmbeddedHtml();
    $second = AdvancedRichEntry::make('summary')->statePath('summary')->tickableTasks()
        ->state(taskDocument())
        ->container(Schema::make(new TestSchemaComponent)->record($record))->toEmbeddedHtml();

    preg_match_all('/aria-labelledby="([^"]+)"/', $first, $a);
    preg_match_all('/aria-labelledby="([^"]+)"/', $second, $b);

    expect($a[1])->toHaveCount(3)
        ->and(array_intersect($a[1], $b[1]))->toBe([]);
});

it('sets a tick, and setting it again changes nothing', function (): void {
    $once = DocumentTasks::set(taskDocument(), 1, true);

    expect($once)->toContain('<li data-type="taskItem" data-checked="true"><p>Zwei</p></li>')
        ->and(DocumentTasks::set($once, 1, true))->toBe($once)
        ->and(DocumentTasks::set(taskDocument(), 9, true))->toBeNull();
});

it('reads the state of one item', function (): void {
    expect(DocumentTasks::isChecked(taskDocument(), 0))->toBeTrue()
        ->and(DocumentTasks::isChecked(taskDocument(), 1))->toBeFalse()
        ->and(DocumentTasks::isChecked(taskDocument(), 9))->toBeNull()
        ->and(DocumentTasks::isChecked(null, 0))->toBeNull();
});

it('reads an item without the attribute as unticked, as the parser does', function (): void {
    // An empty string is what `getAttribute()` answers for a missing attribute, and also how
    // a bare `data-checked` reads - so the first click on such an item used to write
    // "unticked" over an item that already was.
    $stored = '<ul data-type="taskList"><li data-type="taskItem"><p>Ohne</p></li></ul>';

    expect(DocumentTasks::isChecked($stored, 0))->toBeFalse()
        ->and(DocumentTasks::toggle($stored, 0))->toContain('data-checked="true"');
});

it('keeps the class the editor writes in step with the attribute', function (): void {
    // The editor stores the state twice, because the sanitiser keeps only the class. Anything
    // reading the column as it lies - a search index, an export - reads the class.
    $stored = '<ul data-type="taskList" class="fi-arte-task-list">'
        .'<li data-type="taskItem" data-checked="true" class="fi-arte-task-item fi-arte-task-item-checked"><p>Eins</p></li>'
        .'<li data-type="taskItem" data-checked="false" class="fi-arte-task-item"><p>Zwei</p></li>'
        .'</ul>';

    expect(DocumentTasks::toggle($stored, 0))
        ->toContain('<li data-type="taskItem" data-checked="false" class="fi-arte-task-item"><p>Eins</p></li>')
        ->and(DocumentTasks::toggle($stored, 1))
        ->toContain('<li data-type="taskItem" data-checked="true" class="fi-arte-task-item fi-arte-task-item-checked"><p>Zwei</p></li>');
});

it('gives hand-written markup no class it never had', function (): void {
    expect(DocumentTasks::toggle(taskDocument(), 1))
        ->toContain('<li data-type="taskItem" data-checked="true"><p>Zwei</p></li>');
});

it('centres the button the way it centres the label', function (): void {
    // `TickableTasks` swaps the label for a button, and a rule written for the label alone
    // left the box on the text's baseline.
    $css = file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor.css');

    expect(substr_count($css, '.fi-arte-task-item > :is(label, .fi-arte-task-item-tickable) {'))->toBe(2)
        ->and($css)->not->toContain('.fi-arte-task-item > label {')
        ->and(file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css'))->toBe($css);
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
