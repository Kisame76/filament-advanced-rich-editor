<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use DOMElement;
use Tiptap\Core\Mark;
use Tiptap\Utils\HTML;

/**
 * The PHP half of the editor's font size mark.
 *
 * Content is normally stored as HTML, and Filament's state cast pushes that HTML through
 * the PHP editor on every hydration. Without this mark the `<span style="font-size: …">`
 * a size produces would be parsed as an unknown span and dropped, so a size would survive
 * exactly until the record is reopened.
 *
 * The size lives in the inline `style` attribute because Filament's HTML sanitiser allows
 * `style` but not arbitrary data attributes. That is also why it is whitelisted rather than
 * trusted - see `sanitise()`.
 */
class FontSize extends Mark
{
    /**
     * @var string
     */
    public static $name = 'fontSize';

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                'tag' => 'span',
                // Returning `false` rejects the rule, so spans without a size are left to
                // whichever other mark claims them.
                'getAttrs' => fn ($DOMNode): ?bool => static::readSize($DOMNode) === null ? false : null,
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'size' => [
                'parseHTML' => fn ($DOMNode): ?string => static::readSize($DOMNode),
                'renderHTML' => function ($attributes): array {
                    $size = null;

                    if (is_array($attributes)) {
                        $size = $attributes['size'] ?? null;
                    } elseif (is_object($attributes)) {
                        $size = $attributes->size ?? null;
                    }

                    $size = static::sanitise($size);

                    return $size === null ? [] : ['style' => 'font-size: '.$size];
                },
            ],
        ];
    }

    /**
     * @param  object  $mark
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($mark, $HTMLAttributes = [])
    {
        return [
            'span',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes),
            0,
        ];
    }

    /**
     * Reads the `font-size` declaration out of an element's inline style.
     */
    protected static function readSize(mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $style = $DOMNode->getAttribute('style');

        if (blank($style)) {
            return null;
        }

        if (preg_match('/(?:^|;)\s*font-size\s*:\s*([^;]+)/i', $style, $matches) !== 1) {
            return null;
        }

        return static::sanitise($matches[1]);
    }

    /**
     * A size, or nothing.
     *
     * Security: the value ends up inside a `style` attribute, which Filament's HTML
     * sanitiser lets through untouched, and it does not only arrive through the parser
     * above - the document the browser submits carries it verbatim into `setContent()`. A
     * value like `1px; position: fixed; inset: 0` would otherwise escape the declaration it
     * was written into and put an overlay over the page. Every sibling that writes into
     * `style` guards it the same way: the typeface through `ToolbarFontPicker::sanitise()`,
     * the highlight through its own colour check, the spacing and the rotation through
     * their own patterns.
     *
     * The pattern is Filament's own, the one `ImageExtension` uses on a width and a height:
     * a number and an optional CSS length unit, and nothing that could carry a second
     * declaration. A size that is not one is dropped rather than escaped - there is no
     * correct escaping for "this was meant to be a length".
     */
    public static function sanitise(mixed $size): ?string
    {
        if (blank($size) || (! is_string($size) && ! is_int($size) && ! is_float($size))) {
            return null;
        }

        $size = trim((string) $size);

        return preg_match('/^\d+(?:\.\d+)?(?:%|px|em|rem|vw|vh|vmin|vmax|pt|pc|cm|mm|in|ch|ex)?$/i', $size) === 1
            ? $size
            : null;
    }
}
