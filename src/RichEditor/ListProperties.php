<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * What a list can be told about itself: which marker it draws, where its numbering starts,
 * and whether it counts backwards.
 *
 * All three ride in the attributes HTML already has for them - `type`, `start` and
 * `reversed` - rather than in a class or a `style`. Those are on Symfony's safe attribute
 * list, so a list keeps its numbering on the rendered page without anything being added to
 * the application's sanitiser, and a browser draws it correctly with no stylesheet at all.
 *
 * `type` is the one that gets argued about: it is written out of HTML5 as presentational
 * and is still honoured by every browser, and the alternative - `style="list-style-type"` -
 * is presentation spelled longer and survives the sanitiser only because `style` does. The
 * attribute is the smaller lie.
 */
class ListProperties
{
    /**
     * The markers an ordered list can count with, as the values the `type` attribute takes.
     * Order is the order they are drawn in: the numbers everybody starts with, then the two
     * alphabets, then the two sets of Roman numerals.
     *
     * This is the list of what is *valid*, not of what a panel offers - see `IMPLIED`.
     *
     * @var array<int, string>
     */
    public const ORDERED = ['1', 'a', 'A', 'i', 'I'];

    /**
     * The three markers a bullet list can draw. Valid, again, rather than offered: `disc` is
     * what a browser draws unasked, so it is also what "no choice made" means - see
     * `IMPLIED`.
     *
     * @var array<int, string>
     */
    public const BULLET = ['disc', 'circle', 'square'];

    /**
     * The marker a browser draws when nothing has been asked for.
     *
     * Which makes it the one marker not worth offering: a button for `disc` beside a button
     * for "Default" is two buttons that draw the same list, and the same goes for `1` beside
     * the numbers. They stay in the lists above all the same, because a document that
     * already carries `type="disc"` should keep it rather than have it quietly stripped on
     * the next save - what is *valid* and what is worth *offering* are two questions.
     *
     * The two are not quite identical, and the difference is why this is a comment rather
     * than a deletion: `type="disc"` pins the marker whatever a project's stylesheet says,
     * where no attribute at all follows the theme. That is a real distinction and far too
     * fine a one for a toolbar - a project that wants its lists pinned has the styles
     * dropdown for exactly that.
     *
     * @var array<string, string>
     */
    public const IMPLIED = [
        'orderedList' => '1',
        'bulletList' => 'disc',
    ];

    /**
     * The markers a panel offers: everything valid except the one already drawn by asking
     * for nothing.
     *
     * @return array<int, string>
     */
    public static function offered(string $listType): array
    {
        $values = $listType === 'orderedList' ? static::ORDERED : static::BULLET;

        return array_values(array_filter(
            $values,
            static fn (string $value): bool => $value !== (static::IMPLIED[$listType] ?? null),
        ));
    }

    /**
     * What each marker is called in CSS.
     *
     * The attribute alone is not enough and the live editor is where that shows: Filament's
     * prose styles set `list-style-type` on every list, and a stylesheet beats a
     * presentational attribute every time. So the marker is written twice - as the
     * attribute, which is what this package parses and what a bare browser honours, and as
     * an inline `list-style-type`, which is what survives somebody else's CSS.
     *
     * The same reasoning the embed wrapper carries its aspect ratio inline for: the page a
     * document ends up on is not this package's, and `style` is what travels there.
     *
     * The keys are the `type` attribute's own values, so `'1'` arrives here as the integer
     * PHP turns a numeric array key into. Anything reading a key back out therefore casts
     * it, and anything looking one up may pass the string: PHP resolves `CSS['1']` to the
     * same entry.
     *
     * @var array<int|string, string>
     */
    public const CSS = [
        '1' => 'decimal',
        'a' => 'lower-alpha',
        'A' => 'upper-alpha',
        'i' => 'lower-roman',
        'I' => 'upper-roman',
        'disc' => 'disc',
        'circle' => 'circle',
        'square' => 'square',
    ];

    /**
     * The marker an inline `list-style-type` names, in the attribute's own spelling, or
     * null.
     *
     * The way back in: a document that arrived from Word, from Google Docs or from another
     * editor carries the CSS and not the attribute, and this is what makes that document
     * readable rather than plain.
     */
    public static function fromStyle(string $style, string $listType): ?string
    {
        preg_match('/list-style-type:\s*([a-z-]+)/i', $style, $matches);

        if (! isset($matches[1])) {
            return null;
        }

        $css = strtolower($matches[1]);

        foreach (static::CSS as $type => $keyword) {
            // Cast, because `'1'` is an integer key by the time it is read back out - and
            // `type()` answers null for anything that is not a string.
            if ($keyword === $css && static::type((string) $type, $listType) !== null) {
                return (string) $type;
            }
        }

        return null;
    }

    /**
     * The highest number a list may be told to start at.
     *
     * Not a limit anybody will meet, and that is the point: it is here so that a stored
     * document cannot carry `start="99999999999"` into a page, which is a number a browser
     * has to render one marker at a time.
     */
    public const MAX_START = 100000;

    /**
     * The marker for a list of this type, or null.
     *
     * Case matters and is kept: `a` and `A` are different alphabets, and `i` and `I` are
     * different numerals. That is the one place in this package where a value is not
     * lowercased on the way in, and it is why this does not simply reuse the pattern the
     * callout kinds and the language codes follow.
     */
    public static function type(mixed $value, string $listType): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $type = trim($value);
        $allowed = $listType === 'orderedList' ? static::ORDERED : static::BULLET;

        return in_array($type, $allowed, strict: true) ? $type : null;
    }

    /**
     * The number a list starts counting at, or null for the one it would count from anyway.
     *
     * `1` is null on purpose: writing `start="1"` on every list would put an attribute into
     * every document that says exactly what its absence says.
     */
    public static function start(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $start = (int) $value;

        return ($start > 1 && $start <= static::MAX_START) ? $start : null;
    }

    /**
     * Whether the list counts backwards.
     *
     * True or null, never false: `reversed` is a boolean attribute, so what a browser reads
     * is whether it is there at all. Null therefore has to mean "absent" on the way in as
     * well - the caller passes null for an attribute that is not on the element, rather
     * than the empty string a bare `reversed` reads back as.
     *
     * `reversed="false"` is the one spelling that is refused. A browser counts it as
     * reversed, but nothing writes it on purpose, and reading it as true would mean an
     * editor and a page disagreeing with whoever typed it.
     */
    public static function reversed(mixed $value): ?bool
    {
        if ($value === true) {
            return true;
        }

        if (! is_string($value)) {
            return null;
        }

        return strtolower(trim($value)) === 'false' ? null : true;
    }
}
