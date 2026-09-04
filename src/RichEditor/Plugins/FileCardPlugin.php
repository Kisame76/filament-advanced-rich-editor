<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\FileCard;
use Tiptap\Core\Extension;

/**
 * The editor half of the document card.
 *
 * The renderer declares the node whatever a field says - a document somebody attached is
 * one the page should keep showing as a card - so what this adds is only the script that
 * lets the editor draw one while it is being written.
 *
 * No tools and no actions, and that is the current shape of the feature rather than an
 * oversight: this iteration is about how an uploaded document is *drawn*. Where one comes
 * from is the media browser's question, and the browser is being rebuilt. A button opening
 * a dialog of its own would be a second door to answer beside it - the exact complaint the
 * media browser was built to settle - so the way in is the `setFile` command until the
 * browser is ready to offer one.
 */
class FileCardPlugin implements RichContentPlugin
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
        return [
            app(FileCard::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/file-card', 'kisame76/filament-advanced-rich-editor'),
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
