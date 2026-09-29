<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * A pool whose files can be swapped for new ones without their ids changing.
 *
 * Apart from `MediaSource` rather than part of it, because adding a method to that interface
 * would break every source a project wrote for itself. A source that does not implement this
 * one simply has no Replace button - which is also the honest answer for a pool it cannot
 * write to.
 *
 * The id staying is the whole point. Documents point at the id, so every one of them shows the
 * new file the next time it is drawn: a price list is replaced once, not once per page that
 * links to it.
 */
interface ReplacesMedia
{
    /**
     * What may take this item's place, in the form a file picker's `accept` list reads it -
     * endings with their dot, and mime types - or null where nothing may: an embed, which is
     * a link rather than a file, and an id outside the pool.
     *
     * @return array<int, string>|null
     */
    public function replacementTypes(mixed $id): ?array;

    /**
     * Puts an upload in the item's place, keeping its id.
     *
     * Checks again what `replacementTypes()` promised - the family, and the ending where the
     * ending is part of the id - and everything an upload is asked on its way in. Answers
     * `false` rather than raising: a refusal is something the panel says, not a 500.
     *
     * The upload's name is taken over wherever the pool keeps a name apart from the id, and
     * left alone where the name *is* the id. What the item is called afterwards is read back
     * out of `MediaSource::details()`, which is where every card is rewritten from.
     */
    public function replace(mixed $id, UploadedFile $file): bool;
}
