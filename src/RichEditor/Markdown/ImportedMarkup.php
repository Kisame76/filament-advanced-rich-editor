<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * The two places CommonMark's markup and this schema disagree, translated on the way in.
 *
 * Both are the same kind of problem and neither shows up as an error: CommonMark writes
 * perfectly good HTML for a construct the editor has no node for, so the parser drops the
 * markup and keeps whatever text happened to be inside it. What is lost is exactly the
 * part that carried the meaning.
 *
 * One class and one pass over the document, rather than one class per repair, because the
 * two run back to back on the same string and a second `loadHTML()` buys nothing.
 */
class ImportedMarkup
{
    public function apply(string $html): string
    {
        // Two cheap string tests before a DOM parse. Most documents have neither.
        //
        // Matched on the word rather than on `type="checkbox"`, because raw HTML is part of
        // Markdown and an author who writes the list out by hand may quote the attribute
        // either way. The XPath below reads the parsed DOM and cannot tell the two apart;
        // only this line could, and a fast path that decides what the slow path would have
        // found is not a fast path.
        if (! str_contains($html, 'checkbox') && ! str_contains($html, 'footnote')) {
            return $html;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-markdown-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);

        $this->tickBoxes($xpath);
        $this->clearFootnotePlumbing($xpath);

        $root = $document->getElementById('arte-markdown-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    /**
     * `- [x]` as a task item rather than as a bullet that lost its box.
     *
     * The mirror of `TaskItemConverter`, and it exists for the same reason: a task list is
     * a list of decisions and the decisions are the boxes. GitHub-flavoured Markdown spells
     * one as a disabled `<input type="checkbox">` at the front of the item, which is an
     * element no rich text schema has a node for - so left alone the input is dropped and
     * every item arrives unticked, which reads as a document where nothing is done.
     *
     * Nothing has to be switched on for it. `TaskListPlugin` puts the button on a toolbar;
     * the renderer declares both nodes whether or not anything asked for them, which is
     * what lets this pass run unconditionally rather than having to be told.
     */
    protected function tickBoxes(DOMXPath $xpath): void
    {
        $lists = [];

        foreach (iterator_to_array($xpath->query('//li') ?: []) as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $box = $this->boxOf($item);

            if (! $box instanceof DOMElement) {
                continue;
            }

            $list = $item->parentNode;

            // `taskList` is a `<ul>` holding `taskItem+`, so a numbered list has nowhere to
            // put the state and the item stays an ordinary one. The box still goes, and the
            // space behind it with it: left alone the parser drops the input on its own and
            // keeps the space, so the item is stored with an indent nobody typed.
            if (! $list instanceof DOMElement || $list->tagName !== 'ul') {
                $this->unbox($box);

                continue;
            }

            if (! in_array($list, $lists, strict: true)) {
                $lists[] = $list;
            }
        }

        foreach ($lists as $list) {
            $this->split($list);
        }
    }

    /**
     * The checkbox that makes an item a task item, or none.
     *
     * Looked for at the front of the item and at the front of its first child, and nowhere
     * else. Two shapes rather than one, because a *loose* list - one whose items are set
     * apart by blank lines, which is how a good half of all Markdown is written - wraps
     * each label in a paragraph and puts the box inside that. Read only as a direct child,
     * every box in such a list is missed, and missed silently: the items arrive as ordinary
     * bullets carrying a leading space where the box used to be.
     *
     * Two shapes rather than "anywhere inside", which is the wider rule that suggests
     * itself and is wrong twice over. A checkbox is legal raw HTML, so one sitting in a
     * table cell or in the second paragraph of a long item would promote that whole item -
     * and its whole list - to a task list the author never wrote; and an item holding two
     * of them would be ticked by whichever came last, silently inverting the state this
     * pass exists to preserve. What GFM actually emits is the box first, always.
     */
    protected function boxOf(DOMElement $item): ?DOMElement
    {
        $first = $this->firstElement($item);

        if ($this->isBox($first)) {
            return $first;
        }

        // The paragraph a loose item wraps its label in. One level, not a descent: `<li>`,
        // `<p>`, box is the whole of what CommonMark writes.
        $inner = $this->firstElement($first);

        return $this->isBox($inner) ? $inner : null;
    }

    /**
     * The first element inside a node, ignoring the whitespace a pretty-printer leaves.
     */
    protected function firstElement(?DOMElement $node): ?DOMElement
    {
        for ($child = $node?->firstChild; $child instanceof DOMNode; $child = $child->nextSibling) {
            if ($child instanceof DOMElement) {
                return $child;
            }

            // Anything the author actually typed before the box means this is prose that
            // happens to contain a checkbox, not a task item.
            if ($child instanceof DOMText && trim($child->data) !== '') {
                return null;
            }
        }

        return null;
    }

    protected function isBox(?DOMElement $node): bool
    {
        return $node instanceof DOMElement
            && $node->tagName === 'input'
            && $node->getAttribute('type') === 'checkbox';
    }

    /**
     * One list, rewritten as the runs of items it actually holds.
     *
     * Markdown lets an author put a box on some items and not others, and the schema does
     * not: `taskList` holds `taskItem+`. Marking the whole list because one item carried a
     * box therefore puts a plain `listItem` inside a `taskList`, which is not a document
     * the schema describes - and the plain item arrives with none of a task item's
     * structure, so the stylesheet has nothing to draw it with. Neither side coerces that,
     * measured: the field and the page agree on the same wrong shape, which is worse than
     * disagreeing about it, because nothing ever looks odd enough to report.
     *
     * So each run of like items becomes its own list. Split rather than converted, because
     * a plain bullet is not an unticked task: a list of things and a list of decisions are
     * different lists, and an author who wrote both meant both.
     *
     * Rebuilt rather than edited in place, which is what makes the whitespace between the
     * items somebody else's problem: moving items out of a list leaves the text nodes that
     * separated them behind, and a `<ul>` holding nothing but whitespace still parses to an
     * empty list.
     */
    protected function split(DOMElement $list): void
    {
        $document = $list->ownerDocument;
        $parent = $list->parentNode;

        if (! $document instanceof DOMDocument || ! $parent instanceof DOMNode) {
            return;
        }

        /** @var array<int, array{task: bool, items: array<int, array{0: DOMElement, 1: ?DOMElement}>}> $runs */
        $runs = [];

        foreach (iterator_to_array($list->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->tagName !== 'li') {
                continue;
            }

            $box = $this->boxOf($child);
            $isTask = $box instanceof DOMElement;

            if ($runs === [] || $runs[count($runs) - 1]['task'] !== $isTask) {
                $runs[] = ['task' => $isTask, 'items' => []];
            }

            $runs[count($runs) - 1]['items'][] = [$child, $box];
        }

        if ($runs === []) {
            return;
        }

        foreach ($runs as $run) {
            $replacement = $document->createElement('ul');

            // Whatever the original said about itself, since a list can arrive as raw HTML
            // and carry anything. The kind is written after, so a hand-written `data-type`
            // cannot make a run of plain bullets claim to be task items.
            foreach (iterator_to_array($list->attributes ?? []) as $attribute) {
                $replacement->setAttribute($attribute->nodeName, (string) $attribute->nodeValue);
            }

            if ($run['task']) {
                $replacement->setAttribute('data-type', 'taskList');
            } else {
                $replacement->removeAttribute('data-type');
            }

            foreach ($run['items'] as [$item, $box]) {
                if ($box instanceof DOMElement) {
                    $this->tick($item, $box);
                }

                $replacement->appendChild($item);
            }

            $parent->insertBefore($replacement, $list);
        }

        $parent->removeChild($list);
    }

    /**
     * The item's state written where the schema reads it, and the box taken away.
     */
    protected function tick(DOMElement $item, DOMElement $box): void
    {
        $item->setAttribute('data-type', 'taskItem');
        $item->setAttribute('data-checked', $box->hasAttribute('checked') ? 'true' : 'false');

        $this->unbox($box);
    }

    /**
     * The box removed, and the space that stood between it and the label with it.
     *
     * Taken from the node beside the box rather than from the front of the item, because in
     * a loose list the box sits inside a paragraph and the item's first child is that
     * paragraph - so trimming the item trims nothing at all.
     */
    protected function unbox(DOMElement $box): void
    {
        $after = $box->nextSibling;

        $box->parentNode?->removeChild($box);

        if ($after instanceof DOMText) {
            $after->data = ltrim($after->data);
        }
    }

    /**
     * A footnote without the anchors that only make sense in a rendered page.
     *
     * The extension writes a marker and a note and links them to each other by id. The ids
     * are not attributes this schema keeps, so both links arrive pointing at nothing, and
     * the return arrow is a glyph an author has to delete by hand. What a footnote *is*
     * survives without either: a superscript marker in the text, a rule, and a numbered
     * list of notes at the end - which is how a footnote is set in prose anyway.
     */
    protected function clearFootnotePlumbing(DOMXPath $xpath): void
    {
        foreach ($this->byClass($xpath, 'footnote-backref') as $backref) {
            $before = $backref->previousSibling;

            $backref->parentNode?->removeChild($backref);

            // The extension separates the note from its arrow with a non-breaking space,
            // which is left dangling at the end of the sentence once the arrow is gone.
            if ($before instanceof DOMText) {
                $before->data = (string) preg_replace('/[\s\x{A0}]+$/u', '', $before->data);
            }
        }

        foreach ($this->byClass($xpath, 'footnote-ref') as $reference) {
            $parent = $reference->parentNode;

            if (! $parent instanceof DOMNode) {
                continue;
            }

            // Unwrapped rather than removed: the number inside it is the marker, and it is
            // already sitting in the `<sup>` that makes it one.
            while ($reference->firstChild instanceof DOMNode) {
                $parent->insertBefore($reference->firstChild, $reference);
            }

            $parent->removeChild($reference);
        }
    }

    /**
     * The `<a>` elements carrying a class, matched as a whole word.
     *
     * @return array<int, DOMElement>
     */
    protected function byClass(DOMXPath $xpath, string $class): array
    {
        $found = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');

        return array_values(array_filter(
            iterator_to_array($found ?: []),
            fn (DOMNode $node): bool => $node instanceof DOMElement,
        ));
    }
}
