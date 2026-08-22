<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * How wide and how tall a picture is.
 *
 * The one piece of a file's description that is not already in the database. It is read off
 * the file, which is a request on a remote disk - so it is read for the one picture the
 * details panel is showing, never for the forty in the grid behind it.
 *
 * Everything this package uploads is stamped with its dimensions at upload time, where the
 * file is on local disk anyway and measuring it is free. This is the answer for everything
 * else: media that was already there, or that something other than this editor put there.
 */
class MediaDimensions
{
    /**
     * Only the header is needed, and for the formats a browser draws it is in the first few
     * hundred bytes. Reading the whole of a 12 MB photograph to learn two numbers would be the
     * expensive way to do a cheap thing.
     *
     * Not quite a few hundred bytes, though. A JPEG out of a camera or a photo editor carries
     * its colour profile and its EXIF block ahead of the frame header that holds the size, and
     * an embedded ICC profile alone can run past 64 KB - which measured those pictures as
     * unmeasurable, and cached that answer. A quarter of a megabyte clears the profiles that
     * occur in practice and is still a header rather than a file.
     */
    protected const HEADER_BYTES = 262144;

    /**
     * @return array{width: int, height: int}|null
     */
    public static function fromPath(?string $path): ?array
    {
        if (blank($path) || ! is_file($path)) {
            return null;
        }

        return static::read(fn (): mixed => @getimagesize($path));
    }

    /**
     * @param  resource|null  $stream
     * @return array{width: int, height: int}|null
     */
    public static function fromStream(mixed $stream): ?array
    {
        if (! is_resource($stream)) {
            return null;
        }

        $header = @stream_get_contents($stream, static::HEADER_BYTES);

        @fclose($stream);

        if (! is_string($header) || $header === '') {
            return null;
        }

        return static::fromString($header);
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function fromString(?string $contents): ?array
    {
        if (blank($contents)) {
            return null;
        }

        return static::read(fn (): mixed => @getimagesizefromstring($contents));
    }

    /**
     * A measurement, taken once per file.
     *
     * Reading how big a picture is means opening it, and a listing does that for every row -
     * so the answer is remembered against something that changes when the file changes. The
     * key carries the size and the modification time for exactly that reason: a file replaced
     * under the same name is a different picture and gets measured again.
     *
     * @param  callable(): (array{width: int, height: int}|null)  $measure
     * @return array{width: int, height: int}|null
     */
    public static function remembered(string $key, callable $measure): ?array
    {
        $cache = Cache::store(config('filament-advanced-rich-editor.media_library.cache_store'));

        $cached = $cache->get($key);

        if (is_array($cached)) {
            return isset($cached['width'], $cached['height'])
                ? ['width' => (int) $cached['width'], 'height' => (int) $cached['height']]
                : null;
        }

        $dimensions = $measure();

        // A failure is cached too, and deliberately: a file that cannot be measured - an SVG, a
        // broken upload - would otherwise be opened again on every listing, for ever.
        $cache->put($key, $dimensions ?? [], now()->addDay());

        return $dimensions;
    }

    /**
     * @param  callable(): mixed  $read
     * @return array{width: int, height: int}|null
     */
    protected static function read(callable $read): ?array
    {
        try {
            $size = $read();
        } catch (Throwable $exception) {
            // A truncated file, or one that is not the picture its name claims.
            return null;
        }

        if (! is_array($size)) {
            return null;
        }

        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);

        // An SVG has no pixel size at all, and `getimagesize()` answers zero rather than
        // failing. Zero by zero is not a measurement worth showing.
        if ($width < 1 || $height < 1) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }
}
