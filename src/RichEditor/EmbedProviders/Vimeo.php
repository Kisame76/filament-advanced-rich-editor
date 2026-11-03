<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * Vimeo: `/ID` on the site, `/video/ID` on the player host, either with an unlisted hash
 * after it that is not part of the id.
 */
class Vimeo extends EmbedProvider
{
    public function name(): string
    {
        return 'vimeo';
    }

    public function hosts(): array
    {
        return ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];
    }

    public function patterns(): array
    {
        return ['/(?:video/)?(?<id>[0-9]+)'];
    }

    public function id(): string
    {
        return '[0-9]{6,12}';
    }

    public function src(): string
    {
        return 'https://player.vimeo.com/video/{id}';
    }

    /**
     * Vimeo reads the start time out of the fragment rather than the query.
     */
    public function start(): string
    {
        return '#t={start}s';
    }

    public function share(): string
    {
        return 'https://vimeo.com/{id}';
    }

    public function frameHosts(): array
    {
        return ['vimeo.com'];
    }

    /**
     * Vimeo's still has no address that can be built from an id, so it has to be asked for.
     */
    public function oembed(): string
    {
        return 'https://vimeo.com/api/oembed.json';
    }
}
