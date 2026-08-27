<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Callouts;
use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A note, a tip, a warning or a danger box: a paragraph pulled out of the flow and drawn
 * as something the reader is meant to stop at.
 *
 * It holds blocks rather than text, so a callout can carry a list or a second paragraph -
 * which is the difference between an infobox and a coloured sentence.
 *
 * What identifies it in markup is `data-type="callout"`, and what says which kind it is
 * is a class. Both survive Filament's sanitiser (`HtmlSanitizerConfig` in
 * `Filament\Support\SupportServiceProvider` allows `class` and `data-type` on every
 * element); a `data-variant` would not, and the colour would be gone from every rendered
 * page while still sitting in the database.
 */
class Callout extends Node
{
    /**
     * @var string
     */
    public static $name = 'callout';

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
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            'variant' => [
                'default' => Callouts::DEFAULT,
                'parseHTML' => static fn (mixed $DOMNode): string => ($DOMNode instanceof DOMElement)
                    ? (Callouts::fromClassList($DOMNode->getAttribute('class')) ?? Callouts::DEFAULT)
                    : Callouts::DEFAULT,
                // The variant is already in the class the renderer writes. Without this the
                // serialiser would put it in `$HTMLAttributes` as well and the wrapper would
                // carry a bare `variant="note"`, which is not an attribute HTML has and not
                // one the sanitiser keeps.
                'renderHTML' => static fn (): array => [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                'tag' => 'div[data-type]',
                // Above the default 50: `data-type` carries task lists, grids and custom
                // blocks too, and a rule claiming plain `div` must not get there first.
                'priority' => 51,
                // The parser's own selector can express a value, but only an alphabetic
                // one - so the check lives here, where it is the same check the JavaScript
                // half makes and where a `div` belonging to somebody else is handed back
                // rather than swallowed.
                'getAttrs' => static fn (mixed $DOMNode) => ($DOMNode instanceof DOMElement)
                    && $DOMNode->getAttribute('data-type') === self::$name
                        ? null
                        : false,
            ],
        ];
    }

    /**
     * The variant is read off the node rather than out of `$HTMLAttributes`.
     *
     * The serialiser calls this a second time to work out the closing tag, and that call
     * passes the node alone - so anything taken from the second argument would produce the
     * opening tag correctly and then differ on the way out.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $variant = Callouts::nameOrDefault($node->attrs->variant ?? null);

        return [
            'div',
            HTML::mergeAttributes(
                $this->options['HTMLAttributes'],
                $HTMLAttributes,
                [
                    'class' => 'fi-arte-callout '.Callouts::className($variant),
                    'data-type' => static::$name,
                ],
            ),
            0,
        ];
    }
}
