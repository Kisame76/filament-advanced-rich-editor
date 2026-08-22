<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A media collection hangs off the record, not off the field, so everything here is about one
 * question: when one editor is saved, whose images is it allowed to delete?
 */
beforeEach(function (): void {
    // The package works without `spatie/laravel-medialibrary`, so the suite has to as well.
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Post', 'content' => '']);

    // An attachment as the provider stores one: in the shared collection, marked with the
    // field that uploaded it.
    $this->attach = function (string $owner): Media {
        return $this->post
            ->addMediaFromString('a picture')
            ->usingFileName($owner.'-'.uniqid().'.jpg')
            ->withCustomProperties([SpatieMediaLibraryFileAttachmentProvider::OWNER_PROPERTY => $owner])
            ->toMediaCollection('rich-editor');
    };

    $this->provider = function (string $owner): SpatieMediaLibraryFileAttachmentProvider {
        return SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
            ->recordUsing(fn (): MediaPost => $this->post)
            ->ownerUsing(fn (): string => $owner);
    };
});

it('deletes the attachments the saved field no longer references', function (): void {
    $kept = ($this->attach)('content');
    $removed = ($this->attach)('content');

    ($this->provider)('content')->cleanUpFileAttachments([$kept->uuid]);

    expect(Media::find($kept->getKey()))->not->toBeNull()
        ->and(Media::find($removed->getKey()))->toBeNull();
});

it('leaves the other editor on the same record alone', function (): void {
    // The bug this exists for: two rich editors on one model share a collection, so an
    // unscoped sweep deleted the other field's images on every save.
    $mine = ($this->attach)('content');
    $theirs = ($this->attach)('summary');

    // Saving `content` with nothing left in it.
    ($this->provider)('content')->cleanUpFileAttachments([]);

    expect(Media::find($mine->getKey()))->toBeNull()
        ->and(Media::find($theirs->getKey()))->not->toBeNull();

    // And the other way round, so neither field is merely lucky about its name.
    ($this->provider)('summary')->cleanUpFileAttachments([]);

    expect(Media::find($theirs->getKey()))->toBeNull();
});

it('never deletes media it did not put there', function (): void {
    // A project that keeps something else in the same collection, or media from before the
    // attachments were marked at all. Unknown provenance is not a licence to delete.
    $foreign = $this->post
        ->addMediaFromString('not ours')
        ->usingFileName('logo.jpg')
        ->toMediaCollection('rich-editor');

    ($this->provider)('content')->cleanUpFileAttachments([]);

    expect(Media::find($foreign->getKey()))->not->toBeNull();
});

it('does nothing at all when there is no field to scope by', function (): void {
    // A `RichContentRenderer` handed the provider directly has no field behind it, and a
    // provider that cannot tell what it owns must not guess.
    $media = ($this->attach)('content');

    SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn (): MediaPost => $this->post)
        ->cleanUpFileAttachments([]);

    expect(Media::find($media->getKey()))->not->toBeNull();
});

it('never deletes a picture the browser offered to other records', function (): void {
    // The whole point of the browser is that a picture uploaded for one article is the picture
    // the next article wants - so with the shipped pool, the uuid in this record's content may
    // also be sitting in another record's content, where nothing here can see it. A sweep that
    // deletes it takes that other record's image away, silently and for good.
    $shared = ($this->attach)('content');

    $editor = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));

    ($this->provider)('content')
        ->source($editor->getMediaSource())
        ->cleanUpFileAttachments([]);

    expect(Media::find($shared->getKey()))->not->toBeNull();
});

it('still sweeps when the browser only ever showed this record', function (): void {
    // Narrowed back to the record, nobody else could have been offered the picture, so the
    // sweep is safe again and the disk does not grow forever.
    $removed = ($this->attach)('content');

    $editor = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->mediaLibraryScope('record')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));

    ($this->provider)('content')
        ->source($editor->getMediaSource())
        ->cleanUpFileAttachments([]);

    expect(Media::find($removed->getKey()))->toBeNull();
});

it('still sweeps a field whose browser is switched off', function (): void {
    // No browser means no pool wider than the record, so nothing else can hold the uuid and
    // the sweep keeps working exactly as it did before the browser existed.
    $removed = ($this->attach)('content');

    $editor = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->mediaLibrary(false)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));

    ($this->provider)('content')
        ->source($editor->getMediaSource())
        ->cleanUpFileAttachments([]);

    expect(Media::find($removed->getKey()))->toBeNull();
});
