<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaLibraryItem;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What a `data-id` in the content is allowed to become.
 *
 * The id travels in the document, and a document is client-submitted the whole time somebody
 * is typing into it - so every lookup has to be scoped to something. The scope is the pool the
 * browser lists from, which is the same object, so widening one widens the other and they
 * cannot drift into a gap.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    // Real bytes: the pool lists images only, so a text file called `.png` would make the
    // "the browser did offer it" assertion pass for the wrong reason.
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->stranger = MediaLibraryItem::create(['title' => 'Someone else', 'content' => '']);

    // A picture that belongs to a model this field has nothing to do with.
    $this->secret = $this->stranger
        ->addMediaFromString($this->png)
        ->usingFileName('secret.png')
        ->toMediaCollection('private-collection');
});

it('refuses a uuid the record does not own', function (): void {
    $provider = SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn (): MediaPost => MediaPost::create(['title' => 'Mine', 'content' => '']))
        ->ownerUsing(fn (): string => 'content');

    expect($provider->getFileAttachmentUrl($this->secret->uuid))->toBeNull();
});

it('refuses it on a create page too, where there is no record to scope by', function (): void {
    // A field on a create page has no record yet, and the absence of one used to fall through
    // to an unscoped lookup - which is right for a renderer reading a saved document, and wrong
    // here, because the document is being typed at this very moment. Pasting a foreign uuid
    // into a new article would resolve it.
    $provider = SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn () => null)
        ->ownerUsing(fn (): string => 'content');

    expect($provider->getFileAttachmentUrl($this->secret->uuid))->toBeNull();
});

it('still resolves a create page picture the browser did offer', function (): void {
    // The scope on a create page is the pool, so anything the browser listed stays pickable
    // before the record exists - which is the whole point of the dialog working there.
    $offered = $this->stranger
        ->addMediaFromString($this->png)
        ->usingFileName('offered.png')
        ->toMediaCollection('rich-editor');

    $provider = SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn () => null)
        ->ownerUsing(fn (): string => 'content')
        ->source(SpatieMediaSource::make(collection: 'rich-editor'));

    expect($provider->getFileAttachmentUrl($offered->uuid))->not->toBeNull();
});

it('leaves a renderer that was handed the provider directly unscoped', function (): void {
    // No field, so no record and no pool to scope against - and nothing to scope for either:
    // the content a renderer reads came out of the database rather than off a keyboard.
    $provider = SpatieMediaLibraryFileAttachmentProvider::make('rich-editor');

    expect($provider->getFileAttachmentUrl($this->secret->uuid))->not->toBeNull();
});
