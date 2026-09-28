<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Sidecar;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What a tile shows before anyone opens the file.
 *
 * A badge saying `MP4` says nothing; a still says which film it is. The cost of that is a
 * process per file, which is why almost everything here is about the cap and the marker: a
 * library fills in over a few openings, and a file that cannot produce a cover is never
 * asked twice.
 *
 * The suite runs with covers switched off - see `TestCase::defineEnvironment()` - so this is
 * the one file that turns them on.
 */
beforeEach(function (): void {
    Storage::fake('public');

    config()->set('filament-advanced-rich-editor.media_library.covers.enabled', true);

    // Nothing here should ever reach a real binary. A test that shells out is a test whose
    // answer depends on the machine it ran on.
    Process::fake();

    $this->jpeg = "\xFF\xD8\xFF\xE0".str_repeat('x', 40);

    // A tag built the way `Id3CoverTest` builds one, in front of a real sound.
    //
    // The real bytes matter on the media library side and nowhere else: a row's mime type
    // is sniffed off its content, and a bare tag with no audio behind it is an
    // `application/octet-stream` - which the pool is right to leave out of a listing. The
    // disk source reads the extension and would never have noticed.
    $syncsafe = static fn (int $size): string => chr(($size >> 21) & 0x7F)
        .chr(($size >> 14) & 0x7F).chr(($size >> 7) & 0x7F).chr($size & 0x7F);

    $body = "\x00".'image/jpeg'."\x00\x03\x00".$this->jpeg;
    $frame = 'APIC'.pack('N', strlen($body))."\0\0".$body;

    // A fifth of a second of a sine wave, written by ffmpeg and checked in beside the film.
    $this->silence = (string) file_get_contents(__DIR__.'/../Fixtures/media/tiny.mp3');

    $this->mp3 = 'ID3'."\x03\x00\x00".$syncsafe(strlen($frame)).$frame.$this->silence;

    $this->source = fn (): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: 'library',
        visibility: 'public',
    );

    $this->covered = fn (string $name): ?string => collect(($this->source)()->page()['items'])
        ->firstWhere('name', $name)['thumbnail'] ?? null;
});

it('ships with covers switched on', function (): void {
    // The suite runs with them off for speed; what a project gets is the other way round.
    expect(config('filament-advanced-rich-editor.media_library.covers.enabled'))->toBeTrue()
        ->and(require __DIR__.'/../../config/filament-advanced-rich-editor.php')
        ->toHaveKey('media_library.covers.enabled', true);
});

it('makes a cover out of a sound the first time it is listed', function (): void {
    Storage::disk('public')->put('library/talk.mp3', $this->mp3);

    expect(($this->covered)('talk.mp3'))->toContain('talk.mp3.cover.jpg');

    Storage::disk('public')->assertExists('library/talk.mp3.cover.jpg');
});

it('uses the cover it already made rather than making it again', function (): void {
    Storage::disk('public')->put('library/talk.mp3', $this->mp3);

    ($this->covered)('talk.mp3');

    // Overwritten, so a second generation would be visible: the real bytes would come back.
    Storage::disk('public')->put('library/talk.mp3.cover.jpg', 'kept');

    ($this->covered)('talk.mp3');

    expect(Storage::disk('public')->get('library/talk.mp3.cover.jpg'))->toBe('kept');
});

it('remembers a sound it could not get a picture out of', function (): void {
    Storage::disk('public')->put('library/silent.mp3', 'no tag here at all');

    expect(($this->covered)('silent.mp3'))->toBeNull()
        ->and(Sidecar::read(Storage::disk('public'), 'library/silent.mp3')[CoverGenerator::ATTEMPTED_KEY])
        ->toBeTrue();
});

it('does not try a second time once it has failed', function (): void {
    Storage::disk('public')->put('library/talk.mp4', 'not really a film');

    ($this->covered)('talk.mp4');
    ($this->covered)('talk.mp4');

    // The marker is what stops it. Without it a library of forty undecodable films would be
    // forty processes on every single opening of the dialog.
    Process::assertRanTimes(fn (): bool => true, 1);
});

it('makes at most a few covers in one listing', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.covers.per_page', 2);

    foreach (['one', 'two', 'three', 'four'] as $name) {
        Storage::disk('public')->put("library/{$name}.mp3", $this->mp3);
    }

    $covers = array_filter(array_column(($this->source)()->page()['items'], 'thumbnail'));

    expect($covers)->toHaveCount(2);

    // And the rest fill in on the next opening rather than never.
    $covers = array_filter(array_column(($this->source)()->page()['items'], 'thumbnail'));

    expect($covers)->toHaveCount(4);
});

it('makes none at all where the project switched them off', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.covers.enabled', false);

    Storage::disk('public')->put('library/talk.mp3', $this->mp3);

    expect(($this->covered)('talk.mp3'))->toBeNull()
        // Nothing written, marker included: switching it on later must start from scratch.
        ->and(Storage::disk('public')->exists('library/talk.mp3.json'))->toBeFalse();
});

it('leaves a picture to be its own thumbnail', function (): void {
    Storage::disk('public')->put('library/sunset.png', 'x');

    expect(($this->covered)('sunset.png'))->toContain('sunset.png')
        ->and(Storage::disk('public')->exists('library/sunset.png.cover.jpg'))->toBeFalse();
});

it('starts no process for a single lookup, only for a listing', function (): void {
    // The panel asks about one file at a time, and a details request that shelled out would
    // be a process per click.
    Storage::disk('public')->put('library/talk.mp4', 'not really a film');

    ($this->source)()->find('library/talk.mp4');
    ($this->source)()->details('library/talk.mp4');

    Process::assertNothingRan();
});

it('writes a media row its cover where Spatie keeps its conversions', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($this->mp3)
        ->usingFileName('talk.mp3')
        ->toMediaCollection('rich-editor');

    $item = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    )->page()['items'][0];

    expect($item['thumbnail'])->toContain('talk-arte-cover.jpg')
        // Marked generated, which is what keeps anything from falling back to the original -
        // and the original of a sound is not a picture.
        ->and($media->refresh()->hasGeneratedConversion(CoverGenerator::CONVERSION))->toBeTrue();
});

it('remembers a media row it could not get a picture out of', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($this->silence)
        ->usingFileName('silent.mp3')
        ->toMediaCollection('rich-editor');

    $item = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    )->page()['items'][0];

    expect($item['thumbnail'])->toBeNull()
        ->and($media->refresh()->getCustomProperty(CoverGenerator::ATTEMPTED_PROPERTY))->toBeTrue();
});
