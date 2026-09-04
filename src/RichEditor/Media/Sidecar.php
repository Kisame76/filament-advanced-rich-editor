<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * What a disk knows about a file that the file itself cannot say.
 *
 * A media row has columns to put an alt text in; a file on a disk has nothing but its bytes,
 * and rewriting those to store a caption is not something a media browser should be doing to
 * somebody's photograph. So the description lives beside the file, in a JSON document named
 * after it - `sunset.png.json` next to `sunset.png` - which travels with the file when a
 * directory is copied and is readable by anything that can read the disk.
 *
 * Reading never throws. A sidecar is decoration: one that is missing, unreadable or
 * hand-edited into nonsense means the file has no description, which is exactly what a file
 * with no sidecar at all means.
 */
class Sidecar
{
    /**
     * The companion's path. `sunset.png` keeps its extension so two files that differ only
     * by it - `talk.mp4` and `talk.mp3` - do not share one sidecar.
     */
    public static function pathFor(string $path, string $suffix = 'json'): string
    {
        return $path.'.'.$suffix;
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(Filesystem $disk, string $path): array
    {
        try {
            if (! $disk->exists(static::pathFor($path))) {
                return [];
            }

            $contents = $disk->get(static::pathFor($path));
        } catch (Throwable $exception) {
            return [];
        }

        if (! is_string($contents) || blank($contents)) {
            return [];
        }

        $decoded = json_decode($contents, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merges into whatever is there rather than replacing it.
     *
     * The panel sends one field at a time and the cover marker is written by something else
     * entirely, so a write that replaced the document would erase whichever of the two ran
     * first. A key given as null is removed, which is how a description is cleared.
     *
     * @param  array<string, mixed>  $data
     */
    public static function write(Filesystem $disk, string $path, array $data): bool
    {
        $merged = [...static::read($disk, $path), ...$data];

        $merged = array_filter($merged, static fn (mixed $value): bool => $value !== null);

        try {
            if ($merged === []) {
                // Nothing left worth keeping. An empty JSON document beside every file would
                // be litter, and litter this package would then have to list around.
                return $disk->exists(static::pathFor($path))
                    ? $disk->delete(static::pathFor($path))
                    : true;
            }

            return $disk->put(
                static::pathFor($path),
                (string) json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        } catch (Throwable $exception) {
            // A read-only disk, or one that is full. The browser is told `false` and shows
            // the last value it knew; nothing here is worth a 500.
            return false;
        }
    }
}
