<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of the paragraph indent.
 *
 * What is stored is a `margin-inline-start` in the block's inline `style`, and what is kept
 * in the document is the depth it stands for. Three decisions are behind that, and each one
 * had a cheaper-looking alternative.
 *
 * The style rather than a class or a `data-*`. Filament's sanitiser allows `class` and
 * `style` on every element and exactly six `data-*` attributes
 * (`SupportServiceProvider::$allowedAttributes`), so a `data-indent` would survive the
 * database and die on the rendered page - the indent would be visible while editing and
 * gone for the reader, which is the one failure mode nothing else in this package has. A
 * class survives, but a class means nothing without the stylesheet that defines it, and
 * this package does not ship one over a project's rendered pages. A margin means the same
 * thing everywhere.
 *
 * The logical `margin-inline-start` rather than `margin-left`. Blocks in this package carry
 * a direction of their own (`TextDirection`), and an indent on a right-to-left paragraph
 * belongs on its right. The logical property is the one that follows the block instead of
 * the page. `margin-left` is still read on the way in, because it is what every other
 * editor and every hand-written document writes, and reading it converts it.
 *
 * The depth rather than the length. Both halves offer one step in and one step out, and a
 * length that is not on the grid makes the next step land off it too - so the attribute is
 * a whole number of steps, and the length is derived from it on the way out. The cost is
 * that a project which changes the step re-grids its existing documents on the next save;
 * that is a smaller price than a document whose paragraphs slowly drift apart.
 */
