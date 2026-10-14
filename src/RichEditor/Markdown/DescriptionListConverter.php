<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Teaches the Markdown converter what a definition list is.
 *
 * Markdown has no list of this kind in its core, and the converter strips tags it has no
 * spelling for - so a glossary came out as its terms and descriptions run together into one
 * line. This writes the spelling Markdown Extra started and CommonMark's description list
 * extension reads, which is the one `fromMarkdown()` reads back:
 *
 * ```
 * Apple
 * : A fruit.
 *
 * Pear
 * : Another one.
 * ```
 *
 * A term on its own line, each of its descriptions on a line after a colon, and a blank line
 * before a term that follows a description - never between two terms, which CommonMark reads
 * as one entry's terms only while nothing separates them.
 */
class DescriptionListConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        // The children arrive converted already, so the value is Markdown, marks and all.
        $value = trim($element->getValue());

        return match ($element->getTagName()) {
            'dt' => ($this->followsADescription($element) ? "\n" : '').$value."\n",
            'dd' => ': '.$value."\n",
            // A block like a paragraph: the blank line after it, and none before - the block
            // in front of it already ended on one.
            default => $value."\n\n",
        };
    }

    /**
     * Whether the element before this one in its list, whitespace aside, is a description -
     * which is what makes this term the start of a new entry.
     */
    protected function followsADescription(ElementInterface $element): bool
    {
        $previous = $element->getPreviousSibling();

        while ($previous !== null && $previous->isWhitespace()) {
            $previous = $previous->getPreviousSibling();
        }

        return $previous?->getTagName() === 'dd';
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedTags(): array
    {
        return ['dl', 'dt', 'dd'];
    }
}
