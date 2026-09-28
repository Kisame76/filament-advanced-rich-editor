<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

/**
 * Inline content sitting where only blocks belong, put into a paragraph.
 *
 * `doc` holds `block+`, so a text node is not something that belongs under it. Nothing
 * raises over one - measured on both sides - and that is exactly what makes it worth
 * repairing rather than trusting: the two sides carry on *differently*. The editor coerces
 * the stray content into a paragraph while it loads, and the renderer writes it out as it
 * found it, so `toHtml()` puts a bare node between two blocks. The same document is two
 * paragraphs in the field and one paragraph plus loose text on the page, where nothing a
 * paragraph is styled with can reach it. And the shape is stable: parsing that markup gives
 * the same document back, so it stays wrong until somebody opens the record and saves it.
 *
 * Markdown produces exactly that, by a route nobody would guess and every document with a
 * `<div>` in it takes. Raw HTML is part of Markdown, so `Ein <div>roh</div> Wort` converts
 * to `<p>Ein <div>roh</div> Wort</p>` - and a `<div>` inside a `<p>` closes that paragraph
 * while the markup is parsed, which leaves the words after it with no block around them.
 * Repairing the markup first does not help: the editor drops the `<div>` itself, and its
 * text comes loose at that point instead. So the repair is made where the damage is
 * visible, on the finished document.
 *
 * What it will not do is guess. Both rules below were measured against the editor's own
 * schema, read out of a running browser rather than reasoned about, because `tiptap-php`
 * declares no content spec on its nodes and there is nothing here to ask.
 */
class LooseText
{
    /**
     * The node types that cannot stand on their own.
     *
     * Read from the schema the browser builds: `text`, `hardBreak`, `image` and `mention`
     * all report `inline: true` and `group: inline`. Images and mentions being on this list
     * is not a detail - Filament configures `Image` with `inline: true`, so a picture that
     * arrives as raw HTML rather than as `![]()` lands under `doc` as its own child, the
     * browser wraps it in a paragraph on load and the renderer prints a bare `<img>`
     * between two blocks. That is the same divergence a loose word is, and a list naming
     * only text would walk straight past it.
     */
    public const INLINE = ['text', 'hardBreak', 'image', 'mention'];

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function apply(array $document): array
    {
        return $this->repair($document, isRoot: true);
    }

    /**
     * One node, its children repaired, and theirs.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function repair(array $node, bool $isRoot): array
    {
        $content = $node['content'] ?? null;

        if (! is_array($content) || $content === []) {
            return $node;
        }

        // Downwards first, so a container is asked about children that are already whole.
        $content = array_map(
            fn (mixed $child): mixed => is_array($child) ? $this->repair($child, isRoot: false) : $child,
            $content,
        );

        $node['content'] = $this->shouldWrap($content, $isRoot) ? $this->wrap($content) : $content;

        return $node;
    }

    /**
     * Whether loose content here is damage or simply how this document was always shaped.
     *
     * At the root it is always damage: `doc` never holds inline content in either engine,
     * and the browser and the renderer visibly disagree about one - which is the whole
     * reason this class exists.
     *
     * Below the root the test is a *paragraph* beside the loose run, and nothing weaker.
     * That is the fingerprint of the only thing that produces this damage: a paragraph
     * closed early by an element inside it always leaves its first half behind as a
     * paragraph, with the rest loose beside it. Anything weaker fires on documents that
     * were never broken, and the near miss is worth writing down because it was measured
     * rather than imagined - "sits beside a block" reads like the same rule and is not.
     * `listItem[text, bulletList]` is every nested list ever written; `heading[text, image]`
     * is every README title with a badge in it; `paragraph[image, text]` is a picture in a
     * sentence. All three are inline-beside-a-non-inline, none is damaged, all three are
     * what the browser holds too - and wrapping them puts a paragraph inside a heading,
     * splits a caption away from its picture, and rewrites every nested list on the way
     * through. A paragraph sibling appears in none of them.
     *
     * @param  array<int, mixed>  $content
     */
    protected function shouldWrap(array $content, bool $isRoot): bool
    {
        $loose = false;
        $paragraph = false;

        foreach ($content as $node) {
            if ($this->isLoose($node)) {
                $loose = true;

                continue;
            }

            if (is_array($node) && ($node['type'] ?? null) === 'paragraph') {
                $paragraph = true;
            }
        }

        return $loose && ($isRoot || $paragraph);
    }

    /**
     * The children, with every loose run of them put into a paragraph.
     *
     * @param  array<int, mixed>  $content
     * @return array<int, mixed>
     */
    protected function wrap(array $content): array
    {
        $wrapped = [];
        $run = [];

        foreach ($content as $node) {
            if ($this->isLoose($node)) {
                $run[] = $node;

                continue;
            }

            // A run of loose nodes is one paragraph rather than one each: they were a
            // single line of content before the element between them was unwrapped.
            $wrapped = [...$wrapped, ...$this->paragraph($run)];
            $run = [];

            $wrapped[] = $node;
        }

        return [...$wrapped, ...$this->paragraph($run)];
    }

    protected function isLoose(mixed $node): bool
    {
        return is_array($node) && in_array($node['type'] ?? null, static::INLINE, strict: true);
    }

    /**
     * The run wrapped in a paragraph, or nothing at all where the run is empty.
     *
     * No attributes are written: what a paragraph declares belongs to the schema that
     * declares it, and naming a default here would pin this package to a value that is
     * Filament's to change. Anything missing is filled in when the document is loaded.
     *
     * @param  array<int, mixed>  $run
     * @return array<int, array<string, mixed>>
     */
    protected function paragraph(array $run): array
    {
        if ($run === []) {
            return [];
        }

        return [['type' => 'paragraph', 'content' => $run]];
    }
}
