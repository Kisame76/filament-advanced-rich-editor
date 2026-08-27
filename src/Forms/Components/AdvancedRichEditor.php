<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast as BaseRichEditorStateCast;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\SourceCodeAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Callouts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Languages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AlignmentPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AutosavePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CalloutPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CharacterCountPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CharactersPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CodeBlockPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DragHandlePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\EmbedPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\EmojiPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FindReplacePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontFamilyPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontSizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\HelpPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageCaptionPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageDecorativePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageFloatPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageLinkPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LanguagePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LineHeightPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LinkPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ListPropertiesPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\MentionMenuPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\PasteCleanupPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SlashMenuPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SourceCodePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\StylesPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextBackgroundPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextDirectionPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts\RichEditorStateCast;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageFloat;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarListPanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPin;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tiptap\Editor;

class AdvancedRichEditor extends RichEditor
{
    protected string $view = 'filament-advanced-rich-editor::rich-editor';

    protected string|Alignment|Closure|null $toolbarAlignment = null;

    protected bool|Closure|null $isStickyToolbar = null;

    protected string|Closure|null $stickyToolbarOffset = null;

    protected string|int|Closure|null $maxHeight = null;

    /**
     * Whether the field answered the height question itself, however it answered it.
     */
    protected bool $hasMaxHeight = false;

    /**
     * @var array<int, int> | Closure | null
     */
    protected array|Closure|null $headingLevels = null;

    protected bool|Closure|null $hasHeadingParagraph = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $listTypes = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $alignments = null;

    protected bool|Closure|null $hasLineHeight = null;

    /**
     * @var array<int, mixed> | Closure | null
     */
    protected array|Closure|null $lineHeights = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $moreTools = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $toolsMenu = null;

    protected bool|Closure|null $hasEmoji = null;

    protected bool|Closure|null $hasFind = null;

    protected bool|Closure|null $hasAccessibility = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $accessibilityRules = null;

    protected bool|Closure|null $hasAutosave = null;

    protected bool|Closure|null $warnsOnLeave = null;

    protected bool|Closure|null $hasDragHandle = null;

    protected bool|Closure|null $hasDragHandleInsert = null;

    protected bool|Closure|null $hasPasteCleanup = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $pasteKeepStyles = null;

    protected bool|Closure|null $hasTextDirection = null;

    protected bool|Closure|null $hasFontPicker = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $fonts = null;

    protected bool|Closure|null $hasSourceCode = null;

    protected bool|Closure|null $hasHelp = null;

    protected string|Htmlable|Closure|null $helpMore = null;

    protected string|Closure|null $helpMoreLabel = null;

    protected bool|Closure|null $hasCharacterCount = null;

    protected bool|Closure|null $hasCharacterCountWords = null;

    protected int|Closure|null $characterCountLimit = null;

    protected bool|Closure|null $hasTaskList = null;

    protected bool|Closure|null $hasCallouts = null;

    protected bool|Closure|null $hasLanguages = null;

    /**
     * @var array<mixed>|Closure|null
     */
    protected array|Closure|null $languageOptions = null;

    protected bool|Closure|null $hasListProperties = null;

    protected bool|Closure|null $hasCharacters = null;

    /**
     * @var array<int, mixed>|Closure|null
     */
    protected array|Closure|null $calloutVariants = null;

    protected bool|Closure|null $isNullWhenEmpty = null;

    /**
     * @var array<string, mixed>|Closure|null
     */
    protected array|Closure|null $styles = null;

    protected bool|Closure|null $hasTextToolbar = null;

    protected bool|Closure|null $hasStylePreview = null;

    /**
     * @var array<int, mixed>|Closure|null
     */
    protected array|Closure|null $textToolbarButtons = null;

    protected bool|Closure|null $hasFontSize = null;

    protected bool|Closure|null $hasTextColor = null;

    protected bool|Closure|null $hasTextBackground = null;

    protected bool|Closure|null $hasCustomColors = null;

    protected bool|Closure|null $hasFullscreen = null;

    protected bool|Closure|null $hasLinkAttributes = null;

    protected bool|Closure|null $hasEmbeds = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $codeBlockLanguages = null;

    protected bool|Closure|null $hasSlashMenu = null;

    /**
     * @var array<string, array<int, string>> | Closure | null
     */
    protected array|Closure|null $slashGroups = null;

    protected string|Closure|null $slashChar = null;

    protected bool|Closure|null $hasMentionMenu = null;

    protected bool|Closure|null $hasImageToolbar = null;

    protected bool|Closure|null $hasImageFloat = null;

    protected bool|Closure|null $hasImageDimensions = null;

    protected bool|Closure|null $hasImageDecorative = null;

    protected bool|Closure|null $hasImageLink = null;

    protected string|Closure|false|null $imageLoading = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $backgroundColors = null;

    /**
     * @var array<string, int|Closure|null>
     */
    protected array $fontSizeOptions = [];

    protected bool|Closure|null $hasMediaLibrary = null;

    protected ?Closure $mediaLibraryQuery = null;

    protected string|Closure|null $mediaLibraryDirectory = null;

    protected int|Closure|null $mediaLibraryPageSize = null;

    protected string|Closure|null $mediaLibraryThumbnail = null;

    protected bool|Closure|null $mediaLibraryListView = null;

    protected string|Closure|null $mediaLibraryScope = null;

    protected mixed $mediaLibraryUploadsTo = null;

    protected ?SpatieMediaLibraryPlugin $spatieMediaLibraryPlugin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tools([
            RichEditorTool::make('image')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image'))
                ->icon(Icons::get('image'))
                ->action('attachFiles', arguments: '{ id: $getEditor().getAttributes(\'image\')?.id, src: $getEditor().getAttributes(\'image\')?.src, alt: $getEditor().getAttributes(\'image\')?.alt }')
                ->activeKey('image'),

            // Filament's alignment labels read "Align start" and so on, which is noise in a
            // dropdown where every option is an alignment. The logical names are kept as
            // the tool names, so right-to-left content still behaves correctly; only the
            // wording is ours, and it is translatable like everything else.
            ...array_map(
                static fn (string $alignment): RichEditorTool => RichEditorTool::make("align{$alignment}")
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.align.'.strtolower($alignment)))
                    ->jsHandler("\$getEditor()?.chain().focus().setTextAlign('".strtolower($alignment)."').run()")
                    ->activeJsExpression("\$getEditor()?.isActive({ textAlign: '".strtolower($alignment)."' })")
                    ->icon('fi-o-align-'.strtolower($alignment))
                    ->iconAlias('forms:components.rich-editor.toolbar.align-'.strtolower($alignment)),
                ['Start', 'Center', 'End', 'Justify'],
            ),

            // Both act on the image the caret has selected, which is the only state the
            // floating toolbar they live in is shown in. Active styling is off because
            // there is nothing to be active about - they do something and are done.
            // Filament draws the blockquote as a speech bubble, which reads as a comment
            // rather than a quotation - and collides with the alt text panel's icon.
            RichEditorTool::make('blockquote')
                ->label(__('filament-forms::components.rich_editor.tools.blockquote'))
                ->jsHandler('$getEditor()?.chain().focus().toggleBlockquote().run()')
                ->icon(Icons::get('blockquote')),

            // Filament's link tool hands the dialog the URL and whether the target is
            // `_blank`, which is everything its own dialog asks for. Reopening a link that
            // carries more than that would show those fields empty and write the emptiness
            // back on save, so the tool passes the whole set. `url` is passed alongside
            // `href` so that a field with `->linkAttributes(false)`, which falls back to
            // Filament's dialog, still fills in.
            RichEditorTool::make('link')
                ->label(__('filament-forms::components.rich_editor.tools.link'))
                ->action(arguments: '{ url: $getEditor().getAttributes(\'link\')?.href, href: $getEditor().getAttributes(\'link\')?.href, shouldOpenInNewTab: $getEditor().getAttributes(\'link\')?.target === \'_blank\', target: $getEditor().getAttributes(\'link\')?.target, rel: $getEditor().getAttributes(\'link\')?.rel, hreflang: $getEditor().getAttributes(\'link\')?.hreflang, referrerpolicy: $getEditor().getAttributes(\'link\')?.referrerpolicy, id: $getEditor().getAttributes(\'link\')?.id }')
                ->toggle()
                // Filament's own drawing and alias, because nothing about the button
                // changed - only what the dialog behind it asks for.
                ->icon(Heroicon::Link)
                ->iconAlias('forms:components.rich-editor.toolbar.link'),

            // Where a picture sits. Pressing the placement it already has takes it off, so
            // three buttons cover four states - the idiom the callouts already use.
            //
            // The active state is spelled out rather than left to Filament, which asks
            // `editor.isActive(<the tool's name>)` and therefore only ever recognises a
            // node or a mark. A placement is neither: it is a global attribute on the
            // image node. So the expression reads the node under the selection, the same
            // way the command that writes it does - `getAttributes()` answers for the
            // selection, and the selection is a plain caret again the moment anything
            // focuses away from the picture.
            ...array_map(
                static fn (string $placement): RichEditorTool => RichEditorTool::make('imageFloat'.ucfirst($placement))
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_float_'.$placement))
                    ->jsHandler('$getEditor()?.commands.setImageFloat('.Js::from($placement)->toHtml().')')
                    ->activeJsExpression(
                        'editorUpdatedAt && $getEditor()?.state?.doc?.nodeAt($getEditor()?.state?.selection?.from)?.attrs?.float === '
                        .Js::from($placement)->toHtml(),
                    )
                    ->icon(Icons::get('image_float_'.$placement)),
                ['left', 'center', 'right'],
            ),

            // Active state spelled out for the same reason the placements need it:
            // Filament asks `editor.isActive(<tool name>)`, which only ever recognises a
            // node or a mark, and this is a global attribute on the image node.
            RichEditorTool::make('imageDecorative')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_decorative'))
                ->jsHandler('$getEditor()?.commands.toggleImageDecorative()')
                ->activeJsExpression(
                    'editorUpdatedAt && $getEditor()?.state?.doc?.nodeAt($getEditor()?.state?.selection?.from)?.attrs?.decorative === true',
                )
                ->icon(Icons::get('image_decorative')),

            RichEditorTool::make('imageRotateLeft')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_rotate_left'))
                ->jsHandler('$getEditor()?.commands.rotateImage(-90)')
                ->activeStyling(false)
                ->icon(Icons::get('image_rotate_left')),

            RichEditorTool::make('imageRotateRight')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_rotate_right'))
                ->jsHandler('$getEditor()?.commands.rotateImage(90)')
                ->activeStyling(false)
                ->icon(Icons::get('image_rotate_right')),

            RichEditorTool::make('imageDownload')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_download'))
                ->jsHandler(<<<'JS'
                    (() => {
                        const source = $getEditor()?.getAttributes('image')?.src

                        if (! source) {
                            return
                        }

                        const link = document.createElement('a')
                        link.href = source
                        // `download` is honoured for same-origin files; a remote image
                        // opens in a new tab instead, which is the browser's call.
                        link.download = (source.split('/').pop() ?? 'image').split('?')[0]
                        link.target = '_blank'
                        link.rel = 'noopener'

                        document.body.appendChild(link)
                        link.click()
                        link.remove()
                    })()
                    JS)
                ->activeStyling(false)
                ->icon(Icons::get('image_download')),

            RichEditorTool::make('imageDelete')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_delete'))
                ->jsHandler('$getEditor()?.chain().focus().deleteSelection().run()')
                ->activeStyling(false)
                ->extraAttributes(['class' => 'fi-arte-image-delete'])
                ->icon(Icons::get('image_delete')),

            RichEditorTool::make('paragraph')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.paragraph'))
                ->jsHandler('$getEditor()?.chain().focus().setParagraph().run()')
                ->icon('fi-o-paragraph')
                ->iconAlias('forms:components.rich-editor.toolbar.paragraph'),

            // Filament labels `h1` "Title", which reads wrong in a dropdown listing
            // every level next to each other. Re-registering the six heading tools
            // under their own names replaces the parent's copies (`getTools()` keys
            // tools by name, and tools registered later win) and leaves everything
            // else about them untouched.
            ...array_map(
                static fn (int $level): RichEditorTool => RichEditorTool::make("h{$level}")
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.heading_level', ['level' => $level]))
                    ->jsHandler("\$getEditor()?.chain().focus().toggleHeading({ level: {$level} }).run()")
                    ->activeKey('heading')
                    ->activeOptions(['level' => $level])
                    ->icon("fi-o-h{$level}")
                    ->iconAlias("forms:components.rich-editor.toolbar.h{$level}"),
                range(1, 6),
            ),
        ]);

