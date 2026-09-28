<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A video or a sound that lives on this server, as the element a browser will actually play.
 *
 * One node for both, and not two. `<video>` and `<audio>` are the same node wearing a
 * different tag - an address, a preload, a loop - and the one thing that differs, the
 * poster, is an attribute a sound simply has no use for. Two nodes would have been two of
 * every parse rule, every render branch and every test, to say the same thing twice.
 *
 * Nothing has to be unlocked in the application's sanitiser for this, which is the whole
 * reason it is a smaller job than the embed was. `video`, `audio`, `source` and `track` are
 * on Symfony's safe element list, `allowSafeElements()` puts every safe attribute on every
 * safe element, and `src`, `controls`, `preload`, `poster` and `loop` are all safe there.
 * `autoplay` is the one that is not - it is marked unsafe and would be stripped - and this
 * node does not offer it, which is the right answer twice over.
 *
 * `controls` is written unconditionally and is not an attribute anyone can turn off. A
 * media element without controls is a file nobody can play and nobody can see is there;
 * the two states worth having are "in the document" and "not in the document".
 */
class Media extends Node
{
    /**
     * @var string
     */
    public static $name = 'media';

    /**
     * @var string
     */
    public static $group = 'block';

    /**
     * @var bool
     */
    public static $atom = true;

    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            'kind' => [
                'default' => 'video',
                // The tag itself, which is the one place the answer cannot be wrong.
                'parseHTML' => static fn ($DOMNode): string => MediaUrl::kind(
                    $DOMNode instanceof DOMElement ? $DOMNode->nodeName : null,
                    static::source($DOMNode),
                ),
            ],
            'src' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => MediaUrl::src(static::source($DOMNode)),
            ],
            // The attachment this node points at, where it points at one at all.
            //
            // The same `data-id` Filament's own image node carries, and it has to be the same
            // for a reason that is not tidiness: the id is what `resolveFileAttachmentIds()`
            // collects, and what that method returns is the list `cleanUpFileAttachments()`
            // spares. A video whose id nothing walks is a video deleted by the next save of
            // the same record - see `Media/FileAttachments.php`.
            //
            // `data-id` is one of the six `data-*` attributes Filament's sanitiser passes
            // through, so it reaches the database. A file pointed at by a plain address and
            // nothing else simply has no id, which is the ordinary case for a typed URL.
            'id' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => ($DOMNode instanceof DOMElement)
                    ? ($DOMNode->getAttribute('data-id') ?: null)
                    : null,
            ],
            'poster' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => $DOMNode instanceof DOMElement
                    ? MediaUrl::src($DOMNode->getAttribute('poster'))
                    : null,
            ],
            'title' => [
                'default' => null,
                'parseHTML' => static fn ($DOMNode): ?string => $DOMNode instanceof DOMElement
                    ? ($DOMNode->getAttribute('title') ?: null)
                    : null,
            ],
            'preload' => [
                'default' => 'metadata',
                'parseHTML' => static fn ($DOMNode): string => MediaUrl::preload(
                    $DOMNode instanceof DOMElement ? $DOMNode->getAttribute('preload') : null,
                ),
            ],
            'loop' => [
                'default' => false,
                'parseHTML' => static fn ($DOMNode): bool => $DOMNode instanceof DOMElement
                    && $DOMNode->hasAttribute('loop'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            ['tag' => 'video'],
            ['tag' => 'audio'],
        ];
    }

    /**
     * The attributes are read off the node rather than out of `$HTMLAttributes`, for the
     * reason `Embed` documents: the serialiser calls this a second time with the node
     * alone to work out the closing tag.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>|null
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $attributes = (array) ($node->attrs ?? []);

        $src = MediaUrl::src($attributes['src'] ?? null);

        // Nothing to play. An element with no `src` is a broken control bar in the middle
        // of the page - the reader sees something is wrong and the author sees nothing.
        if ($src === null) {
            return null;
        }

        $kind = MediaUrl::kind($attributes['kind'] ?? null, $src);

        return [
            $kind,
            HTML::mergeAttributes([
                'src' => $src,
                'data-id' => $attributes['id'] ?? null,
                'controls' => 'controls',
                'preload' => MediaUrl::preload($attributes['preload'] ?? null),
                'loop' => ($attributes['loop'] ?? false) ? 'loop' : null,
                'title' => $attributes['title'] ?? null,
                'poster' => $kind === 'video' ? MediaUrl::src($attributes['poster'] ?? null) : null,
                'class' => 'fi-arte-media fi-arte-media-'.$kind,
                // Inline rather than left to the class, the way the embed's shape is: this
                // package's stylesheet is loaded into the admin panel and the page the
                // content ends up on is somebody else's. A `<video>` arriving there with
                // only a class on it is drawn at its own pixel size and overflows the
                // column. `style` survives the sanitiser, so the width travels with it.
                'style' => $kind === 'video' ? 'width: 100%; height: auto;' : 'width: 100%;',
            ]),
            null,
        ];
    }

    /**
     * The address an element points at: its own `src`, or the first `<source>` inside it.
     *
     * The second is what a hand-written document and most other editors produce, and
     * reading it is what turns one into a node instead of dropping it. Only the first
     * source is kept - this node plays one file, and a list of formats is a thing to state
     * once the browser needs it rather than a thing to carry through an editor that would
     * silently lose all but one of them anyway.
     */
    protected static function source(mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        // Filled rather than merely present. A `<video src="">` with a `<source>` inside it
        // is an element pointing at its source, and reading the empty attribute would have
        // this half drop a node the browser half keeps - the two would then disagree about
        // whether the document holds a player at all.
        if (filled($DOMNode->getAttribute('src'))) {
            return $DOMNode->getAttribute('src');
        }

        $source = $DOMNode->getElementsByTagName('source')->item(0);

        return $source instanceof DOMElement ? $source->getAttribute('src') : null;
    }
}
