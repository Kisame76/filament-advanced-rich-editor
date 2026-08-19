<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Icons\Heroicon;

/**
 * A named, reusable dropdown for the rich editor toolbar.
 *
 * The parent already renders the trigger, the menu and the swapping of the
 * trigger icon to the active child, so this class only adds the factories the
 * package's toolbar tokens are built from.
 */
class ToolbarDropdown extends ToolbarButtonGroup
{
    /**
     * @param  array<int, int>  $levels
     */
    public static function headings(array $levels, ?string $label = null): static
    {
        return static::make(
            $label ?? __('filament-advanced-rich-editor::advanced-rich-editor.tools.headings'),
            array_values(array_map(
                static fn (int $level): string => "h{$level}",
                $levels,
            )),
        )
            ->icon('fi-o-heading')
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
            ->icon(Heroicon::ListBullet)
            ->textualButtons();
    }
}
