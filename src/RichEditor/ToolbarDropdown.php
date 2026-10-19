<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextCasePlugin;
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
     * Sentence case, lower case, UPPER CASE. Textual for the same reason the spacing dropdown
     * is: the options are read rather than aimed at. Each one still carries its drawing -
     * Lucide's Aa, AB and ab - because that is the rare set where an icon says the whole
     * thing, and the trigger keeps the mixed one as the sign for the family.
     */
    public static function textCase(?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.text_case.label'),
            array_values(array_filter(array_map(
                TextCasePlugin::toolName(...),
                TextCasePlugin::MODES,
            ))),
        )
            ->icon(Icons::get('text_case'))
            ->staticIcon()
            ->textualButtons();
    }

    /**
     * The date and time formats this field offers. Textual, and with a trigger that keeps
     * its own icon, for the reason the spacing dropdown does: the options are read rather
     * than aimed at, and there is nothing among them to swap the trigger for.
     *
     * @param  array<int, string>  $keys
     */
    public static function dateTime(array $keys, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.date_time.label'),
            array_values(array_filter(array_map(
                DateTimeFormats::toolName(...),
                $keys,
            ))),
        )
            ->icon(Icons::get('date_time'))
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
     * Nothing is guessed: every anchor has to appear exactly once, or nothing is threaded in
     * at all. Half an injection is worse than none - a menu bound to `menuUpClass` and
     * `dropUp` with nothing declaring them is an Alpine error on every page - and that is
     * exactly what Filament 5.10 would have got: its dropdown is a component of its own,
     * `filamentDropdown()`, floated and flipped away from the edge by Filament, and its markup
     * carries the menu's class and none of the rest. There the parent's markup is left as it
     * is, because the job is already done. `ToolbarDropdownTest` holds both generations.
     */
    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        return $html === '' ? $html : $this->takingTheOneTriggerAtItsWord($this->openingAwayFromTheEdge($html));
    }

    /**
     * Filament 5.10's dropdown, told that the one trigger it has is its trigger.
     *
     * `filamentDropdown()` hangs the menu and its ARIA on the first trigger that is visible,
     * and finds it with `getComputedStyle()` and `getClientRects()` - when it starts, and
     * again each time the panel's attributes change. Every such read makes the browser work
     * out all the styles that changed since the last one, and under Filament's `:has()` rules
     * on the repeater that is the whole page: measured at 28 ms a read, seven dropdowns an
     * editor, 4.3 s of a page of twenty editors before its first frame - where stock, with no
     * dropdown in its toolbar, pays nothing.
     *
     * A dropdown in this toolbar has a single trigger, and upstream takes the first trigger
     * when none of them is visible, so with one the measuring cannot change the answer. With
     * more than one, or a trigger that is not the one it already had, the measured way is
     * taken as before - kept from a dropdown of its own, so this calls upstream's code rather
     * than a copy of it.
     *
     * Threaded in like the anchors above: exactly one, or the markup is left as it came. A
     * Filament before 5.10 has no `filamentDropdown()` in it, and needs none of this.
     */
    protected function takingTheOneTriggerAtItsWord(string $html): string
    {
        $anchor = e('{ ...filamentDropdown(), ');

        if (substr_count($html, $anchor) !== 1) {
            return $html;
        }

        return str_replace($anchor, $anchor.e(
            'arteMeasuredTrigger: filamentDropdown().getTrigger, '
            .'getTrigger() { '
            .'const triggers = this.getTriggers(); '
            .'return ((triggers.length === 1) && ((this.activeTrigger ?? triggers[0]) === triggers[0])) '
            .'? (this.activeTrigger = triggers[0]) '
            .': this.arteMeasuredTrigger(); '
            .'}, ',
        ), $html);
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
        $attributes = $this->edgeAwareAttributes();

        foreach (array_keys($attributes) as $anchor) {
            if (substr_count($html, $anchor) !== 1) {
                return $html;
            }
        }

        return str_replace(array_keys($attributes), array_values($attributes), $html);
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
     * The effect that hands the trigger the icon of whichever option is active - the
     * behaviour the alignment dropdown is built on. A static trigger simply assigns its own
     * icon; the `fi-active` binding is separate, so the button still lights up while one of
     * its tools is on.
     *
     * The parent writes every option's icon into the effect as a string, a second copy of
     * what the menu right below the trigger already draws: seven dropdowns and 17 KB per
     * editor. This one only works out which option is active - the first one on, checked in
     * the order the menu lists them, as the parent does - and copies that option's icon out
     * of the menu. The default content stays as the one string, for nothing being active and
     * for an option without an icon, which the parent would have shown as an empty trigger.
     *
     * The options are found by the class the parent gives them rather than by their `role`:
     * a tool's own attributes win over the parent's, so a tool may carry a role of its own -
     * Filament 5.10 gives a toggle `menuitemcheckbox` - and every option after it would then
     * be counted one place early. And anywhere inside the dropdown rather than as its direct
     * child, because 5.10 puts the menu inside a floating panel of its own.
     *
     * @param  array<RichEditorTool>  $resolvedButtons
     */
    protected function buildTriggerEffect(array $resolvedButtons, string $defaultContent): string
    {
        if ($this->hasStaticIcon()) {
            return 'triggerContent = '.Js::from($defaultContent)->toHtml();
        }

        $checks = [];

        foreach (array_values($resolvedButtons) as $index => $button) {
            $checks[] = 'if ('.$this->buildActiveExpression($button).") return {$index};";
        }

        $active = '(() => { '.implode(' ', $checks).' return -1; })()';

        return 'triggerContent = $el.querySelectorAll(\'.fi-fo-rich-editor-dropdown-tool-menu > .fi-fo-rich-editor-dropdown-tool-option\')['.$active.']'
            .'?.querySelector(\':scope > .fi-icon\')?.outerHTML ?? '.Js::from($defaultContent)->toHtml();
    }
}
