<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Illuminate\Support\Js;
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
