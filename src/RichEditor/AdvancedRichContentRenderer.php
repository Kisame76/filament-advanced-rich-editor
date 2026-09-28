<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Closure;
use DateInterval;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\RichEditor\TipTapExtensions\MentionExtension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Contracts\TransformsRenderedHtml;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\FileCardConverter;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\ImportedMarkup;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\LooseText;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown\TaskItemConverter;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\FontFamily;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\FontSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Language;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\StyleClass;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\TextBackground;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Callout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\FileCard;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Media;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\TaskItem;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\BlockStyle;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageCaption;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageDecorative;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageFloat;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageRotate;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ListProperties;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Mention;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\TextDirection;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
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
     * What `highlightCode()` was told, kept beside the highlighter it built.
     *
     * The highlighter holds it too, and holds it privately - which is right for a
     * collaborator and wrong for the render cache, whose whole job is to tell two
     * configurations apart. Two lines of the same colour are one page; two different
     * themes are two.
     *
     * @var array{0: string|BackedEnum|null, 1: array<string, string|BackedEnum>|null}|null
     */
    protected ?array $codeThemes = null;

    /**
     * Plugins every render declares, whether or not it was handed them.
     *
     * The seam a package that adds a node of its own needs. Handing plugins to a render
     * already works and is what a field does, but the call this package is built around is
     * `AdvancedRichContentRenderer::make($article->content)->toHtml()` with nothing else
     * said - and a node that only survives when somebody remembers to name its plugin is a
     * node that disappears from the page the day somebody forgets. That is the same bug this
     * package found seven times in itself.
     *
     * Static rather than a container binding because it has to survive `make()`, which
     * builds a fresh renderer every time and has no idea what an application registered.
     *
     * @var array<int, RichContentPlugin>
     */
    protected static array $globalPlugins = [];

    protected bool $isCached = false;

    protected int|DateInterval|null $cacheTtl = null;

    protected ?string $cacheStore = null;

    protected string|Closure|null $cacheKey = null;

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
     * What this package asks the Markdown parser for on the way back in.
     *
     * A `javascript:` url is the one thing CommonMark writes that a document has no
     * business keeping. The schema already drops it from a link, because a link is a mark
     * and a mark is checked; an image's address is an attribute and is stored exactly as
     * written. Nothing executes either way - browsers stopped running `javascript:` in a
     * `src` long ago - but a column is not the place for it. Anything passed to
     * `fromMarkdown()` wins over this, the same as on the way out.
     */
    public const MARKDOWN_IMPORT_OPTIONS = [
        'allow_unsafe_links' => false,
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
     * Declares plugins for every render, from a service provider.
     *
     * For the half of a plugin this package owns. The editor half is Filament's and already
     * open: a `RichContentPlugin` handed to a field through `->plugins()` gets its PHP
     * extensions, its JS extensions and its tools. What was closed is the rendering half -
     * a node that renders in the form and vanishes on the page, because the renderer builds
     * its own extension list and a plain `make()->toHtml()` was never told about the plugin.
     *
     * A plugin that also implements `TransformsRenderedHtml` gets a pass over the finished
     * markup, which is how an attribute becomes a structure - the thing a schema cannot do
     * and this package does four times over for captions, links, decorative pictures and
     * column widths.
     *
     * Registering the same plugin twice registers it once: service providers run in an order
     * nobody controls, and a plugin declared in two of them should not render its node twice.
     */
    public static function extendWith(RichContentPlugin ...$plugins): void
    {
        foreach ($plugins as $plugin) {
            static::$globalPlugins[$plugin::class] = $plugin;
        }
    }

    /**
     * Forgets what `extendWith()` was told.
     *
     * For tests, and for the application that registers conditionally and has to undo it.
     */
    public static function forgetExtensions(): void
    {
        static::$globalPlugins = [];
    }

    /**
     * @return array<int, RichContentPlugin>
     */
    public static function getGlobalPlugins(): array
    {
        return array_values(static::$globalPlugins);
    }

    /**
     * The plugins this render uses: the ones it was handed, and the ones every render has.
     *
     * The render's own come first, so a field that configured a plugin wins over the plain
     * instance an application registered - the same rule `withoutDuplicates()` applies to
     * the extensions further down, and for the same reason: an instance carries
     * configuration and the first of a name is the one that keeps it.
     *
     * @return array<int, RichContentPlugin>
     */
    public function getPlugins(): array
    {
        $plugins = [];

        // The render's own first, so the copy a field configured is the one kept: an
        // instance carries configuration and the first of a class is the one that has it.
        // Keyed by class rather than concatenated, because the same plugin can arrive both
        // ways - registered once by a service provider and handed to this render as well -
        // and a `TransformsRenderedHtml` pass present twice runs twice, wrapping what it
        // wraps inside a second copy of itself.
        foreach ([...parent::getPlugins(), ...static::getGlobalPlugins()] as $plugin) {
            $plugins[$plugin::class] ??= $plugin;
        }

        return array_values($plugins);
    }

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
        $this->codeThemes = [
            $theme ?? config('filament-advanced-rich-editor.code_block.theme', 'github-light'),
            $themes ?? config('filament-advanced-rich-editor.code_block.themes'),
        ];

        $this->codeHighlighter = new CodeHighlighter(...$this->codeThemes);

        return $this;
    }

    /**
     * Keeps the rendered markup, so the next page does not build it again.
     *
     * Turning a TipTap document into HTML is a parse, a walk over every node and a pass
     * through the sanitiser, and a page that prints the same article to a thousand readers
     * does all of it a thousand times. Off by default, because a cache nobody asked for is
     * a stale page nobody can explain.
     *
     * The key is the content AND the configuration: the same article rendered with anchors,
     * with a different code theme or with another set of named styles is another page, and
     * a key built from the content alone would hand one of them the other's markup. See
     * `Fingerprint` for how that is worked out, and for the two things it cannot see:
     *
     *  - what a closure closes over. A merge tag whose value comes from a variable, or a
     *    node processor built around one, prints the same key for two different pages.
     *  - what a mention provider will answer. Labels are looked up when the page is drawn,
     *    which is the whole point of them, and a cached page holds the ones from last time.
     *
     * Either of those is a reason to pass `->cacheKey()` a key that says what changed, or
     * to leave the cache off for that render.
     *
     * `$ttl` is seconds; null takes the configured lifetime, and false turns a cache that
     * was switched on back off. Where content holds private attachments the lifetime is
     * capped at the life of the temporary URLs in it - see `getRenderCacheTtl()`.
     */
    public function cached(bool|int|DateInterval|null $ttl = null, ?string $store = null): static
    {
        if ($ttl === false) {
            $this->isCached = false;

            return $this;
        }

        $this->isCached = true;
        $this->cacheTtl = ($ttl === true) ? null : $ttl;
        $this->cacheStore = $store ?? $this->cacheStore;

        return $this;
    }

    /**
     * The key this render is remembered under, in place of the one worked out from the
     * configuration.
     *
     * For the two cases the fingerprint cannot see - a closure that closes over something,
     * a provider that answers differently over time - and for the ordinary case of a record
     * that already knows when it last changed:
     * `->cacheKey($post->getKey().'-'.$post->updated_at->timestamp)` is shorter to compute
     * and easier to reason about than any hash of the document.
     *
     * Passing null puts the fingerprint back.
     */
    public function cacheKey(string|Closure|null $key): static
    {
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * How long this render may be kept.
     *
     * Capped where the markup can hold a temporary URL, which is what Filament hands back
     * for an attachment on a private disk. Those expire - half an hour by default - and a
     * page cached for a day would spend the rest of it showing broken pictures. A render
     * with an attachment provider is exempt: the provider decides what a URL is, and
     * Spatie's are ordinary ones.
     */
    public function getRenderCacheTtl(): int|DateInterval|null
    {
        $ttl = $this->cacheTtl ?? config('filament-advanced-rich-editor.render_cache.ttl');

        $expiry = $this->hasTemporaryAttachmentUrls()
            ? (int) config('filament.temporary_file_url_expiry_minutes', 30) * 60
            : null;

        if ($expiry === null) {
            return $ttl;
        }

        return is_int($ttl) ? min($ttl, $expiry) : $expiry;
    }

    /**
     * The key one flavour of this render is remembered under.
     *
     * The flavour is part of it because the same document is asked for as markup, as plain
     * text and as Markdown, and those are three answers rather than one.
     */
    public function getRenderCacheKey(string $variant): string
    {
        $key = value($this->cacheKey) ?? $this->getRenderFingerprint();

        return implode('.', [
            (string) config('filament-advanced-rich-editor.render_cache.prefix', 'arte.render'),
            $variant,
            $key,
        ]);
    }

    /**
     * Everything about this renderer that a reader would notice, as one string.
     *
     * The extensions are in it as themselves rather than as the switches that produced
     * them: a plugin a project wrote contributes nodes this class has never heard of, and
     * asking the list what is in it is the only way to notice.
     */
    public function getRenderFingerprint(): string
    {
        return Fingerprint::of([
            'content' => $this->content,
            'extensions' => $this->getTipTapPhpExtensions(),
            'anchors' => [$this->anchorLevels, $this->anchorPosition->value, $this->anchorSymbol, $this->anchorClass],
            'styles' => $this->styles,
            'linkAttributes' => $this->hasLinkAttributes,
            'code' => $this->codeThemes,
            'disk' => $this->fileAttachmentsDiskName,
            'visibility' => $this->fileAttachmentsVisibility,
            'attachments' => $this->getFileAttachmentProvider(),
            'mergeTags' => $this->mergeTags,
            'customBlocks' => $this->customBlocks,
            'mentions' => $this->mentionProviders,
            'colors' => $this->textColors,
            'protocols' => $this->linkProtocols,
            'processors' => $this->nodeProcessors,
            'plugins' => $this->plugins,
            // The registered ones as well as the render's own: a plugin that only
            // transforms markup adds no extension, so the list above cannot see it.
            'globalPlugins' => static::getGlobalPlugins(),
        ]);
    }

    /**
     * Whether the markup this render produces can hold a URL that expires.
     */
    protected function hasTemporaryAttachmentUrls(): bool
    {
        if ($this->getFileAttachmentProvider() !== null) {
            return false;
        }

        $disk = $this->fileAttachmentsDiskName ?? config('filament.default_filesystem_disk');

        // Filament's own rule, read the same way it reads it.
        return ($this->fileAttachmentsVisibility ?? ($disk === 'public' ? 'public' : 'private')) === 'private';
    }

    /**
     * @param  Closure(): string  $render
     */
    protected function remember(string $variant, Closure $render): string
    {
        if (! $this->isCached) {
            return $render();
        }

        $store = Cache::store($this->cacheStore ?? config('filament-advanced-rich-editor.render_cache.store'));
        $key = $this->getRenderCacheKey($variant);
        $ttl = $this->getRenderCacheTtl();

        return $ttl === null
            ? $store->rememberForever($key, $render)
            : $store->remember($key, $ttl, $render);
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
            app(Media::class),
            // And once more, for the reason above it: an uploaded document is a card in
            // the markup, so a render that had to be told about the node would hand a
            // reader three bare spans - a file name with a word after it, which is what
            // the card exists not to be.
            app(FileCard::class),
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
            // And a picture that says it carries nothing has to keep saying so: an empty
            // `alt` that lost its role is indistinguishable from a description somebody
            // forgot, and that is precisely what the accessibility check has to report.
            app(ImageDecorative::class),
            // And an address a picture points at is one it should still point at.
            app(ImageLink::class),
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

        return $this->remember('markdown.'.Fingerprint::of($options), function () use ($options): string {
            $converter = new HtmlConverter([...static::MARKDOWN_OPTIONS, ...$options]);
            $converter->getEnvironment()->addConverter(new TaskItemConverter);
            $converter->getEnvironment()->addConverter(new FileCardConverter);

            return trim($converter->convert($this->toUnsafeHtml()));
        });
    }

    /**
     * A Markdown document, read into the document this editor stores.
     *
     * The mirror of `toMarkdown()`, and the more dangerous direction: what the export gets
     * wrong is a string somebody reads, and what this gets wrong is a column somebody
     * keeps. Markdown says more than any rich text schema can hold, and every one of those
     * things arrives looking like content.
     *
     * `league/commonmark` is not an optional dependency the way the export's converter is -
     * `laravel/framework` requires it - so nothing has to be installed for this. `Str::markdown()`
     * is the GitHub-flavoured converter, which brings tables, strikethrough, bare urls and
     * `- [x]` along with it; tables in particular need no permission from the toolbar,
     * because Filament's renderer declares them unconditionally.
     *
     * Three things are done on top of it, and none of them is a preference:
     *
     * - Footnotes are parsed. Without the extension CommonMark reads `[^1]: The note.` as a
     *   link reference definition: the note's text disappears from the document and the
     *   marker before it becomes a link pointing at what used to be the note. A footnote
     *   *with* the extension is something this schema can hold - a superscript marker, a
     *   rule and a numbered list - so the extension turns a silent loss into a document.
     * - `ImportedMarkup` translates the markup CommonMark writes for a construct this
     *   schema has no node for.
     * - `LooseText` repairs the document afterwards, because raw HTML - which is part of
     *   Markdown - can leave text with no block around it, which the field and the page
     *   then disagree about.
     *
     * The result is the document itself rather than a renderer holding it, because the one
     * thing anybody wants it for is the column:
     *
     * ```php
     * $article->content = AdvancedRichContentRenderer::make()->fromMarkdown($markdown);
     * ```
     *
     * Nothing has to be registered for any of it, task lists included: the nodes are
     * declared here unconditionally, on the rule this class states six times over - a
     * renderer that has to be told is one that drops the thing the day somebody forgets to
     * say so.
     *
     * @param  array<string, mixed>  $options
     * @param  array<int, ExtensionInterface>  $extensions
     * @return array<string, mixed>
     */
    public function fromMarkdown(string $markdown, array $options = [], array $extensions = []): array
    {
        $html = Str::markdown(
            $markdown,
            [...static::MARKDOWN_IMPORT_OPTIONS, ...$options],
            $this->markdownExtensions($extensions),
        );

        $html = (new ImportedMarkup)->apply($html);

        // TipTap's PHP parser reads the body of a parsed document without checking that
        // there is one, so an empty string raises rather than answering. An empty paragraph
        // is the answer rather than an empty `content` array because it is what Filament's
        // own state cast puts in a field nobody has typed into.
        $document = $this->getEditor()->setContent(blank($html) ? '<p></p>' : $html)->getDocument();

        return (new LooseText)->apply((array) $document);
    }

    /**
     * The parser extensions, with the one this package insists on unless it was named.
     *
     * Named again rather than added twice: CommonMark registers a parser per extension and
     * a second copy of the footnote one raises while the environment is built. A caller
     * passing their own configured `FootnoteExtension` is the case that matters, and it is
     * theirs to configure.
     *
     * @param  array<int, ExtensionInterface>  $extensions
     * @return array<int, ExtensionInterface>
     */
    protected function markdownExtensions(array $extensions): array
    {
        foreach ($extensions as $extension) {
            if ($extension instanceof FootnoteExtension) {
                return $extensions;
            }
        }

        return [new FootnoteExtension, ...$extensions];
    }

    /**
     * The sanitised markup, out of the cache where this render was told to keep one.
     *
     * Wrapped here rather than around `toUnsafeHtml()`: what is worth keeping is the
     * finished page, and the unsafe half is a step on the way to it that `toMarkdown()`
     * also walks - caching both would store the same document twice.
     */
    public function toHtml(): string
    {
        return $this->remember('html', fn (): string => parent::toHtml());
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

        // After the captions, and that order is the point: the link belongs around the
        // picture inside a `<figure>` rather than around the figure, because a caption is
        // text about the picture rather than part of what is being linked.
        $html = (new LinkedImages)->apply($html);

        // Same again: the empty description that belongs beside `role="presentation"` is one
        // no schema can write, because a blank attribute value is dropped on the way out.
        $html = (new DecorativeImages)->apply($html);

        // Same reasoning: a width somebody dragged a column to is a width that belongs on
        // the page, and the attribute it is stored in means nothing to a browser.
        $html = (new TableColumnWidths)->apply($html);

        // Before the sanitiser rather than after it: what a highlighter produces is markup,
        // and markup this package generates goes through the same door as everything else.
        $html = $this->codeHighlighter?->apply($html) ?? $html;

        // Last, and on the finished markup: a plugin adding a node of its own needs the
        // same attribute-into-structure step the four passes above are, and giving it a
        // half-built document to work on would make the order of this method its problem.
        // Still before the sanitiser, so nothing here reaches a page that a document could
        // not have carried there itself.
        foreach ($this->getPlugins() as $plugin) {
            if ($plugin instanceof TransformsRenderedHtml) {
                $html = $plugin->transformRenderedHtml($html);
            }
        }

        return $html;
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
     *
     * Two further repairs, both of them the serialiser's rather than the document's: the
     * separator it puts between any two children, and the escaping it does to text that is
     * on its way out of HTML rather than into it. See `joinAdjacentText()` and the return
     * below.
     */
    public function toText(): string
    {
        // The same reasoning as `toUnsafeHtml()`: the serialiser reads the document before
        // it checks that there is one.
        if (blank($this->content)) {
            return '';
        }

        return $this->remember('text', function (): string {
            $editor = $this->getEditor();

            // In the order Filament's own `toText()` uses them, with the mention passes
            // added: resolving first, so that the text is written from what the providers
            // say now rather than from the copy the document was saved with.
            $this->processMergeTags($editor);
            $this->flattenMergeTagsForText($editor);
            $this->processMentions($editor);
            $this->flattenMentionsForText($editor);
            $this->joinAdjacentText($editor);

            // The serialiser escapes every text node it walks, which is right on the way
            // into markup and wrong in the one method that promises there is none. An index
            // holding `Tom &amp; Jerry` does not answer a search for Tom & Jerry, and a meta
            // description built on it says the entity out loud. Whoever prints this is
            // printing text, and escaping text for a page is the page's job - `{{ }}`
            // already does it.
            return html_entity_decode($editor->getText(), ENT_QUOTES, 'UTF-8');
        });
    }

    /**
     * The first few lines of the document, for a teaser or a meta description.
     *
     * Built on `toText()`, so a mention reads the way it was typed and a merge tag carries
     * the value it holds now. The length is the length of the text and the ellipsis is
     * added on top, the way `Str::limit()` counts - anyone reading the call will assume
     * that, and a second convention for the same thing is one to look up every time.
     *
     * The cut falls on a word boundary, and nothing is appended where the text already
     * ends in a full stop; `Excerpt` is where both of those are decided and tested.
     */
    public function toExcerpt(?int $characters = null, ?string $end = null): string
    {
        return Excerpt::from(
            $this->toText(),
            $characters ?? (int) config('filament-advanced-rich-editor.excerpt.characters', 160),
            $end ?? (string) config('filament-advanced-rich-editor.excerpt.end', '…'),
        );
    }

    /**
     * Rewrites every mention into the text it reads as.
     *
     * The joining that keeps "Ping @Ada and #Backend" on one line used to live here and
     * now lives in `joinAdjacentText()`, which runs straight after: what a mention needed
     * turned out to be what every sentence in the document needs.
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

            $flattened = [];

            foreach ($node->content as $child) {
                if (($child->type ?? null) !== 'mention') {
                    $flattened[] = $child;

                    continue;
                }

                $resolved = static::mentionAsText($child);

                // A mention with neither a label nor an id names nobody. Dropping it leaves
                // the sentence around it intact, which is the best available answer.
                if ($resolved !== null) {
                    $flattened[] = $resolved;
                }
            }

            $node->content = $flattened;
        });
    }

    /**
     * Joins neighbouring pieces of text into one.
     *
     * The serialiser puts its block separator between ANY two children rather than between
     * blocks, and a sentence carrying a link, a bold word or a mention is three or four
     * text nodes. Without this pass `<p>Hallo <strong>Welt</strong>!</p>` comes back as
     * three lines, two of which are one word - which is the shape a search index and an
     * excerpt both fall over.
     *
     * The marks on the second node are dropped in the joining, and that is what this is
     * for: the document is walked once by `getText()` after this and never rendered again.
     */
    protected function joinAdjacentText(Editor $editor): void
    {
        $editor->descendants(function (object &$node): void {
            if (! isset($node->content) || ! is_array($node->content)) {
                return;
            }

            $joined = [];

            foreach ($node->content as $child) {
                $last = end($joined);

                if ($last && ($last->type ?? null) === 'text' && ($child->type ?? null) === 'text') {
                    $last->text = ($last->text ?? '').($child->text ?? '');

                    continue;
                }

                $joined[] = $child;
            }

            $node->content = $joined;
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

    /**
     * The upstream pass, with one condition added: an attachment that resolves to nothing
     * leaves the stored source alone.
     *
     * Filament assigns unconditionally - `$node->attrs->src = $this->getFileAttachmentUrl(…)`
     * - which is right while a provider is there and destructive when one is not. Without
     * one, every picture that came from an upload loses the `src` it was written with, and
     * the page draws an empty box of exactly the right size, because the measurements do
     * survive. Nothing about it looks like a configuration problem: the document is intact,
     * the file is on the disk, the URL works if you paste it into a browser.
     *
     * The attachment id stays the truth wherever it can be resolved - it has to, because a
     * private disk hands out URLs that expire. This only decides what happens when there is
     * no answer at all, and there "keep what was written" beats "erase it". The stored
     * source may be stale, which is the same risk every picture without an attachment id
     * already carries; losing a good one is not a risk but a certainty.
     */
    protected function processFileAttachments(Editor $editor): void
    {
        $editor->descendants(function (object &$node): void {
            // Every node that can carry an attachment, not only the picture. A video picked
            // out of the library points at its file the same way, and a renderer that walked
            // past it would draw a player whose address is whatever the document happened to
            // be saved with - stale on a private disk, and empty on a fresh upload.
            if (! FileAttachments::carriedBy($node->type ?? null)) {
                return;
            }

            if (blank($node->attrs->id ?? null)) {
                return;
            }

            $url = $this->getFileAttachmentUrl($node->attrs->id);

            if (blank($url)) {
                return;
            }

            $node->attrs->src = $url;
        });
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
