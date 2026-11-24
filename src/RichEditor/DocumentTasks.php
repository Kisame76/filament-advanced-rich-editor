<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Turning one tick mark over, in the document as it is stored.
 *
 * The tick is flipped where it lives rather than by rebuilding the document through the
 * editor: a round trip through the schema would rewrite every node on the way past, and a
 * click on a checkbox has no business touching the paragraph next to it. What changes here
 * is one attribute on one element, and everything else comes back out byte for byte.
 *
 * The item is addressed by its position in document order, which is the order it was
 * rendered in - the page counts from the same document the page was drawn from. Anything
 * else would need an id on every task item, and the sanitiser does not carry one.
 */
class DocumentTasks
{
    /**
     * The document with the nth task item's tick turned over, or null where there is no
     * such item - a document that changed between the drawing and the click.
     *
     * @param  string|array<string, mixed>|null  $content
     * @return string|array<string, mixed>|null
     */
    public static function toggle(string|array|null $content, int $index): string|array|null
    {
        $checked = static::isChecked($content, $index);

        return $checked === null ? null : static::set($content, $index, ! $checked);
    }

    /**
     * The document with the nth task item ticked or unticked, or null where there is no such
     * item.
     *
     * Setting rather than turning over is what makes a click safe to repeat: a second click
     * that arrives before the first one's answer asks for the state the first one already
     * wrote, instead of undoing it.
     *
     * @param  string|array<string, mixed>|null  $content
     * @return string|array<string, mixed>|null
     */
    public static function set(string|array|null $content, int $index, bool $checked): string|array|null
    {
        if ($index < 0) {
            return null;
        }

        return is_array($content)
            ? static::setInDocument($content, $index, $checked)
            : static::setInMarkup($content, $index, $checked);
    }

    /**
     * Whether the nth task item is ticked, or null where there is no such item.
     *
     * @param  string|array<string, mixed>|null  $content
     */
    public static function isChecked(string|array|null $content, int $index): ?bool
    {
        if ($index < 0) {
            return null;
        }

        if (is_array($content)) {
            $seen = 0;
            $checked = null;

            static::walk($content, function (array &$node) use ($index, &$seen, &$checked): void {
                if ($seen++ === $index) {
                    $checked = (bool) ($node['attrs']['checked'] ?? false);
                }
            });

            return $checked;
        }

        if (blank($content)) {
            return null;
        }

        $document = static::parse($content);

        if ($document === null) {
            return null;
        }

        $item = static::items($document)->item($index);

        return $item instanceof DOMElement ? static::markedChecked($item) : null;
    }

    /**
     * How many task items a document holds, for a caller that wants to know an index is
     * worth sending.
     *
     * @param  string|array<string, mixed>|null  $content
     */
    public static function count(string|array|null $content): int
    {
        if (is_array($content)) {
            $count = 0;
            static::walk($content, static function () use (&$count): void {
                $count++;
            });

            return $count;
        }

        if (blank($content)) {
            return 0;
        }

        $document = static::parse($content);

        return $document === null ? 0 : static::items($document)->count();
    }

    protected static function setInMarkup(?string $html, int $index, bool $checked): ?string
    {
        if (blank($html)) {
            return null;
        }

        $document = static::parse($html);

        if ($document === null) {
            return null;
        }

        $item = static::items($document)->item($index);

        if (! $item instanceof DOMElement) {
            return null;
        }

        // Written the way this package's own parser reads it back. `false` rather than
        // removing the attribute: an item that says it is unticked is clearer in a diff than
        // one that says nothing.
        $item->setAttribute('data-checked', $checked ? 'true' : 'false');

        // The editor writes the state twice, as the attribute and as a class, because the
        // sanitiser keeps only the class. Turning the attribute over alone left markup that
        // contradicted itself - harmless wherever it is rendered again, since the renderer
        // reads the attribute, and wrong wherever the column is read as it lies: a search
        // index, an export, a page printing it raw. Only where the editor's class is there
        // at all: hand-written markup gets no class it never had.
        $classes = preg_split('/\s+/', trim($item->getAttribute('class'))) ?: [];

        if (in_array('fi-arte-task-item', $classes, strict: true)) {
            $classes = array_values(array_diff($classes, ['fi-arte-task-item-checked']));

            if ($checked) {
                $classes[] = 'fi-arte-task-item-checked';
            }

            $item->setAttribute('class', implode(' ', $classes));
        }

        $root = $document->getElementById('arte-task-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    protected static function setInDocument(array $document, int $index, bool $checked): ?array
    {
        $seen = 0;
        $found = false;

        static::walk($document, function (array &$node) use ($index, $checked, &$seen, &$found): void {
            if ($seen++ !== $index) {
                return;
            }

            $node['attrs']['checked'] = $checked;
            $found = true;
        });

        return $found ? $document : null;
    }

    /**
     * The spellings of "ticked" this package's own parser accepts, so a state read here is
     * the state a re-parse would read. A missing attribute is unticked, as it is to the
     * parser - `getAttribute()` answers an empty string for it, which is also how a bare
     * `data-checked` reads, so the two have to be told apart first.
     */
    protected static function markedChecked(DOMElement $item): bool
    {
        return $item->hasAttribute('data-checked')
            && in_array($item->getAttribute('data-checked'), ['', 'true', 'checked', 'data-checked'], strict: true);
    }

    /**
     * Every task item in the tree, in document order.
     *
     * @param  array<string, mixed>  $node
     * @param  callable(array<string, mixed>): void  $callback
     */
    protected static function walk(array &$node, callable $callback): void
    {
        if (($node['type'] ?? null) === 'taskItem') {
            $callback($node);
        }

        if (! isset($node['content']) || ! is_array($node['content'])) {
            return;
        }

        foreach ($node['content'] as &$child) {
            if (is_array($child)) {
                static::walk($child, $callback);
            }
        }
    }

    protected static function parse(string $html): ?DOMDocument
    {
        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-task-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return $loaded ? $document : null;
    }

    /**
     * @return DOMNodeList<DOMNode>
     */
    protected static function items(DOMDocument $document): DOMNodeList
    {
        return (new DOMXPath($document))->query('//li[@data-type="taskItem"]') ?: new DOMNodeList;
    }
}
