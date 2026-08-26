<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Str;

/**
 * The languages a passage can be marked as being in, and everything derived from a code.
 *
 * A mark rather than an attribute on the block, and that is the whole point of the feature:
 * WCAG 3.1.2 is about a *passage*, which is usually a phrase inside a sentence - a French
 * title in a German paragraph, a Latin term in an English one. A `lang` on the paragraph
 * cannot say that, so the roadmap's "a global attribute, exactly like `dir`" is the one
 * thing this could not be.
 *
 * Codes are lowercased on the way in, on the way out and on the way back. `lang` is
 * case-insensitive by specification, so `fr-CA` and `fr-ca` are the same language - and if
 * the two were kept apart, a document stored with one spelling would light up no button for
 * the other.
 */
class Languages
{
    /**
     * The shape of a language tag, kept to the part of BCP 47 anybody types: a primary
     * subtag and any number of subtags after it. Anything else is dropped rather than
     * escaped - a code travels into a tool name and into the JavaScript a button carries,
     * and there is no legitimate language called `fr') || alert('`.
     */
    public const CODE = '/^[a-z]{2,8}(-[a-z0-9]{1,8})*$/';

    /**
     * What the shipped config offers. Six rather than a hundred: this is the list somebody
     * quoting a phrase reaches for, and a project that needs Norwegian says so in one line.
     *
     * Endonyms, because a language is best named in itself - that is the name a reader
     * picking it out of a list recognises, and it needs no translation file of its own.
     *
     * @var array<string, string>
     */
    public const VALUES = [
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'it' => 'Italiano',
        'la' => 'Latina',
    ];

    /**
     * The configured list as rows, with unusable codes removed and duplicates collapsed.
     * Order is kept: it is the order the dropdown reads in.
     *
     * A list may be written either way round - `['fr' => 'Français']` or `['fr']` - because
     * a code is its own worst label but is still better than nothing, and a project adding
     * one language should not have to look up how it spells its own name.
     *
     * @param  array<mixed>  $values
     * @return array<int, array{code: string, label: string}>
     */
    public static function normalize(array $values): array
    {
        $rows = [];

        foreach ($values as $key => $value) {
            $code = static::code(is_string($key) ? $key : (is_string($value) ? $value : ''));

            if ($code === null || array_key_exists($code, $rows)) {
                continue;
            }

            $label = (is_string($key) && is_string($value) && $value !== '') ? $value : null;

            $rows[$code] = [
                'code' => $code,
                'label' => $label ?? static::VALUES[$code] ?? $code,
            ];
        }

        return array_values($rows);
    }

    /**
     * A language tag as it is allowed to appear in markup and in a handler, or null.
     */
    public static function code(string $value): ?string
    {
        $code = strtolower(trim($value));

        return preg_match(static::CODE, $code) === 1 ? $code : null;
    }

    /**
     * The tool a language is offered as. One tool per language, for the reason the callout
     * kinds are one tool each: the dropdown, the slash menu and the lit-up button are all
     * built on registered tools.
     */
    public static function toolName(string $code): string
    {
        return 'language'.Str::studly($code);
    }

    /**
     * The entry that takes the marking off again. A dropdown listing only languages has no
     * way back to "the language of the page", which is what most of a document is in.
     */
    public const CLEAR = 'languageNone';
}
