<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Export\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kisame76\FilamentAdvancedRichEditor\Export\DocumentExport;

/**
 * A queued export has been written. For whatever a project does next that is not a Filament
 * notification: a mail with the file attached, a webhook, a cleanup scheduled for later.
 */
class DocumentExported
{
    use Dispatchable;

    public function __construct(
        public DocumentExport $export,
        public string $path,
        public string $disk,
    ) {}
}
