<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

/**
 * The paper sizes a document is exported on, upright and in millimetres.
 *
 * Millimetres because that is what every one of them is defined in - Letter and Legal
 * included, whose inch values come out to the tenth of a millimetre. The three drivers each
 * want a different unit (DomPDF points, Gotenberg inches, Browsershot whatever it is told),
 * and converting once from the unit the standard uses keeps the three from rounding apart.
 */
enum PageFormat: string
{
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case Letter = 'letter';
    case Legal = 'legal';

    /**
     * Width and height, portrait.
     *
     * @return array{0: float, 1: float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::A3 => [297.0, 420.0],
            self::A4 => [210.0, 297.0],
            self::A5 => [148.0, 210.0],
            self::Letter => [215.9, 279.4],
            self::Legal => [215.9, 355.6],
        };
    }
}
