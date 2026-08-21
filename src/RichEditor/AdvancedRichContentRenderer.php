<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\TaskItemConverter;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use League\HTMLToMarkdown\HtmlConverter;
use RuntimeException;
use Tiptap\Core\Extension;
use Tiptap\Editor;
use Tiptap\Marks\Link as BaseLink;

/**
 * Filament's renderer with the output this package adds on top.
 *
 * A subclass rather than a set of macros on Filament's own class. A macro is registered
 * once and applies to every renderer in the application, including the ones other
 * packages build for their own content - installing this package would change what those
 * render. A subclass changes nothing that was not asked for, and a project that wants the
 * additions everywhere - including Filament's own model rich content attributes - can
 * bind it in the container instead, which is one line and is a decision rather than a
 * side effect.
 */
class AdvancedRichContentRenderer extends RichContentRenderer
{
    /**
     * The heading levels to anchor, or null while the renderer was never asked for
     * anchors.
     *
     * @var array<int, int> | null
     */
    protected ?array $anchorLevels = null;

    protected AnchorPosition $anchorPosition = AnchorPosition::None;

    protected string $anchorSymbol = '#';

    protected string $anchorClass = 'fi-arte-anchor';

    protected bool $hasLinkAttributes = true;

    /**
     * What this package asks the converter for on top of its own defaults.
     *
     * `atx` is the heading style everything written today uses; the library still
     * defaults to underlining h1 and h2, which is valid Markdown and looks like a
     * mistake in a diff. Anything passed to `toMarkdown()` wins over this.
     */
    public const MARKDOWN_OPTIONS = [
        'header_style' => 'atx',
        // Markdown that carries stray HTML is Markdown only in name. Rich content holds
        // markup the converter has no spelling for - the label and the box of a task
        // item, a grid, the wrapper of a custom block - and stripping those to their text
        // is what makes the result readable as Markdown.
        'strip_tags' => true,
    ];

    /**
     * Makes this the renderer the container hands out for Filament's own.
     *
     * Call it from a service provider where the additions should apply everywhere,
     * including the places that build a renderer themselves and take no arguments -
     * Filament's model rich content attributes among them. It is a call rather than
     * something this package does on installation, because it changes what every other
     * package's rich content renders through.
     */
    public static function bind(): void
    {
        app()->bind(RichContentRenderer::class, static::class);
    }

    /**
     * Whether links carry `hreflang`, `referrerpolicy` and an `id` on top of what Filament
     * declares.
     *
     * On by default: content that already holds those attributes should keep them. Turning
     * it off matches a field that was set up the same way, and strips them on the next
     * render.
     */
    public function linkAttributes(bool $condition = true): static
    {
        $this->hasLinkAttributes = $condition;

        return $this;
    }

    /**
     * Anchors the headings, so a link can point at one.
     *
     * `$position` decides whether the heading also carries a link to itself, and where.
     * The marker is a symbol, so a screen reader announces it as one - `Wrap` is the
     * position to reach for where that matters, since it makes the heading's own text
     * the link.
     *
     * @param  array<int, int> | null  $levels
     */
    public function anchorHeadings(
        ?array $levels = null,
        AnchorPosition|string|null $position = null,
        ?string $symbol = null,
        ?string $class = null,
    ): static {
        $this->anchorLevels = $levels ?? config('filament-advanced-rich-editor.anchors.levels', [2, 3]);

        $position ??= config('filament-advanced-rich-editor.anchors.position', AnchorPosition::None);

        $this->anchorPosition = $position instanceof AnchorPosition
            ? $position
            : AnchorPosition::from($position);

        $this->anchorSymbol = $symbol ?? config('filament-advanced-rich-editor.anchors.symbol', '#');
        $this->anchorClass = $class ?? config('filament-advanced-rich-editor.anchors.class', 'fi-arte-anchor');

        return $this;
    }

