<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Reads the link somebody pasted and works out what to frame.
 *
 * A video link comes in whatever shape the share button produced: a watch URL, a shortened
 * one, a mobile one, a Shorts one, sometimes an embed URL already. None of those except the
 * last can go into an `iframe` - YouTube answers a framed watch page with "refused to
 * connect" - so the link is taken apart and the embed URL is built rather than guessed at.
 *
 * The host is compared exactly, against a list. A substring test is the obvious way to do
 * this and is how the same feature is usually written; it also frames
 * `youtube.com.attacker.test`, which is a domain anybody can register.
 */
class EmbedUrl
{
    /**
     * Host => provider. Every host that may be recognised, spelled out, because what comes
     * out of here decides what ends up in an `iframe`.
     *
     * @var array<string, string>
     */
    public const HOSTS = [
        'youtube.com' => 'youtube',
        'www.youtube.com' => 'youtube',
        'm.youtube.com' => 'youtube',
        'music.youtube.com' => 'youtube',
        'youtube-nocookie.com' => 'youtube',
        'www.youtube-nocookie.com' => 'youtube',
        'youtu.be' => 'youtube',
        'vimeo.com' => 'vimeo',
        'www.vimeo.com' => 'vimeo',
        'player.vimeo.com' => 'vimeo',
    ];

    /**
     * What a video id may look like, per provider. Both are narrower than the id could
     * theoretically be, and neither allows anything that would change the meaning of the
     * URL it is written into.
     *
     * @var array<string, string>
     */
    public const IDS = [
        'youtube' => '/^[A-Za-z0-9_-]{6,20}$/',
        'vimeo' => '/^[0-9]{6,12}$/',
    ];

    /**
     * The provider, the video and the timestamp - or null where the link is one this
     * package has no business framing.
     *
     * @return array{provider: string, id: string, start: int|null}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // A link copied without its scheme is still the link somebody meant.
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $provider = static::HOSTS[$host] ?? null;

        if ($provider === null) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        $id = $provider === 'youtube'
            ? static::youtubeId($host, $parts['path'] ?? '', $query)
            : static::vimeoId($parts['path'] ?? '');

        if ($id === null || preg_match(static::IDS[$provider], $id) !== 1) {
            return null;
        }

        return [
            'provider' => $provider,
            'id' => $id,
            'start' => static::seconds($query['t'] ?? $query['start'] ?? $parts['fragment'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected static function youtubeId(string $host, string $path, array $query): ?string
    {
        // `youtu.be/ID` puts the id where every other form puts a route.
        if ($host === 'youtu.be') {
            return trim($path, '/') ?: null;
        }

        foreach (['embed', 'shorts', 'v', 'live'] as $segment) {
            if (preg_match('~^/'.$segment.'/([^/?\#]+)~', $path, $matches) === 1) {
                return $matches[1];
            }
        }

        $id = $query['v'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    protected static function vimeoId(string $path): ?string
    {
        // `/video/ID` on the player host, a bare `/ID` on the site, and either may carry an
        // unlisted hash after it that is not part of the id.
        return preg_match('~/(?:video/)?([0-9]+)~', $path, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * A timestamp in whatever spelling the share button used, as seconds.
     */
    protected static function seconds(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = ltrim(strtolower(trim($value)), 't=');

        if (ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        // `1m30s`, `2h5m`, `90s` - the shape the share dialog writes.
        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $matches) !== 1) {
            return null;
        }

        $seconds = ((int) ($matches[1] ?? 0)) * 3600
            + ((int) ($matches[2] ?? 0)) * 60
            + ((int) ($matches[3] ?? 0));

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * The URL a browser will actually frame.
     */
    public static function src(string $provider, string $id, ?int $start = null): string
    {
        if ($provider === 'vimeo') {
            // Vimeo reads the start time out of the fragment rather than the query.
            return 'https://player.vimeo.com/video/'.$id.($start ? '#t='.$start.'s' : '');
        }

        // The privacy-preserving host by default: embedding a video should not decide on
        // its own to put a tracking cookie on the reader's machine.
        $host = config('filament-advanced-rich-editor.embed.youtube_nocookie', true)
            ? 'www.youtube-nocookie.com'
            : 'www.youtube.com';

        return 'https://'.$host.'/embed/'.$id.($start ? '?start='.$start : '');
    }
}
