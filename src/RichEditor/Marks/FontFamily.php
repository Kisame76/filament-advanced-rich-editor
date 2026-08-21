<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontPicker;
use Tiptap\Core\Mark;
use Tiptap\Utils\HTML;

/**
 * The PHP half of the typeface mark.
 *
 * Content is stored as HTML and pushed back through the PHP editor on every hydration, so
 * without this the `<span style="font-family: …">` a choice produces would be read as an
 * unknown span and dropped - a typeface would last exactly until the record was reopened.
 *
 * The value is checked rather than trusted on the way in and on the way out. It ends up in
 * a `style` attribute that Filament's sanitiser passes through untouched, which makes this
 * the one place where a family name that is really a stylesheet gets stopped.
 */
class FontFamily extends Mark
{
    /**
     * @var string
     */
    public static $name = 'fontFamily';

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
                // `false` rejects the rule, leaving spans without a family to other marks.
                'getAttrs' => fn ($DOMNode): ?bool => static::readFamily($DOMNode) === null ? false : null,
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'family' => [
                'parseHTML' => fn ($DOMNode): ?string => static::readFamily($DOMNode),
                'renderHTML' => function ($attributes): array {
                    $family = is_array($attributes)
                        ? ($attributes['family'] ?? null)
                        : (is_object($attributes) ? ($attributes->family ?? null) : null);

                    $family = ToolbarFontPicker::sanitise(is_string($family) ? $family : null);

                    return $family === null ? [] : ['style' => 'font-family: '.$family];
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

    protected static function readFamily(mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $style = $DOMNode->getAttribute('style');

        if (blank($style) || preg_match('/(?:^|;)\s*font-family\s*:\s*([^;]+)/i', $style, $matches) !== 1) {
            return null;
        }

        return ToolbarFontPicker::sanitise($matches[1]);
    }
}
