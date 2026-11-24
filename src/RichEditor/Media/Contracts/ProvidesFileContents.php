<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts;

/**
 * A file attachment provider that can hand over the file itself, not only its address.
 *
 * Asked by the PDF export, which embeds every picture rather than fetching it: a PDF engine
 * that requests addresses is one a document can point at the server's own network, and an
 * address on a private disk expires before a queued export may get to it. A provider without
 * this contract keeps working - its pictures are taken by address, where the export is
 * allowed to fetch that address at all.
 */
interface ProvidesFileContents
{
    /**
     * The bytes of the file behind an attachment id - the version the address would have
     * pointed at - or null where there is no such file.
     */
    public function getFileAttachmentContents(mixed $file): ?string;
}
