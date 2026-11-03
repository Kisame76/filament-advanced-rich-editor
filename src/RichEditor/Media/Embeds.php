<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\EmbedAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders\EmbedProviders;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;

/**
 * A video somebody else hosts, as a thing the library can hold.
 *
 * What is stored is what the embed *is* - a provider, an id, a timestamp - which is exactly
 * what the node stores, and for the same reason: the address belongs to YouTube and changes
 * shape, and `youtube-nocookie` has to stay a setting rather than a decision frozen into
 * every record ever saved.
 *
 * Everything read back out of storage goes through `describes()`. The file is on this
 * project's own disk, which is not the same as it being trustworthy: the id ends up inside
 * an iframe address and the ratio inside a style attribute, and a library directory is
 * somewhere a deployment script, a sync tool or a person with an editor can reach.
 */
class Embeds
{
    /**
     * What the file is called. `.embed.` is also what `DiskMediaSource::read()` looks for
     * before `accepts()` gets a chance to refuse it as a JSON document nothing can draw.
     */
    public const SUFFIX = 'embed.json';

    /**
     * One name per embed, so adding the same one twice corrects the entry. An id that is not
     * a plain word is hashed: a Spotify id carries a slash, which would be a folder, and a map's
     * is a few hundred characters, which no file system takes as a name.
     */
    public static function fileName(string $provider, string $id): string
    {
        $name = (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1) ? $id : hash('xxh128', $id);

        return $provider.'-'.$name.'.'.self::SUFFIX;
    }

    /**
     * The five fields, normalised - or null where this is not an embed this package will
     * put in a frame.
     *
     * @param  array<string, mixed>  $embed
     * @return array{provider: string, id: string, start: int|null, title: string|null, ratio: string}|null
     */
    public static function describes(array $embed): ?array
    {
        $provider = is_string($embed['provider'] ?? null) ? $embed['provider'] : '';
        $id = is_string($embed['id'] ?? null) ? $embed['id'] : '';

        // The same two checks the node makes on its way out, made again on the way in: a
        // provider this package has no business framing, and an id that is not an id.
        if (! EmbedUrl::accepts($provider, $id)) {
            return null;
        }

        $start = $embed['start'] ?? null;
        $start = (is_int($start) || (is_string($start) && ctype_digit($start))) && ((int) $start) > 0
            ? (int) $start
            : null;

        $title = is_string($embed['title'] ?? null) && filled(trim($embed['title']))
            ? trim($embed['title'])
            : null;

        // A CSS value written into a `style` attribute, so it is chosen from the list rather
        // than checked - there is no correct escaping for "this was meant to be a ratio".
        // The same list the dialog offers, so the two cannot drift.
        $ratio = is_string($embed['ratio'] ?? null) && array_key_exists($embed['ratio'], EmbedAction::RATIOS)
            ? $embed['ratio']
            : (EmbedProviders::get($provider)?->ratio() ?? Embed::DEFAULT_RATIO);

        return [
            'provider' => $provider,
            'id' => $id,
            'start' => $start,
            'title' => $title,
            'ratio' => $ratio,
        ];
    }

    /**
     * @return array{provider: string, id: string, start: int|null, title: string|null, ratio: string}|null
     */
    public static function read(?string $json): ?array
    {
        if (! is_string($json) || blank($json)) {
            return null;
        }

        $decoded = json_decode($json, associative: true);

        return is_array($decoded) ? static::describes($decoded) : null;
    }

    /**
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public static function encode(array $embed): string
    {
        return (string) json_encode($embed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The address a person recognises, which is the one Copy link should hand over.
     *
     * Not the frame address: nobody wants `player.vimeo.com/video/123` in a chat message,
     * and pasting a watch link back into this dialog is how the entry was made.
     *
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public static function link(array $embed): string
    {
        return EmbedUrl::share($embed['provider'], $embed['id'], $embed['start']);
    }

    /**
     * What the tile is called. Its title where somebody gave it one, and otherwise what it
     * is - a bare id under a tile is not a name anybody recognises.
     *
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public static function name(array $embed): string
    {
        if (filled($embed['title'])) {
            return (string) $embed['title'];
        }

        $provider = EmbedProviders::get($embed['provider'])?->label() ?? $embed['provider'];

        // Cut where an id is long enough to be noise: a map's runs to hundreds of characters.
        return $provider.' · '.Str::limit($embed['id'], 24);
    }
}
