<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;

/**
 * A file deleted or replaced in the library, in a document as it is stored.
 *
 * The same discipline as `DocumentTasks`, and for the same reason: the change is made where it
 * lives rather than by rebuilding the document through the editor. A round trip through a
 * schema would rewrite every node on the way past - and the document belongs to some other
 * record, whose field may carry blocks this one has never heard of, which a schema that does
 * not know them would drop. What changes here is the element pointing at the file; everything
 * around it comes back out as it went in.
 *
 * What points at the library is what carries an attachment id: a picture, a player and a
 * card - `FileAttachments::TYPES`, stored as an `<img>`, a `<video>` or `<audio>`, and an
 * `<a download>`. A custom block has a `data-id` too, naming the block rather than a file, and
 * is never taken for one.
 */
class DocumentAttachments
{
    /**
     * Whether a document points at the file anywhere.
     *
     * @param  string|array<string, mixed>|null  $content
     */
    public static function contains(string|array|null $content, mixed $id): bool
    {
        $id = static::id($id);

        if ($id === null) {
            return false;
        }

        if (is_array($content)) {
            $found = false;

            static::walk($content, static function (array &$node) use ($id, &$found): void {
                $found = $found || static::pointsAt($node, $id);
            });

            return $found;
        }

        $document = static::parse($content);

        return ($document !== null) && (static::elements($document, $id) !== []);
    }

    /**
     * The document without anything pointing at the file, or null where nothing did.
     *
     * @param  string|array<string, mixed>|null  $content
     * @return string|array<string, mixed>|null
     */
    public static function remove(string|array|null $content, mixed $id): string|array|null
    {
        $id = static::id($id);

        if ($id === null) {
            return null;
        }

        if (is_array($content)) {
            $changed = false;
            $pruned = static::prune($content, $id, $changed);

            return $changed ? $pruned : null;
        }

        $document = static::parse($content);
        $elements = ($document === null) ? [] : static::elements($document, $id);

        if ($elements === []) {
            return null;
        }

        foreach ($elements as $element) {
            $element->parentNode?->removeChild($element);
        }

        return static::render($document);
    }

    /**
     * The document with everything pointing at the file pointed at its replacement, or null
     * where nothing did.
     *
     * `src` is the file's address now. A card takes the `name` it downloads under and shows,
     * and the `size` it shows. A picture somebody gave a size keeps its width and takes the
     * new picture's shape from `width` and `height`, which are the new picture's own - the
     * old height with a new picture of another shape would squash it into the old one's box.
     *
     * @param  string|array<string, mixed>|null  $content
     * @param  array{src?: string|null, name?: string|null, size?: string|null, width?: int|null, height?: int|null}  $changes
     * @return string|array<string, mixed>|null
     */
    public static function update(string|array|null $content, mixed $id, array $changes): string|array|null
    {
        $id = static::id($id);

        if ($id === null) {
            return null;
        }

        if (is_array($content)) {
            $changed = false;

            static::walk($content, static function (array &$node) use ($id, $changes, &$changed): void {
                if (! static::pointsAt($node, $id)) {
                    return;
                }

                $changed = true;

                if (filled($changes['src'] ?? null)) {
                    $node['attrs']['src'] = $changes['src'];
                }

                if (($node['type'] ?? null) === 'file') {
                    foreach (['name', 'size'] as $key) {
                        if (filled($changes[$key] ?? null)) {
                            $node['attrs'][$key] = $changes[$key];
                        }
                    }
                }

                if (($node['type'] ?? null) === 'image') {
                    $height = static::heightFor($node['attrs']['width'] ?? null, $node['attrs']['height'] ?? null, $changes);

                    if ($height !== null) {
                        $node['attrs']['height'] = $height;
                    }
                }
            });

            return $changed ? $content : null;
        }

        $document = static::parse($content);
        $elements = ($document === null) ? [] : static::elements($document, $id);

        if ($elements === []) {
            return null;
        }

        foreach ($elements as $element) {
            static::point($element, $changes);
        }

        return static::render($document);
    }

