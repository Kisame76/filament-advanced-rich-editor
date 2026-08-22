<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaLibraryItem;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Who owns an uploaded picture.
 *
 * A media row belongs to a model, and the default path generator builds the file's path out of
 * the row's id - so two rows can never share one file, and a picture uploaded while editing an
 * article is that article's. Deleting the article takes the file with it, which is fine while
 * only that article shows it and not fine the moment a second one reuses it.
 *
 * Sending uploads to a model that exists for the purpose breaks the coupling: nothing an editor
 * deletes owns them.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    config()->set('media-library.disk_name', 'public');

    // Real bytes: the browser lists images, and the pool is right to drop a text file called
    // `.png` - which would make every assertion here pass for the wrong reason.
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->post = MediaPost::create(['title' => 'Article', 'content' => '']);
    $this->library = MediaLibraryItem::create(['title' => 'Library', 'content' => '']);

    $this->upload = function (string $name = 'hafen.png'): TemporaryUploadedFile {
        $fake = UploadedFile::fake()->image($name, 10, 6);
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->provider = fn (bool $toLibrary): SpatieMediaLibraryFileAttachmentProvider => SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn (): MediaPost => $this->post)
        ->ownerUsing(fn (): string => 'content')
        ->uploadsToUsing($toLibrary ? fn (): MediaLibraryItem => $this->library : null);

    $this->editor = fn (bool $toLibrary): AdvancedRichEditor => AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->mediaLibraryUploadsTo($toLibrary ? fn (): MediaLibraryItem => $this->library : null)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));
});

it('attaches an upload to the record by default', function (): void {
    ($this->provider)(false)->saveUploadedFileAttachment(($this->upload)());

    expect($this->post->refresh()->getMedia('rich-editor'))->toHaveCount(1)
        ->and($this->library->refresh()->getMedia('rich-editor'))->toHaveCount(0);
});

it('attaches it to the library where one was named', function (): void {
    ($this->provider)(true)->saveUploadedFileAttachment(($this->upload)());

    expect($this->library->refresh()->getMedia('rich-editor'))->toHaveCount(1)
        // And nothing hangs off the article, so deleting the article cannot take it away.
        ->and($this->post->refresh()->getMedia('rich-editor'))->toHaveCount(0);
});

it('never sweeps a library picture', function (): void {
    // The sweep asks what the record owns. A library picture is not the record's to delete -
    // which is the whole reason for moving it out of the record's collection.
    $uuid = ($this->provider)(true)->saveUploadedFileAttachment(($this->upload)());

    ($this->provider)(true)->cleanUpFileAttachments([]);

    expect(Media::where('uuid', $uuid)->exists())->toBeTrue();
});

it('still sweeps the record\'s own uploads', function (): void {
    $uuid = ($this->provider)(false)->saveUploadedFileAttachment(($this->upload)());

    ($this->provider)(false)->cleanUpFileAttachments([]);

    expect(Media::where('uuid', $uuid)->exists())->toBeFalse();
});

it('needs no saved record once uploads go elsewhere', function (): void {
    // A library is already there before the form is opened, so there is nothing to wait for -
    // and a create page can write its attachments at once instead of deferring them.
    expect(($this->provider)(true)->isExistingRecordRequiredToSaveNewFileAttachments())->toBeFalse()
        ->and(($this->provider)(false)->isExistingRecordRequiredToSaveNewFileAttachments())->toBeTrue();
});

it('shows what it uploads to, because the pool is the collection', function (): void {
    // A library and the articles that draw from it share one collection, so both are in the
    // pool - which is the point. Naming a library must not send pictures somewhere the grid
    // cannot show and the lookup will not resolve.
    $this->library->addMediaFromString($this->png)->usingFileName('shared.png')->toMediaCollection('rich-editor');
    $this->post->addMediaFromString($this->png)->usingFileName('article.png')->toMediaCollection('rich-editor');

    $names = array_column(($this->editor)(true)->getMediaSource()->page()['items'], 'fileName');

    expect($names)->toContain('shared.png', 'article.png');
});

it('resolves a library picture it did not upload', function (): void {
    $media = $this->library->addMediaFromString($this->png)->usingFileName('shared.png')->toMediaCollection('rich-editor');

    expect(($this->editor)(true)->getFileAttachmentProvider()->getFileAttachmentUrl((string) $media->uuid))
        ->toBeString()->not->toBeEmpty();
});

it('does not widen the pool past the collection', function (): void {
    // Directing uploads elsewhere changes who owns them, not what the browser may reach. A
    // library kept in another collection is another library.
    $this->library->addMediaFromString($this->png)->usingFileName('elsewhere.png')->toMediaCollection('other-library');
    $this->post->addMediaFromString($this->png)->usingFileName('article.png')->toMediaCollection('rich-editor');

    $names = array_column(($this->editor)(true)->getMediaSource()->page()['items'], 'fileName');

    expect($names)->toBe(['article.png']);
});
