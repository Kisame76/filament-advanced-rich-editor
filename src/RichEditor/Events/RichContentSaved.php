<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * A rich content column was written.
 *
 * The one question every application eventually asks of an editor and the one neither the
 * editor nor the renderer can answer: optimise the pictures in this document, check its
 * links, clear the cache that renders it. All three want the moment the document was stored,
 * and that moment belongs to the model rather than to the field - a record written by an
 * import, a queued job or tinker never went near a field at all.
 *
 * One event per changed column rather than one per save, because a listener acts on a
 * document and a model may hold two. What it carries is what a listener would otherwise have
 * to reconstruct: the record, which column, what is in it now, and what stood there before.
 *
 * Deliberately a plain object with no behaviour on it. Anything it could offer - the mentions
 * in the document, its plain text, its excerpt - is already a call somewhere else, and an
 * event that grew those would be a second place for them to disagree.
 */
final class RichContentSaved
{
    public function __construct(
        public readonly Model $record,
        public readonly string $attribute,
        public readonly mixed $content,
        /**
         * What the column held before this save, and null where there was no before: the
         * record was created by it.
         */
        public readonly mixed $previousContent,
        public readonly bool $wasRecentlyCreated,
    ) {}
}
