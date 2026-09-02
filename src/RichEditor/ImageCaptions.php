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
        // Two repairs, both inline for the same reason: the page this lands on does not
        // load this package's stylesheet, and without either of them the picture is placed
        // wrongly rather than merely styled plainly.
        //
        // Browsers indent `<figure>` by 40px on both sides, which pushes a captioned image
        // out of line with every paragraph around it. That is a user agent default rather
        // than anyone's design decision, so it comes off here.
        //
        // And a figure is a block, so it fills the column whatever the picture inside it
        // measures. Two things quietly break on that. A caption is centred, so over a
        // picture somebody resized it drifts off to the side of it - centred on the column
        // rather than under the picture. And `moveFloat()` below centres a picture by
        // moving `margin-inline: auto` onto this element, which does nothing at all to a
        // block that is already as wide as its container. Boxing the figure to its contents
        // is what makes both work, and it is placement rather than decoration - everything
        // else about how a caption looks is still left to `.fi-arte-figure`.
        //
        // Logical rather than physical, like the `margin-inline` beside it and like the
        // stylesheet half of the same repair. `width` names the horizontal axis; this names
        // the one the text runs along, and the two stop agreeing the moment a document is
        // set in a vertical writing mode - which is a thing a package carrying a direction
        // tool has to assume somebody will do.
        $figure->setAttribute('style', 'margin-inline: 0; inline-size: fit-content; max-inline-size: 100%;');

        // A `<figure>` is a block, and placing a picture inside a block places it within
        // the block rather than placing the block - a float reads as a caption sitting in a
        // column of its own with the text refusing to come near it, and a centred picture
        // is centred inside a figure that is still hard left. So the placement moves out to
        // the figure, along with the gap the extension wrote beside it.
        $this->moveFloat($image, $figure);

        $figcaption = $document->createElement('figcaption');
        // `textContent` escapes; a caption is text somebody typed, not markup.
        $figcaption->textContent = $caption;

        $replaceable->parentNode?->replaceChild($figure, $replaceable);

        $figure->appendChild($image);
        $figure->appendChild($figcaption);
    }

    /**
     * The declarations that place a picture, taken off the image and put on the figure
     * around it.
     *
     * Only these: everything else the image carries - its width, its height, the transform
     * of a turned picture - describes the picture and belongs where it is.
     *
     * Matched on the value as well as on the property, because two of these names are not
     * this feature's alone. `margin-inline` is also what a quarter turn writes to make its
     * layout box match what is drawn, paired with a `margin-block` that is not on this
     * list - moving one half of that pair and leaving the other splits the compensation
     * across two elements and lays the picture across the lines around it. So only the
     * automatic margin travels, which is the one that centres.
     */
    protected function moveFloat(DOMElement $image, DOMElement $figure): void
    {
        $style = $image->getAttribute('style');

        if (blank($style)) {
            return;
        }

        $moved = [];
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);

            if ($declaration === '') {
                continue;
            }

            [$property, $value] = [...explode(':', $declaration, 2), ''];
            $property = strtolower(trim($property));
            $value = strtolower(trim($value));

            if (static::places($property, $value)) {
                $moved[] = $declaration;

                continue;
            }

            $kept[] = $declaration;
        }

        if ($moved === []) {
            return;
        }

        // After whatever the figure already says, because a longhand that follows a
        // shorthand is the one that counts - the figure carries `margin-inline: 0`.
        $figure->setAttribute('style', trim(implode('; ', [
            rtrim($figure->getAttribute('style'), '; '),
            ...$moved,
        ]), '; ').';');

        if ($kept === []) {
            $image->removeAttribute('style');

            return;
        }

        $image->setAttribute('style', implode('; ', $kept).';');
    }

    /**
     * Whether one declaration is part of placing the picture rather than describing it.
     */
    protected static function places(string $property, string $value): bool
    {
        return match ($property) {
            // A float and the gap beside it. `margin-block-end` is the float's alone: a
            // turn writes the shorthand `margin-block`, which is a different property.
            'float', 'margin-inline-start', 'margin-inline-end', 'margin-block-end' => true,
            // The centring pair, and only where it centres. A turn writes both of these
            // names too, with a length rather than `auto` and with `inline-flex` rather
            // than `block`.
            'margin-inline' => $value === 'auto',
            'display' => $value === 'block',
            default => false,
        };
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
