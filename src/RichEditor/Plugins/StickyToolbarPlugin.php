<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Tells a pinned toolbar from one resting at the top of its field.
 *
 * Nothing is stored and nothing is drawn: the script watches the bar with an
 * IntersectionObserver and gives it `fi-arte-stuck` while it is held under the page header,
 * and the stylesheet squares the bar's top corners then - the round ones belong to the top
 * of the field, which has scrolled away by that point. See `resources/js/sticky-toolbar.js`.
 *
 * Registered only on a field whose bar is sticky, which a capped field never is.
 */
class StickyToolbarPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/sticky-toolbar', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