        // Registered as a closure instead of an instance: `hasTaskList()` may
        // be configured with a closure that is only resolvable once the field
        // is bound to its schema, which is long after `setUp()` has run.
        // `getPlugins()` evaluates the closure on every call, so the plugin
        // simply disappears when the task list is turned off.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasTaskList()
                ? [TaskListPlugin::make()]
                : [],
        );

        // Carries its variants rather than reading the config itself: the tools it
        // registers are one per kind, and which kinds there are is a per-field answer.
        // Nothing is registered for a field with none, so the node is not declared either
        // and the editor's JSON stays free of callouts.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasCallouts()
                && ($variants = $component->getCalloutVariants()) !== []
                    ? [CalloutPlugin::make()->variants($variants)]
                    : [],
        );

        // Carries its languages for the reason the callout plugin carries its kinds: the
        // tools it registers are one per language, and which languages there are is a
        // per-field answer.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasLanguages()
                && ($languages = $component->getLanguageOptions()) !== []
                    ? [LanguagePlugin::make()->languages($languages)]
                    : [],
        );

        // No tools of its own: what it registers is the schema both halves need for a list
        // to keep its marker and its numbering. The controls live in the bubble that
        // appears while the caret is in a list.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasListProperties()
                ? [ListPropertiesPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasCharacters()
                ? [CharactersPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasFontSize()
                ? [FontSizePlugin::make()]
                : [],
        );

        // Only useful where images can be dragged at all, and it is the same condition
        // the floating toolbar below is built from.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasResizableImages()
                ? [ImageResizePlugin::make()]
                : [],
        );

        // Registered on its own switch rather than on the resizing one: a field may let a
        // picture be floated without letting it be dragged to a new size.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasImageFloat()
                ? [ImageFloatPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasImageDecorative()
                ? [ImageDecorativePlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasImageLink()
                ? [ImageLinkPlugin::make()]
                : [],
        );

        // The text colour needs no plugin - Filament ships that mark on both sides - but
        // the background one is this package's own.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasTextBackground()
                ? [TextBackgroundPlugin::make()]
                : [],
        );

        // Both of these carry a toolbar tool, so switching one off is also what takes its
        // entry out of the dropdown it sits in: an unregistered name is dropped there.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasEmoji()
                ? [EmojiPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasTextDirection()
                ? [TextDirectionPlugin::make()]
                : [],
        );

        // Off means the extension is not loaded at all, which is what takes the keyboard
        // shortcut away with the button: a bar reachable by `Ctrl+F` on a field that hides
        // the tool is a feature, and one on a field that switched searching off is a bug.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasFind()
                ? [FindReplacePlugin::make()]
                : [],
        );

        // Off means the tool is gone and the extension is not loaded. Nothing is stored
        // either way: a check marks no document, and a picture that was given alt text is an
        // ordinary picture by the time it is saved.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasAccessibility()
                ? [AccessibilityPlugin::make()]
                : [],
        );

        // A draft never reaches the application, so switching it off takes the drafts of
        // the next session away and leaves every record exactly as it is.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasAutosave()
                ? [AutosavePlugin::make()]
                : [],
        );

        // Nothing is stored either way, so a field that switches the grip off keeps every
        // document that was ever rearranged with it.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasDragHandle()
                ? [DragHandlePlugin::make()]
                : [],
        );

        // Off means the extension is not loaded, and a paste then arrives the way Filament
        // takes it: this cleans the markup on its way in and stores nothing of its own, so
        // switching it off changes the next paste and no document already written.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasPasteCleanup()
                ? [PasteCleanupPlugin::make()]
                : [],
        );

        // Resolved on every call, because the list may be a closure and two fields on one
        // page may offer different styles. Nothing is registered where a project named
        // none, which is the shipped state.
        $this->plugins(
            static function (AdvancedRichEditor $component): array {
                $styles = Styles::for($component);

                return $styles === [] ? [] : [StylesPlugin::make($styles)];
            },
        );

        // Always: a caption is worth having where nothing may be dragged, and it lives in
        // the schema - a field that stopped declaring it would drop every caption already
        // written on the next save.
        $this->plugins([ImageCaptionPlugin::make()]);

        // Always, for a different reason: it repairs two keyboard shortcuts Filament's own
        // build binds and then declines to answer, on every field, whether or not this one
        // shows the alignment dropdown.
        $this->plugins([AlignmentPlugin::make()]);

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasEmbeds()
                ? [EmbedPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->getCodeBlockLanguages() === []
                ? []
                : [CodeBlockPlugin::make()],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasSlashMenu()
                ? [SlashMenuPlugin::make()]
                : [],
        );

        // Only where the field mentions something. The extension carries Filament's own
        // name and therefore takes its place, and taking the place of a node on a field
        // that was never given a provider would be a swap nobody asked for.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->getMentionMenuForJs() === null
                ? []
                : [MentionMenuPlugin::make()],
        );

        // The editor half of the widened link and of the heading anchor. Both are
        // attributes on nodes Filament owns, and both are dropped by the parser unless
        // something declares them.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasLinkAttributes()
                ? [LinkPlugin::make()]
                : [],
        );

        // Built with the field's own list, because the tools are one per spacing: two fields
        // on one page may offer different numbers, and a spacing without a tool is dropped
        // from the dropdown rather than breaking it.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasLineHeight()
                ? [LineHeightPlugin::make($component->getLineHeights())]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasSourceCode()
                ? [SourceCodePlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasHelp()
                ? [HelpPlugin::make()]
                : [],
        );

        // Registered whenever the picker is, and also wherever a document might already
        // carry a typeface: the mark is what keeps it through the next save.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasFontPicker()
                ? [FontFamilyPlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasCharacterCount()
                ? [CharacterCountPlugin::make()]
                : [],
        );

        // Filament's own slot under the field, so the counter needs nothing from inside the
        // editor's view - and a closure, so the first number is measured against the state
        // this render actually has.
        $this->belowContent(static function (AdvancedRichEditor $component): ?CharacterCount {
            if (! $component->hasCharacterCount()) {
                return null;
            }

            $counted = $component->measureCharacterCount($component->getState());

            return CharacterCount::make()
                ->characters($counted['characters'])
                ->words($component->hasCharacterCountWords() ? $counted['words'] : null)
                ->limit($component->getCharacterCountLimit());
        });

        // The disk half of the browser's one rule: what the grid lists is what a stored id may
        // point at. On the media library side the provider enforces it, because every lookup
        // goes through the provider. A plain disk field has no provider to enforce anything,
        // so Filament's own tamper guard is switched on and answered from the same pool.
        //
        // Two things stay valid regardless, and both have to: a path that is already in the
        // saved content - Filament checks that itself, so nothing anyone has published breaks -
        // and a file uploaded a moment ago, which is a pending attachment rather than a path
        // and would otherwise blank out while it was still being written.
        //
        // Set in `setUp()`, so a field that calls `preventFileAttachmentPathTampering()` itself
        // overwrites this rather than fighting it.
        $this->preventFileAttachmentPathTampering(
            // Only where a disk pool is what answers. A media collection is authorised by the
            // provider instead, and a field with no pool at all has nothing to answer with -
            // switching the guard on there would refuse ids this package never issued.
            condition: static fn (AdvancedRichEditor $component): bool => $component->getMediaSource() instanceof DiskMediaSource,
            allowFilePathUsing: static fn (AdvancedRichEditor $component, string $file): bool => $component->getUploadedFileAttachment($file) !== null
                || ($component->getMediaSource()?->has($file) ?? false),
        );
    }

    /**
     * @return array<int, string | array<int, string>>
     */
    public function getDefaultToolbarButtons(): array
    {
        // Falling back to the shipped default keeps the field usable when the
        // package's config has not been published or merged yet.
        return config('filament-advanced-rich-editor.toolbar') ?? [
            ['undo', 'redo'],
            'divider',
            ['headings', 'styles', 'fontSize'],
            'divider',
            ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
            'divider',
            ['alignment', 'lineHeight'],
            'divider',
            ['lists', 'image', 'embed', 'table', 'callouts'],
            'divider',
            ['more'],
            'pin',
            ['tools', 'fullscreen'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getToolbarButtons(): array
    {
        // The whole bar, pinned half included: this is what Filament asks for when it
        // looks a button up, and a button that is pinned is still on the toolbar.
        $split = $this->getSplitToolbarButtons();

        return [...$split['flow'], ...$split['pinned']];
    }

    /**
     * The toolbar in the two halves the view renders: the groups that are aligned on the
     * bar, and the ones pinned to an edge behind the `'pin'` marker.
     *
     * Splitting before the dividers are collapsed is deliberate - each half then gets the
     * same treatment the whole bar used to get, so a divider left leading or trailing by
     * the split goes the way every other redundant divider goes.
     *
     * @return array{flow: array<int, array<int, mixed>>, pinned: array<int, array<int, mixed>>}
     */
    public function getSplitToolbarButtons(): array
    {
        // The parent implementation type-hints every toolbar item as
        // `string | ToolbarButtonGroup`, which would fail on dividers and any
        // other custom object. It is therefore reimplemented on top of the very
        // same trait method the parent aliases, with a widened item handling.
        $groups = ToolbarLayout::resolve($this->getBaseToolbarButtons(), $this);

        $tools = $this->getTools();

        $groups = array_map(
            fn (mixed $group): array => array_map(
                fn (mixed $item): mixed => ($item instanceof ToolbarButtonGroup)
                    ? $item->resolve($tools)
                    : $item,
                // A token that expanded into a bare item is wrapped, because
                // the view expects every top level entry to be a group.
                is_array($group) ? $group : [$group],
            ),
            $groups,
        );

        [$flow, $pinned] = $this->splitToolbarAtPin($groups);

        return [
            'flow' => $this->collapseToolbarDividers($flow),
            'pinned' => $this->collapseToolbarDividers($pinned),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getFlowToolbarButtons(): array
    {
        return $this->getSplitToolbarButtons()['flow'];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getPinnedToolbarButtons(): array
    {
        return $this->getSplitToolbarButtons()['pinned'];
    }

    /**
     * Cuts the resolved toolbar at the first `'pin'` marker. The marker may sit anywhere,
     * a group of its own or in the middle of one, and it is dropped either way - it is a
     * place in the bar, not a thing on it. Any further marker has nothing left to split
     * and goes the same way.
     *
     * @param  array<int, array<int, mixed>>  $groups
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, array<int, mixed>>}
     */
    protected function splitToolbarAtPin(array $groups): array
    {
        $flow = [];
        $pinned = [];

        $isPinned = false;

        foreach ($groups as $group) {
            $before = [];
            $after = [];

            foreach ($group as $item) {
                if ($item instanceof ToolbarPin) {
                    $isPinned = true;

                    continue;
                }

                if ($isPinned) {
                    $after[] = $item;

                    continue;
                }

                $before[] = $item;
            }

            // A group that the marker cut in half stays two groups: what was on either
            // side of it was never in the same cluster to begin with.
            if (filled($before)) {
                $flow[] = $before;
            }

            if (filled($after)) {
                $pinned[] = $after;
            }
        }

        return [$flow, $pinned];
    }

    /**
     * Drops every divider that has nothing left to separate. Emptying a group with
     * `disableToolbarButtons()` leaves the dividers that used to flank it sitting
     * next to each other, and a divider at either end of the toolbar borders on
     * nothing at all - both are artefacts of the layout, never something a caller
     * asked for.
     *
     * @param  array<int, array<int, mixed>>  $groups
     * @return array<int, array<int, mixed>>
     */
    protected function collapseToolbarDividers(array $groups): array
    {
        $collapsed = [];

        // Nothing has been rendered yet, so a divider seen now would be a leading one.
        $isDividerRedundant = true;

        foreach ($groups as $group) {
            $items = [];

            foreach ($group as $item) {
                if ($item instanceof ToolbarDivider) {
                    if ($isDividerRedundant) {
                        continue;
                    }

                    $isDividerRedundant = true;
                    $items[] = $item;

                    continue;
                }

                $isDividerRedundant = false;
                $items[] = $item;
            }

            if (filled($items)) {
                $collapsed[] = $items;
            }
        }

        // A trailing divider can only be recognised once the last item is known, so
        // it is peeled off here rather than inside the loop above.
        while (filled($collapsed)) {
            $lastGroupKey = array_key_last($collapsed);
            $lastItemKey = array_key_last($collapsed[$lastGroupKey]);

            if (! ($collapsed[$lastGroupKey][$lastItemKey] instanceof ToolbarDivider)) {
                break;
            }

            unset($collapsed[$lastGroupKey][$lastItemKey]);

            if (blank($collapsed[$lastGroupKey])) {
                unset($collapsed[$lastGroupKey]);
            }
        }

        return array_values(array_map(array_values(...), $collapsed));
    }

    /**
     * Dividers and the pin marker carry no button names at all, so they must never
     * answer a `hasToolbarButton()` lookup - otherwise a decorative item could satisfy
     * checks such as `hasFileAttachmentsByDefault()`.
     */
    protected function hasToolbarButtonInItem(object $item, string $button): bool
    {
        if (($item instanceof ToolbarDivider) || ($item instanceof ToolbarPin)) {
            return false;
        }

        return parent::hasToolbarButtonInItem($item, $button);
    }

    /**
     * @param  array<string>  $buttonsToDisable
     */
    protected function filterDisabledToolbarButtonsFromItem(object $item, array $buttonsToDisable): ?object
    {
        // Dividers and the pin marker survive `disableToolbarButtons()` untouched: they
        // are layout, not a button, and dropping them would leave the remaining groups
        // visually glued together - or move the pinned half back onto the bar.
        // `collapseToolbarDividers()` afterwards removes only the dividers the disabling
        // left with nothing to separate.
        if (($item instanceof ToolbarDivider) || ($item instanceof ToolbarPin)) {
            return $item;
        }

        return parent::filterDisabledToolbarButtonsFromItem($item, $buttonsToDisable);
    }

    /**
     * The default toolbar exposes attachments through the `image` tool, which
     * mounts the same `attachFiles` action the parent looks for. Without this,
     * uploads would be rejected for every editor using the package default.
     */
    public function hasFileAttachmentsByDefault(): bool
    {
        return $this->hasToolbarButton(['attachFiles', 'image']);
    }

    /**
     * Where the toolbar's button groups sit on the bar.
     *
     * Accepts Filament's `Alignment` enum or its string value; only the horizontal cases
     * mean anything here. The default lives in the config file so a project can set it
     * once, and a field always wins.
     */
    public function toolbarAlignment(string|Alignment|Closure|null $alignment): static
    {
        $this->toolbarAlignment = $alignment;

        return $this;
    }

    public function getToolbarAlignment(): string
    {
        $alignment = $this->evaluate($this->toolbarAlignment)
            ?? config('filament-advanced-rich-editor.toolbar_alignment', 'center');

        if ($alignment instanceof Alignment) {
            $alignment = $alignment->value;
        }

        // `left`/`right` are the physical aliases of the logical cases, and `justify`
        // means "spread out" for a row of buttons.
        return match ($alignment) {
            'left' => 'start',
            'right' => 'end',
            'justify' => 'between',
            'start', 'center', 'end', 'between' => $alignment,
            default => throw new LogicException("The rich editor toolbar cannot be aligned to [{$alignment}]. Use start, center, end or between."),
        };
    }

    /**
     * Which edge the buttons behind the `'pin'` marker sit on.
     *
     * Not a setting: they take the edge the aligned groups are not pushed against. A bar
     * aligned to the end leaves the start free and the pinned buttons take it; any other
     * bar leaves the end free, which is the corner a row of editor controls is looked for
     * in anyway.
     */
    public function getToolbarPinSide(): string
    {
        return $this->getToolbarAlignment() === 'end' ? 'start' : 'end';
    }

    public function stickyToolbar(bool|Closure $condition = true): static
    {
        $this->isStickyToolbar = $condition;

        return $this;
    }

    public function isStickyToolbar(): bool
    {
        // A capped field scrolls inside itself, and the toolbar is not in the box that
        // moves - it stays above the text without being pinned to anything. Pinning it to
        // the viewport as well would peel it off the field as the page scrolls past.
        if (filled($this->getMaxHeight())) {
            return false;
        }

        return (bool) ($this->evaluate($this->isStickyToolbar) ?? config('filament-advanced-rich-editor.sticky.enabled') ?? true);
    }

    public function stickyToolbarOffset(string|Closure|null $offset): static
    {
        $this->stickyToolbarOffset = $offset;

        return $this;
    }

    public function getStickyToolbarOffset(): string
    {
        return (string) ($this->evaluate($this->stickyToolbarOffset) ?? config('filament-advanced-rich-editor.sticky.offset') ?? '4rem');
    }

    /**
     * The languages the code block's picker offers, as `value => label`.
     *
     * An empty list takes the picker away: a project that curated the languages down to
     * nothing has said it does not want one. A language a block already carries is offered
     * even when it is not listed - it is still what the block is written in.
     *
     * @param  array<string, string> | Closure  $languages
     */
    public function codeBlockLanguages(array|Closure $languages): static
    {
        $this->codeBlockLanguages = $languages;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getCodeBlockLanguages(): array
    {
        $languages = $this->evaluate($this->codeBlockLanguages)
            ?? config('filament-advanced-rich-editor.code_block.languages')
            ?? [];

        return is_array($languages) ? $languages : [];
    }

    /**
     * What the code block script needs from PHP: the languages and the wording for a block
     * that declares none.
     *
     * @return array<string, mixed>|null
     */
    public function getCodeBlockSettingsForJs(): ?array
    {
        $languages = $this->getCodeBlockLanguages();

        if ($languages === []) {
            return null;
        }

        return [
            'languages' => $languages,
            'plain' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.code_block.plain'),
        ];
    }

    /**
     * Whether the field can embed a video.
     *
     * Off means the button and the script are gone; stored embeds are still rendered,
     * because a field that stops offering something has no business deleting what was
     * written before it stopped.
     */
    public function embeds(bool|Closure $condition = true): static
    {
        $this->hasEmbeds = $condition;

        return $this;
    }

    public function hasEmbeds(): bool
    {
        return (bool) ($this->evaluate($this->hasEmbeds) ?? config('filament-advanced-rich-editor.embed.enabled') ?? true);
    }

    /**
     * What the embed script needs from PHP: the provider names, in the panel's language,
     * and whether YouTube is embedded through its cookie-free host.
     *
     * @return array<string, mixed>|null
     */
    public function getEmbedSettingsForJs(): ?array
    {
        if (! $this->hasEmbeds()) {
            return null;
        }

        return [
            'nocookie' => (bool) config('filament-advanced-rich-editor.embed.youtube_nocookie', true),
            'labels' => [
                'youtube' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.youtube'),
                'vimeo' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.vimeo'),
            ],
        ];
    }

    /**
     * Whether typing the slash character opens a menu of the commands this field offers.
     */
    public function slashMenu(bool|Closure $condition = true): static
    {
        $this->hasSlashMenu = $condition;

        return $this;
    }

    public function hasSlashMenu(): bool
    {
        return (bool) ($this->evaluate($this->hasSlashMenu) ?? config('filament-advanced-rich-editor.slash.enabled') ?? true);
    }

    /**
     * Whether the mention menu is this package's own.
     *
     * Filament draws a mention as a label and nothing else. This one has room for a picture
     * and a line of context beneath the name, which is what tells two people with the same
     * name apart. Switched off, the field falls back to Filament's menu - the node, and
     * everything stored, is the same either way.
     */
    public function mentionMenu(bool|Closure $condition = true): static
    {
        $this->hasMentionMenu = $condition;

        return $this;
    }

    public function hasMentionMenu(): bool
    {
        return (bool) ($this->evaluate($this->hasMentionMenu) ?? config('filament-advanced-rich-editor.mentions.menu') ?? true);
    }

    /**
     * What the mention menu offers, for the view to hand to the script.
     *
     * Null where the menu is switched off and where the field mentions nothing: an
     * extension that replaces Filament's own has no business loading for a field that never
     * asked for mentions.
     *
     * The triggers are Filament's own description of them - the same array its extension is
     * configured with - so a provider written against Filament works here untouched. The key
     * is what lets the script call back for a search, the same way the media browser does.
     *
     * @return array<string, mixed>|null
     */
    public function getMentionMenuForJs(): ?array
    {
        if (! $this->hasMentionMenu()) {
            return null;
        }

        $triggers = $this->getMentionsForJs();

        if ($triggers === []) {
            return null;
        }

        // The rows go in front of the labels where a provider has them. Both are read from
        // the same list in the same order, which is how a trigger is matched to the provider
        // it was built from - Filament's own description carries no way back to it.
        $providers = array_values($this->getMentionProviders());

        foreach ($triggers as $index => $trigger) {
            $provider = $providers[$index] ?? null;

            if ($provider instanceof MentionProvider && $provider->hasRows()) {
                $triggers[$index]['items'] = $provider->getRows();
            }
        }

        return [
            'key' => $this->getKey(),
            'triggers' => $triggers,
        ];
    }

    /**
     * What the slash menu offers, and in which groups.
     *
     * Keys are group names, which are also the translation keys their headings are read
     * from; values are tool names, in the order they appear. `'headings'` expands to the
     * levels this field offers. A name the field does not have is dropped, exactly as it is
     * inside a toolbar dropdown.
     *
     * @param  array<string, array<int, string>> | Closure  $groups
     */
    public function slashGroups(array|Closure $groups): static
    {
        $this->slashGroups = $groups;

        return $this;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getSlashGroups(): array
    {
        $groups = $this->evaluate($this->slashGroups)
            ?? config('filament-advanced-rich-editor.slash.groups');

        return is_array($groups) && $groups !== [] ? $groups : SlashMenu::GROUPS;
    }

    /**
     * The character that opens the menu.
     */
    public function slashChar(string|Closure $char): static
    {
        $this->slashChar = $char;

        return $this;
    }

    public function getSlashChar(): string
    {
        return (string) ($this->evaluate($this->slashChar)
            ?? config('filament-advanced-rich-editor.slash.char')
            ?? '/');
    }

    /**
     * What the slash menu offers, for the view to hand to the script. Null while the menu
     * is switched off, and while it has nothing to offer - a panel that can only ever say
     * "no matching command" is one that should not open.
     *
     * @return array<string, mixed>|null
     */
    public function getSlashMenuForJs(): ?array
    {
        if (! $this->hasSlashMenu()) {
            return null;
        }

        $menu = SlashMenu::for($this);

        return $menu['groups'] === [] ? null : $menu;
    }

    /**
     * Whether the link tool offers `rel`, `referrerpolicy`, `hreflang` and an anchor, and
     * whether the schema keeps them.
     *
     * Both halves move together on purpose. A dialog that writes an attribute the schema
     * drops is a dialog that lies, and a schema that keeps one nothing can write is dead
     * weight.
     */
    public function linkAttributes(bool|Closure $condition = true): static
    {
        $this->hasLinkAttributes = $condition;

        return $this;
    }

    public function hasLinkAttributes(): bool
    {
        return (bool) ($this->evaluate($this->hasLinkAttributes) ?? config('filament-advanced-rich-editor.link.attributes') ?? true);
    }

    /**
     * @return array<Action>
     */
    public function getDefaultActions(): array
    {
        $replacements = [];

        if ($this->hasLinkAttributes()) {
            $replacements['link'] = LinkAction::make();
        }

        // Asked for the pool rather than for the setting: the browser is switched on by
        // default, but a field may have nothing browsable behind it - a foreign attachment
        // provider, or a disk with no directory to tell this field's pictures apart. An empty
        // grid would be a worse answer than Filament's own dialog.
        if ($this->getMediaSource() !== null) {
            // The media browser IS the image dialog rather than a second one beside it, so it
            // takes over Filament's action instead of adding to it. Everything that opens the
            // dialog - the toolbar button, the slash menu, clicking an image that is already
            // there - keeps working without being told.
            $replacements['attachFiles'] = MediaLibraryAction::make();
        }

        if ($replacements === []) {
            return parent::getDefaultActions();
        }

        // Replaced by name rather than appended: two actions called `link` would leave
        // which dialog opens to the order of the array.
        return [
            ...array_filter(
                parent::getDefaultActions(),
                static fn (Action $action): bool => ! array_key_exists($action->getName(), $replacements),
            ),
            ...array_values($replacements),
        ];
    }

    /**
     * The editor a save is parsed through.
     *
     * Filament builds Filament's renderer here, so without this the widened link mark and
     * the heading anchor would reach a rendered page but not the schema that a save and a
     * hydration go through - and an attribute that survives being shown but not being
     * saved is worse than one that was never offered.
     */
    public function getTipTapEditor(): Editor
    {
        return AdvancedRichContentRenderer::make()
            ->plugins($this->getPlugins())
            ->linkProtocols($this->getLinkProtocols())
            ->linkAttributes($this->hasLinkAttributes())
            ->styles(Styles::for($this))
            ->getEditor();
    }

    /**
     * Caps the editor's height and lets it scroll inside the field.
     *
     * Any CSS length; a bare number is read as pixels, because `max-height: 400` is not
     * one and would leave the field growing with nothing to show for the call.
     *
     * Passing null undoes a height set project-wide, which is why this is remembered
     * separately from the value: null is an answer here, not the absence of one.
     */
    public function maxHeight(string|int|Closure|null $height): static
    {
        $this->maxHeight = $height;
        $this->hasMaxHeight = true;

        return $this;
    }

    public function getMaxHeight(): ?string
    {
        $height = $this->hasMaxHeight
            ? $this->evaluate($this->maxHeight)
            : config('filament-advanced-rich-editor.max_height');

        if (blank($height)) {
            return null;
        }

        return is_numeric($height) ? $height.'px' : (string) $height;
    }

    /**
     * @param  array<int, int> | Closure  $levels
     */
    public function headingLevels(array|Closure $levels): static
    {
        $this->headingLevels = $levels;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getHeadingLevels(): array
    {
        $levels = array_values(array_map(
            intval(...),
            $this->evaluate($this->headingLevels) ?? config('filament-advanced-rich-editor.heading_levels') ?? [1, 2, 3, 4],
        ));

        foreach ($levels as $level) {
            if (($level >= 1) && ($level <= 6)) {
                continue;
            }

            throw new LogicException("The heading level [{$level}] used by the rich editor [{$this->getName()}] does not exist, because HTML only defines the headings [h1] to [h6].");
        }

        return $levels;
    }

    /**
     * @param  array<int, string> | Closure  $types
     */
    /**
     * Whether the headings dropdown also offers the plain paragraph.
     *
     * With it, the dropdown covers every block the caret can be in, so it reads as a
     * choice rather than a set of toggles. Filament's heading tools already turn a heading
     * back into a paragraph when the active level is picked again, so a block never ends
     * up unset either way.
     */
    public function headingParagraph(bool|Closure $condition = true): static
    {
        $this->hasHeadingParagraph = $condition;

        return $this;
    }

    public function hasHeadingParagraph(): bool
    {
        return (bool) ($this->evaluate($this->hasHeadingParagraph)
            ?? config('filament-advanced-rich-editor.heading_paragraph', true));
    }

    /**
     * Which list types the 'lists' dropdown offers, in the listed order.
     *
     * @param  array<int, string> | Closure  $types
     */
    public function listTypes(array|Closure $types): static
    {
        $this->listTypes = $types;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getListTypes(): array
    {
        // `taskList` is left in place even when the task list is disabled:
        // `ToolbarButtonGroup::resolve()` silently drops button names without a
        // matching tool, so the dropdown simply renders one option less.
        return array_values($this->evaluate($this->listTypes) ?? config('filament-advanced-rich-editor.lists') ?? ['bulletList', 'orderedList', 'taskList']);
    }

    /**
     * Which alignments the 'alignment' dropdown offers, in the listed order. The first
     * one doubles as the trigger's resting icon.
     *
     * @param  array<int, string> | Closure  $alignments
     */
    public function alignments(array|Closure $alignments): static
    {
        $this->alignments = $alignments;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAlignments(): array
    {
        return array_values($this->evaluate($this->alignments)
            ?? config('filament-advanced-rich-editor.alignments')
            ?? ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify']);
    }

    /**
     * The line spacing dropdown. Switching it off also drops the extension, so a field that
     * has none stops declaring the attribute - and content that already carries a spacing
     * loses it on the next save, the same way the direction does.
     */
    public function lineHeight(bool|Closure $condition = true): static
    {
        $this->hasLineHeight = $condition;

        return $this;
    }

    public function hasLineHeight(): bool
    {
        return (bool) ($this->evaluate($this->hasLineHeight) ?? config('filament-advanced-rich-editor.line_height.enabled') ?? true);
    }

    /**
     * Which spacings the 'lineHeight' dropdown offers, in the listed order.
     *
     * @param  array<int, mixed> | Closure  $values
     */
    public function lineHeights(array|Closure $values): static
    {
        $this->lineHeights = $values;

        return $this;
    }

    /**
     * Canonicalised and de-duplicated, so `1.50` and `1.5` name one option and not two, and
     * a value that is not a line height at all is dropped rather than rendered.
     *
     * @return array<int, string>
     */
    public function getLineHeights(): array
    {
        return LineHeight::values(array_values($this->evaluate($this->lineHeights)
            ?? config('filament-advanced-rich-editor.line_height.values')
            ?? [1, 1.15, 1.5, 2]));
    }

    /**
     * What the overflow dropdown offers, in the listed order. Any Filament tool name goes;
     * an empty list drops the button, which is how a field says it wants none of this.
     *
     * @param  array<int, string> | Closure  $tools
     */
    public function moreTools(array|Closure $tools): static
    {
        $this->moreTools = $tools;

        return $this;
    }

    /**
     * What the `'tools'` menu offers: the things a field does rather than the things it
     * writes - searching, checking, the source view, the shortcut list.
     *
     * In the shipped toolbar as `['tools', 'fullscreen']`, so the corner never changes
     * shape: switching the check or the source view on puts them in the menu rather than
     * beside it, and the preview, statistics and export tools still to come go the same way.
     * A project that would rather have the buttons names them individually.
     *
     * @param  array<int, string> | Closure  $tools
     */
    public function toolsMenu(array|Closure $tools): static
    {
        $this->toolsMenu = $tools;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getToolsMenu(): array
    {
        return array_values($this->evaluate($this->toolsMenu)
            ?? config('filament-advanced-rich-editor.tools_menu')
            ?? ['find', 'accessibility', 'sourceCode', 'help']);
    }

    /**
     * @return array<int, string>
     */
    public function getMoreTools(): array
    {
        return array_values($this->evaluate($this->moreTools)
            ?? config('filament-advanced-rich-editor.more')
            ?? ['subscript', 'superscript', 'code', 'codeBlock', 'blockquote', 'clearFormatting', 'horizontalRule',
                'details', 'emoji', 'characters']);
    }

    /**
     * The emoji picker. Nothing about it is stored as markup - an emoji is a character -
     * so switching it off later leaves every emoji already written where it is.
     */
    public function emoji(bool|Closure $condition = true): static
    {
        $this->hasEmoji = $condition;

        return $this;
    }

    public function hasEmoji(): bool
    {
        return (bool) ($this->evaluate($this->hasEmoji) ?? config('filament-advanced-rich-editor.emoji') ?? true);
    }

    /**
     * Finding and replacing inside this field. Nothing about it is stored - a search marks
     * no document and a replacement is ordinary text - so switching it off later changes
     * nothing that was ever written with it.
     */
    public function find(bool|Closure $condition = true): static
    {
        $this->hasFind = $condition;

        return $this;
    }

    public function hasFind(): bool
    {
        return (bool) ($this->evaluate($this->hasFind) ?? config('filament-advanced-rich-editor.find') ?? true);
    }

    /**
     * The strings and icons the bar draws, for the view to hand to the script. Null while
     * searching is switched off, which is also when the extension that would read them was
     * never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getFindSettingsForJs(): ?array
    {
        return $this->hasFind() ? FindReplacePlugin::getLabels() : null;
    }

    /**
     * The check that reads the document and says what is wrong with it.
     *
     * Six questions, and they are the six a person writing an article can answer and nobody
     * downstream can: a picture nobody described, a link that says "click here", a heading
     * level jumped over, a table with no header row, a link with nothing in it, and a colour
     * that cannot be read on the page it is going to.
     *
     * Shipped off, and switched on per field or in the config. Two reasons, and the second
     * is the real one: it is a review tool rather than a way of writing, so it belongs on
     * the bar of the fields a project decided it belongs on - and the contrast rule is
     * measured against a page this package has to be told the colour of. Shipped on, every
     * project whose pages are not white would be handed findings that are wrong, which is
     * the surest way to teach somebody to stop reading a panel.
     *
     * Nothing about it is stored - a check marks no document - so switching it on and off
     * changes nothing that was written either way.
     */
    public function accessibility(bool|Closure $condition = true): static
    {
        $this->hasAccessibility = $condition;

        return $this;
    }

    public function hasAccessibility(): bool
    {
        return (bool) ($this->evaluate($this->hasAccessibility) ?? config('filament-advanced-rich-editor.accessibility.enabled') ?? false);
    }

    /**
     * Which of the six are asked. A rule left out is not reported and not counted.
     *
     * @param  array<int, string> | Closure  $rules
     */
    public function accessibilityRules(array|Closure $rules): static
    {
        $this->accessibilityRules = $rules;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAccessibilityRules(): array
    {
        $rules = $this->evaluate($this->accessibilityRules)
            ?? config('filament-advanced-rich-editor.accessibility.rules')
            ?? AccessibilityPlugin::RULES;

        return array_values(array_filter(
            (array) $rules,
            static fn (mixed $rule): bool => is_string($rule) && in_array($rule, AccessibilityPlugin::RULES, strict: true),
        ));
    }

    /**
     * The palette as three channels rather than as names.
     *
     * Filament stores `data-color="ink"` and not the colour, which is the right way round
     * and leaves the browser with a name it cannot turn into a contrast ratio. Only the
     * light half crosses over: a document rendered in both themes is two questions, and
     * answering one of them twice would be a panel listing everything twice.
     *
     * @return array<string, string>
     */
    public function getAccessibilityPalette(): array
    {
        $palette = [];

        foreach ($this->getTextColorsForPicker() as $color) {
            if (filled($color['color'])) {
                $palette[$color['value']] = $color['color'];
            }
        }

        return $palette;
    }

    /**
     * What the extension reads off the editor element. Null while the check is switched off,
     * which is also when the extension that would read it was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getAccessibilitySettingsForJs(): ?array
    {
        if (! $this->hasAccessibility()) {
            return null;
        }

        return AccessibilityPlugin::getSettings([
            'rules' => $this->getAccessibilityRules(),
            'weakPhrases' => AccessibilityPlugin::getWeakPhrases(),
            'threshold' => (float) (config('filament-advanced-rich-editor.accessibility.threshold') ?? 4.5),
            'largeThreshold' => (float) (config('filament-advanced-rich-editor.accessibility.large_threshold') ?? 3.0),
            // What the editor cannot know, because both belong to the front end: the colour
            // a page is, and the colour it writes on it where nobody chose one.
            'background' => (string) (config('filament-advanced-rich-editor.accessibility.background') ?? '#ffffff'),
            'text' => (string) (config('filament-advanced-rich-editor.accessibility.text') ?? '#18181b'),
            'palette' => $this->getAccessibilityPalette(),
        ]);
    }

    /**
     * Keeping a draft of this field in the browser, so a lost reply is not a lost article.
     *
     * Nothing about it reaches the application: the draft lives in the browser's own
     * storage, it is offered back the next time the same field on the same record is
     * opened, and it is dropped as soon as the document on screen says the same thing.
     *
     * It is content in a browser's storage on whatever machine somebody was working on, and
     * it outlives the session that wrote it - `autosave.ttl` is how long, and a field
     * holding something that should not sit there switches this off.
     */
    public function autosave(bool|Closure $condition = true): static
    {
        $this->hasAutosave = $condition;

        return $this;
    }

    public function hasAutosave(): bool
    {
        return (bool) ($this->evaluate($this->hasAutosave) ?? config('filament-advanced-rich-editor.autosave.enabled') ?? true);
    }

    /**
     * Whether closing the tab with unsaved changes asks first.
     *
     * The browser writes the question and always has; what a page decides is only whether
     * it is asked. Asked means asked for a stray space as much as for an afternoon's work,
     * which is why it is a switch of its own.
     */
    public function autosaveWarnOnLeave(bool|Closure $condition = true): static
    {
        $this->warnsOnLeave = $condition;

        return $this;
    }

    public function warnsOnLeave(): bool
    {
        return (bool) ($this->evaluate($this->warnsOnLeave) ?? config('filament-advanced-rich-editor.autosave.warn_on_leave') ?? true);
    }

    /**
     * What tells one field's draft from another's.
     *
     * The record, the model it belongs to, the Livewire component the form is in and the
     * path to this field within it - and none of it in the clear: it is a key in storage
     * that anything on the origin can read, so what it says is that two drafts are different
     * rather than what either of them is about. The browser adds the page it is on, which is
     * the half PHP cannot answer: to Livewire every request looks like the same endpoint.
     */
    public function getAutosaveKey(): string
    {
        $record = $this->getRecord();

        // A schema's record is a model most of the time and an array some of the time, and
        // an array has neither a class nor a key worth telling two of them apart by. It
        // therefore counts as a record that does not exist yet, which is what a form on a
        // page with nothing saved behind it already is.
        $model = is_object($record) ? $record::class : '';
        $key = ($record instanceof Model ? $record->getKey() : null) ?? 'new';

        return substr(hash('sha256', implode('|', [
            $this->getLivewire()::class,
            $model,
            (string) $key,
            $this->getStatePath(),
        ])), 0, 16);
    }

    /**
     * What the extension reads off the editor element. Null while drafts are switched off,
     * which is also when the extension that would read them was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getAutosaveSettingsForJs(): ?array
    {
        if (! $this->hasAutosave()) {
            return null;
        }

        return AutosavePlugin::getSettings([
            'key' => $this->getAutosaveKey(),
            'debounce' => (int) (config('filament-advanced-rich-editor.autosave.debounce') ?? 1500),
            // Seconds in the config file, because that is how a person writes a day;
            // milliseconds by the time the browser compares it to a timestamp.
            'ttl' => (int) (config('filament-advanced-rich-editor.autosave.ttl') ?? 86400) * 1000,
            'warnOnLeave' => $this->warnsOnLeave(),
        ]);
    }

    /**
     * The grip in the margin that a block can be dragged by, and the plus beside it.
     *
     * Only the top level of the document gets one, and the grip on a list therefore takes
     * the list rather than the item under the mouse: a list item is a node that may only
     * live inside a list, so a drag of one is a drag that refuses more often than it works.
     *
     * Nothing about it is stored - rearranging a document changes the order of what is in it
     * and leaves no trace of how - so switching it off later changes nothing that was
     * written with it.
     */
    public function dragHandle(bool|Closure $condition = true): static
    {
        $this->hasDragHandle = $condition;

        return $this;
    }

    public function hasDragHandle(): bool
    {
        return (bool) ($this->evaluate($this->hasDragHandle) ?? config('filament-advanced-rich-editor.drag_handle.enabled') ?? true);
    }

    /**
     * The plus that starts a new block under the one being hovered.
     *
     * What it inserts is not a paragraph: the caret lands in an empty block and the slash
     * menu opens on top of it, so the button offers everything that could go there. Where
     * the slash menu is switched off it makes the empty block and stops, which is the whole
     * of what it can honestly do without a list to offer.
     */
    public function dragHandleInsert(bool|Closure $condition = true): static
    {
        $this->hasDragHandleInsert = $condition;

        return $this;
    }

    public function hasDragHandleInsert(): bool
    {
        return (bool) ($this->evaluate($this->hasDragHandleInsert) ?? config('filament-advanced-rich-editor.drag_handle.insert') ?? true);
    }

    /**
     * The icons and labels the handle draws, for the view to hand to the script. Null while
     * the grip is switched off, which is also when the extension that would read them was
     * never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getDragHandleSettingsForJs(): ?array
    {
        return $this->hasDragHandle() ? DragHandlePlugin::getSettings($this->hasDragHandleInsert()) : null;
    }

    /**
     * Cleaning what arrives from the clipboard.
     *
     * Word puts a stylesheet, a handful of tags no browser has heard of and a list that is
     * not a list onto the clipboard alongside the paragraph; Google Docs puts every run of
     * text in a span carrying eleven declarations, one of which is the only place its bold
     * lives. Both are turned back into a document on the way in - structure kept, typography
     * dropped - and a copy from another editor is left exactly as it is.
     *
     * Nothing about it is stored, so switching it off changes the next paste and no document
     * that was ever written with it.
     */
    public function pasteCleanup(bool|Closure $condition = true): static
    {
        $this->hasPasteCleanup = $condition;

        return $this;
    }

    public function hasPasteCleanup(): bool
    {
        return (bool) ($this->evaluate($this->hasPasteCleanup) ?? config('filament-advanced-rich-editor.paste.cleanup') ?? true);
    }

    /**
     * The style properties a cleaned paste keeps.
     *
     * Shipped as the alignment and nothing else, which is the one thing in Word's `style`
     * whose absence a reader would notice and the one this package has no other way to
     * carry. Everything else there - the font, the size, the colour, the line height - is
     * parsed into a mark of this package's own, so a property left standing is not noise the
     * next save drops: it is Calibri 11pt in black, in the document, for good.
     *
     * A project that wants a paste to arrive wearing its colours names them here. Naming a
     * property also takes it out of the promotion to tags, because `font-weight` kept is a
     * style somebody wants rather than a `<strong>` and a style.
     *
     * @param  array<int, string> | Closure  $properties
     */
    public function pasteKeepStyles(array|Closure $properties): static
    {
        $this->pasteKeepStyles = $properties;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getPasteKeepStyles(): array
    {
        $properties = $this->evaluate($this->pasteKeepStyles)
            ?? config('filament-advanced-rich-editor.paste.keep_styles')
            ?? PasteCleanupPlugin::DEFAULT_KEEP_STYLES;

        $kept = [];

        foreach ((array) $properties as $property) {
            // A published config file is hand-written, and a stray null or number in this
            // list is a typo rather than a property: dropped here, because the alternative
            // is a TypeError out of a form that was only being rendered.
            if (! is_string($property)) {
                continue;
            }

            $property = strtolower(trim($property));

            if ($property !== '') {
                $kept[] = $property;
            }
        }

        return $kept;
    }

    /**
     * What the extension reads off the editor element. Null while the cleaning is switched
     * off, which is also when the extension that would read it was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getPasteSettingsForJs(): ?array
    {
        return $this->hasPasteCleanup() ? PasteCleanupPlugin::getSettings($this->getPasteKeepStyles()) : null;
    }

    /**
     * The two direction buttons. Switching them off keeps the `dir` attribute out of the
     * editor's schema, which means a document that already carries one loses it on the next
     * save - the parser only keeps what something declares.
     */
    public function textDirection(bool|Closure $condition = true): static
    {
        $this->hasTextDirection = $condition;

        return $this;
    }

    public function hasTextDirection(): bool
    {
        return (bool) ($this->evaluate($this->hasTextDirection) ?? config('filament-advanced-rich-editor.text_direction') ?? true);
    }

    /**
     * The typeface dropdown.
     */
    public function fontPicker(bool|Closure $condition = true): static
    {
        $this->hasFontPicker = $condition;

        return $this;
    }

    public function hasFontPicker(): bool
    {
        return (bool) ($this->evaluate($this->hasFontPicker) ?? config('filament-advanced-rich-editor.fonts.enabled') ?? true);
    }

    /**
     * The typefaces this field offers, as label => CSS stack. Setting them here replaces
     * everything the package would otherwise find: no directory is read, no generic stack
     * is added, and no `@font-face` is written - the field is saying it knows better, and
     * that the fonts it names are already loaded.
     *
     * @param  array<string, string> | Closure | null  $fonts
     */
    public function fonts(array|Closure|null $fonts): static
    {
        $this->fonts = $fonts;

        return $this;
    }

    /**
     * @return array<string, string> | null
     */
    public function getFonts(): ?array
    {
        return $this->evaluate($this->fonts);
    }

    /**
     * The button that opens the shortcut list, and whatever else this field has to say.
     */
    public function help(bool|Closure $condition = true): static
    {
        $this->hasHelp = $condition;

        return $this;
    }

    public function hasHelp(): bool
    {
        return (bool) ($this->evaluate($this->hasHelp) ?? config('filament-advanced-rich-editor.help') ?? true);
    }

    /**
     * Something to tell the people writing in this field - a house rule, a reminder, a link
     * to the style guide. It becomes a second tab in the help dialog, and only then: with
     * nothing to say there is nothing to tab between.
     *
     * A plain string is escaped and its line breaks kept. Pass an `Htmlable` to write
     * markup, which is trusted, so build it in code rather than out of anything a user typed.
     */
    public function helpMore(string|Htmlable|Closure|null $content, string|Closure|null $label = null): static
    {
        $this->helpMore = $content;
        $this->helpMoreLabel = $label;

        return $this;
    }

    public function getHelpMore(): ?Htmlable
    {
        $content = $this->evaluate($this->helpMore) ?? config('filament-advanced-rich-editor.help_more');

        if (blank($content)) {
            return null;
        }

        return $content instanceof Htmlable ? $content : new HtmlString(nl2br(e($content)));
    }

    public function getHelpMoreLabel(): string
    {
        return $this->evaluate($this->helpMoreLabel)
            ?? __('filament-advanced-rich-editor::advanced-rich-editor.help.more');
    }

    /**
     * The button that opens the document as HTML.
     */
    public function sourceCode(bool|Closure $condition = true): static
    {
        $this->hasSourceCode = $condition;

        return $this;
    }

    public function hasSourceCode(): bool
    {
        return (bool) ($this->evaluate($this->hasSourceCode) ?? config('filament-advanced-rich-editor.source_code', false));
    }

    /**
     * The markup as this field's own schema writes it, which is the form it is stored in.
     * What the source view opens with, and what it hands back.
     */
    public function normaliseSourceHtml(?string $html): string
    {
        return SourceCodeAction::normalise($this, $html);
    }

    /**
     * The line under the editor that says how long the text is.
     */
    public function characterCount(bool|Closure $condition = true): static
    {
        $this->hasCharacterCount = $condition;

        return $this;
    }

    public function hasCharacterCount(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacterCount) ?? config('filament-advanced-rich-editor.character_count.enabled') ?? true);
    }

    public function characterCountWords(bool|Closure $condition = true): static
    {
        $this->hasCharacterCountWords = $condition;

        return $this;
    }

    public function hasCharacterCountWords(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacterCountWords) ?? config('filament-advanced-rich-editor.character_count.words') ?? false);
    }

    /**
     * A number to count towards without a rule behind it, for the fields that have a target
     * rather than a maximum. Without one the counter shows `maxLength()`, which is the
     * number Filament already validates - two sources for one limit is how they drift.
     */
    public function characterCountLimit(int|Closure|null $limit): static
    {
        $this->characterCountLimit = $limit;

        return $this;
    }

    public function getCharacterCountLimit(): ?int
    {
        return $this->evaluate($this->characterCountLimit) ?? $this->getMaxLength();
    }

    /**
     * How long a piece of content is, measured the way `getLengthValidationRules()` measures
     * it: the text the PHP serialiser produces, escaping and all. The browser mirrors these
     * rules so that the number never changes meaning between the first render and the first
     * keystroke.
     *
     * @return array{characters: int, words: int}
     */
    public function measureCharacterCount(mixed $content): array
    {
        if (blank($content)) {
            return ['characters' => 0, 'words' => 0];
        }

        $text = $this->getTipTapEditor()->setContent($content)->getText();

        return [
            'characters' => Str::length($text),
            // Words are counted on what was written rather than on what was escaped: nobody
            // means `&amp;` when they count words.
            'words' => count(preg_split('/\s+/u', trim(html_entity_decode($text)), flags: PREG_SPLIT_NO_EMPTY) ?: []),
        ];
    }

    /**
     * Filament's own cast, swapped for the one that survives an empty string. Swapped rather
     * than appended, because the two directions apply the casts in opposite orders and a
     * guard that has to run first in both cannot be a second entry in the list.
     *
     * @return array<StateCast>
     */
    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn (StateCast $stateCast): StateCast => ($stateCast instanceof BaseRichEditorStateCast)
                ? app(RichEditorStateCast::class, ['richEditor' => $this])
                : $stateCast,
            parent::getDefaultStateCasts(),
        );
    }

    /**
     * Whether an empty document is stored as nothing rather than as `<p></p>`.
     *
     * Off by default, and that is a decision about somebody else's database rather than a
     * preference: a column that is `NOT NULL` without a default takes Filament's `<p></p>`
     * and refuses a null, so turning this on for everyone would break a save that works
     * today. Turned on, a field that renders nothing on the page is also nothing in the
     * record, which is what `@if($post->content)` and a `whereNull` both expect.
     */
    public function nullWhenEmpty(bool|Closure $condition = true): static
    {
        $this->isNullWhenEmpty = $condition;

        return $this;
    }

    public function shouldBeNullWhenEmpty(): bool
    {
        return (bool) ($this->evaluate($this->isNullWhenEmpty)
            ?? config('filament-advanced-rich-editor.null_when_empty', false));
    }

    public function mutateDehydratedState(mixed $state): mixed
    {
        if ($this->shouldBeNullWhenEmpty() && ! $this->hasContent($state)) {
            return null;
        }

        return parent::mutateDehydratedState($state);
    }

    public function mutatesDehydratedState(): bool
    {
        return parent::mutatesDehydratedState() || $this->shouldBeNullWhenEmpty();
    }

    /**
     * Whether a piece of state holds anything a reader would see.
     *
     * Accepts state in every shape one arrives in - the document Livewire carries, the
     * markup a record was hydrated from, or nothing at all - because this is asked on the
     * way into the validator, which is before the casts have finished agreeing on one.
     */
    public function hasContent(mixed $state): bool
    {
        if ($state instanceof Htmlable) {
            $state = $state->toHtml();
        }

        // Also the guard that keeps an empty string away from the parser: `setContent('')`
        // walks a document body that was never built and dies on the null.
        if (blank($state)) {
            return false;
        }

        if (is_string($state)) {
            $state = $this->getTipTapEditor()->setContent($state)->getDocument();
        }

        if (! is_array($state)) {
            return true;
        }

        return ! DocumentContent::isBlank($state);
    }

    /**
     * Filament rejects a document holding exactly one empty paragraph, which is the shape an
     * untouched editor produces and nothing else. A second empty paragraph, a space, a line
     * break, the same document as markup and a field holding nothing at all all get through
     * a `required()` that was meant to stop them. `hasContent()` answers the question once,
     * for every shape.
     */
    public function getRequiredValidationRule(): string|Closure
    {
        if (! $this->isRequired()) {
            return 'nullable';
        }

        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->hasContent($value)) {
                return;
            }

            $fail('validation.required')->translate();
        };
    }

    /**
     * The named styles this field offers, or null to take the project's.
     *
     * An empty array is a field saying it wants none, which is why null rather than `[]` is
     * what means "not answered" - the same distinction `moreTools([])` draws.
     *
     * @param  array<string, mixed>|Closure|null  $styles
     */
    public function styles(array|Closure|null $styles): static
    {
        $this->styles = $styles;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStyles(): ?array
    {
        return $this->evaluate($this->styles);
    }

    public function taskList(bool|Closure $condition = true): static
    {
        $this->hasTaskList = $condition;

        return $this;
    }

    public function hasTaskList(): bool
    {
        return (bool) ($this->evaluate($this->hasTaskList) ?? config('filament-advanced-rich-editor.task_list') ?? true);
    }

    public function callouts(bool|Closure $condition = true): static
    {
        $this->hasCallouts = $condition;

        return $this;
    }

    public function hasCallouts(): bool
    {
        return (bool) ($this->evaluate($this->hasCallouts) ?? config('filament-advanced-rich-editor.callouts.enabled') ?? true);
    }

    /**
     * Which kinds of callout this field offers, in the order the dropdown and the slash
     * menu read them in.
     *
     * @param  array<int, mixed>|Closure|null  $variants
     */
    public function calloutVariants(array|Closure|null $variants): static
    {
        $this->calloutVariants = $variants;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCalloutVariants(): array
    {
        return Callouts::normalize(
            $this->evaluate($this->calloutVariants)
                ?? config('filament-advanced-rich-editor.callouts.variants')
                ?? Callouts::VARIANTS,
        );
    }

    /**
     * Whether a passage can be marked as being written in another language.
     */
    public function languages(bool|Closure $condition = true): static
    {
        $this->hasLanguages = $condition;

        return $this;
    }

    public function hasLanguages(): bool
    {
        return (bool) ($this->evaluate($this->hasLanguages) ?? config('filament-advanced-rich-editor.languages.enabled') ?? true);
    }

    /**
     * Which languages the dropdown offers, in order.
     *
     * Either `['fr' => 'Français']` or `['fr']`: a code is its own worst label but is still
     * better than nothing, and a project adding one language should not have to look up how
     * that language spells its own name.
     *
     * @param  array<mixed>|Closure|null  $languages
     */
    public function languageOptions(array|Closure|null $languages): static
    {
        $this->languageOptions = $languages;

        return $this;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function getLanguageOptions(): array
    {
        return Languages::normalize(
            $this->evaluate($this->languageOptions)
                ?? config('filament-advanced-rich-editor.languages.values')
                ?? Languages::VALUES,
        );
    }

    /**
     * Whether a list can be told which marker to draw, where to start counting and whether
     * to count backwards.
     *
     * Off means the schema is not declared on either side, so a stored list keeps its
     * markup in the database and loses it on the next save - the same bargain every other
     * switch in here makes.
     */
    public function listProperties(bool|Closure $condition = true): static
    {
        $this->hasListProperties = $condition;

        return $this;
    }

    public function hasListProperties(): bool
    {
        return (bool) ($this->evaluate($this->hasListProperties) ?? config('filament-advanced-rich-editor.list_properties') ?? true);
    }

    /**
     * The special characters picker. Nothing about it is stored as markup - a dash is a
     * character - so switching it off later leaves every one already written where it is.
     */
    public function characters(bool|Closure $condition = true): static
    {
        $this->hasCharacters = $condition;

        return $this;
    }

    public function hasCharacters(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacters) ?? config('filament-advanced-rich-editor.characters') ?? true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getDefaultFloatingToolbars(): array
    {
        $toolbars = parent::getDefaultFloatingToolbars();

        if ($this->hasTextToolbar() && ($text = $this->getTextToolbarButtons()) !== []) {
            $toolbars['paragraph'] = $text;
        }

        // What a list is told about itself, in the one place it means anything.
        //
        // Filament shows a floating toolbar while `editor.isActive(<its key>)`, so a bubble
        // keyed `bulletList` appears when the caret enters one and takes itself away on the
        // way out. That is the whole reason these three controls are affordable at all: a
        // bar already carrying five dropdowns has no room to say something permanently that
        // is true in one paragraph out of twenty.
        //
        // With a selection inside a list Filament shows the paragraph bubble instead of
        // this one - a list item holds a paragraph, and its own rule prefers the text bar
        // when there is text selected. Which is right: somebody who has selected words
        // wants to format them.
        if ($this->hasListProperties()) {
            $toolbars['bulletList'] = [ToolbarListPanel::bullet()];
            $toolbars['orderedList'] = [ToolbarListPanel::ordered()];
        }

        if (! $this->hasImageToolbar()) {
            return $toolbars;
        }

        $buttons = [];

        // The aspect ratio switch and the size panel only mean something where a drag can
        // happen at all - both write the very attributes a drag commits.
        if ($this->hasResizableImages()) {
            $buttons[] = ToolbarImageLock::make();
            $buttons[] = ToolbarImagePanel::size();
            $buttons[] = 'imageRotateLeft';
            $buttons[] = 'imageRotateRight';
            $buttons[] = ToolbarDivider::make();
        }

        // Before the alt text, and beside the rotations: both are about where the picture
        // sits rather than about what it says.
        if ($this->hasImageFloat()) {
            $buttons[] = 'imageFloatLeft';
            $buttons[] = 'imageFloatCenter';
            $buttons[] = 'imageFloatRight';
            $buttons[] = ToolbarDivider::make();
        }

        $buttons[] = ToolbarImagePanel::alt();

        // Beside the alt text, because it is the same question answered the other way
        // round: this one has nothing to say, and saying so deliberately is what keeps it
        // off the accessibility check's list of descriptions somebody forgot.
        if ($this->hasImageDecorative()) {
            $buttons[] = 'imageDecorative';
        }

        // With the description and the mark, because all three are about what the picture
        // means rather than where it sits.
        if ($this->hasImageLink()) {
            $buttons[] = 'imageLink';
        }

        $buttons[] = 'imageDownload';
        $buttons[] = 'imageDelete';

        // Keyed by node name: `editor.isActive('image')` is true for the node selection a
        // click on an image produces, which is what the bubble menu shows itself on.
        return [
            ...$toolbars,
            'image' => $buttons,
        ];
    }

    /**
     * Whether the editor marks the text a style sits on.
     *
     * Off, and that is the same reasoning the empty styles list follows rather than an
     * oversight. The classes belong to the project, so the look does too, and none of them
     * resolve in an admin panel that has never loaded the front end's stylesheet - a package
     * that invented an appearance here would be putting a design on content it knows nothing
     * about, and getting it wrong.
     *
     * Turned on, styled text gets a neutral marking: a rule down the side of a block, a
     * dotted line under a run of text. It says that something is set without claiming to
     * know what it looks like, and a project's own `[data-style]` rules overrule it.
     */
    public function stylePreview(bool|Closure $condition = true): static
    {
        $this->hasStylePreview = $condition;

        return $this;
    }

    public function hasStylePreview(): bool
    {
        return (bool) ($this->evaluate($this->hasStylePreview)
            ?? config('filament-advanced-rich-editor.style_preview', false));
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraInputAttributes(): array
    {
        $attributes = parent::getExtraInputAttributes();

        if (! $this->hasStylePreview()) {
            return $attributes;
        }

        // On the field's own wrapper rather than on the styled node: the marking is a
        // decision one field makes, and the nodes are shared with every other one.
        $attributes['class'] = trim(($attributes['class'] ?? '').' fi-arte-style-preview');

        return $attributes;
    }

    /**
     * The bar that appears over selected text.
     *
     * Keyed `'paragraph'` rather than `'text'`, and that is not a naming choice: Filament's
     * JavaScript treats this one key as a special case and shows its toolbar on a non-empty
     * selection inside a paragraph, where every other key waits for `isActive()` on a node.
     * A key called anything else would be drawn and never shown.
     */
    public function textToolbar(bool|Closure $condition = true): static
    {
        $this->hasTextToolbar = $condition;

        return $this;
    }

    public function hasTextToolbar(): bool
    {
        return (bool) ($this->evaluate($this->hasTextToolbar)
            ?? config('filament-advanced-rich-editor.text_toolbar', true));
    }

    /**
     * What the bar over a selection holds. An empty list takes the bar away, the same way
     * an empty `moreTools()` takes the overflow button away.
     *
     * @param  array<int, mixed>|Closure|null  $buttons
     */
    public function textToolbarButtons(array|Closure|null $buttons): static
    {
        $this->textToolbarButtons = $buttons;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getTextToolbarButtons(): array
    {
        $buttons = $this->evaluate($this->textToolbarButtons)
            ?? config('filament-advanced-rich-editor.text_toolbar_buttons')
            ?? ['styles', 'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'];

        // Resolved through the same tokens the bar itself uses, so `'styles'` and
        // `'textColor'` mean here what they mean up there - and a switched-off feature
        // takes its button out of the bubble too rather than leaving a dead one behind.
        return ToolbarLayout::resolve(array_values($buttons), $this);
    }

    /**
     * The little toolbar that appears over a selected image.
     */
    public function imageToolbar(bool|Closure $condition = true): static
    {
        $this->hasImageToolbar = $condition;

        return $this;
    }

    public function hasImageToolbar(): bool
    {
        return (bool) ($this->evaluate($this->hasImageToolbar)
            ?? config('filament-advanced-rich-editor.images.toolbar', true));
    }

    /**
     * Whether the text may run past a picture instead of starting below it.
     *
     * The oldest thing anybody has ever asked an editor for, and the one piece of laying a
     * picture out that this package did not have: the size, the rotation and the caption
     * were all already there.
     */
    public function imageFloat(bool|Closure $condition = true): static
    {
        $this->hasImageFloat = $condition;

        return $this;
    }

    public function hasImageFloat(): bool
    {
        return (bool) ($this->evaluate($this->hasImageFloat)
            ?? config('filament-advanced-rich-editor.images.float', true));
    }

    /**
     * Whether a picture may be given an address to point at.
     *
     * Rendered as an `<a>` around the picture, and around the picture inside a `<figure>`
     * where there is a caption: a caption is text about the picture rather than part of what
     * is being linked.
     */
    public function imageLink(bool|Closure $condition = true): static
    {
        $this->hasImageLink = $condition;

        return $this;
    }

    public function hasImageLink(): bool
    {
        return (bool) ($this->evaluate($this->hasImageLink)
            ?? config('filament-advanced-rich-editor.images.link', true));
    }

    /**
     * Whether a picture may be marked as carrying nothing worth describing.
     *
     * A divider, a texture, a flourish beside a heading the words already say. Such a
     * picture wants an empty `alt` and `role="presentation"` together, and the pair is what
     * makes it different from a description somebody forgot - which is the thing the
     * accessibility check cannot tell apart on its own.
     *
     * Off, and the reason is that sentence: the whole of what the mark buys is a check that
     * stops reporting a deliberate empty `alt`, and that check ships off too. A field that
     * has not switched the check on gains a button whose meaning is not on its face and
     * whose effect nobody will see - on a bar that already carries thirteen. Switch this on
     * with the check, which is the only place it pays.
     */
    public function imageDecorative(bool|Closure $condition = true): static
    {
        $this->hasImageDecorative = $condition;

        return $this;
    }

    public function hasImageDecorative(): bool
    {
        return (bool) ($this->evaluate($this->hasImageDecorative)
            ?? config('filament-advanced-rich-editor.images.decorative', false));
    }

    /**
     * Whether an inserted picture is given the size it was measured at.
     *
     * On by default, because the point of it is a browser that leaves the right hole for a
     * picture it has not got yet - without which the article below jumps when it arrives.
     *
     * The catch is worth knowing before turning it off is considered: Filament renders
     * `width` as an inline `style` as well as an attribute, and this package's own resizing
     * drags the same pair, so the measured size is also the displayed size. A page with the
     * usual `img { max-width: 100%; height: auto }` handles that and gains the aspect ratio
     * for nothing; a page that caps the width and lets the height stand gets a squashed
     * picture. Turn this off rather than find that out on the front page.
     */
    public function imageDimensions(bool|Closure $condition = true): static
    {
        $this->hasImageDimensions = $condition;

        return $this;
    }

    public function hasImageDimensions(): bool
    {
        return (bool) ($this->evaluate($this->hasImageDimensions)
            ?? config('filament-advanced-rich-editor.images.dimensions', true));
    }

    /**
     * The loading hint written onto an inserted picture: `lazy`, `eager`, or nothing.
     *
     * Nothing by default, and that is the considered answer rather than the timid one. A
     * field does not know where on a page its pictures land, and `lazy` on the one above the
     * fold delays the very thing it is usually reached for - that picture is generally the
     * largest contentful paint, and telling the browser to wait for it makes the number it
     * is measured by worse. A project that knows its layout says so, per field or in the
     * config; the measured size above is what earns the bulk of the same prize anyway.
     *
     * `false` is the way to say none where the project set one, and null is the way back to
     * whatever the project said - the same two answers `->cached(false)` gives the renderer.
     * Without the first there would be no way to keep a teaser field eager on a project that
     * turned lazy loading on everywhere.
     */
    public function imageLoading(string|Closure|false|null $loading): static
    {
        $this->imageLoading = $loading;

        return $this;
    }

    public function getImageLoading(): ?string
    {
        $loading = $this->evaluate($this->imageLoading);

        if ($loading === false) {
            return null;
        }

        $loading ??= config('filament-advanced-rich-editor.images.loading');

        // Whitelisted here rather than left to whoever writes it down. This is public and
        // says it answers with one of the two hints a browser knows; a typo in the config -
        // `lasy`, or `auto`, which is not a value `loading` has - would otherwise be handed
        // out as though it were one, and every caller would need the same list to be safe.
        return ImageAttributes::loadingHint(is_string($loading) ? $loading : null);
    }

    /**
     * The gap written beside a floated picture, as a CSS length, or null where the project
     * would rather draw it itself.
     *
     * Read here as well as in `ImageFloat` so the editor and the page write the same
     * number: this one reaches the editor as a custom property on the field, the other
     * lands in the stored markup.
     */
    public function getImageFloatGap(): ?string
    {
        return ImageFloat::gap();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getFloatingToolbars(): array
    {
        // Same reason as `getToolbarButtons()`: the parent type-hints every item as
        // `string | ToolbarButtonGroup` and would raise a TypeError on anything else, so
        // the widened version is reimplemented here rather than worked around.
        $toolbars = $this->evaluate($this->floatingToolbars) ?? $this->getDefaultFloatingToolbars();

        $tools = $this->getTools();

        return array_map(
            fn (array $buttons): array => array_map(
                fn (mixed $item): mixed => ($item instanceof ToolbarButtonGroup)
                    ? $item->resolve($tools)
                    : $item,
                $buttons,
            ),
            $toolbars,
        );
    }

    /**
     * Whether an image can be dragged to a new size inside the editor.
     *
     * Filament defaults this to `false`; the package default lives in the config file so
     * that a project can flip it once instead of on every field. A value set on the field
     * still wins.
     */
    public function hasResizableImages(): bool
    {
        $condition = $this->evaluate($this->hasResizableImages);

        if ($condition !== null) {
            return (bool) $condition;
        }

        return (bool) config('filament-advanced-rich-editor.images.resizable', true);
    }

    /**
     * The button that expands the editor to fill the window.
     */
    public function fullscreen(bool|Closure $condition = true): static
    {
        $this->hasFullscreen = $condition;

        return $this;
    }

    public function hasFullscreen(): bool
    {
        return (bool) ($this->evaluate($this->hasFullscreen)
            ?? config('filament-advanced-rich-editor.fullscreen', true));
    }

    /**
     * The swatch dropdown that paints the letters.
     *
     * The palette is Filament's own `textColors()`, so a project configures it once and
     * both the swatches and the stored `data-color` values follow - which is also what
     * keeps a colour dark-mode aware, since a palette entry carries a light and a dark
     * value while a hand-picked one cannot.
     */
    public function textColor(bool|Closure $condition = true): static
    {
        $this->hasTextColor = $condition;

        return $this;
    }

    public function hasTextColor(): bool
    {
        return (bool) ($this->evaluate($this->hasTextColor)
            ?? config('filament-advanced-rich-editor.colors.text', true));
    }

    /**
     * The swatch dropdown that paints behind the letters.
     */
    public function textBackground(bool|Closure $condition = true): static
    {
        $this->hasTextBackground = $condition;

        return $this;
    }

    public function hasTextBackground(): bool
    {
        return (bool) ($this->evaluate($this->hasTextBackground)
            ?? config('filament-advanced-rich-editor.colors.background', true));
    }

    /**
     * @param  array<string, string> | Closure  $colors  label keyed by CSS colour
     */
    public function backgroundColors(array|Closure $colors): static
    {
        $this->backgroundColors = $colors;

        return $this;
    }

    /**
     * The background palette, in the shape the swatch dropdown consumes.
     *
     * @return array<int, array{value: string, label: string, color: string, darkColor: string}>
     */
    public function getBackgroundColors(): array
    {
        $colors = $this->evaluate($this->backgroundColors)
            ?? config('filament-advanced-rich-editor.colors.background_palette')
            ?? [];

        $resolved = [];

        foreach ($colors as $color => $label) {
            // A list rather than a map is accepted too, in which case the colour is its
            // own label - handy for a quick palette of hex values.
            if (is_int($color)) {
                $color = $label;
            }

            $resolved[] = [
                'value' => (string) $color,
                'label' => (string) $label,
                'color' => (string) $color,
                'darkColor' => (string) $color,
            ];
        }

        return $resolved;
    }

    /**
     * The palette both the swatch grid and Filament's own machinery read.
     *
     * Only the default is replaced: a field that configures `textColors()`, or a model
     * that registers them on its rich content attribute, still wins. Filament's default
     * lists every Tailwind hue including nine neutrals that are hard to tell apart in a
     * grid of swatches, which is a fine palette for a labelled select and a poor one here.
     *
     * @return array<string, TextColor>
     */
    public function getTextColors(): array
    {
        if (filled($this->evaluate($this->textColors)) || filled($this->getContentAttribute()?->getTextColors())) {
            return parent::getTextColors();
        }

        $palette = config('filament-advanced-rich-editor.colors.text_palette');

        if (blank($palette)) {
            return parent::getTextColors();
        }

        $colors = [];

        foreach ($palette as $name => $color) {
            // A plain `name => colour` map is accepted too, in which case the colour is
            // used for both themes.
            $colors[$name] = is_array($color)
                ? TextColor::make($color['label'] ?? $name, $color['color'] ?? null, $color['dark'] ?? null)
                : TextColor::make($name, $color);
        }

        return $colors;
    }

    /**
     * The text palette, translated from Filament's own colour objects.
     *
     * @return array<int, array{value: string, label: string, color: string, darkColor: string}>
     */
    public function getTextColorsForPicker(): array
    {
        $resolved = [];

        foreach ($this->getTextColors() as $name => $color) {
            $resolved[] = [
                'value' => (string) $name,
                'label' => (string) ($color->getLabel() ?? $name),
                'color' => (string) $color->getColor(),
                'darkColor' => (string) $color->getDarkColor(),
            ];
        }

        return $resolved;
    }

    public function customColors(bool|Closure $condition = true): static
    {
        $this->hasCustomColors = $condition;

        return $this;
    }

    public function hasCustomColors(): bool
    {
        return (bool) ($this->evaluate($this->hasCustomColors)
            ?? config('filament-advanced-rich-editor.colors.custom', true));
    }

    /**
     * The toolbar's font size stepper, and with it the font size mark.
     *
     * Turning it off also unregisters the TipTap extensions, so no size can be applied
     * and none is parsed out of existing content.
     */
    public function fontSize(bool|Closure $condition = true): static
    {
        $this->hasFontSize = $condition;

        return $this;
    }

    public function hasFontSize(): bool
    {
        return (bool) ($this->evaluate($this->hasFontSize)
            ?? config('filament-advanced-rich-editor.font_size.enabled', true));
    }

    /**
     * The bounds of the stepper. Anything left as null keeps the configured default.
     */
    public function fontSizeOptions(
        int|Closure|null $min = null,
        int|Closure|null $max = null,
        int|Closure|null $step = null,
        int|Closure|null $default = null,
    ): static {
        $this->fontSizeOptions = [
            'min' => $min ?? ($this->fontSizeOptions['min'] ?? null),
            'max' => $max ?? ($this->fontSizeOptions['max'] ?? null),
            'step' => $step ?? ($this->fontSizeOptions['step'] ?? null),
            'default' => $default ?? ($this->fontSizeOptions['default'] ?? null),
        ];

        return $this;
    }

    /**
     * @return array{min: int, max: int, step: int, default: int, sizes: array<int, int>}
     */
    public function getFontSizeOptions(): array
    {
        $option = fn (string $key, int $fallback): int => (int) ($this->evaluate($this->fontSizeOptions[$key] ?? null)
            ?? config("filament-advanced-rich-editor.font_size.{$key}", $fallback));

        $min = max(1, $option('min', 8));
        $max = max($min, $option('max', 96));

        $sizes = $this->evaluate($this->fontSizeOptions['sizes'] ?? null)
            ?? config('filament-advanced-rich-editor.font_size.sizes')
            ?? [8, 9, 10, 11, 12, 14, 16, 18, 24, 30, 36, 48, 60, 72, 96];

        return [
            'min' => $min,
            'max' => $max,
            'step' => max(1, $option('step', 1)),
            'default' => min($max, max($min, $option('default', 16))),
            'sizes' => array_values(array_map(intval(...), $sizes)),
        ];
    }

    /**
     * Whether the image button opens the media browser instead of Filament's upload dialog.
     *
     * On by default, because a picture that is already on the server is the common case in a
     * long document and re-uploading it is the one thing the stock dialog forces. Turning it
     * off restores Filament's own dialog exactly.
     */
    public function mediaLibrary(bool|Closure $condition = true): static
    {
        $this->hasMediaLibrary = $condition;

        return $this;
    }

    public function hasMediaLibrary(): bool
    {
        if (! $this->hasFileAttachments()) {
            return false;
        }

        return (bool) ($this->evaluate($this->hasMediaLibrary)
            ?? config('filament-advanced-rich-editor.media_library.enabled')
            ?? true);
    }

    /**
     * Opens the browser onto a library shared across records, rather than onto this record's
     * own attachments.
     *
     * The closure receives the media query and returns the pool. It is the whole definition:
     * whatever it returns is what the grid lists, and - because the file attachment provider
     * authorises through the same object - what a saved `data-id` is allowed to resolve to.
     * Widening the browser and widening the lookup is deliberately one act, so the two cannot
     * drift into a gap.
     *
     * Media library fields only; a plain disk field takes `mediaLibraryDirectory()` instead.
     *
     * @param  Closure(Builder<Media>): mixed|null  $callback
     */
    public function mediaLibraryQuery(?Closure $callback): static
    {
        $this->mediaLibraryQuery = $callback;

        return $this;
    }

    /**
     * The directory the browser lists, for a field that stores attachments as plain files.
     *
     * Null keeps the pool at the field's own `fileAttachmentsDirectory()`, which is where its
     * uploads already land. Naming a directory makes it a shared library: everything under it
     * can be browsed, reused across records, and referenced from saved content.
     */
    public function mediaLibraryDirectory(string|Closure|null $directory): static
    {
        $this->mediaLibraryDirectory = $directory;

        return $this;
    }

    public function getMediaLibraryDirectory(): ?string
    {
        $directory = $this->evaluate($this->mediaLibraryDirectory)
            ?? config('filament-advanced-rich-editor.media_library.directory');

        return filled($directory) ? trim((string) $directory, '/') : null;
    }

    /**
     * The model new uploads belong to, instead of the record being edited.
     *
     * A media row belongs to a model and its file lives at a path built from the row's id, so a
     * picture uploaded while editing an article is that article's - and deleting the article
     * takes the file with it. Harmless while only that article shows it; not harmless once a
     * second one reuses it.
     *
     * Give the pictures a model of their own and the coupling is gone: nothing an editor
     * deletes owns them.
     *
     *     ->mediaLibraryUploadsTo(fn () => MediaLibrary::firstOrCreate(['key' => 'editor']))
     *
     * Naming one also points the browser at it, so uploading and browsing agree without a
     * second call. `mediaLibraryQuery()` still overrides the pool outright.
     */
    public function mediaLibraryUploadsTo(mixed $target): static
    {
        $this->mediaLibraryUploadsTo = $target;

        return $this;
    }

    public function getMediaLibraryUploadTarget(): ?Model
    {
        $target = $this->evaluate($this->mediaLibraryUploadsTo);

        return ($target instanceof Model) ? $target : null;
    }

    /**
     * How far the browser looks. Three settings, each narrower than the last:
     *
     *   'collection'  every picture in the collection this field uploads to, whichever record
     *                 or model it belongs to. The default, because the collection *is* the
     *                 library: a picture put in `rich-editor` is a picture for rich editors,
     *                 and an article and a post that both upload there draw from one pool
     *                 rather than each fetching the same picture again. Separate libraries are
     *                 separate collections, which is what collections are for.
     *   'model'       only the records of the model being edited.
     *   'record'      only the record in front of you.
     *
     * Whichever it is, it is also what a stored `data-id` may resolve to - the browser and the
     * lookup are one object. `mediaLibraryQuery()` overrides it entirely.
     */
    public function mediaLibraryScope(string|Closure|null $scope): static
    {
        $this->mediaLibraryScope = $scope;

        return $this;
    }

    public function getMediaLibraryScope(): string
    {
        $scope = $this->evaluate($this->mediaLibraryScope)
            ?? config('filament-advanced-rich-editor.media_library.scope')
            ?? 'collection';

        return in_array($scope, ['collection', 'model', 'record'], strict: true) ? $scope : 'collection';
    }

    /**
     * The conversion the grid draws its tiles from.
     *
     * A grid of full-size photographs is a dialog that takes seconds to open, for pictures
     * shown at about 120 pixels wide - so point this at a small conversion and the browser
     * gets cheap. It is deliberately separate from the conversion used when a picture is
     * *inserted*: the tile should be small and the image in the document should not.
     *
     * The conversion has to exist on the model, because that is the only place Spatie lets
     * anyone declare one - a package cannot add a conversion to somebody else's model:
     *
     *     public function registerMediaConversions(?Media $media = null): void
     *     {
     *         $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 320, 320);
     *     }
     *
     * Anything the model has not generated falls back to the original, so naming a conversion
     * that does not exist yet costs nothing and starts working as soon as it does.
     */
    public function mediaLibraryThumbnail(string|Closure|null $conversion): static
    {
        $this->mediaLibraryThumbnail = $conversion;

        return $this;
    }

    public function getMediaLibraryThumbnail(): ?string
    {
        $conversion = $this->evaluate($this->mediaLibraryThumbnail)
            ?? config('filament-advanced-rich-editor.media_library.thumbnail');

        return filled($conversion) ? (string) $conversion : null;
    }

    /**
     * How many pictures one page of the grid holds. The grid loads the next page on demand, so
     * this is a request size rather than a limit on the library.
     */
    public function mediaLibraryPageSize(int|Closure|null $size): static
    {
        $this->mediaLibraryPageSize = $size;

        return $this;
    }

    public function getMediaLibraryPageSize(): int
    {
        $size = $this->evaluate($this->mediaLibraryPageSize)
            ?? config('filament-advanced-rich-editor.media_library.page_size')
            ?? 40;

        return max(1, min(200, (int) $size));
    }

    /**
     * Which of the two layouts the browser opens on.
     *
     * The grid is the default because picking a picture is done by looking at pictures. The
     * list is what the grid cannot do: names, sizes and dates lined up in columns, which is how
     * you find one file among four hundred rather than recognise one among twelve.
     *
     * Only the opening layout. Which one somebody browses in afterwards is a habit rather than
     * a setting, so the dialog remembers their last choice and that wins over this.
     */
    public function mediaLibraryListView(bool|Closure $condition = true): static
    {
        $this->mediaLibraryListView = $condition;

        return $this;
    }

    public function hasMediaLibraryListView(): bool
    {
        return (bool) ($this->evaluate($this->mediaLibraryListView)
            ?? config('filament-advanced-rich-editor.media_library.list_view')
            ?? false);
    }

    /**
     * The pool the browser lists from, and the pool a stored id may resolve against.
     *
     * Built fresh rather than memoised: the closures inside it read the live component, and
     * fields are cloned - by repeaters, by custom block modals - so a source cached on one
     * instance would keep answering with the record of another.
     */
    public function getMediaSource(): ?MediaSource
    {
        if (! $this->hasMediaLibrary()) {
            return null;
        }

        $provider = $this->getFileAttachmentProvider();

        if ($provider instanceof SpatieMediaLibraryFileAttachmentProvider) {
            // Already attached by `getFileAttachmentProvider()`, which is the one place that
            // has to do it: everything Filament resolves goes through the provider, and a
            // source built only here would leave those lookups unauthorised.
            return $provider->getSource();
        }

        // Any other provider defines both where an attachment lives and what its id means, and
        // neither is anything this package can enumerate. Treating it as a plain disk field
        // would open a grid on the wrong pool and, because the pool is also the authoriser,
        // refuse the very ids that provider issued. No browser is the honest answer; Filament's
        // own dialog takes the button back.
        if ($provider !== null) {
            return null;
        }

        $library = $this->getMediaLibraryDirectory();
        $directory = $library ?? $this->getFileAttachmentsDirectory();

        // Filament leaves `fileAttachmentsDirectory()` null by default, and uploads then land at
        // the root of the disk among everything else on it. A grid over that root is not a
        // library of this field's pictures, it is the disk - other features' uploads, avatars,
        // exports - and it would let a stored path resolve to any of them. A directory is what
        // turns a disk into a pool, so without one there is nothing to browse.
        if (blank($directory)) {
            return null;
        }

        return DiskMediaSource::make(
            disk: $this->getFileAttachmentsDiskName(),
            directory: (string) $directory,
            visibility: $this->getFileAttachmentsVisibility(),
            acceptedMimeTypes: $this->getFileAttachmentsAcceptedFileTypes(),
            isRecordScoped: blank($library),
        );
    }

    /**
     * The Spatie pool, for the provider to authorise through.
     *
     * Kept apart from `getMediaSource()` so the two cannot call each other: the provider is
     * where a source is attached, and `getMediaSource()` reads it back off the provider.
     */
    protected function buildSpatieMediaSource(SpatieMediaLibraryFileAttachmentProvider $provider): SpatieMediaSource
    {
        // Where uploads are sent, the browser looks. Otherwise naming a library would mean
        // pictures landing somewhere the grid cannot show and the lookup will not resolve -
        // one call that quietly needs a second one to be of any use.
        $owner = $this->getMediaLibraryUploadTarget();

        return SpatieMediaSource::make(
            collection: $provider->getCollection(),
            conversion: $provider->getConversion(),
            visibility: $provider->getDefaultFileAttachmentVisibility(),
            poolQuery: $this->mediaLibraryQuery,
            getRecordUsing: fn (): mixed => $owner ?? $this->getRecord(),
            acceptedMimeTypes: $this->getFileAttachmentsAcceptedFileTypes(),
            thumbnailConversion: $this->getMediaLibraryThumbnail(),
            scope: $this->getMediaLibraryScope(),
            // Stands in for the record on a create form, which has none yet - otherwise the
            // library would be empty at exactly the moment somebody reaches for a picture they
            // already have.
            getModelUsing: fn (): mixed => $owner ?? $this->getModel(),
        );
    }

    /**
     * One page of the browser, fetched by the grid as it scrolls.
     *
     * Exposed to the front end, so it re-reads the pool from the field on every call rather
     * than trusting anything the browser sends beyond a search term and a page number.
     *
     * @return array{items: array<int, array<string, mixed>>, folders: array<int, array{name: string, path: string}>, parent: string|null, hasMore: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaLibraryPageForJs(string $search = '', ?string $folder = null, int $page = 1, ?string $type = null, ?string $sort = null): array
    {
        $source = $this->getMediaSource();

        if (! $source) {
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'perPage' => $this->getMediaLibraryPageSize()];
        }

        // Taken over here rather than as each file lands. Uploading is a request per file, and
        // writing to the component in the middle of one forces a render between the first file
        // and the second - which is enough for Filament to rebuild its schema cache and refuse
        // the next upload, because the guard on `_startUpload` only accepts a path that a
        // cached schema still knows about.
        //
        // Doing it when the browser asks for a page keeps every upload request untouched, and
        // the browser asks as soon as an upload finishes.
        $this->adoptMountedUploads();

        $search = trim($search);
        $page = max(1, $page);

        $perPage = $this->getMediaLibraryPageSize();

        $result = $source->page(
            search: $search,
            folder: $folder,
            page: $page,
            perPage: $perPage,
            filters: ['type' => $type, 'sort' => $sort],
        );

        // Sent rather than left for the browser to infer. A grid that guesses the page size
        // from how many tiles came back reads a short last page as a tiny page size, and then
        // divides the whole library by it - which is how a two-page library grew a footer
        // saying "2 / 41" with a Next button leading to nothing.
        $result['perPage'] = $perPage;

        // A picture uploaded a moment ago is not in the library yet - Filament holds it as a
        // pending attachment and only writes it on save - so a browser that listed the library
        // alone would answer "no such picture" about the file somebody just chose. It goes at
        // the front of the first page, where the newest things already are.
        //
        // Only on the first page, and only at the top of the tree: a pending upload has no
        // folder to sit in, and repeating it under every folder would be worse than not
        // showing it at all.
        if ($page === 1 && blank($folder)) {
            $pending = $this->getPendingMediaItems($search);

            if ($type !== null && filled($type)) {
                $pending = array_values(array_filter(
                    $pending,
                    static fn (array $item): bool => $item['mime'] === $type,
                ));
            }

            $result['items'] = [...$pending, ...$result['items']];
            $result['total'] = ((int) $result['total']) + count($pending);
        }

        return $result;
    }

    /**
     * Everything the details panel shows about one picture.
     *
     * A second call rather than part of the page, because the expensive field is the size in
     * pixels: a picture that was never stamped with it has to be opened to be measured, and
     * doing that for a grid would be a file read per tile. The panel shows one at a time.
     *
     * @return array<string, mixed>|null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaDetailsForJs(?string $id = null): ?array
    {
        if (blank($id) || ! $this->hasMediaLibrary()) {
            return null;
        }

        // A pending upload is not in the pool, and it is already described in full by the
        // listing - the file is local, so its size in pixels was read there rather than left
        // for this call.
        $pending = $this->pendingMediaItem($id, $this->getUploadedFileAttachment($id));

        if ($pending !== null) {
            return $pending;
        }

        return $this->getMediaSource()?->details($id);
    }

    /**
     * Takes over the uploads the image dialog is holding.
     *
     * The dialog is a modal, and a modal's form is thrown away when it closes - so an upload
     * that lived there disappeared the moment somebody pressed apply, taking every picture
     * they had queued up but not used yet with it.
     *
     * Moved here instead, where Filament already keeps pending attachments: they belong to the
     * field, outlive any number of trips through the dialog, and are turned into real files
     * only when the form is saved - and then only the ones the content actually references.
     * Everything else is simply never written, so nothing has to be cleaned up.
     *
     * @param  array<mixed>  $files
     */
    public function registerPendingUploads(array $files): void
    {
        // Held in a variable because `data_set()` takes its target by reference, which a method
        // call cannot be.
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        foreach ($files as $file) {
            if (! ($file instanceof TemporaryUploadedFile)) {
                continue;
            }

            // Named after the file rather than after the key it arrived under. The dialog
            // reports its whole set every time one more picture is added, and the keys of that
            // set are Filament's business - a plain list one moment, keyed by id the next - so
            // trusting them would queue the same upload twice under two different names.
            //
            // Hashed, because the temporary file name carries dots and `data_set()` reads a dot
            // as a level of nesting: one attachment would quietly become a tree that nothing
            // can find again.
            $id = 'arte-'.md5($file->getFilename());

            data_set($livewire, "componentFileAttachments.{$statePath}.{$id}", $file);
        }
    }

    /**
     * Takes over whatever the image dialog is currently holding.
     *
     * The dialog's own form belongs to a modal and is thrown away when the modal closes, so an
     * upload left there disappears the moment somebody presses apply - taking every picture
     * they had queued up but not used yet with it. Moved to the field instead, where Filament
     * already keeps pending attachments.
     *
     * Read out of the mounted actions rather than pushed in by the dialog, so that nothing is
     * written to the component while an upload request is in flight.
     */
    public function adoptMountedUploads(): void
    {
        $mounted = data_get($this->getLivewire(), 'mountedActions');

        if (! is_array($mounted)) {
            return;
        }

        foreach ($mounted as $action) {
            $files = data_get($action, 'data.file');

            if (is_array($files)) {
                $this->registerPendingUploads($files);
            }
        }
    }

    /**
     * Lets go of every upload this field was holding.
     *
     * Called once the form has been saved, which is the moment they have all been decided: the
     * ones the content references have been written to disk and carry real ids now, and the
     * rest are pictures somebody fetched and did not use.
     *
     * Both are finished with. Keeping the first would show the same picture twice in the
     * browser - once as the file it became, once as the upload it used to be - and keeping the
     * second would offer a temporary file that is about to be swept away as though it were a
     * library item.
     *
     * The temporary files go too. Livewire prunes its own directory eventually, but "eventually"
     * is a lot of abandoned uploads on a busy editor, and these are known to be finished with.
     */
    public function discardPendingUploads(): void
    {
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        $attachments = data_get($livewire, "componentFileAttachments.{$statePath}");

        if (! is_array($attachments) || $attachments === []) {
            return;
        }

        foreach ($attachments as $file) {
            if (! ($file instanceof TemporaryUploadedFile)) {
                continue;
            }

            // A file Livewire has already swept, or one on a disk that will not have it
            // deleted: not being able to tidy up is not a reason to fail a save.
            rescue(static fn () => $file->delete(), report: false);
        }

        data_set($livewire, "componentFileAttachments.{$statePath}", []);
    }

    /**
     * The uploads this field is holding that have not been saved yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingMediaItems(string $search = ''): array
    {
        $attachments = data_get($this->getLivewire(), "componentFileAttachments.{$this->getStatePath()}");

        if (! is_array($attachments)) {
            return [];
        }

        $items = [];

        foreach (array_reverse($attachments, preserve_keys: true) as $id => $attachment) {
            if (! is_string($id)) {
                continue;
            }

            $item = $this->pendingMediaItem($id, $attachment);

            if ($item === null) {
                continue;
            }

            if (filled($search) && ! str_contains(Str::lower($item['name']), Str::lower($search))) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * One pending upload as the grid draws an item, or null where it is not one this browser
     * should offer - a file that failed Filament's own validation, or something that is not a
     * picture and would insert a broken image.
     *
     * @return array<string, mixed>|null
     */
    protected function pendingMediaItem(string $id, mixed $attachment): ?array
    {
        if (! ($attachment instanceof TemporaryUploadedFile)) {
            return null;
        }

        // Through the field rather than off the file: this is where Filament re-checks the
        // size and the accepted types, and a rejected upload must not become a tile.
        $file = $this->getUploadedFileAttachment($id);

        if (! $file) {
            return null;
        }

        $mime = (string) $file->getMimeType();

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        $url = $this->getUploadedFileAttachmentTemporaryUrl($file);

        if (blank($url)) {
            return null;
        }

        $name = (string) $file->getClientOriginalName();

        return [
            'id' => $id,
            'url' => $url,
            'thumbnail' => $url,
            'name' => $name,
            'fileName' => $name,
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'folder' => null,
            'createdAt' => null,
            'modifiedAt' => null,
            // Measured here rather than left for the panel: a pending upload is a file Livewire
            // is holding on local disk, so reading its header costs nothing, and there are a
            // handful of them rather than a library's worth.
            ...($this->measurePending($file) ?? ['width' => null, 'height' => null]),
            // Drawn differently, and said out loud: this one is not in the library until the
            // form is saved, and somebody who navigates away now will not find it again.
            'pending' => true,
        ];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    protected function measurePending(TemporaryUploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (is_file($path)) {
            return MediaDimensions::fromPath($path);
        }

        // Livewire keeps temporary uploads on a remote disk in some setups, where there is no
        // local file to point `getimagesize()` at.
        return MediaDimensions::fromString((string) $file->get());
    }

    /**
     * The item behind an id the dialog sent back, pending uploads included.
     *
     * Never trusts the id: a pending one has to be an upload this field is actually holding,
     * and a stored one has to be something the pool would have listed.
     *
     * @return array<string, mixed>|null
     */
    public function findMediaItem(mixed $id): ?array
    {
        if (is_string($id) && ($pending = $this->pendingMediaItem($id, $this->getUploadedFileAttachment($id))) !== null) {
            return $pending;
        }

        return $this->getMediaSource()?->find($id);
    }

    public function spatieMediaLibrary(?string $collection = null, ?string $conversion = null, ?string $disk = null, ?string $visibility = null): static
    {
        $isFirstCall = $this->spatieMediaLibraryPlugin === null;

        $this->spatieMediaLibraryPlugin = SpatieMediaLibraryPlugin::make(
            $collection ?? config('filament-advanced-rich-editor.spatie.collection'),
            $conversion ?? config('filament-advanced-rich-editor.spatie.conversion'),
            $disk ?? config('filament-advanced-rich-editor.spatie.disk'),
            $visibility ?? config('filament-advanced-rich-editor.spatie.visibility'),
        );

        // The plugin is registered through a closure so the record resolver is
        // always bound to the component the plugins are resolved for. Fields are
        // cloned (repeaters, custom block modals), and a resolver captured here
        // would keep answering with the record of the original instance.
        // `plugins()` appends, so the closure is registered once and simply reads
        // whatever the latest call to this method configured.
        if ($isFirstCall) {
            $this->plugins(static function (AdvancedRichEditor $component): array {
                $plugin = $component->getSpatieMediaLibraryPlugin();

                return $plugin ? [$plugin] : [];
            });
        }

        return $this;
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        // Field level configuration wins over plugins, which in turn win over
        // the provider declared on the model's rich content attribute.
        if ($provider = $this->getSpatieMediaLibraryPlugin()?->getFileAttachmentProvider()) {
            return $this->withMediaSource($provider);
        }

        foreach ($this->getPlugins() as $plugin) {
            if (! ($plugin instanceof HasFileAttachmentProvider)) {
                continue;
            }

            if ($provider = $plugin->getFileAttachmentProvider()) {
                return $this->withMediaSource($provider);
            }
        }

        return $this->withMediaSource(parent::getFileAttachmentProvider());
    }

    /**
     * Hands the provider the pool the media browser lists from.
     *
     * Every lookup Filament performs - hydrating a document, saving one, rendering one back -
     * goes through the provider, so this is the single place where "what the browser offers"
     * becomes "what a stored id may resolve to". Attaching it anywhere else would leave some
     * of those paths reading against the record scope alone, and a picture picked from the
     * library would resolve in the editor and vanish on the page.
     */
    protected function withMediaSource(?FileAttachmentProvider $provider): ?FileAttachmentProvider
    {
        if ($provider instanceof SpatieMediaLibraryFileAttachmentProvider) {
            $provider->source(
                $this->hasMediaLibrary() ? $this->buildSpatieMediaSource($provider) : null,
            );
        }

        return $provider;
    }

    /**
     * Returns the field level Spatie plugin with a record resolver bound to
     * this very instance. Creating the closure here - and not in
     * `spatieMediaLibrary()` - is what keeps it pointing at the live component.
     */
    protected function getSpatieMediaLibraryPlugin(): ?SpatieMediaLibraryPlugin
    {
        return $this->spatieMediaLibraryPlugin
            ?->recordUsing(fn (): mixed => $this->getRecord())
            ->ownerUsing(fn (): string => $this->getName())
            ->uploadsToUsing(fn (): mixed => $this->getMediaLibraryUploadTarget());
    }

    public function saveFileAttachments(): void
    {
        parent::saveFileAttachments();

        // Not on a create page, where the parent bails out and leaves the work to
        // `saveFileAttachmentsToRecord()`: discarding here would throw the uploads away before
        // a single one of them had been written.
        if ($this->getFileAttachmentProvider()?->isExistingRecordRequiredToSaveNewFileAttachments() && (! $this->getRecord())) {
            return;
        }

        $this->discardPendingUploads();
    }

    /**
     * Writes the editor's state to a record that has just been created.
     *
     * Copied from the parent so one dereference can be made safe: Filament resolves the
     * attribute name with `$this->getContentAttribute()->getName()`, and
     * `getContentAttribute()` is null unless the *model* registers rich content through
     * `HasRichContent`. Any file attachment provider that needs a persisted record - the
     * Spatie one does, because media is attached to a saved model - reaches this method
     * on every create page, so a plain model would fatal with "Call to a member function
     * getName() on null" the first time an image is uploaded. The field's own name is the
     * correct fallback, since that is the attribute the state came from.
     *
     * `$record` is guarded as well: the parent assumes `saveRelationships()` never runs
     * without one.
     */
    public function saveFileAttachmentsToRecord(): void
    {
        $fileAttachmentProvider = $this->getFileAttachmentProvider();

        if (! $fileAttachmentProvider?->isExistingRecordRequiredToSaveNewFileAttachments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record?->wasRecentlyCreated) {
            return;
        }

        $fileAttachmentIds = $this->resolveFileAttachmentIds();

        $record->setAttribute(
            $this->getContentAttribute()?->getName() ?? $this->getName(),
            $this->getState(),
        );
        $record->save();

        $fileAttachmentProvider->cleanUpFileAttachments(exceptIds: $fileAttachmentIds);

        $this->discardPendingUploads();
    }
}
