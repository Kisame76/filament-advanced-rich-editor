<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Forms\Components;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\SourceCodeAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\CharacterCount;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CharacterCountPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\EmojiPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontFamilyPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontSizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\HelpPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LineHeightPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SourceCodePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextBackgroundPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextDirectionPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPin;
use LogicException;

class AdvancedRichEditor extends RichEditor
{
    protected string $view = 'filament-advanced-rich-editor::rich-editor';

    protected string|Alignment|Closure|null $toolbarAlignment = null;

    protected bool|Closure|null $isStickyToolbar = null;

    protected string|Closure|null $stickyToolbarOffset = null;

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

    protected bool|Closure|null $hasEmoji = null;

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

    protected bool|Closure|null $hasFontSize = null;

    protected bool|Closure|null $hasTextColor = null;

    protected bool|Closure|null $hasTextBackground = null;

    protected bool|Closure|null $hasCustomColors = null;

    protected bool|Closure|null $hasFullscreen = null;

    protected bool|Closure|null $hasImageToolbar = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $backgroundColors = null;

    /**
     * @var array<string, int|Closure|null>
     */
    protected array $fontSizeOptions = [];

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
            ['headings', 'fontFamily', 'fontSize'],
            'divider',
            ['bold', 'italic', 'underline', 'strike', 'textColor', 'textBackground'],
            'divider',
            ['alignment', 'lineHeight'],
            'divider',
            ['lists', 'link', 'image', 'table', 'blockquote', 'codeBlock'],
            'divider',
            ['more'],
            'pin',
            ['sourceCode', 'fullscreen', 'help'],
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
     * @return array<int, string>
     */
    public function getMoreTools(): array
    {
        return array_values($this->evaluate($this->moreTools)
            ?? config('filament-advanced-rich-editor.more')
            ?? ['subscript', 'superscript', 'code', 'clearFormatting', 'horizontalRule', 'details', 'emoji']);
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
        return (bool) ($this->evaluate($this->hasSourceCode) ?? config('filament-advanced-rich-editor.source_code') ?? true);
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

    public function taskList(bool|Closure $condition = true): static
    {
        $this->hasTaskList = $condition;

        return $this;
    }

    public function hasTaskList(): bool
    {
        return (bool) ($this->evaluate($this->hasTaskList) ?? config('filament-advanced-rich-editor.task_list') ?? true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getDefaultFloatingToolbars(): array
    {
        $toolbars = parent::getDefaultFloatingToolbars();

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

        $buttons[] = ToolbarImagePanel::alt();
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
            return $provider;
        }

        foreach ($this->getPlugins() as $plugin) {
            if (! ($plugin instanceof HasFileAttachmentProvider)) {
                continue;
            }

            if ($provider = $plugin->getFileAttachmentProvider()) {
                return $provider;
            }
        }

        return parent::getFileAttachmentProvider();
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
            ->ownerUsing(fn (): string => $this->getName());
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
    }
}
