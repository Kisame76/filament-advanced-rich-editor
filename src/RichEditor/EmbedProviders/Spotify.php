<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * A track, an album, a playlist, a podcast episode or show, or an artist on Spotify.
 *
 * The id carries its kind, `track/4uLU…`, because the player address does: one service, six
 * kinds of thing, and an id alone cannot say which.
 */
class Spotify extends EmbedProvider
{
    public function name(): string
    {
        return 'spotify';
    }

    public function hosts(): array
    {
        return ['open.spotify.com'];
    }

    public function patterns(): array
    {
        // A link opened in another language carries `intl-de/` in front.
        return ['^[^/]+/(?:intl-[a-z-]+/)?(?:embed/)?(?<id>(?:track|album|playlist|episode|show|artist)/[A-Za-z0-9]{22})'];
    }

    public function id(): string
    {
        return '(?:track|album|playlist|episode|show|artist)/[A-Za-z0-9]{22}';
    }

    public function src(): string
    {
        return 'https://open.spotify.com/embed/{id}';
    }

    public function share(): string
    {
        return 'https://open.spotify.com/{id}';
    }

    /**
     * The height Spotify's own embed code gives every kind: a strip, not a picture, and the
     * same strip however wide the column is.
     */
    public function height(): int
    {
        return 352;
    }

    public function oembed(): string
    {
        return 'https://open.spotify.com/oembed';
    }
}
