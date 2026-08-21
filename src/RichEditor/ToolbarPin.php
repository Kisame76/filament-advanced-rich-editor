<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * The point a toolbar splits at.
 *
 * Everything after it is pinned to one edge of the bar instead of travelling with the
 * groups that are aligned on it: the buttons that are about the editor rather than about
 * the text - the source view, the fullscreen switch, the help dialog - belong in a corner
 * and should stay in that corner however the rest of the bar is arranged.
 *
 * It is a marker rather than a component: it draws nothing, and the view never renders it.
 * The field splits the resolved toolbar on it and hands the two halves out separately, so
 * a marker that ended up somewhere it cannot be used simply disappears.
 */
final class ToolbarPin
{
    public static function make(): self
    {
        return new self;
    }
}
