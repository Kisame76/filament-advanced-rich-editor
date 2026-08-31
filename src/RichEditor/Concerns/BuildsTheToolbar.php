<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Enums\Alignment;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPresets;
use LogicException;

/**
 * The bar above the editor: which buttons it carries, in which groups, and where
 * on the bar they sit.
 *
 * Filament answers the first question with a flat list of groups. This package has to
 * answer three more on top of it - a pin that holds a group against one edge, dividers that
 * have to collapse when the buttons around them are switched off, and an overflow menu that
 * receives whatever a project pushed out of the way - and every one of them is a rewrite of
 * the same list rather than a new one.
 */
trait BuildsTheToolbar
{
    protected string|Closure|null $preset = null;

    /**
     * Resolved once per field: five readers ask for it, several more than once per render.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $presetLayout = null;

    protected string|Alignment|Closure|null $toolbarAlignment = null;

    protected bool|Closure|null $isStickyToolbar = null;

    protected string|Closure|null $stickyToolbarOffset = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $moreTools = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $toolsMenu = null;

    /**
     * @return array<int, string | array<int, string>>
     */
    public function getDefaultToolbarButtons(): array
    {
        // Falling back to the shipped default keeps the field usable when the
        // package's config has not been published or merged yet. That fallback is the
        // `'default'` preset, which is where the shipped bar is written down.
        // The mode sits under a named preset, which says something more specific about the
        // bar than "there is none", and over the config file, which was not asked.
        return $this->getPresetLayout()['toolbar']
            ?? ($this->isNotion() ? [] : null)
            ?? config('filament-advanced-rich-editor.toolbar')
            ?? ToolbarPresets::shipped()['default']['toolbar'];
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
        // A preset answers this itself, because reading it off the buttons is what let a
        // shrinking bar take the upload, the drop and the paste-upload away in silence.
        //
        // Only while the preset still describes what is on screen, though. A field that
        // replaced the bar outright is no longer drawing the preset's toolbar, so the
        // picture button is the honest answer again - the one an unpreset field gives.
        if ($this->evaluate($this->toolbarButtons) === null) {
            // The preset first: it is the more specific statement about the bar, so a
            // `->notion()->preset('comment')` field is a comment box without a bar rather
            // than a document that quietly started taking files.
            //
            // Then the mode, which has to answer for itself for the reason it exists: it
            // draws no bar, and reading the answer off a bar that is not there would switch
            // the upload off on the one field where `/` is the only way to insert a picture.
            $answer = $this->getPresetLayout()['file_attachments'] ?? $this->notionDefaultFor('fileAttachments');

            if ($answer !== null) {
                return (bool) $answer;
            }
        }

        return $this->hasToolbarButton(['attachFiles', 'image']);
    }

    /**
     * Where the toolbar's button groups sit on the bar.
     *
     * Accepts Filament's `Alignment` enum or its string value; only the horizontal cases
     * mean anything here. The default lives in the config file so a project can set it
     * once, and a field always wins.
     */
    /**
     * A named starting point for all four bars at once.
     *
     * `'minimal'`, `'comment'`, `'blog'`, `'default'` and `'full'` are shipped, and
     * `toolbar_presets` in the config file is where a project adds its own or replaces one
     * of these under the same name.
     *
     * It is a default rather than an instruction: everything the field says itself - the
     * toolbar, the overflow, the tools menu, the bubble, attachments - still wins. What it
     * beats is the configuration, and it beats it as a fixed list: `->preset('default')`
     * stays the bar this package ships even where a project has rebuilt its own.
     */
    public function preset(string|Closure|null $preset): static
    {
        $this->preset = $preset;
        $this->presetLayout = null;

        return $this;
    }

    /**
     * The preset's own answers, or nothing where the field named none.
     *
     * A preset may speak about one bar and stay silent about the rest, so every reader
     * below asks for its key and falls through to the configuration when it is absent.
     *
     * @return array<string, mixed>
     */
    public function getPresetLayout(): array
    {
        if ($this->presetLayout !== null) {
            return $this->presetLayout;
        }

        $preset = $this->evaluate($this->preset);

        // `null` is "no preset". An empty string is a name that resolved to nothing - a
        // missing config key, a closure that found no record - and it raises like any other
        // name nobody registered, because silently drawing the configured bar hides it.
        return $this->presetLayout = ($preset === null) ? [] : ToolbarPresets::get($preset);
    }

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
            ?? $this->getPresetLayout()['tools_menu']
            ?? config('filament-advanced-rich-editor.tools_menu')
            ?? ToolbarPresets::shipped()['default']['tools_menu']);
    }

    /**
     * @return array<int, string>
     */
    public function getMoreTools(): array
    {
        return array_values($this->evaluate($this->moreTools)
            ?? $this->getPresetLayout()['more']
            ?? config('filament-advanced-rich-editor.more')
            ?? ToolbarPresets::shipped()['default']['more']);
    }
}
