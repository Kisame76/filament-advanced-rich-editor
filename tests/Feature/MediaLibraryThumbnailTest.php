<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\ThumbnailPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What the grid draws.
 *
 * A tile is about 120 pixels wide, so a grid that loads full-size photographs is a dialog that
 * takes seconds to open for no visible gain. The conversion it draws from is deliberately not
 * the one an inserted picture uses - and it has to fall back rather than break, because a
 * conversion the model never declared, or one whose queue job has not run yet, is a URL to a
 * file that is not there.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
});

it('draws the thumbnail conversion where the model has one', function (): void {
    $post = ThumbnailPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString($this->png)->usingFileName('hafen.png')->toMediaCollection('rich-editor');

    $item = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): ThumbnailPost => $post,
        thumbnailConversion: 'arte-thumb',
    )->page()['items'][0];

    expect($item['thumbnail'])->toContain('arte-thumb')
        // The picture that gets inserted is the original, not the 64px tile.
        ->and($item['url'])->not->toContain('arte-thumb')
        ->and($item['url'])->toContain('hafen.png');
});

it('falls back to the original where the conversion was never declared', function (): void {
    // The common case on day one: a library full of pictures and a model that has not been
    // given a conversion yet. A grid of broken images would be a worse answer than a slow one.
    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString($this->png)->usingFileName('hafen.png')->toMediaCollection('rich-editor');

    $item = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
        thumbnailConversion: 'arte-thumb',
    )->page()['items'][0];

    expect($item['thumbnail'])->toBe($item['url'])
        ->and($item['thumbnail'])->toContain('hafen.png');
});

it('falls back while the conversion has not been generated yet', function (): void {
    // A fresh upload with a queued conversion behind it. The row knows the conversion exists
    // and knows it has not been made.
    $post = ThumbnailPost::create(['title' => 'Post', 'content' => '']);

    $media = $post->addMediaFromString($this->png)->usingFileName('hafen.png')->toMediaCollection('rich-editor');

    // Cleared in memory rather than saved: saving media re-runs the conversions, which would
    // put the flag straight back and leave the test asserting nothing.
    $media->generated_conversions = [];

    expect($media->hasGeneratedConversion('arte-thumb'))->toBeFalse()
        ->and(MediaUrl::forWithFallback($media, 'arte-thumb', 'public'))->not->toContain('arte-thumb')
        // And still a picture, rather than nothing at all.
        ->and(MediaUrl::forWithFallback($media, 'arte-thumb', 'public'))->toContain('hafen.png');
});

it('draws the original when no thumbnail conversion was asked for', function (): void {
    $post = ThumbnailPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString($this->png)->usingFileName('hafen.png')->toMediaCollection('rich-editor');

    $item = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): ThumbnailPost => $post,
    )->page()['items'][0];

    expect($item['thumbnail'])->toBe($item['url']);
});

it('takes the conversion from the field, then from the config', function (): void {
    expect(editor()->getMediaLibraryThumbnail())->toBeNull();

    config()->set('filament-advanced-rich-editor.media_library.thumbnail', 'project-thumb');

    expect(editor()->getMediaLibraryThumbnail())->toBe('project-thumb')
        ->and(editor()->mediaLibraryThumbnail('field-thumb')->getMediaLibraryThumbnail())->toBe('field-thumb');
});
