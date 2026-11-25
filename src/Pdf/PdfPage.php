<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

/**
 * The sheet a document is laid out on: its size as it will be printed, and the margins.
 *
 * Everything in millimetres, and the size already turned the way it will come out - a
 * landscape A4 is 297 by 210 here, not A4 with a flag beside it. A driver then never has to
 * know about orientation at all, and the three that exist ask for it three different ways.
 */
final readonly class PdfPage
{
    public function __construct(
        public float $width,
        public float $height,
        public float $marginTop,
        public float $marginRight,
        public float $marginBottom,
        public float $marginLeft,
    ) {}

    public const POINTS_PER_MILLIMETRE = 72 / 25.4;

    /**
     * A named sheet, turned and with margins where they are given and from the configuration
     * where they are not - the sheet an export prints on, and the one a page view draws.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $margins  Clockwise from the top.
     */
    public static function configured(?PageFormat $format = null, ?bool $isLandscape = null, ?array $margins = null): self
    {
        [$width, $height] = ($format ?? self::configuredFormat())->dimensions();

        if (($isLandscape ?? self::isConfiguredLandscape()) === ($width < $height)) {
            [$width, $height] = [$height, $width];
        }

        [$top, $right, $bottom, $left] = $margins ?? self::configuredMargins();

        return new self($width, $height, $top, $right, $bottom, $left);
    }

    public static function configuredFormat(): PageFormat
    {
        $format = (string) config('filament-advanced-rich-editor.pdf.page', 'a4');

        return PageFormat::tryFrom(strtolower($format)) ?? PageFormat::A4;
    }

    public static function isConfiguredLandscape(): bool
    {
        return config('filament-advanced-rich-editor.pdf.orientation', 'portrait') === 'landscape';
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function configuredMargins(): array
    {
        return self::shorthand(array_map('floatval', (array) config('filament-advanced-rich-editor.pdf.margins', [20, 20, 20, 20])));
    }

    /**
     * Margins as CSS reads its shorthand: one value for all four sides, two for top and
     * bottom then the sides, three for top, the sides and bottom, four clockwise from the
     * top. Anything else is the default twenty millimetres all round.
     *
     * @param  array<int|string, float>  $values
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function shorthand(array $values): array
    {
        $values = array_values($values);

        return match (count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            4 => [$values[0], $values[1], $values[2], $values[3]],
            default => [20.0, 20.0, 20.0, 20.0],
        };
    }

    /**
     * The size in PostScript points, which is what DomPDF's paper takes.
     *
     * @return array{0: float, 1: float}
     */
    public function sizeInPoints(): array
    {
        return [$this->width * self::POINTS_PER_MILLIMETRE, $this->height * self::POINTS_PER_MILLIMETRE];
    }

    public function isLandscape(): bool
    {
        return $this->width > $this->height;
    }

    /**
     * The `@page` rule the document carries, so the markup describes its own sheet.
     *
     * DomPDF reads the margins from here and nowhere else; a Chromium driver reads both, and
     * is told the size natively as well, which is what it believes when the two disagree.
     */
    public function toCss(): string
    {
        return sprintf(
            '@page { size: %smm %smm; margin: %smm %smm %smm %smm; }',
            self::number($this->width),
            self::number($this->height),
            self::number($this->marginTop),
            self::number($this->marginRight),
            self::number($this->marginBottom),
            self::number($this->marginLeft),
        );
    }

    /**
     * The cover's sheet: no margin on the first page, and the margins as the cover's own
     * padding instead.
     *
     * The margin is what keeps the header and the footer off the cover. DomPDF repeats a
     * fixed element on every page from the first, whatever stands before it, and places it
     * against the page's content box - so they hang into the margins on every page, and on
     * one without margins they hang off the sheet.
     */
    public function coverCss(): string
    {
        return sprintf(
            '@page :first { margin: 0; } .fi-arte-pdf-cover { padding: %smm %smm %smm %smm; }',
            self::number($this->marginTop),
            self::number($this->marginRight),
            self::number($this->marginBottom),
            self::number($this->marginLeft),
        );
    }

    /**
     * Where the header and the footer stand: in the margins, two fifths of the way in from
     * the edge of the sheet, which leaves them clear of the text at any margin that holds
     * them at all.
     */
    public function runningElementsCss(): string
    {
        return sprintf(
            '.fi-arte-pdf-header { top: -%smm; } .fi-arte-pdf-footer { bottom: -%smm; }',
            self::number($this->marginTop * 0.6),
            self::number($this->marginBottom * 0.6),
        );
    }

    /**
     * A length as CSS writes it: no trailing zeros, and never in the locale's own notation -
     * `sprintf('%F')` rather than a cast, because a cast under `de_DE` writes a comma.
     */
    public static function number(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return ($formatted === '-0') ? '0' : $formatted;
    }
}
