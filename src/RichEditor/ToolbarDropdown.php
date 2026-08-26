<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;

/**
 * A named, reusable dropdown for the rich editor toolbar.
 *
 * The parent already renders the trigger, the menu and the swapping of the
 * trigger icon to the active child, so this class adds the factories the package's
 * toolbar tokens are built from - and the one switch that turns that swapping off,
 * for a trigger that has to stay recognisable.
 */
class ToolbarDropdown extends ToolbarButtonGroup
{
    use OpensAwayFromTheEdge;

    protected bool|Closure $hasStaticIcon = false;

    /**
     * `$withParagraph` puts the plain paragraph in front of the levels, which turns the
     * dropdown into a complete choice: every block the caret can sit in is listed, so the
     * trigger always has something to show and there is a way back out of a heading
     * without hunting for a toggle.
     *
     * @param  array<int, int>  $levels
     */
    public static function headings(array $levels, bool $withParagraph = true, ?string $label = null): static
    {
        $buttons = array_values(array_map(
            static fn (int $level): string => "h{$level}",
            $levels,
        ));

        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.headings'),
            $withParagraph ? ['paragraph', ...$buttons] : $buttons,
        )
            ->icon(Icons::get('headings'))
            ->textualButtons();
    }

    /**
     * The trigger deliberately carries no icon of its own: `ToolbarButtonGroup` then falls
     * back to the first option's icon and swaps it for whichever option is active, so the
     * button always shows the alignment the caret is sitting in.
     *
     * @param  array<int, string>  $alignments
     */
    public static function alignment(array $alignments, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.alignment'),
            array_values($alignments),
        )
            ->textualButtons();
    }

    /**
     * @param  array<int, string>  $types
     */
    public static function lists(array $types, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.lists'),
            array_values($types),
        )
            ->icon(Icons::get('lists'))
            ->textualButtons();
    }

    /**
     * The callout dropdown. Not a static trigger: the icon becomes whichever kind the
     * caret is sitting in, so the bar says which box you are inside without being opened.
     * The family's own icon is what it rests at, which is a sign for the whole menu rather
     * than for whichever variant a project happened to list first.
     *
     * @param  array<int, string>  $variants
     */
    public static function callouts(array $variants, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.callouts'),
            array_values(array_map(Callouts::toolName(...), $variants)),
        )
            ->icon(Icons::get('callouts'))
            ->textualButtons();
    }

    /**
     * The language dropdown, with the way back out at the top of it.
     *
     * A static trigger: every entry carries the same globe, so there is nothing to swap it
     * for, and the icon is the only thing on a button with no room for a label. Which
     * language is on is shown where it can be - the entry is lit inside the menu, and the
     * trigger lights up while the caret sits in a marked passage.
     *
     * @param  array<int, array{code: string, label: string}>  $languages
     */
    public static function languages(array $languages, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.language'),
            [
                Languages::CLEAR,
                ...array_map(
                    static fn (array $language): string => Languages::toolName($language['code']),
                    array_values($languages),
                ),
            ],
        )
            ->icon(Icons::get('language'))
            ->staticIcon()
            ->textualButtons();
    }

    /**
     * The spacing dropdown. Its trigger keeps its own icon: the options are numbers, so
     * there is nothing to swap it for, and the icon is the only thing that says what the
     * numbers are about.
     *
     * @param  array<int, mixed>  $values
     */
    public static function lineHeight(array $values, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.line_height.label'),
            array_values(array_filter(array_map(
                static fn (mixed $value): ?string => LineHeight::toolName($value),
                $values,
            ))),
        )
            ->icon(Icons::get('line_height'))
            ->staticIcon()
            ->textualButtons();
    }

    /**
     * The overflow menu: the tools a toolbar would rather not spend a button on. It is
     * the one dropdown whose trigger stays put - the three dots are the way back to what
     * is inside, so they must not be traded for the icon of whatever the caret is in -
     * and it is textual, because a grid of icons for "remove formatting" and "details"
     * would need a tooltip on every one of them.
     *
     * @param  array<int, string>  $tools
     */
    public static function more(array $tools, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.more'),
            array_values($tools),
        )
            ->icon(Icons::get('more'))
            ->staticIcon()
            ->textualButtons();
    }

    /**
     * The other overflow: what a field does rather than what it writes.
     *
     * Named rather than a second set of three dots, and that is the whole reason it can
     * exist at all. Two menus both called "More" on one bar are two doors with the same
     * sign and different rooms behind them; a menu called Tools is a different kind of
     * thing, and a reader has to be told which once rather than guess every time.
     *
     * @param  array<int, string>  $tools
     */
    public static function tools(array $tools, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.tools_menu'),
            array_values($tools),
        )
            ->icon(Icons::get('tools_menu'))
            ->staticIcon()
            ->textualButtons();
    }

    /**
     * The parent's markup, taught to open upwards when there is no room below it.
     *
     * Every menu in this package hangs off its trigger with `position: absolute`, and
     * `.fi-fo-rich-editor-content` scrolls its own overflow - so a menu opening low in the
     * editor is cut off by the editor itself. The bar over a selection makes that the
     * normal case rather than the edge case, since it hangs below the text it belongs to.
     * Raising `z-index` does nothing about it: the menu is clipped by geometry, and paint
     * order has no say.
     *
     * The package's own dropdowns - the colour pickers, the font size stepper, the style
     * picker, the image and list panels - each build their own markup and drop
     * `OpensAwayFromTheEdge` into it. This one does not build its markup; Filament does.
     * So the three attributes the trait needs are threaded into what the parent rendered
     * rather than the whole method being reimplemented, which would mean owning a copy of
     * upstream's markup and watching it rot.
     *
     * Nothing is guessed: each anchor has to appear exactly once or that injection is
     * skipped, and `ToolbarDropdownTest` fails loudly if upstream ever renames one - which
     * is the trade for not owning the markup.
     */
    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        return $html === '' ? $html : $this->openingAwayFromTheEdge($html);
    }

    /**
     * The anchors in the parent's markup, and what each one becomes.
     *
     * @return array<string, string>
     */
    protected function edgeAwareAttributes(): array
    {
        return [
            // The state the trait needs, spliced in beside the parent's own.
            'x-data="{ open: false, ' => 'x-data="{ open: false, '.e($this->menuPositioning()).' ',
            // The trigger is measured from, and measures once it has been opened. The
            // entity rather than a bare `&&`: this is an attribute value the parent already
            // escaped, and it stays that way.
            'x-on:click="open = !open"' => 'x-ref="trigger" x-on:click="open = !open; open &amp;&amp; positionMenu()"',
            // And the menu is what gets measured and turned over. `menuUpClass` is the
            // trait's own Alpine property, the same binding every other dropdown in this
            // package uses - not the constant behind it, so the two cannot drift.
            'class="fi-fo-rich-editor-dropdown-tool-menu"' => 'x-ref="menu" x-bind:class="{ [menuUpClass]: dropUp }" class="fi-fo-rich-editor-dropdown-tool-menu"',
        ];
    }

    protected function openingAwayFromTheEdge(string $html): string
    {
        foreach ($this->edgeAwareAttributes() as $anchor => $replacement) {
            if (substr_count($html, $anchor) !== 1) {
                continue;
            }

            $html = str_replace($anchor, $replacement, $html);
        }

        return $html;
    }

    /**
     * Keeps the configured icon on the trigger instead of swapping it for the active
     * option, which is what the parent does by default.
     */
    public function staticIcon(bool|Closure $condition = true): static
    {
        $this->hasStaticIcon = $condition;

        return $this;
    }

    public function hasStaticIcon(): bool
    {
        return (bool) $this->evaluate($this->hasStaticIcon);
    }

    /**
     * The parent builds an effect that walks the options and hands the trigger the icon of
     * whichever one is active - the behaviour the alignment dropdown is built on. A static
     * trigger simply assigns its own icon; the `fi-active` binding is separate, so the
     * button still lights up while one of its tools is on.
     *
     * @param  array<RichEditorTool>  $resolvedButtons
     */
    protected function buildTriggerEffect(array $resolvedButtons, string $defaultContent): string
    {
        if (! $this->hasStaticIcon()) {
            return parent::buildTriggerEffect($resolvedButtons, $defaultContent);
        }

        return 'triggerContent = '.Js::from($defaultContent)->toHtml();
    }
}
