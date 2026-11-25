<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Docx;

use RuntimeException;

/**
 * A Word file was asked for and PHPWord is not installed.
 *
 * It is not a dependency of this package, for the reason no PDF engine is one: an editor
 * field has no business pulling a word processor's file format into every application that
 * uses it. A project that exports to Word installs it; one that does not never knows.
 */
class MissingDocxLibrary extends RuntimeException
{
    public static function make(): self
    {
        return new self('Writing a Word file needs [phpoffice/phpword]. Install it with `composer require phpoffice/phpword`.');
    }
}
