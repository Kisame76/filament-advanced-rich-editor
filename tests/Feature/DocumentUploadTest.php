<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\AttachFilesAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A document uploaded the plain way, on a field without a media browser.
 *
 * Filament's dialog and its drop handler insert an `image` node for whatever arrives, so a
 * field that accepted a pdf stored `<img src="….pdf">`. Both ways in now ask the field which
 * node an upload becomes. The drop handler's half is `tests/js/document-upload.test.js`.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new TestSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->mediaLibrary(false)
        ->fileAttachments(true)
        ->fileAttachmentsAcceptedFileTypes(['image/png', 'application/pdf'])
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    // Real bytes, stored the way Livewire stores an upload - see `MediaLibraryPendingTest`.
    $this->hold = function (string $name, string $bytes, ?string $id = null): string {
        $fake = new UploadedFile(tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);
        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);
        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        $id ??= 'upload-'.str_replace('.', '-', $name);

        data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", TemporaryUploadedFile::createFromLivewire($stored));

        return $id;
    };

    $this->pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    $this->png = fn (): string => UploadedFile::fake()->image('dot.png', 8, 8)->get();
});

it('hands the drop handler what it needs on a field that takes documents', function (): void {
    expect($this->editor->getDocumentUploadSettingsForJs())->toBe([
        'key' => $this->editor->getKey(),
        'statePath' => $this->editor->getStatePath(),
        'acceptedTypes' => ['image/png', 'application/pdf'],
        'maxSize' => $this->editor->getFileAttachmentsMaxSize(),
        'uploadingMessage' => $this->editor->getUploadingFileMessage(),
    ]);
});

it('stays out of the way where Filament is right for every file', function (): void {
    // Pictures only - Filament's default list. A pdf dropped here is refused, and saying so
    // is Filament's handler's job.
    expect(AdvancedRichEditor::make('content')->fileAttachments(true)
        ->container(Schema::make(new TestSchemaComponent)->record(new Post))
        ->getDocumentUploadSettingsForJs())->toBeNull()
        ->and(AdvancedRichEditor::make('content')->fileAttachments(false)->mediaLibrary(false)
            ->fileAttachmentsAcceptedFileTypes(['application/pdf'])
            ->container(Schema::make(new TestSchemaComponent)->record(new Post))
            ->getDocumentUploadSettingsForJs())->toBeNull();
});

it('stays out of the way of a media browser, which takes documents itself', function (): void {
    $editor = AdvancedRichEditor::make('content')
        ->fileAttachments(true)
        ->fileAttachmentsDirectory('attachments')
        ->fileAttachmentsAcceptedFileTypes(['application/pdf'])
        ->container(Schema::make(new TestSchemaComponent)->record(new Post));

    expect($editor->getMediaSource())->not->toBeNull()
        ->and($editor->getDocumentUploadSettingsForJs())->toBeNull();
});

it('takes any file where the field names no types at all', function (): void {
    $editor = AdvancedRichEditor::make('content')->mediaLibrary(false)->fileAttachments(true)
        ->fileAttachmentsAcceptedFileTypes(fn (): ?array => null)
        ->container(Schema::make(new TestSchemaComponent)->record(new Post));

    expect($editor->getDocumentUploadSettingsForJs())->toMatchArray(['acceptedTypes' => null]);
});

it('makes a dropped pdf a card', function (): void {
    $id = ($this->hold)('Preise 2027.pdf', $this->pdf);

    $node = $this->editor->getUploadedFileAttachmentNodeForJs($id);

    expect($node['type'])->toBe('file')
        ->and($node['attrs'])->toMatchArray([
            'id' => $id,
            'name' => 'Preise 2027.pdf',
            // Written once, as the label a reader sees.
            'size' => ByteSize::format(strlen($this->pdf)),
        ])
        // Livewire lists no pdf among the files it previews, so Filament's handler got no
        // address back and inserted nothing on a local disk. The card has one.
        ->and($node['attrs']['src'])->toBeString()->not->toBe('');
});

