<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * A document parsed on the server, fitted to the schema the editor holds it to in the browser.
 *
 * tiptap-php declares no content spec on its nodes, so it parses markup into whatever shape
 * the markup had. ProseMirror's own parser fits markup to the schema as it reads it; the
 * document Filament hands the browser is read without any check at all, and an ill-fitting
 * one is held as it came until something trips over it - "Called contentMatchAt on a node
 * with invalid content", out of a click on a picture or a keystroke in a table cell. Markup
 * this editor wrote never has the problem. Markup written anywhere else does, constantly:
 *
 *     <p>Text</p><img src="…">       doc[paragraph, image]           a picture is inline
 *     <div>Text</div>                 doc[text]                       a div is no node here
 *     <td>Eins</td>  <td></td>        tableCell[text]  tableCell[]    a cell holds blocks
 *     <blockquote>Zitat</blockquote>  blockquote[text]
 *     <li>One<ul>…</ul></li>          listItem[text, bulletList]      an item opens with a paragraph
 *
 * So each run of inline nodes where only blocks belong becomes a paragraph, and a container
 * that has to hold a block - or open with a paragraph - and does not gets an empty one: what
 * ProseMirror's parser does to the same markup. Both lists below were read out of the schema
 * a running browser builds with every tool of this package on, not reasoned about. A node on
 * neither list is left exactly where it is, since nothing here says whether it is inline.
 */
class BlockContent
{
    /**
     * The node types that belong inside a paragraph, never beside one.
     */
    public const INLINE = ['text', 'hardBreak', 'image', 'mention', 'file'];

    /**
     * The node types that hold blocks and nothing else, by the block they have to open with:
     * any (`block+`) or a paragraph (`paragraph block*`).
     *
     * @var array<string, string|null>
     */
    public const BLOCKS = [
        'doc' => null,
        'blockquote' => null,
        'callout' => null,
        'lead' => null,
        'detailsContent' => null,
        'gridColumn' => null,
        'tableCell' => null,
        'tableHeader' => null,
        'listItem' => 'paragraph',
        'taskItem' => 'paragraph',
    ];

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function apply(array $document): array
    {
        return $this->fit($document);
    }

    /**
     * One node, its children fitted first.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function fit(array $node): array
    {
        $content = $node['content'] ?? [];

        if (is_array($content) && $content !== []) {
            $content = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->fit($child) : $child,
                $content,
            );

            $node['content'] = $content;
        }

        $type = $node['type'] ?? null;

        if (! is_string($type) || ! array_key_exists($type, static::BLOCKS) || ! is_array($content)) {
            return $node;
        }

        $content = $this->wrap($content);
        $first = $content[0]['type'] ?? null;

        if ($content === [] || (static::BLOCKS[$type] !== null && $first !== static::BLOCKS[$type])) {
            array_unshift($content, ['type' => 'paragraph']);
        }

        $node['content'] = $content;

        return $node;
    }

    /**
     * The children, with every run of inline nodes among them put into a paragraph of its own.
     *
     * One paragraph a run rather than one a node: they were a single line before whatever was
     * around them - a `<div>`, a `<figure>` - was dropped, and nothing says where it ended.
     *
     * @param  array<int, mixed>  $content
     * @return array<int, mixed>
     */
    protected function wrap(array $content): array
    {
        $fitted = [];
        $run = [];

        foreach ($content as $node) {
            if (is_array($node) && in_array($node['type'] ?? null, static::INLINE, strict: true)) {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $fitted[] = $this->paragraph($run);
                $run = [];
            }

            $fitted[] = $node;
        }

        if ($run !== []) {
            $fitted[] = $this->paragraph($run);
        }

        return $fitted;
    }

    /**
     * No attributes: what a paragraph declares is its schema's, and the browser fills in
     * every one that is missing as it reads the document.
     *
     * @param  array<int, mixed>  $run
     * @return array<string, mixed>
     */
    protected function paragraph(array $run): array
    {
        return ['type' => 'paragraph', 'content' => $run];
    }
}
