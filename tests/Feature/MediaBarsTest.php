<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\MediaReplacePlugin;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * The bars over something that came out of the library: a picture, a card, a film, a sound,
 * an embed.
 *
 * They were five different answers to one question. The picture could be downloaded and not
 * replaced, the card replaced and not downloaded, and a film had no bar at all. Now each has
 * what makes sense for it - Replace wherever there is a dialog to go back to, Download wherever
 * there is a file, Delete everywhere - in the same order, so a hand that learned one bar knows
 * all of them.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->livewire = new RecordingSchemaComponent;

    // A library to go back to: the one thing Replace needs for a card, a film and a sound.
    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));
});

it('lets a card be downloaded as well as replaced', function (): void {
    $download = $this->editor->getTools()['fileDownload'] ?? null;

    expect($this->editor->getFloatingToolbars()['file'] ?? null)->toBe(['fileReplace', 'fileDownload', 'fileDelete'])
        ->and($download?->getJsHandler())->toContain("getAttributes('file')")
        // Saved under the card's own name - `Quartalsbericht Q3.pdf` - rather than under
        // whatever the storage path happens to end in.
        ->and($download?->getJsHandler())->toContain('attributes.name');
});

it('keeps Download on a card where there is no library to go back to', function (): void {
    expect(editor()->getFloatingToolbars()['file'] ?? null)->toBe(['fileDownload', 'fileDelete']);
});

it('lets a picture be replaced from its own bar', function (): void {
    $replace = $this->editor->getTools()['imageReplace'] ?? null;
    $buttons = $this->editor->getFloatingToolbars()['image'] ?? [];

    expect(array_slice($buttons, -3))->toBe(['imageReplace', 'imageDownload', 'imageDelete'])
        ->and($replace?->getJsHandler())->toContain("mountAction('mediaBrowser'")
        ->and($replace?->getJsHandler())->toContain("kind: 'image'")
        ->and($replace?->getJsHandler())->toContain("getAttributes('image')?.id");
});

it('replaces a picture through Filament\'s own dialog where there is no library', function (): void {
    // Unlike a card or a film: Filament's dialog takes pictures, and replaces the selected
    // one when it is handed its address.
    $replace = editor()->getTools()['imageReplace'] ?? null;

    expect(editor()->getFloatingToolbars()['image'] ?? [])->toContain('imageReplace')
        ->and($replace?->getJsHandler())->toContain("mountAction('attachFiles'")
        ->and($replace?->getJsHandler())->toContain("getAttributes('image')?.src");
});

it('puts a bar over a film or a sound', function (): void {
    $replace = $this->editor->getTools()['mediaReplace'] ?? null;

    expect($this->editor->getFloatingToolbars()['media'] ?? null)->toBe(['mediaReplace', 'mediaDownload', 'mediaDelete'])
        ->and($replace?->getJsHandler())->toContain("replace: 'media'")
        // Opened on the tab of what is being replaced: a sound on Audio, a film on Video.
        ->and($replace?->getJsHandler())->toContain("getAttributes('media')?.kind")
        ->and($this->editor->getTools()['mediaDownload']?->getJsHandler())->toContain("getAttributes('media')")
        ->and($this->editor->getTools()['mediaDelete']?->getJsHandler())->toContain('deleteSelection()');
});

it('keeps Download and Delete over a film where there is no library to go back to', function (): void {
    expect(editor()->getFloatingToolbars()['media'] ?? null)->toBe(['mediaDownload', 'mediaDelete']);
});

it('draws no bar for films where the field takes none', function (): void {
    expect(editor()->media(false)->getFloatingToolbars())->not->toHaveKey('media');
});

it('puts a bar over an embed that goes back to its dialog', function (): void {
    // Nothing to download: the video is on somebody else's server.
    $replace = $this->editor->getTools()['embedReplace'] ?? null;

    expect($this->editor->getFloatingToolbars()['embed'] ?? null)->toBe(['embedReplace', 'embedDelete'])
        ->and($replace?->getJsHandler())->toContain("mountAction('embed'")
        ->and($replace?->getJsHandler())->toContain("getAttributes('embed')?.provider")
        ->and($this->editor->getTools()['embedDelete']?->getJsHandler())->toContain('deleteSelection()')
        ->and(editor()->embeds(false)->getFloatingToolbars())->not->toHaveKey('embed');
});

it('replaces the film it was opened from', function (): void {
    // The film's own bar opens the browser with the film selected. Where the selection
    // arrives described as a caret beside the block, the new film would land next to it.
    Storage::disk('public')->put('article-attachments/talk.mp4', (string) file_get_contents(dirname(__DIR__).'/Fixtures/media/tiny.mp4'));

    $this->editor->getAction('mediaBrowser')?->call([
        'data' => ['src' => null, 'media' => 'article-attachments/talk.mp4'],
        'arguments' => [
            'kind' => 'video',
            'replace' => 'media',
            'id' => 'article-attachments/old.mp4',
            'editorSelection' => ['type' => 'text', 'anchor' => 5, 'head' => 5],
        ],
    ]);

    $dispatched = collect($this->livewire->dispatched)->last();

    expect($dispatched['params']['commands'][0]['name'] ?? null)->toBe('setMedia')
        ->and($dispatched['params']['editorSelection'] ?? null)->toBe(['type' => 'node', 'anchor' => 4]);
});

it('teaches every editor to follow a file replaced in the library', function (): void {
    // Unconditional, like the card: asking whether a field has a library would ask its
    // toolbar, which asks its plugins - and the listener costs nothing while nothing is
    // replaced.
    $plugin = collect(editor()->getPlugins())->first(fn (object $plugin): bool => $plugin instanceof MediaReplacePlugin);

    expect(pluginNames(editor()->mediaLibrary(false)))->toContain(MediaReplacePlugin::class)
        ->and($plugin?->getTipTapJsExtensions()[0] ?? '')->toContain('media-replace')
        ->and($plugin?->getEditorTools())->toBe([]);
});
