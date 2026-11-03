<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A file that is already in the library, uploaded again.
 *
 * Its bytes are what decide, not its name: the same picture saved under another name is still
 * the same picture, and two files of one name may well be two pictures. The search is the pool
 * the browser lists, so finding a copy there is exactly what picking it would have been.
 *
 * Here on a disk; `MediaLibraryDuplicatesSpatieTest` asks the same of a media collection.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new RecordingSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->fileAttachmentsDirectory('library')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->picture = fn (int $width = 12, int $height = 8, string $name = 'hafen.png'): string => UploadedFile::fake()->image($name, $width, $height)->get();

    // An upload the way Livewire holds one, with real bytes - see `MediaLibraryPendingTest`.
    $this->hold = function (string $name, string $bytes): TemporaryUploadedFile {
        $fake = new UploadedFile((string) tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    // A picture dropped into the text and the form saved: the document points at the upload by
    // the id it is held under, and the save turns that into a file.
    $this->saved = function (TemporaryUploadedFile $file): string {
        $pending = $this->editor->registerPendingUpload($file);

        $this->editor->state('<p><img src="/livewire/preview" data-id="'.$pending.'"></p>');
        $this->editor->resolveFileAttachmentIds();

        preg_match('/data-id="([^"]+)"/', (string) $this->editor->getState(), $matches);

        return $matches[1] ?? '';
    };

    $this->files = fn (): array => Storage::disk('public')->files('library');
});

it('points a picture at the copy the library already has', function (): void {
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/harbour.png', $bytes);

    expect(($this->saved)(($this->hold)('IMG_0412.png', $bytes)))->toBe('library/harbour.png')
        // Nothing was written: the upload was let go of rather than stored a second time.
        ->and(($this->files)())->toBe(['library/harbour.png']);
});

it('writes a picture the library does not have', function (): void {
    Storage::disk('public')->put('library/harbour.png', ($this->picture)(12, 8));

    $id = ($this->saved)(($this->hold)('hafen.png', ($this->picture)(16, 9)));

    expect($id)->toStartWith('library/hafen--')
        ->and(($this->files)())->toHaveCount(2);
});

it('tells two files of one size apart by what is in them', function (): void {
    $bytes = ($this->picture)();
    $other = substr_replace($bytes, chr(ord($bytes[40]) ^ 1), 40, 1);

    Storage::disk('public')->put('library/harbour.png', $other);

    expect(strlen($other))->toBe(strlen($bytes))
        ->and(($this->saved)(($this->hold)('hafen.png', $bytes)))->not->toBe('library/harbour.png')
        ->and(($this->files)())->toHaveCount(2);
});

it('finds a copy in a folder of the library', function (): void {
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/2026/harbour.png', $bytes);

    expect(($this->saved)(($this->hold)('hafen.png', $bytes)))->toBe('library/2026/harbour.png');
});

it('finds the copy a save a moment ago wrote', function (): void {
    // The same picture dropped twice before saving is two uploads, and the second finds the
    // file the first has just become.
    $bytes = ($this->picture)();

    $first = ($this->saved)(($this->hold)('hafen.png', $bytes));

    expect(($this->saved)(($this->hold)('hafen kopie.png', $bytes)))->toBe($first)
        ->and(($this->files)())->toHaveCount(1);
});

it('keeps every copy where the field says so', function (): void {
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/harbour.png', $bytes);

    $this->editor->mediaLibraryDeduplicates(false);

    expect(($this->saved)(($this->hold)('hafen.png', $bytes)))->not->toBe('library/harbour.png')
        ->and(($this->files)())->toHaveCount(2);
});

it('looks nowhere without a library', function (): void {
    // No browser, no pool: there is nothing the field could have offered instead.
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/harbour.png', $bytes);

    $this->editor->mediaLibrary(false);

    expect(($this->saved)(($this->hold)('hafen.png', $bytes)))->not->toBe('library/harbour.png');
});

it('leaves the description of a copy to the copy', function (): void {
    // What was typed against the upload belongs to the upload. The file it turned out to be
    // already has a description of its own, perhaps written for every other document using it.
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/harbour.png', $bytes);
    $this->editor->saveMediaMetadataForJs('library/harbour.png', ['alt' => 'The harbour at dawn']);

    $file = ($this->hold)('hafen.png', $bytes);
    $pending = $this->editor->registerPendingUpload($file);
    $this->editor->saveMediaMetadataForJs($pending, ['alt' => 'Typed against the upload']);

    $this->editor->state('<p><img src="/livewire/preview" data-id="'.$pending.'"></p>');
    $this->editor->resolveFileAttachmentIds();

    expect($this->editor->getMediaMetadata('library/harbour.png')['alt'])->toBe('The harbour at dawn');
});

// In the browser, where there is somebody to tell.

it('offers the copy in the browser instead of holding a second one', function (): void {
    $bytes = ($this->picture)();

    Storage::disk('public')->put('library/harbour.png', $bytes);

    data_set($this->livewire, 'mountedActions', [[
        'name' => 'mediaBrowser',
        'data' => ['file' => ['a' => ($this->hold)('IMG_0412.png', $bytes)]],
    ]]);

    $page = $this->editor->getMediaLibraryPageForJs();

    expect($page['copies'])->toBe([['name' => 'IMG_0412.png', 'id' => 'library/harbour.png', 'existing' => 'harbour.png']])
        // Not held, so not offered as an upload of its own - and not left in the dialog's
        // field either, where Submit would fall back to it.
        ->and($this->editor->getPendingMediaItems())->toBe([])
        ->and(data_get($this->livewire, 'mountedActions.0.data.file'))->toBe([])
        ->and($page['items'][0]['id'])->toBe('library/harbour.png');
});

it('holds an upload the library does not have, as before', function (): void {
    data_set($this->livewire, 'mountedActions', [[
        'name' => 'mediaBrowser',
        'data' => ['file' => ['a' => ($this->hold)('hafen.png', ($this->picture)())]],
    ]]);

    $page = $this->editor->getMediaLibraryPageForJs();

    expect($page['copies'])->toBe([])
        ->and($page['items'][0]['pending'] ?? false)->toBeTrue();
});
