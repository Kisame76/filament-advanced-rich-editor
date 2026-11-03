<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts;

/**
 * A file attachment provider that keeps other sizes of a picture beside the picture.
 *
 * Apart from Filament's own contract, which has no such question, so a provider a project
 * wrote for itself goes on working without it - and its pictures are rendered with the one
 * address they always had.
 */
interface ProvidesSrcset
{
    /**
     * The other sizes of the picture behind an attachment id, as a `srcset` lists them -
     * `address 640w, address 320w` - or null where there are none to offer.
     *
     * Only addresses that keep working: a list is written into the page and may be cached
     * with it.
     */
    public function getFileAttachmentSrcset(mixed $file): ?string;
}
