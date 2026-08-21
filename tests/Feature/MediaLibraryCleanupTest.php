<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
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
