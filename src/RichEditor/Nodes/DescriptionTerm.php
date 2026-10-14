<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * A term of a definition list, `<dt>`. It holds text: see `DescriptionList`.
 */
class DescriptionTerm extends Node
{
    /**
     * @var string
     */
    public static $name = 'descriptionTerm';

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
            ['tag' => 'dt'],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['dt', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
