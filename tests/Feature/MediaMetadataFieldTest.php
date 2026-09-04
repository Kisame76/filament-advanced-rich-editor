<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The panel writes a description while the dialog is open, and the file it describes may not
 * exist yet. Both halves of that are here: what the exposed method accepts, and where a
 * description waits while its upload is still a temporary file.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    Storage::disk('public')->put('library/sunset.png', 'x');

    $this->livewire = new TestSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->hold = function (string $name): TemporaryUploadedFile {
        $fake = UploadedFile::fake()->image($name, 8, 8);
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

        return TemporaryUploadedFile::createFromLivewire($stored);
    };
});

it('writes a description through the source', function (): void {
    expect($this->editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => 'The harbour']))
        ->toBeTrue()
        ->and($this->editor->getMediaMetadata('library/sunset.png')['alt'])->toBe('The harbour');
});

it('refuses where there is no library to write to', function (): void {
    $editor = AdvancedRichEditor::make('content')
        ->mediaLibrary(false)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit'));

    expect($editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => 'x']))->toBeFalse();
});

it('refuses a key it does not know and a value that is not text', function (): void {
    // The method is reachable from the browser, so what it accepts is the whole of what it
    // trusts. A stray key would be written into a sidecar and read back for ever.
    expect($this->editor->saveMediaMetadataForJs('library/sunset.png', ['cover' => 'x']))->toBeFalse()
        ->and($this->editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => ['x']]))->toBeFalse()
        ->and($this->editor->saveMediaMetadataForJs('library/sunset.png', []))->toBeFalse()
        ->and($this->editor->getMediaMetadata('library/sunset.png')['alt'])->toBeNull();
});

it('refuses a description longer than a description', function (): void {
    expect($this->editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => str_repeat('a', 1001)]))
        ->toBeFalse()
        ->and($this->editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => str_repeat('a', 1000)]))
        ->toBeTrue();
});

it('holds a description for an upload that has no file on the disk yet', function (): void {
    $id = FileAttachments::PENDING_PREFIX.'held';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", ($this->hold)('hafen.png'));

    expect($this->editor->saveMediaMetadataForJs($id, ['alt' => 'Typed before saving']))->toBeTrue()
        ->and($this->editor->getMediaMetadata($id)['alt'])->toBe('Typed before saving')
        // Nothing was written to the library, because there is nothing there to write to.
        ->and(Storage::disk('public')->allFiles('library'))->toBe(['library/sunset.png']);
});

it('refuses to hold a description for an upload it is not holding', function (): void {
    expect($this->editor->saveMediaMetadataForJs(FileAttachments::PENDING_PREFIX.'ghost', ['alt' => 'x']))
        ->toBeFalse();
});

it('carries a held description onto the file once it is written', function (): void {
    // The one moment a pending id becomes a real one. A description typed in the dialog and
    // then lost on save would be worse than not offering the field before the upload lands.
    $pending = FileAttachments::PENDING_PREFIX.'held';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$pending}", ($this->hold)('hafen.png'));

    $this->editor->saveMediaMetadataForJs($pending, ['alt' => 'Typed before saving']);

    $this->editor->applyPendingMediaMetadata($pending, 'library/sunset.png');

    expect($this->editor->getMediaMetadata('library/sunset.png')['alt'])->toBe('Typed before saving')
        // And it is let go of, so a second save does not write it over a description somebody
        // has since corrected in the library.
        ->and(data_get($this->livewire, 'arteMediaMetadata.'.$pending))->toBeNull();
});

it('describes a picture in the details the panel reads', function (): void {
    $this->editor->saveMediaMetadataForJs('library/sunset.png', ['alt' => 'The harbour']);

    expect($this->editor->getMediaDetailsForJs('library/sunset.png')['alt'])->toBe('The harbour')
        ->and($this->editor->getMediaDetailsForJs('library/sunset.png')['title'])->toBeNull();
});

it('describes a held upload in the details too', function (): void {
    $id = FileAttachments::PENDING_PREFIX.'held';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", ($this->hold)('hafen.png'));

    $this->editor->saveMediaMetadataForJs($id, ['alt' => 'Typed before saving']);

    expect($this->editor->getMediaDetailsForJs($id)['alt'])->toBe('Typed before saving');
});

it('lets go of held descriptions when it lets go of the uploads', function (): void {
    $id = FileAttachments::PENDING_PREFIX.'held';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", ($this->hold)('hafen.png'));
    $this->editor->saveMediaMetadataForJs($id, ['alt' => 'x']);

    $this->editor->discardPendingUploads();

    expect(data_get($this->livewire, 'arteMediaMetadata'))->toBe([]);
});
