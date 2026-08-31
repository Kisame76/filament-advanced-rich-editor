<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Number;

/**
 * What a number looks like in this package.
 *
 * One place, because the package writes numbers in more than one: the line under the field,
 * the statistics dialog, and whatever comes next. `Number::format()` reads a locale of its
 * own, which is English until an application sets it, while the counter's browser half is
 * handed `app()->getLocale()` - left apart, the same count is written one way before the
 * first keystroke and another way after it.
 *
 * This needs `ext-intl`, which the package requires for exactly this reason: Laravel's
 * `Number` builds a `NumberFormatter`, and a host without the extension answers a request
 * with an exception rather than an English-looking number.
 */
final class Numbers
{
    public static function format(int $value): string
    {
        return (string) Number::format($value, locale: app()->getLocale());
    }
}
