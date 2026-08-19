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
 * `style` but not arbitrary data attributes.
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

                    return filled($size)
                        ? ['style' => 'font-size: '.$size]
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

        $size = trim($matches[1]);

        return blank($size) ? null : $size;
    }
}
