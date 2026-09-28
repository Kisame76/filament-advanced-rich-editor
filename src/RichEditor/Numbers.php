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
 * This needs `ext-intl`: Laravel's `Number` builds a `NumberFormatter`, and a host without
 * the extension answers with an exception rather than an English-looking number. The
 * package's `composer.json` requires it directly, which is a statement rather than a fix -
 * `filament/support` already requires it, so no host that can install this package is
 * without it. Declared anyway because this file uses it, and a dependency that is only ever
 * true through somebody else's requirement is one nobody notices being dropped.
 */
final class Numbers
{
    public static function format(int $value): string
    {
        return (string) Number::format($value, locale: app()->getLocale());
    }
}
