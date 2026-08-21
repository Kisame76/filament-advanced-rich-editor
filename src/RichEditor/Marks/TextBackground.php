<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use DOMElement;
use Tiptap\Core\Mark;
use Tiptap\Utils\HTML;

/**
 * The PHP half of the text background mark.
 *
 * Content is stored as HTML and pushed back through the PHP editor on every hydration, so
 * without this the `<mark>` a background colour produces would be parsed by Filament's
 * colourless Highlight and come back plain.
 *
 * The parse rule is narrowed to marks that carry a colour, and the priority puts it in
 * front of that Highlight, so a bare `<mark>` - what Filament's own `highlight` tool
 * writes - still belongs to it.
 */
class TextBackground extends Mark
{
    /**
     * @var string
     */
    public static $name = 'textBackground';

    /**
     * @var int
     */
    public static $priority = 1000;

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
                'tag' => 'mark',
                'getAttrs' => fn ($DOMNode): ?bool => static::readColor($DOMNode) === null ? false : null,
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'color' => [
                'parseHTML' => fn ($DOMNode): ?string => static::readColor($DOMNode),
                'renderHTML' => function ($attributes): array {
                    $color = null;

                    if (is_array($attributes)) {
                        $color = $attributes['color'] ?? null;
                    } elseif (is_object($attributes)) {
                        $color = $attributes->color ?? null;
                    }

                    // Security: the colour ends up inside a `style` attribute, which
                    // Filament's HTML sanitiser lets through untouched. Filament's own
                    // colour handling runs the same guard, and the vendor highlight mark
                    // notably does not - a value like `red;position:fixed;inset:0` would
                    // otherwise escape the declaration it was written into.
                    $color = static::sanitizeColor($color);

                    return filled($color)
                        ? [
                            'data-color' => $color,
                            'style' => 'background-color: '.$color,
                        ]
                        : [];
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
            'mark',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes),
            0,
        ];
    }

    /**
     * Reads the colour from the attribute the browser writes, falling back to the inline
     * style so hand-written markup behaves the same.
     */
    protected static function readColor(mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $color = $DOMNode->getAttribute('data-color');

        if (blank($color)) {
            $style = $DOMNode->getAttribute('style');

            if (filled($style) && preg_match('/(?:^|;)\s*background-color\s*:\s*([^;]+)/i', $style, $matches) === 1) {
                $color = trim($matches[1]);
            }
        }

        return static::sanitizeColor($color);
    }

    /**
     * Narrows a colour to something that cannot escape the `style` attribute it is written
     * into.
     *
     * Filament runs the same guard for its own colours through a `Str::sanitizeCssColor()`
     * macro, which is registered at runtime and therefore invisible to static analysis;
     * the rules are mirrored here so the mark carries its own guarantee. The vendor
     * highlight mark has no guard at all, and Filament's HTML sanitiser passes `style`
     * through untouched, so a value such as `red;position:fixed;inset:0` would otherwise
     * become a full-page overlay.
     */
    protected static function sanitizeColor(mixed $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = trim($color);

        if (blank($color)) {
            return null;
        }

        // #rgb, #rrggbb, #rrggbbaa
        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color) === 1) {
            return $color;
        }

        // A bare keyword such as `red` or `transparent`.
        if (preg_match('/^[a-z]+$/i', $color) === 1) {
            return $color;
        }

        // Functional notations, with the CSS metacharacters that could end the declaration
        // forbidden inside the parentheses.
        if (preg_match('/^(?:rgb|rgba|hsl|hsla|hwb|lab|lch|oklab|oklch|color)\([^();:"\']*\)$/i', $color) === 1) {
            return $color;
        }

        return null;
    }
}
