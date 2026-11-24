<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts;

use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;

/**
 * Turns a finished HTML document into the bytes of a PDF.
 *
 * Bytes rather than a path, and the choice follows from what the three engines offer:
 * DomPDF's `output()` and a Gotenberg response are bytes already, and Browsershot's `pdf()`
 * returns them too, writing and reading its own temporary file either way. A driver that
 * took a path would send two of the three through a file for nothing; `save()` is one
 * `put()` on top of the bytes, wherever the caller wants them.
 *
 * The document arrives complete: every picture inlined, every style reduced to what a PDF
 * engine reads, the sheet described in `@page`. A driver fetches nothing and decides nothing
 * about the content - it only knows how to print.
 */
interface PdfDriver
{
    public function render(string $html, PdfPage $page): string;
}
