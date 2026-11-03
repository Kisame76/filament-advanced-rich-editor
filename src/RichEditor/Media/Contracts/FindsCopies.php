<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts;

/**
 * A pool that can say whether it already holds a file, by what is in it.
 *
 * Apart from `MediaSource` rather than part of it, because adding a method to that interface
 * would break every source a project wrote for itself. A source that does not implement this
 * one simply stores every upload, as every source always did.
 */
interface FindsCopies
{
    /**
     * The id of an item in the pool with exactly these bytes, or null where there is none.
     *
     * The size is asked first, because it is free to ask: only a file of the same size can
     * hold the same bytes, and almost none is. The hash decides among those - see
     * `ContentHash`.
     */
    public function copyOf(int $size, string $hash): mixed;
}
