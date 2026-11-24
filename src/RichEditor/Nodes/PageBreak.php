<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * Where a printed document starts a new page.
 *
 * An empty element that asks for the break in its own `style`, not only through a class: a
 * page rendering stored content may never load this package's stylesheet, and a browser
 * printing it, DomPDF and Chromium all read the inline declaration. On a screen it is
 * nothing at all - an empty block with no height. The editor draws it as a labelled line;
 * see `page-break.js`.
 */
class PageBreak extends Node
{
    /**
     * @var string
     */
    public static $name = 'pageBreak';

    /**
     * @var string
     */
    public static $group = 'block';

    /**
     * The break both ways it is written: `break-after` for browsers, its CSS 2 name for
     * DomPDF, which reads nothing newer.
     */
    public const STYLE = 'break-after: page; page-break-after: always;';

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                // Above the default 50, and checked here rather than in the selector, for the
                // reasons `Callout::parseHTML()` gives.
                'tag' => 'div[data-type]',
                'priority' => 51,
                'getAttrs' => static fn (mixed $DOMNode) => ($DOMNode instanceof DOMElement)
                    && $DOMNode->getAttribute('data-type') === self::$name
                        ? null
                        : false,
            ],
            [
                // A break as other editors write one - CKEditor's empty `div` with the style on
                // it - so a document moved over from one keeps its pages.
                'tag' => 'div[style]',
                'priority' => 51,
                'getAttrs' => static fn (mixed $DOMNode) => ($DOMNode instanceof DOMElement) && static::isForeignBreak($DOMNode)
                    ? null
                    : false,
            ],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        return [
            'div',
            HTML::mergeAttributes(
                $this->options['HTMLAttributes'],
                $HTMLAttributes,
                ['class' => 'fi-arte-page-break', 'data-type' => static::$name, 'style' => self::STYLE],
            ),
        ];
    }

    /**
     * An empty element whose only business is a forced break - what `isPageBreakElement()`
     * in `page-break.js` recognises as well.
     */
    public static function isForeignBreak(DOMElement $element): bool
    {
        return (trim(str_replace("\u{00A0}", ' ', $element->textContent)) === '')
            && (preg_match('/(?:^|;)\s*(?:page-break-(?:after|before)\s*:\s*always|break-(?:after|before)\s*:\s*page)\b/i', $element->getAttribute('style')) === 1);
    }
}
