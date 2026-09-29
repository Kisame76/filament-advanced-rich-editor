<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * The editor half of a file replaced in the media browser.
 *
 * The browser keeps the id and swaps the file behind it, so every document shows the new
 * file the next time it is drawn. The one open underneath the dialog is the exception: its
 * nodes still hold the address they were loaded with - an address a media library has just
 * renamed away. This teaches the editor to listen for the browser saying so and to point its
 * nodes at the new file. See `resources/js/media-replace.js`.
 *
 * Nothing but a script: no node, no button, no dialog.
 */
class MediaReplacePlugin implements RichContentPlugin
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/media-replace', 'kisame76/filament-advanced-rich-editor'),
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
