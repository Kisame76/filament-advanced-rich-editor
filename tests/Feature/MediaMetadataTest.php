<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What a library knows about a file besides the file: the one line of text a screen reader
 * reads instead of it. It belongs to the medium rather than to the insert, which is what
 * lets the same picture carry the same alt text into three documents.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->disk = fn (): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: 'library',
        visibility: 'public',
    );
});

it('answers with nothing at all for a file nobody has described', function (): void {
    Storage::disk('public')->put('library/sunset.png', $this->png);

    expect(($this->disk)()->metadata('library/sunset.png'))
        ->toBe(['alt' => null, 'title' => null]);
});

it('writes a sidecar beside the file and reads it back', function (): void {
    Storage::disk('public')->put('library/sunset.png', $this->png);

    expect(($this->disk)()->saveMetadata('library/sunset.png', ['alt' => 'The harbour at dusk']))
        ->toBeTrue();

    Storage::disk('public')->assertExists('library/sunset.png.json');

    expect(($this->disk)()->metadata('library/sunset.png'))
        ->toBe(['alt' => 'The harbour at dusk', 'title' => null]);
});

it('leaves a key it was not given alone', function (): void {
    // The panel sends one field at a time, and a title written yesterday must not be erased
    // by an alt text written today.
    Storage::disk('public')->put('library/talk.mp4', 'x');

    $source = ($this->disk)();
    $source->saveMetadata('library/talk.mp4', ['title' => 'The talk']);
    $source->saveMetadata('library/talk.mp4', ['alt' => 'ignored on a film']);

    expect($source->metadata('library/talk.mp4')['title'])->toBe('The talk');
});

it('clears a value that is emptied rather than keeping the old one', function (): void {
    Storage::disk('public')->put('library/sunset.png', $this->png);

    $source = ($this->disk)();
    $source->saveMetadata('library/sunset.png', ['alt' => 'Something']);
    $source->saveMetadata('library/sunset.png', ['alt' => '']);

    expect($source->metadata('library/sunset.png')['alt'])->toBeNull();
});

it('refuses a path that is not in the pool', function (): void {
    // The id comes from the browser, and `normalise()` is the one gate every path passes.
    expect(($this->disk)()->saveMetadata('../../.env', ['alt' => 'x']))->toBeFalse()
        ->and(($this->disk)()->metadata('elsewhere/other.png'))
        ->toBe(['alt' => null, 'title' => null]);
});

it('survives a sidecar somebody hand-edited into nonsense', function (): void {
    Storage::disk('public')->put('library/sunset.png', $this->png);
    Storage::disk('public')->put('library/sunset.png.json', 'not json at all');

    expect(($this->disk)()->metadata('library/sunset.png'))
        ->toBe(['alt' => null, 'title' => null]);
});

it('keeps a media row description in its custom properties', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($this->png)
        ->usingFileName('sunset.png')
        ->toMediaCollection('rich-editor');

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    );

    expect($source->saveMetadata((string) $media->uuid, ['alt' => 'The harbour']))->toBeTrue()
        ->and($source->metadata((string) $media->uuid))
        ->toBe(['alt' => 'The harbour', 'title' => null])
        ->and($media->refresh()->getCustomProperty('arte_alt'))->toBe('The harbour');
});

it('refuses a media id outside the pool', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    );

    expect($source->saveMetadata('00000000-0000-0000-0000-000000000000', ['alt' => 'x']))
        ->toBeFalse()
        ->and($source->metadata('not-a-uuid'))->toBe(['alt' => null, 'title' => null]);
});
