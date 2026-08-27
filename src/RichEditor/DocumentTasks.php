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
        if ($index < 0) {
            return null;
        }

        return is_array($content)
            ? static::toggleInDocument($content, $index)
            : static::toggleInMarkup($content, $index);
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

    protected static function toggleInMarkup(?string $html, int $index): ?string
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

        // The two spellings this package's own parser accepts, so what is written back is
        // what a re-parse reads. `false` rather than removing the attribute: an item that
        // says it is unticked is clearer in a diff than one that says nothing.
        $item->setAttribute(
            'data-checked',
            in_array($item->getAttribute('data-checked'), ['', 'true', 'checked', 'data-checked'], strict: true)
                ? 'false'
                : 'true',
        );

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
    protected static function toggleInDocument(array $document, int $index): ?array
    {
        $seen = 0;
        $found = false;

        static::walk($document, function (array &$node) use ($index, &$seen, &$found): void {
            if ($seen++ !== $index) {
                return;
            }

            $node['attrs']['checked'] = ! ($node['attrs']['checked'] ?? false);
            $found = true;
        });

        return $found ? $document : null;
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
