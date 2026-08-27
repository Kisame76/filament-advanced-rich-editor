<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\RichEditor\TipTapExtensions\MentionExtension;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\TaskItemConverter;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\FontFamily;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\FontSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Language;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\StyleClass;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\TextBackground;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Callout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\TaskItem;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\BlockStyle;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageCaption;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageFloat;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageRotate;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ListProperties;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Mention;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\TextDirection;
use League\HTMLToMarkdown\HtmlConverter;
use RuntimeException;
use Tiptap\Core\Extension;
use Tiptap\Editor;
use Tiptap\Marks\Link as BaseLink;
use Tiptap\Nodes\TaskList;

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

    /**
     * @var array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>|null
     */
    protected ?array $styles = null;

    protected bool $hasLinkAttributes = true;

    protected ?CodeHighlighter $codeHighlighter = null;

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
    /**
     * The named styles this render knows about, overriding the project's.
     *
     * A field passes its own list here so that the schema a save is parsed through is the
     * same one the toolbar offered from. Null keeps the project's, which is what a plain
     * render of a stored document wants.
     *
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>|null  $styles
     */
    public function styles(?array $styles): static
    {
        $this->styles = $styles;

        return $this;
    }

    public function linkAttributes(bool $condition = true): static
    {
        $this->hasLinkAttributes = $condition;

        return $this;
    }

    /**
     * Colours the code blocks.
     *
     * A single theme by default, because that needs no stylesheet of yours. Passing a pair
     * writes both into the same markup - the light one as ordinary colours, the dark one as
     * custom properties - and then a rule in your own theme swaps them; see the README.
     *
     * @param  array{light: string|BackedEnum, dark: string|BackedEnum}|null  $themes
     */
    public function highlightCode(string|BackedEnum|null $theme = null, ?array $themes = null): static
    {
        $this->codeHighlighter = new CodeHighlighter(
            $theme ?? config('filament-advanced-rich-editor.code_block.theme', 'github-light'),
            $themes ?? config('filament-advanced-rich-editor.code_block.themes'),
        );

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
        // Before anything else, and on every path: what the mention node renders is the
        // only place a page learns which trigger a mention was written with, because the
        // sanitiser removes the attribute that says so.
        $extensions = array_map(
            static fn (Extension $extension): Extension => $extension instanceof MentionExtension && ! ($extension instanceof Mention)
                ? new Mention($extension->options)
                : $extension,
            parent::getTipTapPhpExtensions(),
        );

        // Only the link mark hangs off the switch, and nothing else may join it here: this
        // used to return early for a field with the attributes turned off, past the point
        // where the nodes below are declared - so asking for Filament's plain links threw
        // away every video and every caption in the document along with them.
        if ($this->hasLinkAttributes) {
            $extensions = array_map(
                // Filament's link mark is replaced rather than joined by a second one. Two
                // extensions of the same name are both applied, which renders a link nested
                // inside a link; and the options carry the protocol allow list the field or
                // the renderer configured, so they are carried across rather than rebuilt.
                static fn (Extension $extension): Extension => $extension instanceof BaseLink && ! ($extension instanceof Link)
                    ? new Link($extension->options)
                    : $extension,
                $extensions,
            );
        }

        return static::withoutDuplicates([
            ...$extensions,
            // Declared unconditionally. An anchor already in the stored markup should
            // survive being rendered whether or not this render asked for new ones, and
            // an attribute nothing writes costs nothing.
            app(Anchor::class),
            // The same reasoning, and one more: a renderer that has to be told about the
            // embed node is one that silently drops every video in a document the day
            // somebody forgets to tell it.
            app(Embed::class),
            // And the same again: a note somebody wrote is a note that belongs on the page,
            // whether or not this render was told the field had callouts switched on.
            app(Callout::class),
            app(ImageCaption::class),
            // And again, for the reason above it: a picture somebody turned is one that
            // should still be turned, and one somebody set the text to run past is one the
            // text should still run past - whether or not this render was told the field
            // offered the buttons. The rotation used to arrive only with
            // `ImageResizePlugin`, so a plain render of a stored document dropped it.
            app(ImageRotate::class),
            app(ImageFloat::class),
            // The same again, six times over. Every one of these used to arrive only with
            // the plugin that puts its button on the bar, so a plain render of a stored
            // document dropped it without a word: a task list came back as an ordinary
            // bullet list with every tick gone, and a size, a typeface, a line height, a
            // highlight and a writing direction simply were not there. The rule this class
            // states four times above holds for them too - a renderer that has to be told
            // is one that drops it the day somebody forgets to say so - and none of them
            // takes any configuration, so declaring them costs a line each.
            app(TaskList::class, ['options' => ['HTMLAttributes' => ['class' => 'fi-arte-task-list']]]),
            app(TaskItem::class, ['options' => ['HTMLAttributes' => ['class' => 'fi-arte-task-item']]]),
            app(FontSize::class),
            app(FontFamily::class),
            app(LineHeight::class),
            app(TextBackground::class),
            app(TextDirection::class),
            // And again: a passage somebody marked as French is one a screen reader should
            // still read in French, and a list somebody set to start at twelve is one that
            // should still start at twelve - whether or not this render was told the field
            // had either switched on.
            app(Language::class),
            app(ListProperties::class),
            // Declared with whatever the project configured, for the reason above: a
            // renderer that has to be told about a style is one that drops it the day
            // somebody forgets to say so. An empty list declares nothing, so a project with
            // no styles pays nothing for them.
            //
            // Skipped where a plugin already brought them, and that is not an optimisation:
            // two extensions of the same name are both applied, so a second copy renders a
            // span inside a span and grows another layer on every save. A field passes its
            // own list through `styles()` and its plugin declares the pair, which is the
            // one that has to win - it is the list the toolbar offered from.
            ...$this->styleExtensions($extensions),
        ]);
    }

    /**
     * One extension per name, keeping the first of any repeat.
     *
     * Two extensions of one name are both applied rather than one winning, and what that
     * looks like depends on what the extension is. A mark renders a span inside a span and
     * grows another layer on every save. A node is worse and quieter: `DOMSerializer`
     * breaks out of its opening-tag loop on the first match but not out of its closing one,
     * so a document picks up a stray `</div>` that a browser silently drops and a diff does
     * not.
     *
     * The list arrives with the field's own plugins in front of the ones declared here, and
     * that is the order to keep: a plugin's instance carries the field's configuration,
     * this one is the fallback for a render that was never told anything.
     *
     * This is not a tidy-up. A field with videos switched on declares the embed node twice
     * - once through `EmbedPlugin`, once here - and every save it made wrote that stray tag.
     *
     * @param  array<int, Extension>  $extensions
     * @return array<int, Extension>
     */
    protected static function withoutDuplicates(array $extensions): array
    {
        $kept = [];
        $seen = [];

        foreach ($extensions as $extension) {
            // An extension with no name of its own cannot collide with anything, so it is
            // passed through rather than folded into one nameless bucket.
            $name = $extension::$name;

            if (! is_string($name) || $name === '') {
                $kept[] = $extension;

                continue;
            }

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $kept[] = $extension;
        }

        return $kept;
    }

    /**
     * The two style extensions, or none where the list is empty or something already
     * declared them.
     *
     * @param  array<int, Extension>  $extensions
     * @return array<int, Extension>
     */
    protected function styleExtensions(array $extensions): array
    {
        foreach ($extensions as $extension) {
            if ($extension instanceof BlockStyle || $extension instanceof StyleClass) {
                return [];
            }
        }

        $styles = $this->styles ?? Styles::all();

        return ($styles === [])
            ? []
            : [
                app(BlockStyle::class, ['options' => ['styles' => Styles::ofScope($styles, 'block')]]),
                app(StyleClass::class, ['options' => ['styles' => Styles::ofScope($styles, 'inline')]]),
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

        $html = parent::toUnsafeHtml();

        // Unconditional, like the attribute it reads: an image that carries a caption is one
        // whose caption belongs on the page, and nothing has to ask for that.
        $html = (new ImageCaptions)->apply($html);

        // Same reasoning: a width somebody dragged a column to is a width that belongs on
        // the page, and the attribute it is stored in means nothing to a browser.
        $html = (new TableColumnWidths)->apply($html);

        // Before the sanitiser rather than after it: what a highlighter produces is markup,
        // and markup this package generates goes through the same door as everything else.
        return $this->codeHighlighter?->apply($html) ?? $html;
    }

    /**
     * The document as plain text, mentions included.
     *
     * TipTap's text serialiser walks `content` and `text` and calls `renderText()` on
     * nothing at all, so an atom node contributes an empty string - and the block separator
     * left around it turns into a hole in the middle of a sentence. Filament already works
     * around this for merge tags by rewriting them into text before serialising; mentions
     * need the same treatment, and this is where a search index, an excerpt or the body of
     * a notification gets its copy of the document.
     */
    public function toText(): string
    {
        // The same reasoning as `toUnsafeHtml()`: the serialiser reads the document before
        // it checks that there is one.
        if (blank($this->content)) {
            return '';
        }

        $editor = $this->getEditor();

        // In the order Filament's own `toText()` uses them, with the mention passes added:
        // resolving first, so that the text is written from what the providers say now
        // rather than from the copy the document was saved with.
        $this->processMergeTags($editor);
        $this->flattenMergeTagsForText($editor);
        $this->processMentions($editor);
        $this->flattenMentionsForText($editor);

        return $editor->getText();
    }

    /**
     * Rewrites every mention into the text it reads as.
     *
     * Adjacent text is joined rather than left as neighbouring nodes, because the serialiser
     * puts its block separator between any two children - so "Ping @Ada and #Backend" would
     * come out as four lines with the mention missing from two of them.
     */
    protected function flattenMentionsForText(Editor $editor): void
    {
        $editor->descendants(function (object &$node): void {
            if (! isset($node->content) || ! is_array($node->content)) {
                return;
            }

            $hasMentions = false;

            foreach ($node->content as $child) {
                if (($child->type ?? null) === 'mention') {
                    $hasMentions = true;

                    break;
                }
            }

            if (! $hasMentions) {
                return;
            }

            $merged = [];

            foreach ($node->content as $child) {
                $resolved = ($child->type ?? null) === 'mention'
                    ? static::mentionAsText($child)
                    : $child;

                // A mention with neither a label nor an id names nobody. Dropping it leaves
                // the sentence around it intact, which is the best available answer.
                if ($resolved === null) {
                    continue;
                }

                $last = end($merged);

                if ($last && ($last->type ?? null) === 'text' && $resolved->type === 'text') {
                    $last->text .= $resolved->text;

                    continue;
                }

                $merged[] = $resolved;
            }

            $node->content = $merged;
        });
    }

    /**
     * One mention as the text node that reads the way it was typed.
     */
    protected static function mentionAsText(object $node): ?object
    {
        $label = $node->attrs->label ?? null;

        // The id rather than nothing: a visible identifier beats a hole where a name should
        // be, and it is what the document has left once the label is gone.
        if (blank($label)) {
            $label = $node->attrs->id ?? null;
        }

        if (blank($label)) {
            return null;
        }

        return (object) [
            'type' => 'text',
            'text' => ($node->attrs->char ?? '@').$label,
        ];
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
