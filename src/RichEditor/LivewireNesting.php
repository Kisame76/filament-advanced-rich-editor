<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use RuntimeException;

/**
 * The one installation step this package cannot perform for you, said by the code instead of
 * only by the README.
 *
 * Livewire caps how deep a property path may be, and answers anything deeper with a 500. A
 * rich editor entangles a document rather than a string, so the path to a word is the path
 * through the document: text inside a list item is
 * `data.content.content.0.content.0.content.0.content.0.text`, eleven segments, and each
 * further level of nesting costs four more. Livewire ships with ten.
 *
 * What makes this worth a guard rather than a paragraph is when it goes wrong. Nothing fails
 * on install, nothing fails on the first paragraph, nothing fails on save. It fails the first
 * time somebody types inside a list - in an exception that names Livewire and a config key,
 * and neither this package nor the editor they were typing in.
 *
 * Two things about the reading are easy to get wrong, and both are asserted:
 *
 * - The value is read, not the key. Livewire merges its own config file, so
 *   `config('livewire.payload.max_nesting_depth')` answers `10` on an installation that
 *   never published `livewire.php`. A check for a missing key would find one and pass.
 * - `null` means "no limit", not "not set". Livewire skips its own check on null
 *   (`$maxDepth !== null && …`), so null is the deepest possible answer rather than the
 *   shallowest.
 */
class LivewireNesting
{
    /**
     * How deep a paragraph inside a list item sits: `data`, `content`, and then four pairs
     * of `content` and an index down to the text node. Measured against the entangled state
     * path rather than taken from the documentation.
     */
    public const LIST_ITEM_DEPTH = 11;

    /**
     * What the guard asks for by default, and what the README has always recommended. Above
     * the point where a plain list breaks, with room for the nesting a real document has -
     * every further level costs four. A project that knows its documents stay flat can ask
     * for less through `->nestingCheck(16)`.
     */
    public const REQUIRED = 32;

    /**
     * The limit Livewire will actually apply. `null` is a value, not an absence.
     */
    public static function limit(): ?int
    {
        $limit = config('livewire.payload.max_nesting_depth');

        return is_numeric($limit) ? (int) $limit : null;
    }

    public static function isEnough(?int $limit, int $required): bool
    {
        return $limit === null || $limit >= $required;
    }

    /**
     * @throws RuntimeException where the editor would break on the first list
     */
    public static function guard(int $required): void
    {
        $limit = static::limit();

        if (static::isEnough($limit, $required)) {
            return;
        }

        throw new RuntimeException(
            "Livewire is configured with a maximum nesting depth of {$limit}, and the advanced rich editor needs at least {$required}. "
            .'Text inside a list item is '.static::LIST_ITEM_DEPTH.' levels deep in an entangled document, so typing in any list would answer with a 500. '
            ."Publish Livewire's config with `php artisan livewire:publish --config` and raise `payload.max_nesting_depth` in `config/livewire.php` to {$required}. "
            .'If this field will never hold a list and you would rather keep the limit where it is, switch the check off with `->nestingCheck(false)`.',
        );
    }
}
