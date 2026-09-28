<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Number;

/**
 * A byte count as the words a reader wants, and the one place that turns one into the other.
 *
 * Small enough to look like it belongs inline somewhere, and deliberately not inline. It is
 * called from two ends that must agree - whoever inserts a card writes the label, and the
 * card carries that label through every later save - so a second rounding rule anywhere
 * would show up as the same file changing size between one document and the next.
 *
 * The result is a *label*, not a number, and that is the whole point. What a document keeps
 * is what it shows: Filament's sanitiser passes exactly six `data-*` attributes through
 * (`vendor/filament/support/src/SupportServiceProvider.php`), `data-size` is not one of
 * them, and a byte count parked there would be silently gone by the time the page rendered.
 * The span's own text survives because text always does.
 *
 * The cost is that a label is frozen in the language it was written in. A document written
 * in German keeps `1,2 MB` when the panel is later read in English - the same thing that is
 * already true of every other word in it, and a better trade than a number that disappears.
 */
class ByteSize
{
    /**
     * What a file this size is called, or null where nobody knows the size.
     *
     * Null rather than a placeholder. A card reading "unknown size" spends a line saying
     * nothing; a card with no size line simply has one fewer thing on it, which is exactly
     * true of a file somebody linked to by address.
     */
    public static function format(mixed $bytes): ?string
    {
        if (is_string($bytes) && $bytes !== '' && ctype_digit($bytes)) {
            $bytes = (int) $bytes;
        }

        if (! is_int($bytes) || $bytes < 0) {
            return null;
        }

        // `Number::fileSize()` follows the application's locale, which is what puts a comma
        // in a German document and a point in an English one. Two decimals below a megabyte
        // would be noise - nobody needs `12,34 KB` - so the precision grows with the unit.
        //
        // Its own rounding is kept as it is, including the part that surprises: it changes
        // unit at nine tenths of the next one, so 940 bytes reads `1 KB` where a file
        // manager would say `940 bytes`. Correcting it here would make this card the one
        // place in a Laravel application that rounds sizes differently from every other,
        // over a difference nobody acts on.
        return Number::fileSize($bytes, precision: $bytes < 1024 * 1024 ? 0 : 1);
    }

    /**
     * A label somebody already wrote, kept only where it is short enough to be one.
     *
     * The parse side of the above: what comes back out of a document is whatever text sat
     * in the size span, and that is text a person could have edited. It is never used for
     * arithmetic, so the only thing worth refusing is a paragraph that has wandered in.
     */
    public static function label(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return ($value === '' || mb_strlen($value) > 32) ? null : $value;
    }
}
