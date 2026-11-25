<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

/**
 * Reduces an inline `style` to what a PDF engine without a browser reads.
 *
 * The markup this package renders carries its shape in `style` on purpose - the page it
 * lands on never loaded the package's stylesheet - and writes it the way a browser today
 * wants it: logical properties, flex boxes, `rem`, custom properties for Filament's grid.
 * DomPDF knows CSS 2.1 and a little more. A declaration it cannot read is not ignored
 * gracefully every time: `display: inline-flex` falls back to `inline` and the box around a
 * file card collapses onto its text, `margin-inline: auto` is dropped and a centred picture
 * sits on the left.
 *
 * So each declaration is translated where CSS 2.1 has a way to say the same thing, and
 * dropped where it has none. Logical properties are read left to right: DomPDF has no
 * right-to-left layout for them to be mirrored in.
 *
 * A Chromium driver reads the reduced styles exactly as well as the originals; none of the
 * translations changes what a browser draws for a left-to-right document.
 */
class PdfStyles
{
    /**
     * What one `rem` is worth here: the browser default the inline styles were written
     * against, not the size of the document's text.
     */
    public const REM_IN_PIXELS = 16;

    /**
     * Properties that carry layout only a flex or grid container reads, or nothing a PDF
     * engine can draw.
     */
    protected const DROPPED = [
        'gap', 'row-gap', 'column-gap',
        'align-items', 'align-self', 'align-content',
        'justify-content', 'justify-items', 'justify-self',
        'place-items', 'place-content', 'place-self',
        'flex', 'flex-direction', 'flex-wrap', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-basis',
        'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows', 'grid-column', 'grid-row', 'grid-area',
        'aspect-ratio', 'object-fit', 'object-position',
        // DomPDF's fixed table layout shares the width out evenly and ignores every width a
        // cell asks for - exactly the widths `TableColumnWidths` fixed the layout to keep.
        // The automatic layout honours them, in DomPDF and in a browser alike.
        'table-layout',
    ];

    /**
     * Values a PDF engine reads as nonsense, and that would take the whole declaration with
     * them. `url()` is among them for a different reason: an address in a style is a fetch,
     * and nothing in a document gets to make the server fetch anything.
     */
    protected const UNREADABLE_VALUES = '/\b(?:var|color-mix|oklab|oklch|lab|lch|url|image-set|env|clamp|min|max)\s*\(|\b(?:fit-content|min-content|max-content)\b/i';

    public static function reduce(string $style): string
    {
        $reduced = [];

        foreach (static::declarations($style) as [$property, $value]) {
            foreach (static::translate($property, $value) as $translatedProperty => $translatedValue) {
                $reduced[$translatedProperty] = $translatedValue;
            }
        }

        return implode(' ', array_map(
            static fn (string $property, string $value): string => "{$property}: {$value};",
            array_keys($reduced),
            $reduced,
        ));
    }

    /**
     * The declarations in a style, split on the semicolons that end one - and not on one
     * inside parentheses, where a `data:` address keeps its own.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function declarations(string $style): array
    {
        $declarations = [];

        foreach (preg_split('/;(?![^(]*\))/', $style) ?: [] as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);

            $property = strtolower(trim($property));
            $value = trim($value);

            if (($property === '') || ($value === '')) {
                continue;
            }

            $declarations[] = [$property, $value];
        }

        return $declarations;
    }

    /**
     * @return array<string, string>
     */
    protected static function translate(string $property, string $value): array
    {
        // Filament's text colour: the colour rides in a custom property that its stylesheet
        // reads, and no PDF engine - nor a Word file - reads custom properties. The light one
        // is the colour on paper; `--dark-color` goes with the rest of them.
        if (($property === '--color') && (preg_match(self::UNREADABLE_VALUES, $value) !== 1)) {
            return ['color' => $value];
        }

        if (str_starts_with($property, '--') || in_array($property, self::DROPPED, strict: true)) {
            return [];
        }

        if (preg_match(self::UNREADABLE_VALUES, $value) === 1) {
            return [];
        }

        $value = static::absolute($value);

        foreach (['margin', 'padding'] as $box) {
            if ($property === "{$box}-inline") {
                return static::pair("{$box}-left", "{$box}-right", $value);
            }

            if ($property === "{$box}-block") {
                return static::pair("{$box}-top", "{$box}-bottom", $value);
            }
        }

        if (preg_match('/^border-inline(-(?:width|style|color))?$/', $property, $matches) === 1) {
            $suffix = $matches[1] ?? '';

            return ["border-left{$suffix}" => $value, "border-right{$suffix}" => $value];
        }

        if (preg_match('/^border-block(-(?:width|style|color))?$/', $property, $matches) === 1) {
            $suffix = $matches[1] ?? '';

            return ["border-top{$suffix}" => $value, "border-bottom{$suffix}" => $value];
        }

        $logical = [
            'inline-start' => 'left',
            'inline-end' => 'right',
            'block-start' => 'top',
            'block-end' => 'bottom',
        ];

        foreach ($logical as $side => $physical) {
            foreach (['margin', 'padding', 'border'] as $box) {
                if (str_starts_with($property, "{$box}-{$side}")) {
                    return [str_replace("{$box}-{$side}", "{$box}-{$physical}", $property) => $value];
                }
            }

            if ($property === "inset-{$side}") {
                return [$physical => $value];
            }
        }

        $sizes = [
            'inline-size' => 'width',
            'block-size' => 'height',
            'min-inline-size' => 'min-width',
            'max-inline-size' => 'max-width',
            'min-block-size' => 'min-height',
            'max-block-size' => 'max-height',
        ];

        if (isset($sizes[$property])) {
            return [$sizes[$property] => $value];
        }

        return match ($property) {
            'display' => static::display($value),
            'float' => ['float' => strtr(strtolower($value), ['inline-start' => 'left', 'inline-end' => 'right'])],
            'text-align' => ['text-align' => strtr(strtolower($value), ['start' => 'left', 'end' => 'right'])],
            'overflow-wrap' => ['word-wrap' => $value],
            default => [$property => $value],
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function display(string $value): array
    {
        $value = strtolower($value);

        return match ($value) {
            'flex', 'grid', 'flow-root' => ['display' => 'block'],
            'inline-flex', 'inline-grid' => ['display' => 'inline-block'],
            // `contents` takes the box away and keeps its children; CSS 2.1 has no way to
            // say that, and leaving the box is the smaller change.
            'contents' => [],
            default => ['display' => $value],
        };
    }

    /**
     * A logical shorthand as its two physical halves: one value for both, or the first for
     * the start and the second for the end.
     *
     * @return array<string, string>
     */
    protected static function pair(string $start, string $end, string $value): array
    {
        $parts = preg_split('/\s+/', $value) ?: [$value];

        return [$start => $parts[0], $end => $parts[1] ?? $parts[0]];
    }

    /**
     * Every `rem` in a value as pixels.
     */
    protected static function absolute(string $value): string
    {
        return (string) preg_replace_callback(
            '/(?<![\w.-])(-?\d*\.?\d+)rem\b/i',
            static fn (array $matches): string => PdfPage::number((float) $matches[1] * self::REM_IN_PIXELS).'px',
            $value,
        );
    }
}
