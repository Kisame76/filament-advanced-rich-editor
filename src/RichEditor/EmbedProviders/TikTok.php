<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * A TikTok video, through TikTok's embed player - an `iframe`, unlike the script its share
 * button hands out, which this package will not put on a page.
 *
 * A short `vm.tiktok.com` link is not read: what it points at is only known by following it,
 * which is a request to TikTok from the server for every paste. The address bar of the video
 * has the long form.
 */
class TikTok extends EmbedProvider
{
    public function name(): string
    {
        return 'tiktok';
    }

    public function hosts(): array
    {
        return ['tiktok.com', 'www.tiktok.com', 'm.tiktok.com'];
    }

    public function patterns(): array
    {
        return [
            '^[^/]+/@[^/]+/video/(?<id>[0-9]+)',
            '^[^/]+/(?:player/v1|embed/v2|embed|v)/(?<id>[0-9]+)',
        ];
    }

    public function id(): string
    {
        return '[0-9]{15,25}';
    }

    public function src(): string
    {
        return 'https://www.tiktok.com/player/v1/{id}';
    }

    public function share(): string
    {
        return 'https://www.tiktok.com/player/v1/{id}';
    }

    /**
     * Upright, the way it was filmed.
     */
    public function ratio(): string
    {
        return '9 / 16';
    }
}
