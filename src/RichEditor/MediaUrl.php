<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;

/**
 * What may be pointed at, and which of the two elements it is.
 *
 * The counterpart to `EmbedUrl`, and the opposite kind of answer. An embed stores what the
 * video *is* and rebuilds the address, because the address belongs to somebody else and
 * changes shape. A self-hosted file has no provider to rebuild from: the address is the
 * whole of what is known, so it is stored as it was written and checked instead.
 *
 * Checked twice over, once here and once in the browser, because the node renders into the
 * editor as well as into a saved page - and Filament's sanitiser only sees the second of
 * those. What is refused is a scheme that is not `http` or `https`, which is the whole of
 * the attack: `javascript:` and `data:` in a `src` are what turns a video into a script.
 * A path with no scheme at all - `/storage/clip.mp4` - is the ordinary case and passes.
 */
class MediaUrl
{
    /**
     * The two elements. Also the two tool names, because a person reaching for one of them
     * is reaching for a video or for a sound, not for "media".
     *
     * @var array<int, string>
     */
    public const KINDS = ['video', 'audio'];

    /**
     * How much of the file a browser fetches before anyone presses play. `metadata` is the
     * shipped answer: it costs a few kilobytes, and it is what lets the element draw its
     * own duration and its own shape instead of collapsing to a default box.
     *
     * @var array<int, string>
     */
    public const PRELOADS = ['none', 'metadata', 'auto'];

    /**
     * An address this package is willing to point an element at, or null.
     *
     * Whitespace and control characters are refused outright rather than stripped. They are
     * not part of any address a person meant to write - a space belongs in a URL as `%20` -
     * and they are how a scheme gets hidden from a check that looks at the front of the
     * string: `java\nscript:` is `javascript:` to a browser and something else to a regular
     * expression that has not thought about it.
     */
    public static function src(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return null;
        }

        // A scheme, if there is one, has to be one a browser fetches a file over. No scheme
        // at all is the ordinary case: a path on this server, absolute or relative.
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $value, $matches) === 1
            && ! in_array(Str::lower($matches[1]), ['http', 'https'], true)) {
            return null;
        }

        return $value;
    }

    /**
     * Which element to draw. What was asked for, where that is one of the two; otherwise
     * what the address looks like; otherwise a video, because a video element playing a
     * sound is a black rectangle with working controls and an audio element handed a film
     * is a bar that plays it invisibly.
     */
    public static function kind(mixed $kind, ?string $src = null): string
    {
        if (is_string($kind) && in_array($kind, static::KINDS, strict: true)) {
            return $kind;
        }

        return static::guess($src) ?? 'video';
    }

    /**
     * The kind an address looks like, or null where its ending says nothing.
     */
    public static function guess(?string $src): ?string
    {
        if (! is_string($src) || $src === '') {
            return null;
        }

        // The path alone: a query string carries `?v=...` and `?token=...`, and neither is
        // the file's name. An address with no path at all - `https://cdn.test` - has no name
        // to read, and falling back to the whole string would read the host's own ending as
        // an extension.
        $path = parse_url($src, PHP_URL_PATH);

        return MediaKinds::ofPath(is_string($path) ? $path : null);
    }

    public static function preload(mixed $value): string
    {
        return is_string($value) && in_array($value, static::PRELOADS, strict: true)
            ? $value
            : 'metadata';
    }
}
