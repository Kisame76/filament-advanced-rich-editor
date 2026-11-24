<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfExport;

/**
 * A queued export has been written. For whatever a project does next that is not a Filament
 * notification: a mail with the file attached, a webhook, a cleanup scheduled for later.
 */
class PdfExported
{
    use Dispatchable;

    public function __construct(
        public PdfExport $export,
        public string $path,
        public string $disk,
    ) {}
}
