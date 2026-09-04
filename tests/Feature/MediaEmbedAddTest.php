<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\EmbedAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * Adding something to the library that is not a file.
 *
 * Beside Upload rather than behind a second door: the whole complaint about the way embeds
 * used to work is that a person had to decide which dialog to open before knowing which one
 * held the video.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->livewire = new TestSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->source = fn (): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: 'library',
        visibility: 'public',
    );

    // Through `getAction()` rather than `getDefaultActions()`: that is what hands back a
    // *prepared* action, bound to the field - which is what makes the `AdvancedRichEditor
    // $component` parameter in its closure resolve to anything.
    $this->action = fn (string $name): ?Action => $this->editor->getAction($name);
});

it('writes an embed into the library and hands back its id', function (): void {
    $id = ($this->source)()->saveEmbed([
        'provider' => 'youtube',
        'id' => 'dQw4w9WgXcQ',
        'start' => null,
        'title' => 'The talk',
        'ratio' => '16 / 9',
    ]);

    expect($id)->toBe('library/youtube-dQw4w9WgXcQ.embed.json')
        ->and(($this->source)()->find($id)['embed']['title'])->toBe('The talk');
});

it('writes the same video twice as one entry', function (): void {
    // The file is named after the video, so adding it again is a correction rather than a
    // duplicate - which is the behaviour anybody would expect from a library.
    $embed = ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null, 'title' => 'First', 'ratio' => '16 / 9'];

    ($this->source)()->saveEmbed($embed);
    ($this->source)()->saveEmbed([...$embed, 'title' => 'Corrected']);

    expect(($this->source)()->page()['total'])->toBe(1)
        ->and(($this->source)()->page()['items'][0]['name'])->toBe('Corrected');
});

it('refuses to write something that is not an embed', function (): void {
    expect(($this->source)()->saveEmbed(['provider' => 'tiktok', 'id' => 'x']))->toBeNull()
        ->and(Storage::disk('public')->allFiles('library'))->toBe([]);
});

it('hands the browser one trigger, to draw beside Upload', function (): void {
    // One door rather than two: from the outside they were one question - "I have an
    // address" - and asking somebody which of two dialogs their link belongs in is asking
    // them to know the answer before they have it.
    $schema = ($this->action)('mediaBrowser')?->schemaComponent($this->editor)->getSchema(testSchema());

    $picker = collect($schema?->getComponents() ?? [])
        ->first(fn (object $component): bool => $component instanceof MediaPicker);

    expect(($this->action)('mediaBrowserUrl'))->not->toBeNull()
        ->and($picker?->getFromUrlAction()?->getName())->toBe('mediaBrowserUrl')
        // And the pair it replaced is gone.
        ->and(($this->action)('mediaBrowserEmbed'))->toBeNull()
        ->and(($this->action)('mediaBrowserAddress'))->toBeNull();
});

it('asks for a link and a title, and nothing else', function (): void {
    // The ratio went with the second door: one shape fits both halves, and a file address
    // has no aspect ratio to state. The toolbar's own embed dialog still offers it.
    $schema = ($this->action)('mediaBrowserUrl')?->schemaComponent($this->editor)->getSchema(testSchema());

    $names = array_map(
        static fn (object $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema?->getComponents() ?? [],
    );

    expect($names)->toBe(['url', 'title']);
});

it('asks the same three questions the embed dialog asks', function (): void {
    // One schema, so a field added to the embed dialog cannot go missing from this one.
    $names = array_map(
        static fn (object $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        EmbedAction::schema(),
    );

    expect($names)->toBe(['url', 'title', 'ratio']);
});

it('still takes a file address where embeds are switched off', function (): void {
    // The door stays: it is about links, and a link to somebody else's file has nothing to
    // do with whether this field frames YouTube.
    $livewire = new TestSchemaComponent;

    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->embeds(false)
        ->container(Schema::make($livewire)->operation('edit')->record(Post::create(['title' => 'P'])));

    data_set($livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['media' => null, 'src' => null]],
        ['name' => 'mediaBrowserUrl', 'data' => []],
    ]);

    $editor->getAction('mediaBrowserUrl')?->call(['data' => ['url' => 'https://cdn.test/talk.mp4']]);

    expect(data_get($livewire, 'mountedActions.0.data.src'))->toBe('https://cdn.test/talk.mp4');
});

