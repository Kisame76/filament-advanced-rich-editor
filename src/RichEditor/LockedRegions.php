<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Locked;

/**
 * Whether the locked parts of a document came through a save untouched.
 *
 * The editor already refuses every change to them, but the editor is in the browser, and what
 * the browser sends can be anything. So the save is checked again here, against the document
 * as it was before - the record's, or the field's default for a new one: every locked part it
 * had has to be in the new one, holding exactly what it held, and nothing else may be locked.
 *
 * Compared as a collection rather than in order, because moving a locked part is not changing
 * it. And compared by the markup each part renders to rather than by its tree: the browser's
 * tree carries every attribute the editor's JavaScript knows, the server's only those this
 * side knows, and the same part would otherwise differ from itself.
 */
final class LockedRegions
{
    /**
     * The locked parts of a document, outermost first. A part inside another is part of it.
     *
     * @param  array<string, mixed>|null  $document
     * @return array<int, array<string, mixed>>
     */
    public static function in(?array $document): array
    {
        if ($document === null) {
            return [];
        }

        if (($document['type'] ?? null) === Locked::$name) {
            return [$document];
        }

        $found = [];

        foreach ($document['content'] ?? [] as $child) {
            if (is_array($child)) {
                array_push($found, ...self::in($child));
            }
        }

        return $found;
    }

    /**
     * Whether `$after` holds exactly the locked parts `$before` held.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  Closure(array<string, mixed>): string  $render  a part as markup
     */
    public static function kept(?array $before, ?array $after, Closure $render): bool
    {
        $parts = static function (?array $document) use ($render): array {
            $rendered = array_map($render, self::in($document));
            sort($rendered);

            return $rendered;
        };

        return $parts($before) === $parts($after);
    }
}
