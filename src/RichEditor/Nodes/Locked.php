<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A part of a document the people writing in it cannot change - the fixed paragraphs of a
 * template, the clause of a contract - while everything around it stays theirs.
 *
 * It holds blocks and nothing else: no attribute, no id. What is locked is told apart by what
 * it holds, which is also what has to stay the same - see `LockedRegions`. An id would be one
 * more thing that could be copied onto a part somebody wrote themselves.
 *
 * For a reader it is a plain `div` around the blocks, with a class nothing on a page styles.
 * The frame and the padlock are the editor's, and only the editor's.
 */
class Locked extends Node
{
    /**
     * @var string
     */
    public static $name = 'locked';

    /**
     * @var string
     */
    public static $group = 'block';

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
                ['class' => 'fi-arte-locked', 'data-type' => static::$name],
            ),
            0,
        ];
    }
}
