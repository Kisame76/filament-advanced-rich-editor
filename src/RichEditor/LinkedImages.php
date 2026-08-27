<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;

/**
 * The anchor around a picture that points somewhere.
 *
 * Built here rather than carried in the document, the same bargain the caption makes: an
 * attribute cannot build a structure, and rebuilding Filament's image node to get one would
 * mean owning its resizing, its uploads and its node view for the sake of a link.
 *
 * Runs after `ImageCaptions`, and that order is the whole of what this file has to get
 * right. A captioned picture is a `<figure>` holding the picture and its caption, and the
 * link belongs around the picture inside it rather than around the pair: a caption is text
 * about the picture, not part of what is being linked, and an anchor wrapping a
 * `<figcaption>` makes the words a click target nobody aimed at. Wrapping first and
 * captioning second would produce exactly that, because the caption pass replaces the block
 * the picture sits in.
 */
class LinkedImages
{
    public function apply(string $html): string
    {
        // Cheap way out for the documents with no linked picture in them, which is most of
        // them. The same guard the caption pass opens with.
        if (! str_contains($html, 'data-href')) {
            return $html;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-link-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $images = (new DOMXPath($document))->query('//img[@data-href]');

        if ($images === false) {
            return $html;
        }

        foreach (iterator_to_array($images) as $image) {
            if ($image instanceof DOMElement) {
                $this->wrap($document, $image);
            }
        }

        $root = $document->getElementById('arte-link-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    protected function wrap(DOMDocument $document, DOMElement $image): void
    {
        $href = ImageLink::normalise($image->getAttribute('data-href'));
        $newTab = $image->getAttribute('data-href-new-tab') === 'true';

        // Whatever happens next, neither attribute reaches the page: they say something the
        // reader either gets as a link or does not get at all.
        $image->removeAttribute('data-href');
        $image->removeAttribute('data-href-new-tab');

        if ($href === null) {
            return;
        }

        // A picture already inside a link is left alone. That is markup somebody wrote in
        // the source view, and a second anchor around it would nest one link inside another
        // - which no browser and no reader can make sense of.
        if ($this->isInsideAnchor($image)) {
            return;
        }

        $anchor = $document->createElement('a');
        $anchor->setAttribute('href', $href);

        if ($newTab) {
            $anchor->setAttribute('target', '_blank');
            // Written whether or not anybody asked, the same reasoning `LinkAction` gives
            // for a text link: `target="_blank"` hands the opened page a handle on the
            // window that opened it, and nobody ticking "new tab" is thinking about that.
            $anchor->setAttribute('rel', 'noopener noreferrer');
        }

        $parent = $image->parentNode;

        // Both statements or neither. `appendChild()` moves the picture into the anchor
        // whether or not the anchor is in the document, so a null-safe replace followed by
        // an unconditional append would take a parentless picture out of the output
        // altogether - a guard that reads like one and drops the thing it guards.
        if ($parent === null) {
            return;
        }

        $parent->replaceChild($anchor, $image);
        $anchor->appendChild($image);
    }

    /**
     * Whether the picture already sits inside a link.
     */
    protected function isInsideAnchor(DOMElement $image): bool
    {
        $parent = $image->parentNode;

        while ($parent instanceof DOMElement) {
            if ($parent->tagName === 'a') {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }
}
