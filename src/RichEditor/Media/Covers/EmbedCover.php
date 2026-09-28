<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Throwable;

/**
 * The still a video service publishes for a video.
 *
 * Fetched and kept rather than pointed at. A tile whose `src` is `i.ytimg.com` is a request
 * to Google from every editor that opens the dialog, on every opening - which is exactly the
 * tracking that embedding through the nocookie host exists to avoid. One fetch, one file on
 * this project's own disk, and the admin panel stops talking to anybody.
 *
 * Failure is quiet and remembered by the caller: a service that is down, a video that has
 * been made private, a network with no way out. The tile keeps its badge.
 */
class EmbedCover
{
    /**
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public static function bytes(array $embed, int $timeout): ?string
    {
        $url = match ($embed['provider']) {
            // Always there, no lookup needed, and 480 wide - which is the tile's size rather
            // than the poster's.
            'youtube' => 'https://i.ytimg.com/vi/'.$embed['id'].'/hqdefault.jpg',
            'vimeo' => static::vimeoUrl($embed['id'], $timeout),
            default => null,
        };

        if ($url === null) {
            return null;
        }

        $bytes = static::get($url, $timeout)?->body() ?: null;

        // A service that answers a missing video with an HTML page rather than a 404 - which
        // both of these do, sometimes. Written out as a cover it would be a broken tile.
        return (is_string($bytes) && MediaDimensions::fromString($bytes) !== null) ? $bytes : null;
    }

    protected static function vimeoUrl(string $id, int $timeout): ?string
    {
        // Vimeo's still has no address that can be built from an id, so it has to be asked
        // for. Two requests, which is the other reason the answer is kept.
        $url = static::get('https://vimeo.com/api/oembed.json', $timeout, [
            'url' => 'https://vimeo.com/'.$id,
        ])?->json('thumbnail_url');

        return (is_string($url) && str_starts_with($url, 'https://')) ? $url : null;
    }

    /**
     * @param  array<string, string>  $query
     */
    protected static function get(string $url, int $timeout, array $query = []): ?Response
    {
        try {
            $response = Http::timeout(max(1, $timeout))->get($url, $query);
        } catch (Throwable $exception) {
            // No route out, DNS, a proxy. All of them are "no cover".
            return null;
        }

        return $response->successful() ? $response : null;
    }
}
