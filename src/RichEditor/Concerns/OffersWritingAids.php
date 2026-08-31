<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\SourceCodeAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DragHandlePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FindReplacePlugin;

/**
 * The tools that help while writing without changing what is written: emoji, find
 * and replace, the drag grip, the source view, the shortcut list and fullscreen.
 *
 * Switching any of them off is the same act every time - the extension is not loaded, so the
 * button and its keyboard shortcut go together, and nothing already written changes.
 */
trait OffersWritingAids
{
    protected bool|Closure|null $hasEmoji = null;

    protected bool|Closure|null $hasFind = null;

    protected bool|Closure|null $hasDragHandle = null;

    protected bool|Closure|null $hasDragHandleInsert = null;

    protected bool|Closure|null $hasSourceCode = null;

    protected bool|Closure|null $hasHelp = null;

    protected string|Htmlable|Closure|null $helpMore = null;

    protected string|Closure|null $helpMoreLabel = null;

    protected bool|Closure|null $hasFullscreen = null;

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
        return (bool) ($this->evaluate($this->hasDragHandle)
            ?? $this->notionDefaultFor('dragHandle')
            ?? config('filament-advanced-rich-editor.drag_handle.enabled')
            ?? true);
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
        return (bool) ($this->evaluate($this->hasDragHandleInsert)
            ?? $this->notionDefaultFor('dragHandleInsert')
            ?? config('filament-advanced-rich-editor.drag_handle.insert')
            ?? true);
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
}
