<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders\EmbedProviders;

/**
 * Reads the link somebody pasted and works out what to frame.
 *
 * A link comes in whatever shape the share button produced: a watch URL, a shortened one, a
 * mobile one, an embed URL already, or the whole embed code a share dialog hands out. None of
 * those except the embed URL can go into an `iframe` - YouTube answers a framed watch page with
 * "refused to connect" - so the link is taken apart and the embed URL is built rather than
 * guessed at.
 *
 * What a service's links look like is the service's own description, in `EmbedProviders`;
 * this is the one reading of it on the PHP side, and `embed.js` is the same reading in the
 * browser.
 */
class EmbedUrl
{
    /**
     * The service, the id and the timestamp - or null where the link is one this package has
     * no business framing.
     *
     * @return array{provider: string, id: string, start: int|null}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        // The whole embed code a share dialog hands out, for the services whose share button
        // offers nothing else - a map, a pen. Its address is what is read.
        if (preg_match('~<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1~is', $url, $matches) === 1) {
            $url = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5));
        }

        if ($url === '') {
            return null;
        }

        // A link copied without its scheme is still the link somebody meant.
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $provider = EmbedProviders::forHost($host);

        if ($provider === null) {
            return null;
        }

        $id = $provider->read($host.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : ''));

        if ($id === null) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        return [
            'provider' => $provider->name(),
            'id' => $id,
            'start' => ($provider->start() !== null)
                ? static::seconds($query['t'] ?? $query['start'] ?? $parts['fragment'] ?? null)
                : null,
        ];
    }

    /**
     * Whether an id is one this service could have - the check every stored embed goes
     * through before anything is built from it.
     */
    public static function accepts(mixed $provider, mixed $id): bool
    {
        return EmbedProviders::get($provider)?->accepts($id) ?? false;
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
     * The URL a browser will actually frame, or an empty string for a service that is not on
     * the list.
     */
    public static function src(string $provider, string $id, ?int $start = null): string
    {
        $service = EmbedProviders::get($provider);

        return $service ? $service->fill($service->src(), $id, $start, $service->start()) : '';
    }

    /**
     * The address a person opens - not the frame: nobody wants `player.vimeo.com/video/123`
     * in a chat message.
     */
    public static function share(string $provider, string $id, ?int $start = null): string
    {
        $service = EmbedProviders::get($provider);

        return $service ? $service->fill($service->share(), $id, $start, $service->shareStart()) : '';
    }
}
