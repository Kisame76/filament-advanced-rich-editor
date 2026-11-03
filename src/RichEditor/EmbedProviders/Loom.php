<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * A screen recording on Loom.
 */
class Loom extends EmbedProvider
{
    public function name(): string
    {
        return 'loom';
    }

    public function hosts(): array
    {
        return ['loom.com', 'www.loom.com'];
    }

    public function patterns(): array
    {
        return ['^[^/]+/(?:share|embed)/(?<id>[a-f0-9]{32})'];
    }

    public function id(): string
    {
        return '[a-f0-9]{32}';
    }

    public function src(): string
    {
        return 'https://www.loom.com/embed/{id}';
    }

    public function share(): string
    {
        return 'https://www.loom.com/share/{id}';
    }

    public function oembed(): string
    {
        return 'https://www.loom.com/v1/oembed';
    }
}
