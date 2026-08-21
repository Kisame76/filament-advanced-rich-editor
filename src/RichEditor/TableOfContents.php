<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * The headings of a document, as a list that links into it.
 *
 * It holds a renderer rather than being one. A table of contents is not a rendering of
 * the content - it is a second thing derived from it - and a class that answered
 * `toHtml()` with a list of links while inheriting a method of the same name meaning
 * "the document" would be a trap for whoever reads the call site.
 *
 * The anchors come from the same pass the renderer uses, so a link here and an `id` on
 * the page cannot drift apart: both are what one `HeadingIds` handed out.
 */
class TableOfContents
{
    protected AdvancedRichContentRenderer $renderer;

    /**
     * @var array<int, int> | null
     */
    protected ?array $levels = null;

    protected string $class = 'fi-arte-toc';

    /**
     * @param  string | array<string, mixed> | null  $content
     */
    public function __construct(string|array|null $content = null)
    {
        $this->renderer = AdvancedRichContentRenderer::make($content);
    }

    /**
     * @param  string | array<string, mixed> | null  $content
     */
    public static function make(string|array|null $content = null): static
    {
        return app(static::class, ['content' => $content]);
    }

    /**
     * Which heading levels the list covers. Everything else is passed over, and a heading
     * under a level that was skipped still nests one step deeper - `h2` followed by `h4`
     * is a document that skipped a level, not two headings of equal rank.
     *
     * @param  array<int, int>  $levels
     */
    public function levels(array $levels): static
    {
        $this->levels = $levels;

        return $this;
    }

    public function class(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    /**
     * The plugins the document needs to parse, for content that carries nodes this
     * package or another one added.
     *
     * @param  array<RichContentPlugin>  $plugins
     */
    public function plugins(array $plugins): static
    {
        $this->renderer->plugins($plugins);

        return $this;
    }

    /**
     * @param  array<string, mixed> | null  $tags
     */
    public function mergeTags(?array $tags): static
    {
        $this->renderer->mergeTags($tags);

        return $this;
    }

    /**
     * @param  array<class-string> | null  $blocks
     */
    public function customBlocks(?array $blocks): static
    {
        $this->renderer->customBlocks($blocks);

        return $this;
    }

    /**
     * The headings, nested, each with the anchor the rendered page will carry.
     *
     * @return array<int, array{level: int, text: string, id: string, children: array<int, mixed>}>
     */
    public function asArray(): array
    {
        return static::nest($this->getHeadings());
    }

    public function asHtml(): Htmlable
    {
        $items = $this->asArray();

        if ($items === []) {
            return new HtmlString('');
        }

        return new HtmlString(
            '<nav class="'.e($this->class).'">'.static::listOf($items).'</nav>',
        );
    }

    /**
     * @return array<int, array{level: int, text: string, id: string}>
     */
    protected function getHeadings(): array
    {
        $levels = $this->levels ?? config('filament-advanced-rich-editor.anchors.levels', [2, 3]);

        return (new HeadingIds)->assignTo($this->renderer->getEditor(), $levels);
    }

    /**
     * Turns the flat reading order into the tree the levels describe.
     *
     * A heading joins the closest heading above it that outranks it, which is what makes
     * a document that jumps from `h2` to `h4` come out as one step of nesting rather than
     * as two lists or as a crash.
     *
     * @param  array<int, array{level: int, text: string, id: string}>  $headings
     * @return array<int, array{level: int, text: string, id: string, children: array<int, mixed>}>
     */
    protected static function nest(array $headings): array
    {
        $tree = [];

        // The headings the next one could belong under, innermost last. Each entry holds a
        // reference to its own children, so appending to it appends into the tree.
        $ancestors = [];

        foreach ($headings as $heading) {
            $entry = [...$heading, 'children' => []];

            // Everything of equal or lower rank is finished: a heading belongs under
            // neither one it outranks nor its own equal.
            while ($ancestors !== [] && $ancestors[array_key_last($ancestors)]['level'] >= $entry['level']) {
                array_pop($ancestors);
            }

            if ($ancestors === []) {
                $siblings = &$tree;
            } else {
                $siblings = &$ancestors[array_key_last($ancestors)]['children'];
            }

            $siblings[] = $entry;

            $ancestors[] = [
                'level' => $entry['level'],
                'children' => &$siblings[array_key_last($siblings)]['children'],
            ];

            unset($siblings);
        }

        return $tree;
    }

    /**
     * @param  array<int, array{level: int, text: string, id: string, children: array<int, mixed>}>  $items
     */
    protected static function listOf(array $items): string
    {
        $html = '<ol>';

        foreach ($items as $item) {
            $html .= '<li><a href="#'.e($item['id']).'">'.e($item['text']).'</a>';
            $html .= $item['children'] === [] ? '' : static::listOf($item['children']);
            $html .= '</li>';
        }

        return $html.'</ol>';
    }
}
