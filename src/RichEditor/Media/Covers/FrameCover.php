<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * One frame out of a film.
 *
 * Through the `ffmpeg` binary rather than through a PHP library, because there is no PHP
 * library: every one of them shells out to the same binary, and requiring one would add a
 * Composer dependency that does nothing this file does not.
 *
 * Which means the binary may simply not be there, and that is the ordinary case rather than
 * the exceptional one - shared hosting does not have it. Everything here is written for
 * that: no exception leaves this class, the caller is told `null`, and the caller remembers
 * so nothing tries again on the next listing.
 */
class FrameCover
{
    /**
     * A second in rather than at zero. The first frame of a great many films is black - a
     * fade in, a slate, a title card on its way up - and a library of black tiles is worse
     * than a library of badges, because a black tile looks like a working thumbnail.
     */
    public const SEEK = '1';

    /**
     * Wide enough for the panel, small enough that forty of them are not a page weight. The
     * odd height is what `-2` asks for: whatever keeps the aspect ratio and is divisible by
     * two, which is what a JPEG encoder needs.
     */
    public const SCALE = 'scale=480:-2';

    public static function read(string $localPath, string $binary, int $timeout): ?string
    {
        if (! is_file($localPath)) {
            return null;
        }

        // ffmpeg writes to a file rather than to standard output for a reason: piped output
        // of an image format it has to seek in is not something every build supports.
        //
        // The name ends in `.jpg`, and that is not decoration: ffmpeg picks its output
        // format from the extension, and handed the extensionless name `tempnam()` returns
        // it has nothing to pick from. `tempnam()` cannot be asked for a suffix, so the file
        // it makes is the stem and both are cleaned up below.
        $stem = (string) tempnam(sys_get_temp_dir(), 'arte-cover');
        $out = $stem.'.jpg';

        try {
            $result = Process::timeout(max(1, $timeout))->run([
                $binary,
                // Before `-i`, which is what makes the seek a jump rather than a decode of
                // everything up to that point.
                '-ss', static::SEEK,
                '-i', $localPath,
                '-frames:v', '1',
                '-vf', static::SCALE,
                '-q:v', '4',
                // One image into one file, which the image2 muxer will not do unless it is
                // asked. Without this it exits ZERO, writes nothing, and says so only in a
                // warning: "use the -update option ... to write a single image". A cover
                // that fails silently and successfully is the worst of both.
                '-update', '1',
                // Overwrite rather than stop and wait for an answer nobody is there to give.
                '-y',
                $out,
            ]);

            if (! $result->successful()) {
                return null;
            }

            $bytes = @file_get_contents($out);

            // A container ffmpeg accepted and could not decode exits zero and writes
            // nothing. An empty file put out as a cover is a broken picture in the grid.
            return (is_string($bytes) && $bytes !== '') ? $bytes : null;
        } catch (Throwable $exception) {
            // A timeout, or a binary that is not executable. Both are "no cover".
            return null;
        } finally {
            foreach ([$out, $stem] as $temporary) {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }
    }
}
