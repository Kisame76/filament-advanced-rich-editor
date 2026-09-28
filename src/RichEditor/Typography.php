<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Which quotation marks and which dash a language uses.
 *
 * The table is the whole feature; the input rules in the browser are plumbing. German opens
 * with `„` and closes with `“` — the shape English uses to *open* — and sets the shorter
 * dash. An editor with one hard-coded pair writes correct English and wrong German, which is
 * what TipTap's own Typography extension does and why reaching it would not have been enough.
 *
 * It lives in PHP rather than in the module that applies it because it is configuration: a
 * project whose language is not shipped adds it where it adds everything else, and does not
 * have to publish a JavaScript file to do it. The browser half keeps a copy as the fallback
 * for when no settings reach it, and `TypographyTest` holds the two together.
 */
class Typography
{
    /**
     * The languages this package is itself translated into, and nothing beyond them.
     *
     * Naming a language's typography is a claim about that language. Three more written from
     * memory would be three claims nobody checked, and a wrong quotation mark is the kind of
     * mistake that is invisible until something is printed — so an unknown language falls
     * back to the English convention, which is what every editor applies today, and a project
     * that knows better says so.
     *
     * @return array<string, array<string, string>>
     */
    public static function defaults(): array
    {
        return [
            'de' => [
                'open' => '„',
                'close' => '“',
                'openSingle' => '‚',
                'closeSingle' => '‘',
                'dash' => '–',
            ],
            'en' => [
                'open' => '“',
                'close' => '”',
                'openSingle' => '‘',
                'closeSingle' => '’',
                'dash' => '—',
            ],
        ];
    }

    /**
     * The table for a locale, however it is spelled. `app()->getLocale()` answers `de_DE` as
     * readily as `de`; only the language in front of it decides anything here.
     *
     * @return array<string, string>
     */
    public static function for(?string $language): array
    {
        $languages = [
            ...static::defaults(),
            ...(config('filament-advanced-rich-editor.typography.languages') ?? []),
        ];

        $tag = strtolower((string) preg_split('/[-_]/', (string) $language)[0]);

        return $languages[$tag] ?? $languages['en'];
    }
}
