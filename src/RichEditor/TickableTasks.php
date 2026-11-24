<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Js;

/**
 * The checkboxes on a view page, made clickable.
 *
 * Runs AFTER the sanitiser rather than before it, which is the opposite of every other
 * pass in this package and is the point: what it writes is a `wire:click`, and the
 * sanitiser's allow list would take that off again. Nothing user-written goes in - the
 * only values interpolated are an integer index and the schema component's own key.
 *
 * The label the renderer draws becomes a button. A label is the right element for a
 * checkbox that exists; here there is no `<input>` to be a label *for* - the sanitiser
 * drops those - so on a page where the box does something, the thing that does it should
 * be the thing you can reach with a keyboard.
 *
 * What a button carries is a number, and that number addresses an item in the STORED
 * document rather than in this markup. The two are only the same list while nothing else
 * on the page looks like a task item - and something can: a merge tag or a custom block
 * renders markup of its own, after parsing and past the sanitiser, and an `li` in it with
 * the same shape would shift every index after it. So the items are found by the attribute
 * the parser itself keys on rather than by the class, and the count is checked against the
 * document before a single button is drawn. Disagreement leaves the boxes as they were:
 * a checkbox that does nothing is a smaller failure than one that ticks its neighbour.
 */
class TickableTasks
{
    /**
     * @param  int|null  $expected  how many task items the stored document holds, or null
     *                              to draw a button for every item found
     */
    public function apply(string $html, string $componentKey, string $method = 'toggleTask', ?int $expected = null): string
    {
        if (! str_contains($html, 'fi-arte-task-item-box')) {
            return $html;
        }

        $document = new DOMDocument;

        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-tick-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        // `data-type` and not the class: this is the attribute `TaskItem` parses on, so the
        // list found here is the list the toggle will walk.
        $items = iterator_to_array((new DOMXPath($document))->query('//li[@data-type="taskItem"]') ?: []);

        if ($expected !== null && count($items) !== $expected) {
            return $html;
        }

        foreach ($items as $index => $item) {
            if ($item instanceof DOMElement) {
                $this->makeClickable($document, $item, $index, $componentKey, $method);
            }
        }

        $root = $document->getElementById('arte-tick-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    protected function makeClickable(DOMDocument $document, DOMElement $item, int $index, string $componentKey, string $method): void
    {
        $control = null;

        foreach ((new DOMXPath($document))->query('.//*[contains(concat(" ", normalize-space(@class), " "), " fi-arte-task-item-control ")]', $item) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $control = $node;

                break;
            }
        }

        if (! $control instanceof DOMElement) {
            return;
        }

        $checked = static::isChecked($item);

        // The state the click asks for rather than "turn it over": a second click that lands
        // before the first one's answer asks for what the first already wrote, and changes
        // nothing - instead of undoing it.
        $call = 'callSchemaComponentMethod('.Js::from($componentKey)->toHtml().', '.Js::from($method)->toHtml()
            .', { index: '.$index.', checked: '.($checked ? 'false' : 'true').' })';

        $button = $document->createElement('button');
        $button->setAttribute('type', 'button');
        $button->setAttribute('class', trim($control->getAttribute('class').' fi-arte-task-item-tickable'));
        $button->setAttribute('wire:click', $call);
        // Nothing is written until the round trip comes back, so the button says so rather
        // than accepting a second click.
        $button->setAttribute('wire:loading.attr', 'disabled');
        // The whole call, arguments included. The request Livewire sends is
        // `callSchemaComponentMethod`, so the name of the method behind it never matched and
        // the button was never disabled; the bare name would match, and grey out every
        // checkbox - and anything else on the page calling into a schema component - while
        // one of them saves. Livewire compares a target's arguments with the call's, so
        // this one disables exactly the button that was pressed.
        $button->setAttribute('wire:target', $call);
        $button->setAttribute('aria-pressed', $checked ? 'true' : 'false');

        // A button with nothing in it but a drawn box has no name, and a screen reader then
        // announces "toggle button, not pressed" for every item in the list. Its name is the
        // item's first line - not the item's whole content, which holds its sub-items too.
        $label = static::labelOf($document, $item);

        if ($label instanceof DOMElement) {
            if (! $label->hasAttribute('id')) {
                $label->setAttribute('id', 'arte-task-'.substr(md5($componentKey), 0, 8).'-'.$index);
            }

            $button->setAttribute('aria-labelledby', $label->getAttribute('id'));
        }

        foreach (iterator_to_array($control->childNodes) as $child) {
            $button->appendChild($child);
        }

        $control->parentNode?->replaceChild($button, $control);
    }

    /**
     * The element that names an item: the first thing in its content that is not a nested
     * list, or the content itself where there is nothing else.
     */
    protected static function labelOf(DOMDocument $document, DOMElement $item): ?DOMElement
    {
        $content = null;

        foreach ((new DOMXPath($document))->query('./*[contains(concat(" ", normalize-space(@class), " "), " fi-arte-task-item-content ")]', $item) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $content = $node;

                break;
            }
        }

        if (! $content instanceof DOMElement) {
            return null;
        }

        foreach ($content->childNodes as $child) {
            if ($child instanceof DOMElement && ! in_array(strtolower($child->tagName), ['ul', 'ol'], strict: true)) {
                return $child;
            }
        }

        return $content;
    }

    protected static function isChecked(DOMElement $item): bool
    {
        return str_contains(' '.$item->getAttribute('class').' ', ' fi-arte-task-item-checked ');
    }
}
