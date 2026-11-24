<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\PageBreak;
use League\HTMLToMarkdown\Converter\DivConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * A page break, in Markdown: nothing. Markdown has no pages, and the nearest thing it has -
 * `---` - is a horizontal rule, which a reader would see as a line that is not there. Every
 * other `div` goes on to the converter it would have met anyway.
 */
class PageBreakConverter extends DivConverter
{
    public function convert(ElementInterface $element): string
    {
        if ($element->getAttribute('data-type') === PageBreak::$name) {
            return '';
        }

        return parent::convert($element);
    }
}