class Indent extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteIndent';

    /**
     * The block types that carry a depth, mirroring the JS extension's own list.
     *
     * `listItem` is deliberately not one of them, and neither is `taskItem`: a list indents
     * by nesting, which is a different thing stored a different way, and a margin beside it
     * would be a second indent that the list's own numbering knows nothing about. The
     * commands hand a selection inside a list to `sinkListItem` instead.
     *
     * @var array<int, string>
     */
    public const TYPES = ['paragraph', 'heading', 'blockquote'];

    /**
     * Prefixed rather than called `indent`, for the reason `BlockStyle` is: this is merged
     * into nodes that Filament and TipTap both own, and the community's own indent
     * extensions call their attribute `indent` - a bare name is a collision waiting to be
     * found.
     */
    public const ATTRIBUTE = 'arteIndent';

    /**
     * One step, as a CSS length. 2.5rem is 40px at a default root size, which is what
     * TinyMCE steps by and what a browser already indents a `<blockquote>` and a list by -
     * so an indented paragraph lines up with the things around it.
     */
    public const DEFAULT_STEP = '2.5rem';

    public const DEFAULT_MAX = 8;

    /**
     * The deepest a configured maximum may go. Past this the margin is wider than the
     * column it sits in on any screen, so it is a typo rather than a request.
     */
    public const MAX_DEPTH = 40;

    /**
     * The units a length may be written in, and what one of them is in CSS pixels.
     *
     * The table exists for one job: reading a length back out of a document that was
     * written with a different step, or with a different unit, and still landing on the
     * right depth. `em` and `ch` are font-relative and cannot honestly be converted, so they
     * are given the root's own default - close enough to pick a depth, and never used to
     * write one, because the writing side only ever multiplies the configured step.
     *
     * `%` is absent: a percentage of a container this side has never seen is not a length.
     *
     * @var array<string, float>
     */
    public const UNITS = [
        'px' => 1.0,
        'pt' => 1.3333333333333333,
        'pc' => 16.0,
        'in' => 96.0,
        'cm' => 37.795275590551185,
        'mm' => 3.7795275590551185,
        'q' => 0.9448818897637796,
        'rem' => 16.0,
        'em' => 16.0,
        'ch' => 8.0,
    ];

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'step' => null,
            'max' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        $step = static::step($this->options['step'] ?? null);
        $max = static::max($this->options['max'] ?? null);

        return [
            [
                'types' => static::TYPES,
                'attributes' => [
                    static::ATTRIBUTE => [
                        'parseHTML' => static function ($DOMNode) use ($step, $max): ?int {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::readIndent($DOMNode, $step, $max);
                        },
                        'renderHTML' => static function ($attributes) use ($step, $max): array {
                            $level = static::level(static::read($attributes, static::ATTRIBUTE), $max);

                            if ($level === null) {
                                return [];
                            }

                            return ['style' => 'margin-inline-start: '.static::length($level, $step)];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * The depth an element's inline style stands for.
     *
     * `margin-inline-start` first and `margin-left` only after it: a document this package
     * wrote carries the logical property, and an element carrying both is one that was
     * written twice - the logical one is the one this package means.
     */
    public static function readIndent(DOMElement $DOMNode, string $step, int $max): ?int
    {
        $style = $DOMNode->getAttribute('style');

        if (blank($style)) {
            return null;
        }

        foreach (['margin-inline-start', 'margin-left'] as $property) {
            if (preg_match('/(?:^|;)\s*'.$property.'\s*:\s*([^;]+)/i', $style, $matches) !== 1) {
                continue;
            }

            $level = static::levelOf($matches[1], $step, $max);

            if ($level !== null) {
                return $level;
            }
        }

        return null;
    }

    /**
     * A length, read as a number of steps: rounded to the nearest whole one and held inside
     * the field's own maximum.
     *
     * Rounding rather than requiring an exact multiple. A document written at one step and
     * read at another would otherwise lose its indentation entirely, and so would one
     * pasted out of a word processor - and half a step is close enough to one step to be
     * what somebody meant. Anything under half a step is no indent, which is also what a
     * `margin-inline-start: 0` and an `auto` come to.
     */
    public static function levelOf(string $length, string $step, int $max): ?int
    {
        $pixels = static::pixels($length);
        $stepPixels = static::pixels($step);

        if ($pixels === null || $stepPixels === null || $stepPixels <= 0.0) {
            return null;
        }

        return static::level((int) round($pixels / $stepPixels), $max);
    }

    /**
     * One length in CSS pixels, or null where it is not a length this side understands.
     */
    public static function pixels(string $length): ?float
    {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?|-?\.\d+)\s*([a-z]*)\s*$/i', $length, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        // A bare `0` is the one length CSS lets stand without a unit, and it is the one an
        // editor writes when it means "no indent".
        if ($unit === '') {
            return $number === 0.0 ? 0.0 : null;
        }

        if (! array_key_exists($unit, static::UNITS)) {
            return null;
        }

        return $number * static::UNITS[$unit];
    }

    /**
     * A configured step, canonicalised - or the shipped one where the configuration does not
     * name a length this side can multiply.
     */
    public static function step(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = static::format((float) $value).'rem';
        }

        if (! is_string($value) || preg_match('/^\s*(\d+(?:\.\d+)?|\.\d+)\s*([a-z]+)\s*$/i', $value, $matches) !== 1) {
            return static::DEFAULT_STEP;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        if ($number <= 0.0 || ! array_key_exists($unit, static::UNITS)) {
            return static::DEFAULT_STEP;
        }

        return static::format($number).$unit;
    }

    /**
     * How deep this field lets a block go. Out of range means the shipped depth rather than
     * a clamp, because a configured `0` is a request to switch the feature off written in
     * the wrong place, and answering it with `1` would be answering a different question.
     */
    public static function max(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            return static::DEFAULT_MAX;
        }

        $max = (int) $value;

        return ($max < 1 || $max > static::MAX_DEPTH) ? static::DEFAULT_MAX : $max;
    }

    /**
     * A depth, held inside the field's maximum. Zero and below is no indent at all, which
     * is stored as no attribute rather than as a zero margin.
     */
    public static function level(mixed $value, int $max): ?int
    {
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1) {
            return null;
        }

        return min($value, $max);
    }

    /**
     * The length a depth writes: the step, that many times over.
     *
     * The step is canonicalised again rather than trusted. Every caller inside this package
     * hands one that already is, but this is a public static and a pattern that assumed the
     * shape would answer a malformed step with a warning about an array key rather than with
     * the shipped step - which is what every other door in this class answers with.
     */
    public static function length(int $level, string $step): string
    {
        preg_match('/^(\d+(?:\.\d+)?)([a-z]+)$/', static::step($step), $matches);

        return static::format(((float) $matches[1]) * $level).$matches[2];
    }

    /**
     * The canonical spelling of a number, so a length written by the toolbar and one parsed
     * back out of a document are the same string.
     */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    protected static function read(mixed $attributes, string $key): mixed
    {
        if (is_array($attributes)) {
            return $attributes[$key] ?? null;
        }

        if (is_object($attributes)) {
            return $attributes->{$key} ?? null;
        }

        return null;
    }
}