it('refuses a YouTube link where the field has no frame to put it in', function (): void {
    // Passed to the file half it would insert a player pointing at a watch page, which
    // plays nothing at all - so the dialog says no while somebody can still paste
    // something else.
    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->embeds(false)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    $schema = $editor->getAction('mediaBrowserUrl')?->schemaComponent($editor)->getSchema(testSchema());

    $url = collect($schema?->getComponents() ?? [])
        ->first(fn (object $component): bool => method_exists($component, 'getName') && $component->getName() === 'url');

    $failed = null;

    foreach ($url?->getValidationRules() ?? [] as $rule) {
        if ($rule instanceof Closure) {
            $rule('url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', function (string $message) use (&$failed): void {
                $failed = $message;
            });
        }
    }

    expect($failed)->toBeString()->not->toBeEmpty();
});

it('leaves the grid with nothing under it', function (): void {
    // Both inputs are gone: the description moved into the panel and the address is behind
    // `+ Add`. What is left is the grid and the panel, which is what a picker is.
    $schema = ($this->action)('mediaBrowser')?->schemaComponent($this->editor)->getSchema(testSchema());

    $visible = collect($schema?->getComponents() ?? [])
        ->filter(fn (object $component): bool => $component instanceof TextInput);

    expect($visible)->toBeEmpty();
});

it('carries a typed address back into the browser rather than inserting it', function (): void {
    // The nested dialog writes into the browser's own form and closes. What is inserted is
    // still whatever the browser's Submit inserts, so there is one insert path and not two.
    data_set($this->livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['media' => null, 'src' => null]],
        ['name' => 'mediaBrowserAddress', 'data' => []],
    ]);

    // `call()` takes the closure's parameters by name, not the form's fields.
    ($this->action)('mediaBrowserUrl')?->call(['data' => ['url' => 'https://cdn.test/talk.mp4']]);

    expect(data_get($this->livewire, 'mountedActions.0.data.src'))->toBe('https://cdn.test/talk.mp4');
});

it('refuses an address a browser will not play a file from', function (): void {
    data_set($this->livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['media' => null, 'src' => null]],
        ['name' => 'mediaBrowserAddress', 'data' => []],
    ]);

    ($this->action)('mediaBrowserUrl')?->call(['data' => ['url' => 'javascript:alert(1)']]);

    expect(data_get($this->livewire, 'mountedActions.0.data.src'))->toBeNull();
});

it('adds an embed to the library and selects it in the browser', function (): void {
    data_set($this->livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['media' => null, 'src' => null]],
        ['name' => 'mediaBrowserUrl', 'data' => []],
    ]);

    ($this->action)('mediaBrowserUrl')?->call(['data' => [
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90',
        'title' => 'The talk',
    ]]);

    Storage::disk('public')->assertExists('library/youtube-dQw4w9WgXcQ.embed.json');

    expect(data_get($this->livewire, 'mountedActions.0.data.media'))
        ->toBe('library/youtube-dQw4w9WgXcQ.embed.json');
});

it('writes nothing for a link it will not frame', function (): void {
    data_set($this->livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['media' => null, 'src' => null]],
        ['name' => 'mediaBrowserUrl', 'data' => []],
    ]);

    ($this->action)('mediaBrowserUrl')?->call(['data' => ['url' => 'https://tiktok.com/x']]);

    expect(Storage::disk('public')->allFiles('library'))->toBe([])
        ->and(data_get($this->livewire, 'mountedActions.0.data.media'))->toBeNull();
});
