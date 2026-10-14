<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

/**
 * The description of a term in a definition list, `<dd>`. It holds text: see `DescriptionList`.
 */
class DescriptionDetails extends Node
{
    /**
     * @var string
     */
    public static $name = 'descriptionDetails';

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
            ['tag' => 'dd'],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['dd', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
