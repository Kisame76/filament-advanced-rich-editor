<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

/**
 * YouTube, in every shape its share button produces: a watch link, youtu.be, Shorts, a live
 * link, an embed link already.
 */
class YouTube extends EmbedProvider
{
    public function name(): string
    {
        return 'youtube';
    }

    public function hosts(): array
    {
        return [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'music.youtube.com',
            'youtube-nocookie.com',
            'www.youtube-nocookie.com',
            'youtu.be',
        ];
    }

    public function patterns(): array
    {
        return [
            // `youtu.be/ID` puts the id where every other form puts a route.
            '^youtu\.be/(?<id>[^/?#]+)',
            '^[^/]+/(?:embed|shorts|v|live)/(?<id>[^/?#]+)',
            '^[^/]+/[^?]*\?(?:[^#]*&)?v=(?<id>[^&#]+)',
        ];
    }

    public function id(): string
    {
        return '[A-Za-z0-9_-]{6,20}';
    }

    /**
     * The privacy-preserving host by default: embedding a video should not decide on its own
     * to put a tracking cookie on the reader's machine.
     */
    public function src(): string
    {
        $host = config('filament-advanced-rich-editor.embed.youtube_nocookie', true)
            ? 'www.youtube-nocookie.com'
            : 'www.youtube.com';

        return 'https://'.$host.'/embed/{id}';
    }

    public function start(): string
    {
        return '?start={start}';
    }

    public function share(): string
    {
        return 'https://www.youtube.com/watch?v={id}';
    }

    public function shareStart(): string
    {
        return '&t={start}';
    }

    public function frameHosts(): array
    {
        return ['youtube-nocookie.com', 'youtube.com'];
    }

    /**
     * Always there, no lookup needed, and 480 wide - which is the tile's size rather than
     * the poster's.
     */
    public function cover(): string
    {
        return 'https://i.ytimg.com/vi/{id}/hqdefault.jpg';
    }
}
