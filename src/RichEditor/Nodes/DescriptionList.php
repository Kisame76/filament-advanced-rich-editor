<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A definition list, `<dl>`: terms and their descriptions, for a glossary that would
 * otherwise end up as a bulleted list with bold words at the front.
 *
 * The PHP half of `resources/js/description-list.js`, and it writes the same markup - the
 * class included - so a list written in the editor and one rendered here parse back into
 * the same document and are styled by the same stylesheet.
 */
class DescriptionList extends Node
{
    /**
     * @var string
     */
    public static $name = 'descriptionList';

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
            ['tag' => 'dl'],
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
            'dl',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes, ['class' => 'fi-arte-description-list']),
            0,
        ];
    }
}
