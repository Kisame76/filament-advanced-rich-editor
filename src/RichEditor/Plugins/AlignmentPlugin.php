<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * The keyboard shortcuts for aligning left and right.
 *
 * No tool and no PHP extension: the four alignment buttons are Filament's own and the
 * attribute they write is TipTap's, so there is nothing here to register, store or render.
 * What is here is a repair. TipTap's `TextAlign` binds `Mod+Shift+L` and `Mod+Shift+R` to
 * `left` and `right`, Filament configures the extension with `start` and `end`, and
 * `setTextAlign` refuses an alignment it was not configured with - so both keys did
 * nothing, and `Ctrl+Shift+R` fell through to the browser's hard reload with the draft
 * still in the field. See `resources/js/alignment.js`.
 *
 * Registered on every field rather than only on one that shows the alignment dropdown,
 * because the keys are bound by Filament's own build either way. A field that hides the
 * buttons still answers `Mod+Shift+E` for centring, and there is no reading of that under
 * which the other two should stay broken.
 */
class AlignmentPlugin implements RichContentPlugin
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/alignment', 'kisame76/filament-advanced-rich-editor'),
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
