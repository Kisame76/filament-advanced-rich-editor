<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * Stored rich content on a page, as one tag.
 *
 *     <x-arte-content :content="$post->body" class="prose" />
 *
 * What it saves is three lines of renderer assembly in every template that prints a
 * document, and the one mistake those three lines invite: reaching for `toUnsafeHtml()`
 * because the name of the safe one is longer. What comes out of here has been through
 * Symfony's sanitiser, which is why the view prints it unescaped.
 *
 * Everything the renderer can be told, this can be told - and what it cannot, `:renderer`
 * can: hand it a renderer you built yourself and the props below are applied on top of it.
 */
class Content extends Component
{
    /**
     * @param  string|array<string, mixed>|null  $content  the stored document, as markup or as a TipTap array
     * @param  string|null  $tag  the element drawn around the document, or null for the document alone
     * @param  bool|array<int, int>  $anchors  true for the configured heading levels, or the levels themselves
     * @param  bool|string  $highlight  true for the configured code theme, or the name of one
     * @param  array<int, array<string, mixed>>|null  $styles  the project's named styles, overriding the config
     * @param  array<string, mixed>|null  $mergeTags
     * @param  array<int, mixed>|null  $mentions  mention providers, for documents that hold mentions
     * @param  bool|int  $cache  true for the configured lifetime, or a number of seconds
     */
    public function __construct(
        public string|array|null $content = null,
        public ?AdvancedRichContentRenderer $renderer = null,
        public ?string $tag = 'div',
        public bool|array $anchors = false,
        public bool|string $highlight = false,
        public ?array $styles = null,
        public bool $linkAttributes = true,
        public ?array $mergeTags = null,
        public ?array $mentions = null,
        public ?string $disk = null,
        public ?string $visibility = null,
        public bool|int $cache = false,
    ) {}

    public function render(): View
    {
        return view('filament-advanced-rich-editor::components.content', [
            'html' => $this->toHtml(),
            'element' => $this->element(),
        ]);
    }

    protected function toHtml(): string
    {
        return $this->buildRenderer()->toHtml();
    }

    protected function buildRenderer(): AdvancedRichContentRenderer
    {
        $renderer = $this->renderer ?? AdvancedRichContentRenderer::make();

        // The content wins over whatever the handed-in renderer was holding, because a
        // template that names both is naming the document it is printing here.
        if ($this->content !== null) {
            $renderer->content($this->content);
        }

        $renderer->linkAttributes($this->linkAttributes);

        if ($this->styles !== null) {
            $renderer->styles($this->styles);
        }

        if ($this->mergeTags !== null) {
            $renderer->mergeTags($this->mergeTags);
        }

        if ($this->mentions !== null) {
            $renderer->mentions($this->mentions);
        }

        if ($this->disk !== null) {
            $renderer->fileAttachmentsDisk($this->disk);
        }

        if ($this->visibility !== null) {
            $renderer->fileAttachmentsVisibility($this->visibility);
        }

        if ($this->anchors !== false) {
            $renderer->anchorHeadings(is_array($this->anchors) ? $this->anchors : null);
        }

        if ($this->highlight !== false) {
            $renderer->highlightCode(is_string($this->highlight) ? $this->highlight : null);
        }

        if ($this->cache !== false) {
            $renderer->cached(is_int($this->cache) ? $this->cache : null);
        }

        return $renderer;
    }

    /**
     * The wrapper's tag name, or null where the document is to stand on its own.
     *
     * Whitelisted rather than printed: it lands between angle brackets, and the one place
     * a value like that may come from is a variable somebody passed in.
     *
     * A name that is not one falls back to a `div` rather than to no wrapper at all.
     * Refusing the name and throwing away the caller's attributes are two different
     * decisions, and only the first one was asked for - dropping the wrapper takes the
     * `class` with it, so a page loses its typography over a typo and nothing says why.
     * Only an explicit null means "no element".
     */
    protected function element(): ?string
    {
        if ($this->tag === null) {
            return null;
        }

        return preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $this->tag) === 1 ? $this->tag : 'div';
    }
}
