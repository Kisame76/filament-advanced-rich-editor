<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\EmbedAction;
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

    public static function fileName(string $provider, string $id): string
    {
        return $provider.'-'.$id.'.'.self::SUFFIX;
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
        if (! isset(EmbedUrl::IDS[$provider]) || preg_match(EmbedUrl::IDS[$provider], $id) !== 1) {
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
            : Embed::DEFAULT_RATIO;

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
        $start = $embed['start'];

        return match ($embed['provider']) {
            'vimeo' => 'https://vimeo.com/'.$embed['id'].($start ? '#t='.$start.'s' : ''),
            default => 'https://www.youtube.com/watch?v='.$embed['id'].($start ? '&t='.$start : ''),
        };
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

        $provider = (string) __('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.'.$embed['provider']);

        return $provider.' · '.$embed['id'];
    }
}
