<?php

declare(strict_types=1);

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\EditImageAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EditorSelection;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Cropping, turning and flipping a picture already in the document, in Filament's own image
 * editor. What is pinned here is this package's half: when the button is there, what the
 * dialog is opened on, and what an edit writes - a new file for this one picture, drawn at the
 * scale the old one was. The editor itself is Filament's, and was driven in the lab.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new RecordingSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->imageEditor()
        ->fileAttachmentsDisk('public')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    // What the image editor hands back: an upload the way Livewire holds one, with real bytes
    // so that it can be measured - see `MediaLibraryPendingTest`.
    $this->edited = function (int $width, int $height): TemporaryUploadedFile {
        $bytes = UploadedFile::fake()->image('hafen-v1.png', $width, $height)->get();
        $fake = new UploadedFile((string) tempnam(sys_get_temp_dir(), 'arte'), 'hafen-v1.png', null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->submit = fn (mixed $file, array $arguments = []) => $this->editor->getAction(EditImageAction::NAME)?->call([
        'data' => ['file' => $file],
        'arguments' => [
            'src' => '/storage/hafen.png',
            'width' => 320,
            'height' => 200,
            'naturalWidth' => 320,
            'editorSelection' => ['type' => 'node', 'anchor' => 4],
            ...$arguments,
        ],
    ]);

    $this->written = fn (): array => $this->livewire->commands()[0]['arguments'][1] ?? [];
});

it('ships off, with neither a button nor a dialog', function (): void {
    expect(editor()->hasImageEditor())->toBeFalse()
        ->and(editor()->getFloatingToolbars()['image'])->not->toContain('imageEdit')
        ->and(collect(editor()->getDefaultActions())->map->getName())->not->toContain(EditImageAction::NAME);
});

it('puts the button beside Replace once it is on', function (): void {
    $buttons = array_values(array_filter(editor()->imageEditor()->getFloatingToolbars()['image'], 'is_string'));

    expect(array_slice($buttons, array_search('imageEdit', $buttons, true), 2))->toBe(['imageEdit', 'imageReplace'])
        ->and(collect(editor()->imageEditor()->getDefaultActions())->map->getName())->toContain(EditImageAction::NAME);
});

it('is switched on from the config file as well', function (): void {
    config()->set('filament-advanced-rich-editor.images.editor', true);

    expect(editor()->hasImageEditor())->toBeTrue();
});

it('opens Filament\'s image editor on the picture as the document shows it', function (): void {
    $action = $this->editor->getAction(EditImageAction::NAME);

    /** @var FileUpload $upload */
    $upload = $action->getSchema(Schema::make($this->livewire)->statePath('data'))->getComponents()[0];
    $upload->rawState(['picture' => '/storage/2026/hafen.png?v=2']);

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->isImageEditorExplicitlyEnabled())->toBeTrue()
        // A drawing comes out as pixels, which is asked about first.
        ->and($upload->isSvgEditingConfirmed())->toBeTrue()
        // Nothing to look up on a disk, nothing stored, and nothing a removal could delete.
        ->and($upload->shouldFetchFileInformation())->toBeFalse()
        ->and($upload->shouldStoreFiles())->toBeFalse()
        ->and($upload->getUploadedFiles())->toBe(['picture' => [
            'name' => 'hafen.png',
            'size' => 0,
            'type' => null,
            'url' => '/storage/2026/hafen.png?v=2',
        ]]);
});

it('goes straight into the editor, the dialog around it unseen', function (): void {
    // What the script does is pinned in `tests/js/edit-image-directly.test.js`; this is that
    // it is there, and the class it lifts.
    $action = $this->editor->getAction(EditImageAction::NAME);
    $upload = $action->getSchema(Schema::make($this->livewire)->statePath('data'))->getComponents()[0];

    expect($action->getExtraModalWindowAttributeBag()->get('class'))->toContain('fi-arte-image-editor-pending')
        ->and($upload->getExtraAlpineAttributes())->toBe(['x-init' => e(EditImageAction::OPENS_DIRECTLY)]);
});

it('points the picture at the edited file, held like any upload until the form is saved', function (): void {
    ($this->submit)(($this->edited)(200, 200));

    $held = data_get($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}");
    $id = array_key_first($held);

    expect($id)->toStartWith(FileAttachments::PENDING_PREFIX)
        ->and($this->livewire->commands()[0]['name'])->toBe('updateAttributes')
        ->and($this->livewire->commands()[0]['arguments'][0])->toBe('image')
        ->and(($this->written)())->toMatchArray(['id' => $id, 'width' => 200, 'height' => 200])
        ->and(($this->written)()['src'])->toContain('preview-file');
});

it('leaves the picture alone where nothing was edited', function (): void {
    // Applied straight away: the field still holds the address it was opened with.
    ($this->submit)('/storage/hafen.png');

    expect($this->livewire->commands())->toBe([])
        ->and(data_get($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}"))->toBeEmpty();
});

it('writes to the picture even where the selection arrived as a caret beside it', function (): void {
    ($this->submit)(($this->edited)(200, 200), ['editorSelection' => ['type' => 'text', 'anchor' => 5, 'head' => 5]]);

    $dispatch = collect($this->livewire->dispatched)->firstWhere('event', 'run-rich-editor-commands');

    expect($dispatch['params']['editorSelection'])->toBe(['type' => 'node', 'anchor' => 4]);
});

it('keeps the scale the picture was drawn at, not its box', function (): void {
    // Shown at half the size of its file, and half of it cut away: a quarter of the old width.
    expect(ImageAttributes::afterEditing(
        ['id' => 'x', 'url' => '/x.png', 'width' => 400, 'height' => 400],
        ['width' => 400, 'height' => 250, 'naturalWidth' => 800],
    ))->toBe(['id' => 'x', 'src' => '/x.png', 'width' => 200, 'height' => 200]);
});

it('keeps the width where the browser could not say how wide the old file was', function (): void {
    expect(ImageAttributes::afterEditing(
        ['id' => 'x', 'url' => '/x.png', 'width' => 400, 'height' => 200],
        ['width' => 300, 'height' => 300],
    ))->toBe(['id' => 'x', 'src' => '/x.png', 'width' => 300, 'height' => 150]);
});

it('gives a picture without a size nothing but its new file', function (array $current): void {
    expect(ImageAttributes::afterEditing(['id' => 'x', 'url' => '/x.png', 'width' => 400, 'height' => 200], $current))
        ->toBe(['id' => 'x', 'src' => '/x.png']);
})->with([
    'no size' => [[]],
    'a width alone' => [['width' => 300]],
    // A percentage says nothing about a scale.
    'a percentage' => [['width' => '50%', 'height' => 200]],
]);

it('corrects a selection that describes a caret beside the node', function (): void {
    expect(EditorSelection::onTheNode(['type' => 'text', 'anchor' => 5, 'head' => 5]))->toBe(['type' => 'node', 'anchor' => 4])
        ->and(EditorSelection::onTheNode(['type' => 'node', 'anchor' => 4]))->toBe(['type' => 'node', 'anchor' => 4])
        ->and(EditorSelection::onTheNode(null))->toBeNull();
});
