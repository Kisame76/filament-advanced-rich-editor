<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * The kinds of callout a field offers, and everything derived from a kind's name.
 *
 * A callout is one node with a variant on it rather than four nodes, because a note that
 * turns out to be a warning should change its colour rather than be deleted and rewritten.
 * The variant is the only thing that differs between them, so it is the only thing stored.
 *
 * Every name that arrives here is checked against `NAME` first. A variant travels into a
 * CSS class, into a tool name and - this is the one that matters - into the JavaScript
 * handler string a toolbar button carries, which is assembled by interpolation. A name is
 * config, not user input, but a quote in one would still end that string early and take
 * the rest of the button with it. Names that do not fit are dropped rather than escaped:
 * there is no legitimate variant called `note') || alert('`.
 */
class Callouts
{
    /**
     * The variants the package ships a label, an icon and a colour for.
     *
     * A project may name others - the class, the tool and the menu entry are all built
     * from the name - and gets the fallbacks: the name in title case, the family's own
     * icon, and the neutral box the stylesheet draws for a variant it has no colour for.
     *
     * @var array<int, string>
     */
    public const VARIANTS = ['note', 'tip', 'warning', 'danger'];

    /**
     * What an unrecognisable variant becomes. A callout drawn in the wrong colour is a
     * smaller problem than a callout that is not drawn at all, which is what dropping the
     * node would mean for a document that already holds one.
     */
    public const DEFAULT = 'note';

    /**
     * A variant may name a class and a JavaScript identifier, so it is a lowercase word,
     * optionally hyphenated, starting with a letter.
     */
    public const NAME = '/^[a-z][a-z0-9-]*$/';

    /**
     * The class the wrapper carries on top of `fi-arte-callout`.
     *
     * The variant rides in a class rather than in a data attribute because Filament's
     * sanitiser keeps `class` and `data-type` and drops everything else - so a callout on
     * a rendered page has exactly these two to say what it is, and only one of them can
     * say which kind.
     */
    public const CLASS_PREFIX = 'fi-arte-callout-';

    /**
     * The configured list, with the names that cannot be one removed and duplicates
     * collapsed. Order is kept: it is the order the dropdown and the slash menu read in.
     *
     * @param  array<int, mixed>  $variants
     * @return array<int, string>
     */
    public static function normalize(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $name = is_string($variant) ? static::name($variant) : null;

            if ($name === null || in_array($name, $normalized, strict: true)) {
                continue;
            }

            $normalized[] = $name;
        }

        return $normalized;
    }

    /**
     * A variant as it is allowed to appear in markup and in a handler, or null.
     */
    public static function name(string $variant): ?string
    {
        $name = strtolower(trim($variant));

        return preg_match(static::NAME, $name) === 1 ? $name : null;
    }

    /**
     * The same, but never null: what a document that holds an unreadable variant is drawn
     * as.
     */
    public static function nameOrDefault(mixed $variant): string
    {
        return (is_string($variant) ? static::name($variant) : null) ?? static::DEFAULT;
    }

    /**
     * The tool a variant is offered as. One tool per variant rather than one tool with a
     * choice behind it, so that the dropdown, the slash menu and the keyboard all reach
     * the same registered thing - which is what `ToolbarDropdown` and `SlashMenu` are
     * built to group and list.
     */
    public static function toolName(string $variant): string
    {
        return 'callout'.Str::studly($variant);
    }

    public static function className(string $variant): string
    {
        return static::CLASS_PREFIX.$variant;
    }

    /**
     * The variant a wrapper's class list says it is, or null.
     */
    public static function fromClassList(string $classes): ?string
    {
        foreach (preg_split('/\s+/', trim($classes)) ?: [] as $class) {
            if (! str_starts_with($class, static::CLASS_PREFIX)) {
                continue;
            }

            $name = static::name(substr($class, strlen(static::CLASS_PREFIX)));

            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The label the button and the menu entry read. A variant the translations have never
     * heard of is titled from its own name, which is the best answer available and better
     * than an untranslated key on a button.
     */
    public static function label(string $variant): string
    {
        $key = "filament-advanced-rich-editor::advanced-rich-editor.callouts.{$variant}";
        $label = __($key);

        return (is_string($label) && $label !== $key)
            ? $label
            : Str::headline($variant);
    }

    /**
     * The icon a variant is drawn with, falling back to the family's own where a project
     * named a variant the icon registry has never heard of. `Icons::get()` throws for an
     * unknown key by design, so the question is asked here rather than caught there.
     */
    public static function icon(string $variant): string|BackedEnum
    {
        $key = 'callout_'.str_replace('-', '_', $variant);

        $known = array_key_exists($key, Icons::defaults())
            || filled(config('filament-advanced-rich-editor.icons.'.$key));

        return Icons::get($known ? $key : 'callouts');
    }
}
