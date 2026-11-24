<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use RuntimeException;

/**
 * A driver was asked to print and the library it prints with is not installed.
 *
 * None of them is a dependency of this package. An editor field has no business pulling a
 * PDF engine and its fonts into every application that uses it, and the engines exclude
 * each other anyway - a project printing through Gotenberg needs neither of the others.
 */
class MissingPdfLibrary extends RuntimeException
{
    public static function for(string $driver, string $package): self
    {
        return new self("The [{$driver}] PDF driver needs [{$package}]. Install it with `composer require {$package}`.");
    }
}
