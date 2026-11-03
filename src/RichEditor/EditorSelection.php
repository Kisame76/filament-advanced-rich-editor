<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * The selection a dialog was opened from, as the node it was opened on.
 *
 * A click on a picture, a card or a player selects the node whole, but the selection the
 * toolbar hands a dialog can describe a caret just past it instead - text rather than node,
 * the anchor one position further on. A command run against that does nothing to the node
 * (`updateAttributes` writes nothing while the picture sits there visibly selected), or puts
 * what replaces it beside it rather than in its place. Silently either way, because a dialog
 * that closes looks like it worked. Filament makes the same correction in its own image
 * dialog; every dialog here that rewrites the node it was opened on makes it through this.
 */
final class EditorSelection
{
    public static function onTheNode(mixed $selection): mixed
    {
        if (! is_array($selection) || (($selection['type'] ?? null) === 'node')) {
            return $selection;
        }

        $selection['type'] = 'node';

        if (is_int($selection['anchor'] ?? null)) {
            $selection['anchor']--;
        }

        unset($selection['head']);

        return $selection;
    }
}
