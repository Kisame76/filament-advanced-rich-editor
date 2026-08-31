<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImageLock;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarListPanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPresets;

/**
 * The bars that appear over a selection rather than above the field.
 *
 * Filament shows one per node name, keyed by what `isActive()` answers for the caret. The
 * text bar is the exception it hard-codes, which is why its key is `'paragraph'` and cannot
 * be anything else. What the picture, list and text bars carry is a per-field answer, so
 * they are assembled here rather than declared once.
 */
trait FloatsToolbars
{
    protected bool|Closure|null $hasTextToolbar = null;

    /**
     * @var array<int, mixed>|Closure|null
     */
    protected array|Closure|null $textToolbarButtons = null;

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
            ?? $this->notionDefaultFor('textToolbar')
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
            ?? $this->getPresetLayout()['text_toolbar_buttons']
            ?? config('filament-advanced-rich-editor.text_toolbar_buttons')
            ?? ToolbarPresets::shipped()['default']['text_toolbar_buttons'];

        // Resolved through the same tokens the bar itself uses, so `'styles'` and
        // `'textColor'` mean here what they mean up there - and a switched-off feature
        // takes its button out of the bubble too rather than leaving a dead one behind.
        return ToolbarLayout::resolve(array_values($buttons), $this);
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
}
