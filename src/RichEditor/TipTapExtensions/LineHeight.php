<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of the line spacing.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser only keeps
 * attributes that something declares - so without `parseHTML` here a paragraph would lose
 * its spacing the first time the record is reopened. And without `renderHTML` the spacing
 * would live in the editor but never reach a saved document, which is where it matters.
 *
 * The value is whitelisted to a bare number rather than passed through. Filament's
 * sanitiser allows `style` on every element but does not look inside CSS, so a value taken
 * from a document could otherwise carry a second declaration in behind a semicolon.
 */
class LineHeight extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteLineHeight';

    /**
     * The block types that carry a spacing, mirroring the JS extension's own list.
     *
     * @var array<int, string>
     */
    public const TYPES = ['paragraph', 'heading', 'blockquote', 'listItem'];

    /**
     * The bounds a value has to sit in to be a line height at all. Wide enough for anything
     * a document reasonably asks for and narrow enough that a stray number is caught.
     */
    public const MIN = 0.5;

    public const MAX = 5.0;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => static::TYPES,
                'attributes' => [
                    'lineHeight' => [
                        'parseHTML' => static function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::readLineHeight($DOMNode);
                        },
                        'renderHTML' => static function ($attributes): array {
                            $lineHeight = static::normalise(static::read($attributes, 'lineHeight'));

                            return $lineHeight === null ? [] : ['style' => "line-height: {$lineHeight}"];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * Reads the `line-height` declaration out of an element's inline style.
     */
    public static function readLineHeight(DOMElement $DOMNode): ?string
    {
        $style = $DOMNode->getAttribute('style');

        if (blank($style)) {
            return null;
        }

        if (preg_match('/(?:^|;)\s*line-height\s*:\s*([^;]+)/i', $style, $matches) !== 1) {
            return null;
        }

        return static::normalise($matches[1]);
    }

    /**
     * A line height is a bare number here - no `150%`, no `24px`, no `inherit`. A unitless
     * height is the one that scales with whatever size the block ends up at, so a heading
     * keeps its proportions, and it is the only spelling that cannot carry CSS of its own.
     */
    public static function normalise(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        $number = (float) $value;

        if ($number < static::MIN || $number > static::MAX) {
            return null;
        }

        // `1.50` and `1.5` are the same spacing, and the toolbar compares the stored value
        // against the one its button carries - so only one of the two spellings may exist.
        return static::format($number);
    }

    /**
     * The canonical spelling of a spacing: `1`, `1.15`, `1.5`. Both halves of the feature
     * build their names and their comparisons from this, so a value written by the toolbar
     * and one parsed back out of a document are always the same string.
     */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * The toolbar name of one spacing. Both the dropdown and the plugin build it from the
     * canonical spelling, so a configured `1.50` and a configured `1.5` name one tool and
     * not two - the dot becomes an underscore because a tool name is also a array key that
     * gets read back out of the toolbar configuration.
     */
    public static function toolName(mixed $value): ?string
    {
        $lineHeight = static::normalise($value);

        return $lineHeight === null
            ? null
            : 'lineHeight'.str_replace('.', '_', $lineHeight);
    }

    /**
     * The configured spacings, canonicalised and de-duplicated, in the listed order.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    public static function values(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): ?string => static::normalise($value), $values),
        )));
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