    /**
     * @return array<int, Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        if (! $this->hasLinkAttributes) {
            return [...parent::getTipTapPhpExtensions(), app(Anchor::class)];
        }

        $extensions = array_map(
            // Filament's link mark is replaced rather than joined by a second one. Two
            // extensions of the same name are both applied, which renders a link nested
            // inside a link; and the options carry the protocol allow list the field or
            // the renderer configured, so they are carried across rather than rebuilt.
            static fn (Extension $extension): Extension => $extension instanceof BaseLink && ! ($extension instanceof Link)
                ? new Link($extension->options)
                : $extension,
            parent::getTipTapPhpExtensions(),
        );

        return [
            ...$extensions,
            // Declared unconditionally. An anchor already in the stored markup should
            // survive being rendered whether or not this render asked for new ones, and
            // an attribute nothing writes costs nothing.
            app(Anchor::class),
        ];
    }

    /**
     * The document as Markdown.
     *
     * `league/html-to-markdown` does the conversion and is an optional dependency: a
     * project that never calls this should not carry it. See its documentation for the
     * options.
     *
     * @param  array<string, mixed>  $options
     */
    public function toMarkdown(array $options = []): string
    {
        if (! class_exists(HtmlConverter::class)) {
            throw new RuntimeException(
                'Rendering rich content as Markdown needs league/html-to-markdown. Install it with `composer require league/html-to-markdown`.',
            );
        }

        $converter = new HtmlConverter([...static::MARKDOWN_OPTIONS, ...$options]);
        $converter->getEnvironment()->addConverter(new TaskItemConverter);

        return trim($converter->convert($this->toUnsafeHtml()));
    }

    public function toUnsafeHtml(): string
    {
        // Filament's own renderer walks the document without checking that there is one,
        // and TipTap's walker reads the node's type before it checks for null - so
        // rendering a rich content column that nobody has typed into yet raises a
        // warning and then throws. An empty record renders as nothing.
        if (blank($this->content)) {
            return '';
        }

        return parent::toUnsafeHtml();
    }

    protected function processNodes(Editor $editor): void
    {
        // Before the caller's own processors: theirs may want to read the anchors, and
        // none of them can have written the ids this pass is about to hand out.
        if ($this->anchorLevels !== null) {
            (new HeadingIds)->assignTo(
                $editor,
                $this->anchorLevels,
                $this->anchorPosition === AnchorPosition::None
                    ? null
                    : fn (object $heading, array $entry): null => $this->linkHeadingToItself($heading, $entry['id']),
            );
        }

        parent::processNodes($editor);
    }

    /**
     * Draws the heading's link to its own anchor, in the configured position.
     */
    protected function linkHeadingToItself(object $heading, string $id): null
    {
        $link = (object) [
            'type' => 'link',
            'attrs' => (object) [
                'href' => '#'.$id,
                'class' => $this->anchorClass,
            ],
        ];

        if ($this->anchorPosition === AnchorPosition::Wrap) {
            // No marker is added; the text already there becomes the link. A heading that
            // carries other marks keeps them - the link is one more mark on the same
            // text, not a replacement for what was written.
            static::markTextNodes($heading, $link);

            return null;
        }

        $marker = (object) [
            'type' => 'text',
            'text' => $this->anchorSymbol,
            'marks' => [$link],
        ];

        $content = $heading->content ?? [];

        $heading->content = $this->anchorPosition === AnchorPosition::Before
            ? [$marker, ...$content]
            : [...$content, $marker];

        return null;
    }

    /**
     * Adds a mark to every piece of text under a node, however deeply it is nested.
     */
    protected static function markTextNodes(object $node, object $mark): void
    {
        if (($node->type ?? null) === 'text') {
            $node->marks = [...($node->marks ?? []), $mark];

            return;
        }

        foreach ($node->content ?? [] as $child) {
            static::markTextNodes($child, $mark);
        }
    }
}
