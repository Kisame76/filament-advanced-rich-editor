<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

/**
 * What an inserted picture carries into the document besides its source.
 *
 * The measuring happens on upload and lives in `MediaDimensions`; this decides what of it
 * is worth writing down. Kept apart from the action that does the inserting because the
 * action needs a Livewire component, a resolved attachment and a dialog behind it, and
 * none of that has an opinion on whether a width of `"0"` is a width.
 *
 * The size is written for one reason: a browser that knows the shape of a picture before it
 * has the picture leaves the right hole for it, and the article below stops jumping when it
 * arrives. Both numbers are needed for that, and `height: auto` on the page - see the note
 * on `withDimensions` below, which is the whole catch.
 */
class ImageAttributes
{
    /**
     * The attributes an inserted image node is given.
     *
     * `$withDimensions` exists because the pair is not free. Filament's `ImageExtension`
     * renders `width` as an inline `style` as well as an attribute - the two are one thing
     * there, and this package's own resizing drags the same pair - so writing the measured
     * size also pins the displayed size to it. On a page carrying the usual
     * `img { max-width: 100%; height: auto }` that is exactly right and costs nothing; on a
     * page that caps the width but not the height it is a squashed picture. A project that
     * cannot promise the reset turns this off rather than finding out on the front page.
     *
     * @param  array<string, mixed>  $item  a resolved library item, pending upload included
     * @return array<string, mixed>
     */
    public static function forInsert(array $item, ?string $alt, ?string $loading, bool $withDimensions): array
    {
        $attributes = [
            'alt' => $alt,
            'id' => $item['id'] ?? null,
            'src' => $item['url'] ?? null,
        ];

        if ($withDimensions) {
            $width = static::pixels($item['width'] ?? null);
            $height = static::pixels($item['height'] ?? null);

            // Both or neither: half a pair says nothing about the shape, and a lone width
            // renders as an inline width with no height beside it - a picture squashed to a
            // strip wherever a reset lets the height follow.
            if ($width !== null && $height !== null) {
                $attributes['width'] = $width;
                $attributes['height'] = $height;
            }
        }

        $loading = static::loadingHint($loading);

        if ($loading !== null) {
            $attributes['loading'] = $loading;
        }

        return $attributes;
    }

    /**
     * What a picture is given once its file has been edited into a new one: the new file, and
     * a size that draws it at the scale the old one was drawn at.
     *
     * A picture without a size is drawn at the size of its file and needs nothing more. One
     * with a size keeps its scale rather than its box: cutting away half of a picture shown at
     * full size leaves a picture half as wide, the way a word processor crops, where keeping
     * the box would stretch what is left back over it - blurred, on a picture that was small
     * to begin with. Where the browser could not say how wide the old file was, the width
     * stays and the height follows the new shape.
     *
     * Both or neither, as on insert: Filament draws the pair as an inline style, and a height
     * kept from the old shape squashes the new one.
     *
     * @param  array<string, mixed>  $item  the new file, resolved
     * @param  array<string, mixed>  $current  the picture's `width` and `height`, and the width of its old file as `naturalWidth`
     * @return array<string, mixed>
     */
    public static function afterEditing(array $item, array $current): array
    {
        $attributes = [
            'id' => $item['id'] ?? null,
            'src' => $item['url'] ?? null,
        ];

        $width = static::length($current['width'] ?? null);
        $height = static::length($current['height'] ?? null);
        $fileWidth = static::pixels($item['width'] ?? null);
        $fileHeight = static::pixels($item['height'] ?? null);

        if ($width === null || $height === null || $fileWidth === null || $fileHeight === null) {
            return $attributes;
        }

        $oldFileWidth = static::length($current['naturalWidth'] ?? null);
        $newWidth = $oldFileWidth === null ? $width : $fileWidth * $width / $oldFileWidth;

        $attributes['width'] = (int) max(1, round($newWidth));
        $attributes['height'] = (int) max(1, round($newWidth * $fileHeight / $fileWidth));

        return $attributes;
    }

    /**
     * A length in pixels as a picture carries it - a number, possibly with a fraction from a
     * drag - or nothing where it is not one: a percentage says nothing about a scale.
     */
    protected static function length(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric(trim($value)))) {
            return null;
        }

        $length = (float) $value;

        return is_finite($length) && $length > 0 ? $length : null;
    }

    /**
     * A whole number of pixels, or nothing.
     *
     * A zero is the interesting case rather than an attack: the measuring returns one for a
     * file it could not read, and a zero written here renders as `width: 0px` and takes the
     * picture off the page. Digits arriving as a string are the ordinary case - Spatie keeps
     * custom properties as JSON and a number can come back out of it spelled.
     */
    protected static function pixels(mixed $value): ?int
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return null;
        }

        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            return null;
        }

        $pixels = (int) $value;

        return $pixels > 0 ? $pixels : null;
    }

    /**
     * One of the two hints a browser knows, or nothing at all.
     *
     * Whitelisted rather than trusted for the reason every other value this package writes
     * into markup is: `loading` is on the sanitiser's allow list, so whatever is put here
     * reaches the page untouched. Anything that is not one of the two is dropped - there is
     * no correct escaping for "this was meant to be a loading hint".
     */
    public static function loadingHint(?string $loading): ?string
    {
        if ($loading === null) {
            return null;
        }

        $loading = strtolower(trim($loading));

        return in_array($loading, ['lazy', 'eager'], strict: true) ? $loading : null;
    }
}
