<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * What a file's bytes are, as one string: two files with the same one hold the same bytes.
 *
 * SHA-256 rather than something faster. Whoever uploads first decides what a later upload of
 * the same bytes turns into, so a hash somebody could find a collision for would let a file
 * be handed out in place of another one that only looks like it.
 *
 * Read as a stream, so a large file is never held in memory whole, and null wherever the file
 * cannot be read - a file that cannot be read is a file nothing can be compared with.
 */
class ContentHash
{
    public const ALGORITHM = 'sha256';

    /**
     * @param  resource|null  $stream
     */
    public static function ofStream(mixed $stream): ?string
    {
        if (! is_resource($stream)) {
            return null;
        }

        try {
            $context = hash_init(static::ALGORITHM);

            hash_update_stream($context, $stream);

            return hash_final($context);
        } catch (Throwable $exception) {
            return null;
        } finally {
            fclose($stream);
        }
    }

    public static function ofPath(string $path): ?string
    {
        $hash = is_file($path) ? hash_file(static::ALGORITHM, $path) : false;

        return is_string($hash) ? $hash : null;
    }

    /**
     * An upload, wherever it is kept: Livewire may hold its temporary files on a remote disk,
     * where the path an uploaded file reports points at nothing local.
     */
    public static function ofUpload(UploadedFile $file): ?string
    {
        if (method_exists($file, 'readStream')) {
            return static::ofStream(rescue(static fn (): mixed => $file->readStream(), report: false));
        }

        return static::ofPath($file->getRealPath() ?: $file->getPathname());
    }
}
