<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

use League\HTMLToMarkdown\Converter\LinkConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Teaches the Markdown converter what a document card is.
 *
 * Without it a card is an `<a>` holding three spans, and a converter that has never heard
 * of it does the only thing it can: it runs the three together into the link text. What
 * comes out is `[PDFQuartalsbericht Q3.pdf88 KB](/storage/…)` - the parts of the card in a
 * row, none of them a name.
 *
 * What survives instead is the link and what the file is called, which is all Markdown has
 * a place for. The kind and the size are drawn rather than written: restating them here
 * would put a second copy of both into a document the card no longer keeps up to date, and
 * a Markdown file claiming `88 KB` about a file that has since been replaced is worse than
 * one that never said.
 *
 * A block on its own line, because that is what the node is. Two cards in a row are two
 * blocks in the document and would otherwise come out as one run of link text.
 */
class FileCardConverter extends LinkConverter
{
    public function convert(ElementInterface $element): string
    {
        $href = trim($element->getAttribute('href'));

        // An ordinary link, which is most of them.
        if ($href === '' || ! static::isCard($element)) {
            return parent::convert($element);
        }

        // A card with nothing to call it. Better the address twice than an empty `[]`,
        // which some readers draw as nothing at all.
        // A blank line on both sides. One card after another, or a card followed by a
        // paragraph, would otherwise run together into a single line of Markdown - and a
        // blank line too many reads the same as one, while a blank line too few does not.
        return "\n\n[".(static::nameOf($element) ?? $href)."]({$href})\n\n";
    }

    /**
     * Whether this `<a>` is a card rather than a link.
     *
     * Two signs, and neither is `hasAttribute()` - `ElementInterface` has no such method,
     * so a bare `<a download>` is indistinguishable from a link carrying none. What is
     * asked instead is whether the element is shaped like a card: a name span is one this
     * package wrote, and a filled `download` is what a card always writes. The text of an
     * ordinary link is deliberately not a sign, or every link in the document would come
     * out as a block on its own line.
     */
    protected static function isCard(ElementInterface $element): bool
    {
        if (trim($element->getAttribute('download')) !== '') {
            return true;
        }

        foreach ($element->getChildren() as $child) {
            if (str_contains($child->getAttribute('class'), 'fi-arte-file-name')) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the card calls the file: its name span, else the `download` attribute, else the
     * link's own text - the same three readings `Nodes/FileCard` does, in the same order.
     */
    protected static function nameOf(ElementInterface $element): ?string
    {
        foreach ($element->getChildren() as $child) {
            if (str_contains($child->getAttribute('class'), 'fi-arte-file-name')) {
                $written = trim($child->getValue());

                if ($written !== '') {
                    return $written;
                }
            }
        }

        $download = trim($element->getAttribute('download'));

        if ($download !== '') {
            return $download;
        }

        $text = trim($element->getValue());

        return $text === '' ? null : $text;
    }
}
