<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Concerns;

/**
 * One attribute of a node or a mark, out of what a `renderHTML` callback is handed: an object
 * or an array, whichever it is.
 */
trait ReadsAttributes
{
    protected static function read(mixed $attributes, string $key): mixed
    {
        if (is_array($attributes)) {
            return $attributes[$key] ?? null;
        }

        if (is_object($attributes)) {
            return $attributes->{$key} ?? null;
        }

        return null;
    }
}
