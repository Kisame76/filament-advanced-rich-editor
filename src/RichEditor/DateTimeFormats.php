<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;

/**
 * What a date written into a document looks like, and where the answer comes from.
 *
 * Three decisions live here, and each one had an alternative that looks cheaper.
 *
 * The locale is asked for explicitly. Carbon's own Laravel provider listens for
 * `LocaleUpdated` and keeps a global locale in step, so `translatedFormat()` usually
 * answers in the application's language without being told - but only where that provider
 * was discovered. This package's own suite registers providers by hand and never gets it,
 * so an ambient locale reads English there while reading German in an application. A
 * feature that cannot be tested in the language it is about is not a feature, so the
 * locale is passed per instance. Nothing here calls `Carbon::setLocale()`: that is a
 * global nothing resets between tests.
 *
 * The timezone follows Filament's own rule rather than a simpler one. A value carrying a
 * time is shown in the display timezone; a date on its own is not, because applying an
 * offset to a date shifts it a whole day for every instant near midnight - measured, and
 * the reason Filament documents the same exemption. Which of the two a format is gets
 * read off the format itself, since that is all there is to read.
 *
 * The browser's clock is never asked. The one place a locale crosses into JavaScript is
 * `CharacterCount`, and it crosses in that direction - PHP tells the browser, never the
 * other way round. A date is the same kind of statement, so it is made on the server and
 * sent over finished.
 */
class DateTimeFormats
{
    /**
     * The three keys the surrounding schema already answers for, which is why they are the
     * only ones allowed to be configured as `null`. Everything else has to say what it
     * wants: there is no fourth question to fall back on.
     *
     * @var array<int, string>
     */
    public const INHERITED = ['date', 'time', 'dateTime'];

    /**
     * Every unescaped format character that says something about the time of day, taken
     * from PHP's own table. `c`, `r` and `U` are in it because each one spells out a full
     * instant, and `I`, `O`, `P`, `T` and `Z` because a zone is only meaningful about one -
     * a date has no offset to name.
     *
     * `e` and `p` are deliberately absent although `date()` treats them as zone tokens:
     * `translatedFormat()` does not implement either and emits the bare letter, so a format
     * carrying one says nothing about a time - and counting it would apply the display
     * timezone to a date-only format and move it a day around midnight, which is the exact
     * thing the distinction exists to prevent. `x` and `X` are absent for the same reason.
     */
    public const TIME_TOKENS = 'aABgGhHisuvIOPTZcrU';

    /**
     * The toolbar name of one configured format. It is also the name a dropdown, the
     * overflow menu and the slash menu address it by, so the key it is built from has to
     * be a bare identifier: a dot would be read as nesting in the alias translation key,
     * would truncate the derived label, and would make the name unusable in a toolbar
     * array where names are matched by exact equality.
     */
    public static function toolName(string $key): ?string
    {
        return static::isKey($key)
            ? 'insert'.ucfirst($key)
            : null;
    }

    /**
     * A configured key is an identifier: a lower-case letter, then letters and digits. The
     * lower-case start is what makes `insert`.ucfirst() a camelCase name rather than a
     * name with a seam in the middle.
     */
    public static function isKey(string $key): bool
    {
        return preg_match('/^[a-z][a-zA-Z0-9]*$/', $key) === 1;
    }

    /**
     * The configured map, cleaned: invalid keys dropped, blank formats dropped except for
     * the three keys something else answers for, order kept. A dropped entry registers no
     * tool at all rather than a tool that inserts nothing.
     *
     * @param  array<mixed>  $formats
     * @return array<string, ?string>
     */
    public static function map(array $formats): array
    {
        $map = [];

        foreach ($formats as $key => $format) {
            if (! is_string($key) || ! static::isKey($key)) {
                continue;
            }

            if (is_string($format) && filled(trim($format))) {
                $map[$key] = $format;

                continue;
            }

            // Only the three inherited keys may stand without a format of their own.
            if (blank($format) && in_array($key, static::INHERITED, strict: true)) {
                $map[$key] = null;
            }
        }

        return $map;
    }

    /**
     * Whether a format says anything about the time of day, which decides whether the
     * display timezone applies to it.
     *
     * A backslash escapes the character after it, exactly as it does in `date()`, so the
     * `t` in `\t\o\d\a\y` is a letter rather than a count of days.
     */
    public static function carriesTime(string $format): bool
    {
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] === '\\') {
                $i++;

                continue;
            }

            if (str_contains(static::TIME_TOKENS, $format[$i])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Now, written the way this format asks for it.
     *
     * `translatedFormat()` rather than `format()`: the second one is PHP's, and PHP's
     * month names are English whatever the application's language is. Four tokens are
     * worth knowing about, because Carbon answers them differently from PHP - `S` is the
     * ordinal suffix of the language rather than the English one, and `e`, `p`, `x` and
     * `X` are not translated at all and come out as the bare letter. `T`, `O` and `P` are
     * the tokens that do name a zone.
     */
    public static function render(string $format, ?string $locale = null): string
    {
        $now = Carbon::now();

        if (static::carriesTime($format)) {
            $now = $now->setTimezone(FilamentTimezone::get());
        }

        return $now
            ->locale($locale ?? app()->getLocale())
            ->translatedFormat($format);
    }
}