    /**
     * @param  array{src?: string|null, name?: string|null, size?: string|null, width?: int|null, height?: int|null}  $changes
     */
    protected static function point(DOMElement $element, array $changes): void
    {
        $src = $changes['src'] ?? null;

        if ($element->tagName === 'a') {
            if (filled($src)) {
                $element->setAttribute('href', $src);
            }

            if (filled($changes['name'] ?? null)) {
                $element->setAttribute('download', $changes['name']);
                static::textOf($element, 'fi-arte-file-name', $changes['name']);
            }

            if (filled($changes['size'] ?? null)) {
                static::textOf($element, 'fi-arte-file-size', $changes['size']);
            }

            return;
        }

        if (filled($src)) {
            $element->setAttribute('src', $src);
        }

        if ($element->tagName !== 'img') {
            return;
        }

        $height = static::heightFor($element->getAttribute('width'), $element->getAttribute('height'), $changes);

        if ($height === null) {
            return;
        }

        $element->setAttribute('height', (string) $height);

        // The same length in the inline style, which is what draws it. `height` only where it
        // starts a declaration, so a `line-height` or a `max-height` is never taken for it.
        if ($element->hasAttribute('style')) {
            $element->setAttribute('style', (string) preg_replace(
                '/(^|;)(\s*)height\s*:\s*[^;]*/i',
                '${1}${2}height: '.rtrim((string) $height, 'px').'px',
                $element->getAttribute('style'),
            ));
        }
    }

    protected static function textOf(DOMElement $card, string $class, string $text): void
    {
        foreach ($card->getElementsByTagName('span') as $span) {
            if (in_array($class, explode(' ', $span->getAttribute('class')), strict: true)) {
                $span->textContent = $text;
            }
        }
    }

    /**
     * The height a sized picture needs to keep the new picture's shape at its width, spelled
     * the way the old height was - or null where either size is not a plain length in pixels,
     * or the new picture's shape is unknown. A percentage is a choice of its own, left alone.
     *
     * @param  array{width?: int|null, height?: int|null}  $changes
     */
    protected static function heightFor(mixed $width, mixed $height, array $changes): int|string|null
    {
        $to = [(int) ($changes['width'] ?? 0), (int) ($changes['height'] ?? 0)];
        $width = static::pixels($width);

        if (($to[0] <= 0) || ($to[1] <= 0) || ($width === null) || (static::pixels($height) === null)) {
            return null;
        }

        $computed = (int) round($width * $to[1] / $to[0]);

        if (! is_string($height)) {
            return $computed;
        }

        return str_ends_with(trim($height), 'px') ? "{$computed}px" : (string) $computed;
    }

    protected static function pixels(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return ($value > 0) ? (float) $value : null;
        }

        if (! is_string($value) || (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(px)?\s*$/i', $value, $match) !== 1)) {
            return null;
        }

        return ((float) $match[1] > 0) ? (float) $match[1] : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected static function pointsAt(array $node, string $id): bool
    {
        return in_array($node['type'] ?? null, FileAttachments::TYPES, strict: true)
            && is_scalar($node['attrs']['id'] ?? null)
            && ((string) $node['attrs']['id'] === $id);
    }

    /**
     * The node with every child pointing at the file left out, all the way down.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected static function prune(array $node, string $id, bool &$changed): array
    {
        if (! isset($node['content']) || ! is_array($node['content'])) {
            return $node;
        }

        $kept = [];

        foreach ($node['content'] as $child) {
            if (is_array($child) && static::pointsAt($child, $id)) {
                $changed = true;

                continue;
            }

            $kept[] = is_array($child) ? static::prune($child, $id, $changed) : $child;
        }

        $node['content'] = $kept;

        return $node;
    }

    /**
     * Every node in the tree, in document order.
     *
     * @param  array<string, mixed>  $node
     * @param  callable(array<string, mixed>): void  $callback
     */
    protected static function walk(array &$node, callable $callback): void
    {
        $callback($node);

        if (! isset($node['content']) || ! is_array($node['content'])) {
            return;
        }

        foreach ($node['content'] as &$child) {
            if (is_array($child)) {
                static::walk($child, $callback);
            }
        }
    }

    /**
     * The elements pointing at the file: pictures, players and cards, and nothing else that
     * happens to carry a `data-id`.
     *
     * @return array<int, DOMElement>
     */
    protected static function elements(DOMDocument $document, string $id): array
    {
        $candidates = (new DOMXPath($document))->query('//img[@data-id] | //video[@data-id] | //audio[@data-id] | //a[@data-id]');

        $elements = [];

        foreach ($candidates ?: [] as $element) {
            if (! $element instanceof DOMElement || ($element->getAttribute('data-id') !== $id)) {
                continue;
            }

            // An `<a>` is a card only where it says so - by the type its node writes, or by
            // the `download` a hand-written one is recognised by.
            if (($element->tagName === 'a') && ($element->getAttribute('data-type') !== 'file') && ! $element->hasAttribute('download')) {
                continue;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    protected static function parse(?string $html): ?DOMDocument
    {
        if (blank($html)) {
            return null;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1. See `DocumentTasks`.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-attachments-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        return $loaded ? $document : null;
    }

    protected static function render(DOMDocument $document): ?string
    {
        $root = $document->getElementById('arte-attachments-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    protected static function id(mixed $id): ?string
    {
        return (is_scalar($id) && filled((string) $id)) ? (string) $id : null;
    }
}
