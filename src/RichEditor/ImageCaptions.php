<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageCaption;

/**
 * Turns the caption an image carries into the markup a caption means.
 *
 * `<figure>` and `<figcaption>` are that markup, and both survive Filament's sanitiser.
 * The document stores the text on the image instead, because a `<figure>` is a structure
 * and a TipTap attribute can only add attributes - see `ImageCaption`.
 *
 * A paragraph holding nothing but the image is replaced by the figure rather than kept
 * around it: a figure inside a paragraph is markup browsers close early and disagree about.
 * An image sitting between words is left alone entirely - a figure is a block that stands
 * apart from the text, and an image in the middle of a sentence is not one.
 */
class ImageCaptions
{
    public const CLASS_NAME = 'fi-arte-figure';

    public function apply(string $html): string
    {
        if (! str_contains($html, 'data-caption')) {
            return $html;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-caption-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        foreach (iterator_to_array((new DOMXPath($document))->query('//img[@data-caption]')) as $image) {
            if ($image instanceof DOMElement) {
                $this->wrap($document, $image);
            }
        }

        $root = $document->getElementById('arte-caption-root');

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
        $caption = ImageCaption::normalise($image->getAttribute('data-caption'));

        // Whatever happens next, the attribute does not reach the page: it says something
        // the reader is either shown properly or not at all.
        $image->removeAttribute('data-caption');

        if ($caption === null) {
            return;
        }

        $replaceable = $this->blockToReplace($image);

        if ($replaceable === null) {
            return;
        }

        $figure = $document->createElement('figure');
        $figure->setAttribute('class', static::CLASS_NAME);
        // Browsers indent `<figure>` by 40px on both sides, which pushes a captioned image
        // out of line with every paragraph around it. That is a user agent default rather
        // than anyone's design decision, and the page this lands on does not load this
        // package's stylesheet - so it comes off here. Everything else about how a caption
        // looks is left to `.fi-arte-figure`.
        $figure->setAttribute('style', 'margin-inline: 0;');

        $figcaption = $document->createElement('figcaption');
        // `textContent` escapes; a caption is text somebody typed, not markup.
        $figcaption->textContent = $caption;

        $replaceable->parentNode?->replaceChild($figure, $replaceable);

        $figure->appendChild($image);
        $figure->appendChild($figcaption);
    }

    /**
     * What the figure takes the place of: the paragraph the image is alone in, or the image
     * itself where it stands on its own. Null where the image shares its line with text.
     */
    protected function blockToReplace(DOMElement $image): ?DOMElement
    {
        $parent = $image->parentNode;

        if (! $parent instanceof DOMElement) {
            return null;
        }

        if (strtolower($parent->nodeName) !== 'p') {
            return $image;
        }

        foreach ($parent->childNodes as $sibling) {
            if ($sibling === $image) {
                continue;
            }

            // Whitespace between block tags is the serialiser's, not the author's.
            if ($sibling->nodeType === XML_TEXT_NODE && trim($sibling->textContent) === '') {
                continue;
            }

            return null;
        }

        return $parent;
    }
}
