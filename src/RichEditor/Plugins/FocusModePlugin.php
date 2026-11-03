<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\Concerns\BuildsToggles;
use Tiptap\Core\Extension;

/**
 * Writing with the rest of the document out of the way: every block but the one being written
 * in dimmed, and the line being written kept in the middle of the view.
 *
 * A view, like the invisible characters - a decoration and a class on the editor, which
 * ProseMirror draws and never serialises - so there is no PHP extension, and switching the
 * feature off later changes nothing that was ever written. See `focus-mode.js`.
 */
class FocusModePlugin implements RichContentPlugin
{
    use BuildsToggles;

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
            FilamentAsset::getScriptSrc('advanced-rich-editor/focus-mode', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            $this->toggleTool('focusMode', 'focus_mode', 'toggleFocusMode', '$getEditor()?.storage?.arteFocusMode?.on === true'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
