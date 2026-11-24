<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts;

/**
 * A driver whose engine prints the header and the footer itself, from templates, rather than
 * as part of the document.
 *
 * Chromium's are: it draws them in the margins, fills the page number and the number of
 * pages into its own `pageNumber` and `totalPages` classes, and would print the same markup a
 * second time if it were also in the document. Such a driver receives them on `PdfDocument`
 * and a document without them; every other driver receives them inside the document.
 */
interface TakesHeaderAndFooter {}
