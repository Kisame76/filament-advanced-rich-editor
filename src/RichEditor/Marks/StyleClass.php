<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\BlockStyle;
use Tiptap\Core\Mark;
use Tiptap\Utils\HTML;

/**
 * The PHP half of the named style a piece of text carries.
 *
 * The inline twin of `BlockStyle`, and the same two things are written for the same two
 * reasons: the classes are what the page uses, the key in `data-style` is what the next
 * parse reads, and the sanitiser passes the first through and drops the second.
 *
 * A mark rather than an attribute, because a selection is not a node. The attribute is
 * called `name` and needs no prefix - it lives inside this mark's own attributes, where
 * nothing else can collide with it.
 */
class StyleClass extends Mark
{
    /**
     * @var string
     */
    public static $name = 'styleClass';

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
            'styles' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        $styles = $this->styles();

        return [
            [
                'tag' => 'span',
                // Returning `false` rejects the rule, so a span carrying no style of ours
                // is left to whichever other mark claims it.
                'getAttrs' => fn ($DOMNode): ?bool => BlockStyle::read($styles, $DOMNode) === null ? false : null,
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        $styles = $this->styles();

        return [
            'name' => [
                'parseHTML' => fn ($DOMNode): ?string => BlockStyle::read($styles, $DOMNode),
                'renderHTML' => function ($attributes) use ($styles): array {
                    $key = BlockStyle::attribute($attributes, 'name');
                    $style = BlockStyle::find($styles, is_string($key) ? $key : null);

                    return ($style === null)
                        ? []
                        : ['data-style' => $style['key'], 'class' => $style['class']];
                },
            ],
        ];
    }

    /**
     * @param  object  $mark
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($mark, $HTMLAttributes = [])
    {
        return [
            'span',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes),
            0,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    protected function styles(): array
    {
        $styles = $this->options['styles'] ?? [];

        return is_array($styles) ? array_values($styles) : [];
    }
}
