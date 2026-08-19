<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes;

use DOMElement;
use Tiptap\Nodes\TaskItem as BaseTaskItem;
use Tiptap\Utils\HTML;

/**
 * `ueberdosis/tiptap-php` declares the task item's `checked` attribute with a `renderHTML`
 * but no `parseHTML`, so parsing saved markup back into a document resets every item to
 * unchecked.
 *
 * That parse is not an edge case: unless a field opts into `->json()`, rich content is
 * stored as HTML and Filament's state cast runs it through the PHP editor every time a
 * record is hydrated into the form. Without the rule below, every tick mark would be lost
 * the moment a saved record is reopened.
 */
class TaskItem extends BaseTaskItem
{
    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        $attributes = parent::addAttributes();

        return [
            ...$attributes,
            'checked' => [
                ...$attributes['checked'],
                // `HTML::renderAttributes()` writes booleans as the strings "true" and
                // "false", which is what the renderer above produces. A bare
                // `data-checked` is accepted as checked as well, so hand-written HTML
                // behaves like it does in the browser extension.
                'parseHTML' => static function (DOMElement $DOMNode): bool {
                    if (! $DOMNode->hasAttribute('data-checked')) {
                        return false;
                    }

                    $value = $DOMNode->getAttribute('data-checked');

                    return in_array($value, ['', 'true', 'checked', 'data-checked'], strict: true);
                },
            ],
        ];
    }

    /**
     * Renders a task item without the `<input type="checkbox">` the vendor node emits.
     *
     * Filament sanitises rich content before it reaches a page: its `HtmlSanitizerConfig`
     * (see `Filament\Support\SupportServiceProvider`) allows a fixed attribute list and
     * `allowSafeElements()`, which drops `<input>` entirely and strips `data-checked`
     * because it is not on the allow list. A checkbox element and the `data-checked`
     * attribute therefore both vanish from rendered output, taking the tick state with
     * them. `class` survives, so the state rides on a modifier class as well and the
     * shipped stylesheet draws the box.
     *
     * `data-checked` is still emitted: it is what both this node and the JS extension
     * parse, and the value stored in the database is unsanitised.
     *
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, $HTMLAttributes = [])
    {
        $isChecked = (bool) ($node->attrs->checked ?? false);

        $attributes = HTML::mergeAttributes(
            $this->options['HTMLAttributes'],
            $HTMLAttributes,
            ['data-type' => static::$name],
            $isChecked ? ['class' => 'fi-arte-task-item-checked'] : [],
        );

        return [
            'li',
            $attributes,
            [
                'label',
                ['class' => 'fi-arte-task-item-control'],
                ['span', ['class' => 'fi-arte-task-item-box']],
            ],
            [
                'div',
                ['class' => 'fi-arte-task-item-content'],
                0,
            ],
        ];
    }
}
