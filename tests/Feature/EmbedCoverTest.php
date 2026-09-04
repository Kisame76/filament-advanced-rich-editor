<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\EmbedCover;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;

/**
 * The still YouTube and Vimeo publish for a video, fetched once and kept.
 *
 * Fetched rather than hot-linked: a tile pointing at `i.ytimg.com` is a request to Google
 * from every editor that opens the dialog, which is the tracking the nocookie host exists to
 * avoid.
 */
beforeEach(function (): void {
    Storage::fake('public');

    config()->set('filament-advanced-rich-editor.media_library.covers.enabled', true);

    // A real one-pixel PNG, because the fetcher refuses an answer that is not a picture -
    // which is how a service answering with an HTML error page is caught.
    $this->picture = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->embed = [
        'provider' => 'youtube',
        'id' => 'dQw4w9WgXcQ',
        'start' => null,
        'title' => 'The talk',
        'ratio' => '16 / 9',
    ];

    $this->source = fn (): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: 'library',
        visibility: 'public',
    );

    $this->put = function (array $embed): void {
        Storage::disk('public')->put(
            'library/'.Embeds::fileName($embed['provider'], $embed['id']),
            Embeds::encode($embed),
        );
    };
});

it('fetches the still YouTube publishes', function (): void {
    Http::fake(['i.ytimg.com/*' => Http::response($this->picture, 200)]);

    expect(EmbedCover::bytes($this->embed, 5))->toBe($this->picture);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'dQw4w9WgXcQ'));
});

it('asks Vimeo where its still is before fetching it', function (): void {
    // Vimeo has no address that can be built from an id, so its oEmbed endpoint is asked
    // first - which is two requests, and the reason the answer is kept.
    Http::fake([
        'vimeo.com/api/oembed.json*' => Http::response(['thumbnail_url' => 'https://i.vimeocdn.com/x.jpg']),
        'i.vimeocdn.com/*' => Http::response($this->picture, 200),
    ]);

    expect(EmbedCover::bytes([...$this->embed, 'provider' => 'vimeo', 'id' => '123456'], 5))
        ->toBe($this->picture);
});

it('says nothing when the service is unreachable or answers with nothing', function (): void {
    Http::fake(['*' => Http::response('', 404)]);

    expect(EmbedCover::bytes($this->embed, 5))->toBeNull();

    Http::fake(['*' => fn () => throw new ConnectionException('down')]);

    expect(EmbedCover::bytes($this->embed, 5))->toBeNull();
});

it('refuses an answer that is not a picture', function (): void {
    Http::fake(['*' => Http::response('<html>not found</html>', 200)]);

    expect(EmbedCover::bytes($this->embed, 5))->toBeNull();
});

it('keeps the still beside the entry and does not fetch it twice', function (): void {
    Http::fake(['*' => Http::response($this->picture, 200)]);

    ($this->put)($this->embed);

    $first = ($this->source)()->page()['items'][0]['thumbnail'];
    ($this->source)()->page();

    expect($first)->toContain('.cover.jpg');

    Http::assertSentCount(1);
});

it('remembers an embed whose still could not be fetched', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    ($this->put)($this->embed);

    ($this->source)()->page();
    ($this->source)()->page();

    expect(($this->source)()->page()['items'][0]['thumbnail'])->toBeNull();

    Http::assertSentCount(1);
});
