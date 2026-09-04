<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * What picking an embed out of the grid writes into the document.
 *
 * Deliberately nothing new: the same `setEmbed` command the embed dialog runs, with the same
 * five attributes. A library entry is a shortcut to that dialog, not a second way of storing
 * a video - which is what keeps every document written before this existed identical to one
 * written after it.
 */
beforeEach(function (): void {
    Storage::fake('public');

    // Nothing else in this suite observes what an action dispatches, so the component
    // records it. `runCommands()` reaches the editor through `dispatch()`, and that is the
    // seam.
    $this->livewire = new RecordingSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->embed = [
        'provider' => 'youtube',
        'id' => 'dQw4w9WgXcQ',
        'start' => 90,
        'title' => 'The talk',
        'ratio' => '16 / 9',
    ];

    $this->path = 'library/'.Embeds::fileName('youtube', 'dQw4w9WgXcQ');

    Storage::disk('public')->put($this->path, Embeds::encode($this->embed));

    // The closure reads `$arguments['editorSelection']` without a fallback, so a call that
    // leaves it out is a warning rather than a test.
    $this->submit = fn (array $data) => $this->editor->getAction('mediaBrowser')?->call([
        'data' => $data,
        'arguments' => ['editorSelection' => null],
    ]);
});

it('calls an entry in the library an embed', function (): void {
    expect(MediaLibraryAction::kindOf(['kind' => 'embed']))->toBe('embed');
});

it('inserts it with the command the embed dialog uses', function (): void {
    ($this->submit)(['media' => $this->path, 'src' => null]);

    $command = $this->livewire->commands()[0] ?? [];

    expect($command['name'] ?? null)->toBe('setEmbed')
        ->and($command['arguments'][0] ?? [])->toBe($this->embed);
});

it('writes nothing at all for an entry that has stopped making sense', function (): void {
    Storage::disk('public')->put('library/youtube-broken.embed.json', 'not json');

    ($this->submit)(['media' => 'library/youtube-broken.embed.json', 'src' => null]);

    expect($this->livewire->commands())->toBe([]);
});

it('replaces a picture the caret is standing on rather than rewriting it', function (): void {
    // An `<img>` cannot be turned into an iframe by writing attributes at it, so this has to
    // fall through to the insert - which is the same reason the video branch does.
    $this->editor->getAction('mediaBrowser')?->call([
        'data' => ['media' => $this->path, 'src' => null],
        'arguments' => [
            'editorSelection' => null,
            'src' => '/storage/old.png',
            'id' => 'old-picture',
        ],
    ]);

    expect($this->livewire->commands()[0]['name'] ?? null)->toBe('setEmbed');
});
