<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\AttachesFiles;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\BuildsTheToolbar;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\CastsItsContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\ChecksItsContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\ChoosesTypefaces;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\ColoursText;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\CountsCharacters;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\FloatsToolbars;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\FormatsBlocks;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\FormatsText;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\HoldsAMediaLibrary;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\KeepsADraft;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\KeepsContentClean;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OffersWritingAids;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensMenus;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\PlacesImages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\PreviewsContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\ServesTheMediaBrowser;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\WritesWithoutAToolbar;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\LivewireNesting;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
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
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\PreviewPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SourceCodePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\StatisticsPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\StylesPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextBackgroundPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextCasePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextDirectionPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TypographyPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;
use Tiptap\Editor;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextToolbarPlugin;
/**
 * Filament's rich editor with the things a long document needs.
 *
 * What the field itself holds is what nothing else could: the registration of every tool
 * and plugin, in one list and in one order, because that order is the order the extensions
 * reach the browser in. Everything a project switches on or off lives in the concern it
 * belongs to, next to the accessors that read it - which is what keeps the next switch a
 * file nobody has to scroll through.
 */
class AdvancedRichEditor extends RichEditor
{
    use AttachesFiles;
    use BuildsTheToolbar;
    use CastsItsContent;
    use ChoosesTypefaces;
    use ColoursText;
    use CountsCharacters;
    use ChecksItsContent;
    use FloatsToolbars;
    use FormatsBlocks;
    use FormatsText;
    use HoldsAMediaLibrary;
    use KeepsADraft;
    use KeepsContentClean;
    use OffersWritingAids;
    use OpensMenus;
    use PlacesImages;
    use ServesTheMediaBrowser;
    use PreviewsContent;
    use WritesWithoutAToolbar;

    protected string $view = 'filament-advanced-rich-editor::rich-editor';

    protected string|int|Closure|null $maxHeight = null;

    /**
     * Whether the field answered the height question itself, however it answered it.
     */
    protected bool $hasMaxHeight = false;

    protected bool|int|Closure|null $nestingCheck = null;

    protected function setUp(): void
    /**
     * @var array<int, Closure(AdvancedRichContentRenderer): mixed>
     */
    protected array $rendererConfiguration = [];

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
            static fn (AdvancedRichEditor $component): array => $component->hasTextCase()
                ? [TextCasePlugin::make()]
                : [],
        );

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasTypography()
                ? [TypographyPlugin::make()]
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

        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasStatistics()
                ? [StatisticsPlugin::make()]
                : [],
        );
        // Only where the bar is actually drawn: what this registers is the rule that governs
        // it, and a rule for a bar that is not there is a message addressed to nobody.
        $this->plugins(
            static fn (AdvancedRichEditor $component): array => $component->hasPreviewFrontEnd()
                ? [PreviewPlugin::make()]
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
            // Also for a field that shows no counter but holds the limit: the rule and the
            // announcement are the same extension.
            static fn (AdvancedRichEditor $component): array => $component->hasCharacterCount()
                || $component->enforcesMaxLength()
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
                ->enforced($component->enforcesMaxLength())
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
        return $this->getRichContentRenderer()->getEditor();
    }

    /**
     * How this field's content renders, said once.
     *
     * Until this existed the field could not build a complete renderer at all. `AdvancedRichEntry`
     * and `AdvancedRichColumn` assemble one out of eight setters through `RendersRichContent`,
     * reading what the *model* declared about the attribute; the field is the other half of
     * that pair and used none of it - it built four setters inline and threw the renderer away
     * on `getEditor()`, because parsing a save is all it ever needed.
     *
     * Which was true until something wanted the field's content as a finished page. Anything
     * built on the four would render an uploaded picture as a broken image and a mention as an
     * empty span, and it would do it quietly: both are valid markup that simply points nowhere,
     * so nothing raises and nobody finds out until a reader does.
     *
     * The state is a parameter rather than always the field's own, because the two callers want
     * different things from it: parsing wants an empty editor to `setContent()` into, and the
     * preview wants the document that is in the browser this moment, which arrived as an
     * argument and is not the state on the server.
     */
    /**
     * Anything about rendering that the field cannot work out for itself.
     *
     * The same hook `AdvancedRichEntry` and `AdvancedRichColumn` carry, and it is here for a
     * reason a preview makes obvious: a field knows its own plugins, mentions and disk, and
     * knows nothing at all about the decisions a page makes on top of them. Heading anchors,
     * a table of contents, syntax colours - all of those are named where the document is
     * rendered, which is somewhere this field has never seen.
     *
     * Without it the preview is truthful about the field and wrong about the page, which is
     * the one thing a preview may not be. Point this at the same description the page renders
     * with rather than a copy of it.
     *
     * @param  Closure(AdvancedRichContentRenderer): mixed  $callback
     */
    public function configureRenderer(Closure $callback): static
    {
        $this->rendererConfiguration[] = $callback;

        return $this;
    }

    public function getRichContentRenderer(mixed $content = null): AdvancedRichContentRenderer
    {
        $renderer = AdvancedRichContentRenderer::make($content)
            ->plugins($this->getPlugins())
            ->linkProtocols($this->getLinkProtocols())
            ->linkAttributes($this->hasLinkAttributes())
            ->styles(Styles::for($this));

        // Last, the same way the entry applies it: what the field was told about rendering
        // wins over what the field worked out about itself. Applied here rather than only in
        // the preview, so that a closure reaching for a plugin is a plugin the schema knows
        // about too - a node the renderer draws and the parser strips is a node that
        // disappears on the next save.
        foreach ($this->rendererConfiguration as $configure) {
            $configure($renderer);
        }

        return $renderer;
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
     * The check that Livewire will let a document through at all.
     *
     * `false` switches it off; a number asks for that depth instead of the default. Both are
     * a project's call to make: the guard knows what breaks, it does not know whether this
     * particular field will ever hold a list.
     */
    public function nestingCheck(bool|int|Closure $condition = true): static
    {
        $this->nestingCheck = $condition;

        return $this;
    }

    /**
     * The depth this field insists on, or `false` where the check is off.
     */
    public function getRequiredNestingDepth(): int|false
    {
        $value = $this->evaluate($this->nestingCheck) ?? config('filament-advanced-rich-editor.nesting_check') ?? true;

        if ($value === false) {
            return false;
        }

        return $value === true ? LivewireNesting::REQUIRED : max(1, (int) $value);
    }

    /**
     * Rendering is the last moment at which the answer is still useful: a 500 out of Livewire
     * arrives after somebody typed, name a config key and not this field. `setUp()` would be
     * earlier but also runs where no editor is ever drawn.
     */
    public function render(): View
    {
        $required = $this->getRequiredNestingDepth();

        if ($required !== false) {
            LivewireNesting::guard($required);
        }

        return parent::render();
    }
}