it('leaves a dropped picture the picture Filament makes', function (): void {
    $id = ($this->hold)('dot.png', ($this->png)());

    $node = $this->editor->getUploadedFileAttachmentNodeForJs($id);

    expect($node['type'])->toBe('image')
        ->and($node['attrs']['id'])->toBe($id)
        ->and($node['attrs']['src'])->toBeString()->not->toBe('');
});

it('answers nothing for an upload the field does not accept', function (): void {
    $id = ($this->hold)('notes.txt', 'plain text');

    expect($this->editor->getUploadedFileAttachmentNodeForJs($id))->toBeNull()
        ->and($this->editor->getUploadedFileAttachmentNodeForJs('nothing-here'))->toBeNull();
});

it('leaves the media browser its own uploads', function (): void {
    // The browser's prefix sends an upload to the browser's list of types, which is not the
    // list this field gave Filament.
    $id = ($this->hold)('Preise.pdf', $this->pdf, id: 'arte-0123');

    expect($this->editor->getUploadedFileAttachmentNodeForJs($id))->toBeNull();
});

it('keeps the card through the save, pointing at the stored file', function (): void {
    $id = ($this->hold)('Preise.pdf', $this->pdf);
    $node = $this->editor->getUploadedFileAttachmentNodeForJs($id);

    $this->editor->rawState(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [$node]],
    ]]);

    $this->editor->saveFileAttachments();

    $html = (string) $this->editor->getState();

    expect($html)->toContain('data-type="file"')
        ->toContain('Preise.pdf')
        ->not->toContain('<img')
        ->not->toContain('data-id="'.$id.'"');
});

it('reaches the upload dialog, which inserts a card for a document', function (): void {
    $id = ($this->hold)('Preise.pdf', $this->pdf);
    $file = data_get($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}");

    $action = collect($this->editor->getDefaultActions())->first(fn (Action $action): bool => $action->getName() === 'attachFiles');

    expect($action)->not->toBeNull();

    $action->schemaComponent($this->editor)->call([
        'arguments' => ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]],
        'data' => ['file' => $file, 'alt' => null],
    ]);

    $commands = collect(Livewire\store($this->livewire)->get('dispatched') ?? [])
        ->map(fn (object $event): array => (fn (): array => [$this->name, $this->params])->call($event))
        ->filter(fn (array $event): bool => $event[0] === 'run-rich-editor-commands')
        ->flatMap(fn (array $event): array => $event[1]['commands']);

    expect($commands)->toHaveCount(1)
        ->and($commands->first()['name'])->toBe('insertContent')
        ->and($commands->first()['arguments'][0]['type'])->toBe('file')
        ->and($commands->first()['arguments'][0]['attrs']['name'])->toBe('Preise.pdf');
});

it('leaves a picture in the upload dialog to Filament', function (): void {
    $id = ($this->hold)('dot.png', ($this->png)());
    $file = data_get($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}");

    AttachFilesAction::make()->schemaComponent($this->editor)->call([
        'arguments' => ['editorSelection' => ['type' => 'text', 'anchor' => 1, 'head' => 1]],
        'data' => ['file' => $file, 'alt' => 'Ein Punkt'],
    ]);

    $commands = collect(Livewire\store($this->livewire)->get('dispatched') ?? [])
        ->map(fn (object $event): array => (fn (): array => [$this->name, $this->params])->call($event))
        ->filter(fn (array $event): bool => $event[0] === 'run-rich-editor-commands')
        ->flatMap(fn (array $event): array => $event[1]['commands']);

    expect($commands)->toHaveCount(1)
        ->and($commands->first()['arguments'][0]['type'])->toBe('image')
        ->and($commands->first()['arguments'][0]['attrs']['alt'])->toBe('Ein Punkt');
});
