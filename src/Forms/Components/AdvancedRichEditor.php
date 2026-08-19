<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Forms\Components;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontSizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageResizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
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

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $listTypes = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $alignments = null;

    protected bool|Closure|null $hasTaskList = null;

    protected bool|Closure|null $hasFontSize = null;

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
                ->icon(Heroicon::Photo)
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
            ['headings', 'fontSize', 'blockquote', 'codeBlock'],
            'divider',
            ['bold', 'italic', 'strike', 'underline', 'link'],
            'divider',
            ['superscript', 'subscript'],
            'divider',
            ['alignment', 'lists'],
            'divider',
            ['image', 'table'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getToolbarButtons(): array
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

        return $this->collapseToolbarDividers($groups);
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
     * Dividers carry no button names at all, so they must never answer a
     * `hasToolbarButton()` lookup - otherwise a decorative item could satisfy
     * checks such as `hasFileAttachmentsByDefault()`.
     */
    protected function hasToolbarButtonInItem(object $item, string $button): bool
    {
        if ($item instanceof ToolbarDivider) {
            return false;
        }

        return parent::hasToolbarButtonInItem($item, $button);
    }

    /**
     * @param  array<string>  $buttonsToDisable
     */
    protected function filterDisabledToolbarButtonsFromItem(object $item, array $buttonsToDisable): ?object
    {
        // Dividers survive `disableToolbarButtons()` untouched: they are layout,
        // not a button, and dropping them would leave the remaining groups
        // visually glued together. `collapseToolbarDividers()` afterwards removes
        // only the ones the disabling left with nothing to separate.
        if ($item instanceof ToolbarDivider) {
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

    public function taskList(bool|Closure $condition = true): static
    {
        $this->hasTaskList = $condition;

        return $this;
    }

    public function hasTaskList(): bool
    {
        return (bool) ($this->evaluate($this->hasTaskList) ?? config('filament-advanced-rich-editor.task_list.enabled') ?? true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getDefaultFloatingToolbars(): array
    {
        $toolbars = parent::getDefaultFloatingToolbars();

        if (! $this->hasResizableImages()) {
            return $toolbars;
        }

        // Keyed by node name: `editor.isActive('image')` is true for the node selection a
        // click on an image produces, which is what the bubble menu shows itself on.
        return [
            ...$toolbars,
            'image' => [ToolbarImageLock::make()],
        ];
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
     * @return array{min: int, max: int, step: int, default: int}
     */
    public function getFontSizeOptions(): array
    {
        $option = fn (string $key, int $fallback): int => (int) ($this->evaluate($this->fontSizeOptions[$key] ?? null)
            ?? config("filament-advanced-rich-editor.font_size.{$key}", $fallback));

        $min = max(1, $option('min', 8));
        $max = max($min, $option('max', 96));

        return [
            'min' => $min,
            'max' => $max,
            'step' => max(1, $option('step', 1)),
            'default' => min($max, max($min, $option('default', 16))),
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
        return $this->spatieMediaLibraryPlugin?->recordUsing(fn (): mixed => $this->getRecord());
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
