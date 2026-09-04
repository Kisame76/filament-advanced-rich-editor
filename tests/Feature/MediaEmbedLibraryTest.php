<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A video somebody else hosts, in the library beside the ones this project does.
 *
 * The same video used in three documents used to be three trips to YouTube and three pastes.
 * Stored as what it *is* - a provider, an id, a timestamp - it is picked from the grid like
 * anything else, and what gets inserted is byte-for-byte what the embed dialog writes.
 */
beforeEach(function (): void {
    Storage::fake('public');

    // Nothing here fetches a cover, and a test that reached the network would be a test
    // whose answer depends on YouTube being up.
    Http::preventStrayRequests();

    $this->embed = [
        'provider' => 'youtube',
        'id' => 'dQw4w9WgXcQ',
        'start' => 90,
        'title' => 'The talk',
        'ratio' => '16 / 9',
    ];

    $this->put = function (array $embed): string {
        $path = 'library/'.Embeds::fileName($embed['provider'], $embed['id']);

        Storage::disk('public')->put($path, Embeds::encode($embed));

        return $path;
    };

    $this->source = fn (): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: 'library',
        visibility: 'public',
    );
});

it('keeps only what an embed is, and refuses the rest', function (): void {
    expect(Embeds::describes($this->embed))->toBe($this->embed)
        // The id goes into an iframe address, so it is checked against the provider's own
        // shape rather than trusted because it came out of a file on our own disk.
        ->and(Embeds::describes([...$this->embed, 'id' => '"><script>']))->toBeNull()
        ->and(Embeds::describes([...$this->embed, 'provider' => 'tiktok']))->toBeNull()
        ->and(Embeds::describes(['provider' => 'youtube']))->toBeNull()
        // A ratio is a CSS value written into a style attribute.
        ->and(Embeds::describes([...$this->embed, 'ratio' => 'red; background: url(x)'])['ratio'])
        ->toBe('16 / 9')
        ->and(Embeds::describes([...$this->embed, 'start' => 'soon'])['start'])->toBeNull();
});

it('names the file after the video it holds', function (): void {
    expect(Embeds::fileName('youtube', 'dQw4w9WgXcQ'))->toBe('youtube-dQw4w9WgXcQ.embed.json');
});

it('lists an embed beside the files', function (): void {
    ($this->put)($this->embed);
    Storage::disk('public')->put('library/sunset.png', 'x');

    $page = ($this->source)()->page();

    $names = array_column($page['items'], 'name');

    expect($names)->toContain('The talk', 'sunset.png')
        ->and($page['kinds'])->toBe(['image', 'embed']);
});

it('carries the embed itself on the row, so nothing has to parse it again', function (): void {
    ($this->put)($this->embed);

    $item = ($this->source)()->page()['items'][0];

    expect($item['kind'])->toBe('embed')
        ->and($item['embed'])->toBe($this->embed)
        ->and($item['mime'])->toBe('')
        // Two addresses, and they are different things: the one a person recognises and
        // copies, and the one a browser will put in a frame.
        ->and($item['url'])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90')
        ->and($item['frame'])->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($item['frame'])->toContain('start=90');
});

it('gives a Vimeo entry the addresses Vimeo uses', function (): void {
    ($this->put)([...$this->embed, 'provider' => 'vimeo', 'id' => '123456']);

    $item = ($this->source)()->page()['items'][0];

    expect($item['url'])->toBe('https://vimeo.com/123456#t=90s')
        ->and($item['frame'])->toBe('https://player.vimeo.com/video/123456#t=90s');
});

it('calls an embed with no title by what it is', function (): void {
    ($this->put)([...$this->embed, 'title' => null]);

    expect(($this->source)()->page()['items'][0]['name'])->toBe('YouTube · dQw4w9WgXcQ');
});

it('narrows to the embeds when that tab is chosen', function (): void {
    ($this->put)($this->embed);
    Storage::disk('public')->put('library/sunset.png', 'x');

    expect(array_column(($this->source)()->page(filters: ['kind' => 'embed'])['items'], 'kind'))
        ->toBe(['embed'])
        ->and(array_column(($this->source)()->page(filters: ['kind' => 'image'])['items'], 'kind'))
        ->toBe(['image']);
});

it('offers no mime type for an embed in the type filter', function (): void {
    // The filter beside the tabs lists mime types, and an embed has none. `application/json`
    // in that list would be a filter that shows the embeds and calls them a document.
    ($this->put)($this->embed);
    Storage::disk('public')->put('library/sunset.png', 'x');

    expect(($this->source)()->page()['types'])->toBe(['image/png']);
});

it('skips an entry somebody hand-edited into nonsense', function (): void {
    Storage::disk('public')->put('library/youtube-broken.embed.json', 'not json');
    Storage::disk('public')->put('library/youtube-evil.embed.json', '{"provider":"evil","id":"x"}');

    expect(($this->source)()->page()['items'])->toBe([]);
});

it('resolves an embed by its path, and nothing else by it', function (): void {
    $path = ($this->put)($this->embed);

    expect(($this->source)()->has($path))->toBeTrue()
        ->and(($this->source)()->find($path)['embed'])->toBe($this->embed)
        ->and(($this->source)()->find('library/youtube-nothing.embed.json'))->toBeNull();
});

it('lists a media row that holds an embed', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString(Embeds::encode($this->embed))
        ->usingFileName(Embeds::fileName('youtube', 'dQw4w9WgXcQ'))
        ->usingName('The talk')
        ->withCustomProperties([
            SpatieMediaSource::EMBED_PROPERTY => true,
            SpatieMediaSource::EMBED_DATA_PROPERTY => $this->embed,
        ])
        ->toMediaCollection('rich-editor');

    $page = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    )->page();

    expect($page['items'][0]['kind'])->toBe('embed')
        ->and($page['items'][0]['embed'])->toBe($this->embed)
        ->and($page['kinds'])->toBe(['embed'])
        // A row whose mime type is `application/json` must not reach the type filter either.
        ->and($page['types'])->toBe([]);
});

it('keeps an embed row out of a library narrowed to pictures', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString(Embeds::encode($this->embed))
        ->usingFileName(Embeds::fileName('youtube', 'dQw4w9WgXcQ'))
        ->withCustomProperties([
            SpatieMediaSource::EMBED_PROPERTY => true,
            SpatieMediaSource::EMBED_DATA_PROPERTY => $this->embed,
        ])
        ->toMediaCollection('rich-editor');

    // An accepted-types list is about files. It has nothing to say about an embed, and
    // narrowing one away with it would hide the Embeds tab on every field that names its
    // picture formats.
    $page = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
        acceptedMimeTypes: ['image/png'],
    )->page();

    expect($page['items'])->toHaveCount(1);
});

it('narrows a media library to its embeds, and away from them', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $post->addMediaFromString($png)->usingFileName('sunset.png')->toMediaCollection('rich-editor');
    $post->addMediaFromString(Embeds::encode($this->embed))
        ->usingFileName(Embeds::fileName('youtube', 'dQw4w9WgXcQ'))
        ->withCustomProperties([
            SpatieMediaSource::EMBED_PROPERTY => true,
            SpatieMediaSource::EMBED_DATA_PROPERTY => $this->embed,
        ])
        ->toMediaCollection('rich-editor');

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    );

    expect(array_column($source->page(filters: ['kind' => 'embed'])['items'], 'kind'))->toBe(['embed'])
        ->and(array_column($source->page(filters: ['kind' => 'image'])['items'], 'kind'))->toBe(['image'])
        ->and($source->page()['kinds'])->toBe(['image', 'embed']);
});
