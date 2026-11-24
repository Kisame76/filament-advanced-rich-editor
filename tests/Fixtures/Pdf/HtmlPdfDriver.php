<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Pdf;

use Kisame76\FilamentAdvancedRichEditor\Pdf\Contracts\PdfDriver;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfDocument;

/**
 * A driver that "prints" the HTML it is handed, so a test can read what went into the file
 * without an engine.
 */
class HtmlPdfDriver implements PdfDriver
{
    public function render(PdfDocument $document): string
    {
        return $document->html;
    }
}
