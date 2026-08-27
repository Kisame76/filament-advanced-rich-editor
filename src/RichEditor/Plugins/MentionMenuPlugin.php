<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * The mention menu with a row worth reading.
 *
 * No PHP extension: the node this inserts is the node Filament already declares on both
 * sides, and `RichEditor\TipTapExtensions\Mention` is what widens it for the rendered page.
 * What ships here is the editor half - a JavaScript extension carrying the same name as
 * Filament's, which is how Filament lets one be replaced.
 *
 * The replacement is the whole point rather than a side effect. Upstream draws a row as one
 * span of text, so an avatar or a second line has nowhere to go, and the suggestion is built
 * into ProseMirror plugins as the editor is constructed - there is no seam to reach in
 * through afterwards. What the menu offers is handed to the script through the editor
 * element, which is the one channel a TipTap extension has to the field it belongs to.
 */
class MentionMenuPlugin implements RichContentPlugin
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/mention', 'kisame76/filament-advanced-rich-editor'),
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
