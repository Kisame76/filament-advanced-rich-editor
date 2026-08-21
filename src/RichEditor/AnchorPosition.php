<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Where the link to a heading's own anchor is drawn, if it is drawn at all.
 */
enum AnchorPosition: string
{
    /**
     * The heading is anchored and nothing is added to it. The default, because the usual
     * reason to want anchors is a table of contents linking into the page - and a marker
     * appearing next to every heading is a visible change to a design nobody asked to
     * change.
     */
    case None = 'none';

    /**
     * A marker in front of the heading text.
     */
    case Before = 'before';

    /**
     * A marker after the heading text.
     */
    case After = 'after';

    /**
     * The heading text itself becomes the link, with no marker. What documentation sites
     * do when the whole heading should be clickable.
     */
    case Wrap = 'wrap';
}
