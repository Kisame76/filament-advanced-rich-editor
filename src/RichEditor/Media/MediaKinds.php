<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Str;

/**
 * The families of file this package knows, and the one place that decides which is which.
 *
 * Before this there were four answers to "is this a picture" in four files - an extension
 * map on the disk source, a `like 'image/%'` in the Spatie query, a `str_starts_with` on a
 * fresh upload, and an `<img>` tag in the grid - and opening the browser to anything else
 * meant finding all four. They are all this now.
 *
 * A family is deliberately coarser than a mime type. What the browser needs to know is
 * which tab a file belongs under and which element can draw it, and that is a question
 * about `image` / `video` / `audio` rather than about `image/avif` versus `image/webp`.
 * The exact mime is still carried on every row for the finer filter that sits beside the
 * tabs.
 *
 * Read off the name rather than off the file, which is why this is a table of extensions.
 * Asking a disk for a mime type is a request per item, and on a remote disk that turns one
 * page of a grid into forty round trips.
 */
class MediaKinds
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const AUDIO = 'audio';

    /**
     * Not a family of file at all: a video somebody else hosts, stored as what it is rather
     * than as bytes. It is here because the browser lists it, tabs it and filters by it -
     * everywhere a family is a label. It is deliberately NOT in `TYPES`, and `families()`
     * exists so that no mime filter can ever be built from it: `LIKE 'embed/%'` matches
     * nothing, and a query narrowed by it would read as an empty library.
     */
    public const EMBED = 'embed';

    /**
     * Extension to mime, by family, in the order the tabs are drawn.
     *
     * Only formats a browser can actually draw or play. A `.mkv` is a video and no browser
     * plays one, so listing it would put a file in the grid that inserts a player nobody
     * can start - which is worse than not showing it, because the failure arrives later and
     * somewhere else.
     *
     * `ogg` is listed as audio and `ogv` as video, which is the convention every encoder
     * follows even though the container holds either. The node lets the kind be corrected;
     * the table only has to be right most of the time.
     *
     * @var array<string, array<string, string>>
     */
    public const TYPES = [
        self::IMAGE => [
            'apng' => 'image/apng',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ],
        self::VIDEO => [
            'mp4' => 'video/mp4',
            'm4v' => 'video/x-m4v',
            'mov' => 'video/quicktime',
            'ogv' => 'video/ogg',
            'webm' => 'video/webm',
        ],
        self::AUDIO => [
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'oga' => 'audio/ogg',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'weba' => 'audio/webm',
        ],
    ];

    /**
     * The families that are files: what a mime filter, an extension lookup and an
     * accepted-types list are built from.
     *
     * @return array<int, string>
     */
    public static function families(): array
    {
        return array_keys(static::TYPES);
    }

    /**
     * Everything the browser has a tab for, the pseudo-family last.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [...static::families(), self::EMBED];
    }

    /**
     * The family a mime type belongs to, or null.
     *
     * Matched on the part before the slash, which is what a family is. `image/jpeg` and an
     * `image/heic` nobody listed are both pictures, and a row already in the library should
     * be filed under the tab it belongs to whether or not this package would have uploaded
     * it.
     */
    public static function of(?string $mime): ?string
    {
        if (! is_string($mime) || ! str_contains($mime, '/')) {
            return null;
        }

        $family = Str::lower(Str::before($mime, '/'));

        return array_key_exists($family, static::TYPES) ? $family : null;
    }

    /**
     * The family a file name belongs to, or null where its ending says nothing.
     */
    public static function ofPath(?string $path): ?string
    {
        $extension = static::extensionOf($path);

        foreach (static::TYPES as $kind => $extensions) {
            if (array_key_exists($extension, $extensions)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * What a file name says it is, or an empty string where nothing here draws it.
     */
    public static function mimeOf(?string $path): string
    {
        $extension = static::extensionOf($path);

        foreach (static::TYPES as $extensions) {
            if (array_key_exists($extension, $extensions)) {
                return $extensions[$extension];
            }
        }

        return '';
    }

    /**
     * The named families as `image/*` patterns, which is the shape Filament's accepted-type
     * setting and Laravel's `mimetypes:` rule both take.
     *
     * @param  array<int, string>|null  $kinds
     * @return array<int, string>
     */
    public static function patterns(?array $kinds = null): array
    {
        return array_map(
            static fn (string $kind): string => "{$kind}/*",
            $kinds === null ? static::families() : array_values(array_intersect(static::families(), $kinds)),
        );
    }

    protected static function extensionOf(?string $path): string
    {
        return is_string($path) ? Str::lower(pathinfo($path, PATHINFO_EXTENSION)) : '';
    }
}
