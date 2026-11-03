<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;

/**
 * The anchor around a picture that points somewhere - or, for a lightbox, at itself.
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
    /**
     * The class the lightbox script finds its links by. A class rather than a `data-`
     * attribute, because Filament's sanitiser lets a class through on anything and only a few
     * named `data-` attributes - and one an app allowed for this would be allowed for every
     * document it ever sanitises.
     */
    public const LIGHTBOX_CLASS = 'fi-arte-lightbox';

    /**
     * @param  bool  $lightbox  whether every other picture is to open large
     */
    public function __construct(
        protected bool $lightbox = false,
    ) {}

    public function apply(string $html): string
    {
        // Cheap way out for the documents with no linked picture in them, which is most of
        // them. The same guard the caption pass opens with.
        if (! str_contains($html, 'data-href') && ! ($this->lightbox && str_contains($html, '<img'))) {
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

        $images = (new DOMXPath($document))->query($this->lightbox ? '//img' : '//img[@data-href]');

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
        if (! $image->hasAttribute('data-href')) {
            $this->wrapForLightbox($document, $image);

            return;
        }

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

        $this->putInside($anchor, $image);
    }

    /**
     * A link from the picture to its own address, which the lightbox script opens in a box
     * over the page - and which, with no script, opens the picture.
     *
     * Not around a picture that says it carries nothing: a link is called what the picture
     * inside it is called, and an empty one is worse than a picture that does not open. Not
     * around one whose source is no address either - an inline `data:` picture - since there
     * is nothing a link could point at.
     */
    protected function wrapForLightbox(DOMDocument $document, DOMElement $image): void
    {
        if (! $this->lightbox || ($image->getAttribute('role') === 'presentation') || $this->isInsideAnchor($image)) {
            return;
        }

        $href = ImageLink::normalise($image->getAttribute('src'));

        if ($href === null) {
            return;
        }

        $anchor = $document->createElement('a');
        $anchor->setAttribute('href', $href);
        $anchor->setAttribute('class', static::LIGHTBOX_CLASS);

        $this->putInside($anchor, $image);
    }

    protected function putInside(DOMElement $anchor, DOMElement $image): void
    {
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
