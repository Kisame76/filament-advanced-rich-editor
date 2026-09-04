<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;

/**
 * The picture a sound file carries about itself.
 *
 * An mp3 out of anything - a podcast host, a music library, a phone - has its cover art in
 * an ID3 tag at the front of the file, and that is the only picture of a sound that exists.
 * Anything else would be asking somebody to upload a second file.
 *
 * Read here rather than through a library, because the whole of what is needed is the first
 * `APIC` frame and the layout has not changed since ID3v2.2. A dependency for this would be
 * a dependency that also parses lyrics, chapters and forty text frames nobody asked about.
 *
 * Two things are deliberately not handled. Unsynchronisation - the scheme that hides
 * `\xFF\xFB` sequences inside a tag so an old decoder does not mistake them for audio - is
 * ignored: it is vanishingly rare on the picture frame, and a picture read wrong is a
 * picture that fails to decode, which is the same outcome as no picture. And a compressed or
 * encrypted frame is skipped rather than unpacked.
 */
class Id3Cover
{
    /**
     * How much of the file has to be read. A tag sits at the front, and its own header caps
     * it at 256 MB - but a cover that is not in the first few megabytes is a cover this
     * package is not going to hold in memory anyway.
     */
    public const HEADER_BYTES = 8 * 1024 * 1024;

    /**
     * The largest picture worth keeping. Album art runs to several megabytes in a music
     * library, and every one of those bytes would be read on a listing that is drawing a
     * tile 120 pixels wide.
     */
    public const MAX_PICTURE_BYTES = 5 * 1024 * 1024;

    /**
     * The ceiling this run actually uses. A setting rather than only a constant because a
     * project with a library of high-resolution album art may want it higher, and because
     * proving the ceiling works should not mean allocating five megabytes to do it.
     */
    public static function maxPictureBytes(): int
    {
        $configured = config('filament-advanced-rich-editor.media_library.covers.max_picture_bytes');

        return is_int($configured) && $configured > 0 ? $configured : self::MAX_PICTURE_BYTES;
    }

    /**
     * The first picture in the tag, or null.
     *
     * @return array{mime: string, bytes: string}|null
     */
    public static function read(string $contents): ?array
    {
        // "ID3", two version bytes, one flags byte, four syncsafe length bytes.
        if (strlen($contents) < 10 || substr($contents, 0, 3) !== 'ID3') {
            return null;
        }

        $major = ord($contents[3]);

        if ($major < 2 || $major > 4) {
            return null;
        }

        $tag = substr($contents, 10, static::syncsafe(substr($contents, 6, 4)));

        // A three-character frame id and a three-byte length in 2.2; four and four after it.
        $idLength = ($major === 2) ? 3 : 4;
        $headerLength = ($major === 2) ? 6 : 10;

        $offset = 0;

        while ($offset + $headerLength <= strlen($tag)) {
            $id = substr($tag, $offset, $idLength);

            // Padding. The rest of the tag is zero bytes, and there is nothing behind them.
            if (trim($id, "\0") === '') {
                return null;
            }

            $length = match (true) {
                $major === 2 => static::plain(substr($tag, $offset + 3, 3)),
                $major === 4 => static::syncsafe(substr($tag, $offset + 4, 4)),
                default => static::plain(substr($tag, $offset + 4, 4)),
            };

            $body = substr($tag, $offset + $headerLength, $length);

            // A frame that claims to run past the end of the tag it is in. Nothing after it
            // can be trusted to be a frame either.
            if ($length < 1 || strlen($body) < $length) {
                return null;
            }

            if ($id === 'APIC' || $id === 'PIC') {
                $picture = static::picture($body, $major);

                if ($picture !== null) {
                    return $picture;
                }
            }

            $offset += $headerLength + $length;
        }

        return null;
    }

    /**
     * The contents of one picture frame.
     *
     * @return array{mime: string, bytes: string}|null
     */
    protected static function picture(string $body, int $major): ?array
    {
        if ($body === '') {
            return null;
        }

        $encoding = ord($body[0]);
        $offset = 1;

        if ($major === 2) {
            // Three characters naming the format - `JPG`, `PNG` - rather than a mime type.
            $format = strtolower(substr($body, $offset, 3));
            $mime = MediaKinds::mimeOf('cover.'.($format === 'jpg' ? 'jpeg' : $format));
            $offset += 3;
        } else {
            $end = strpos($body, "\0", $offset);

            if ($end === false) {
                return null;
            }

            $mime = strtolower(trim(substr($body, $offset, $end - $offset)));
            $offset = $end + 1;
        }

        // The picture type - front cover, back cover, band photograph. Not filtered on:
        // whatever picture a file carries is the picture it has, and a tag with only a back
        // cover in it is better drawn than not.
        $offset++;

        $offset = static::afterDescription($body, $offset, $encoding);

        if ($offset === null) {
            return null;
        }

        // Measured before it is copied. `substr()` on a five-megabyte frame allocates five
        // megabytes to find out it is too big, which is the expensive way to refuse
        // something.
        $length = strlen($body) - $offset;

        if ($length < 1 || $length > static::maxPictureBytes()) {
            return null;
        }

        $bytes = substr($body, $offset);

        // Only a picture, and only one this package would draw. A frame declaring
        // `text/plain` - or `-->`, which is ID3's way of storing a URL instead of an image -
        // is not something to write out as a cover.
        if (MediaKinds::of($mime) !== MediaKinds::IMAGE) {
            return null;
        }

        return ['mime' => $mime, 'bytes' => $bytes];
    }

    /**
     * Where the picture starts: past a description terminated by one zero byte, or by two
     * where the description is UTF-16.
     */
    protected static function afterDescription(string $body, int $offset, int $encoding): ?int
    {
        // 1 and 2 are the two UTF-16 encodings, whose terminator is a zero *character*.
        if ($encoding === 1 || $encoding === 2) {
            for ($i = $offset; $i + 1 < strlen($body); $i += 2) {
                if ($body[$i] === "\0" && $body[$i + 1] === "\0") {
                    return $i + 2;
                }
            }

            return null;
        }

        $end = strpos($body, "\0", $offset);

        return ($end === false) ? null : $end + 1;
    }

    /**
     * Four bytes carrying seven bits each - the scheme that keeps a length from ever looking
     * like the start of an audio frame.
     */
    protected static function syncsafe(string $bytes): int
    {
        $size = 0;

        foreach (str_split($bytes) as $byte) {
            $size = ($size << 7) | (ord($byte) & 0x7F);
        }

        return $size;
    }

    protected static function plain(string $bytes): int
    {
        $size = 0;

        foreach (str_split($bytes) as $byte) {
            $size = ($size << 8) | ord($byte);
        }

        return $size;
    }
}
