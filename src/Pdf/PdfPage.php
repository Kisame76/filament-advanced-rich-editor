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
     * A length as CSS writes it: no trailing zeros, and never in the locale's own notation -
     * `sprintf('%F')` rather than a cast, because a cast under `de_DE` writes a comma.
     */
    public static function number(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return ($formatted === '-0') ? '0' : $formatted;
    }
}
