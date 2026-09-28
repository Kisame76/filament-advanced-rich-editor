<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\PreviewAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * The preview button and the frame behind it.
 *
 * Nothing on either side of the schema. The dialog reads the document that is already there
 * and draws it; nothing is stored either way, so a field that switches the tool off keeps
 * every document that was ever previewed with it.
 */
class PreviewPlugin implements RichContentPlugin
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
     * No JavaScript of its own, and that is a decision rather than an oversight. The obvious
     * module would measure the frame's content and size the frame to it; a fixed box that
     * scrolls inside itself answers the same question, and a preview that grows to the height
     * of a long article inside a dialog that is already scrolling gives a reader two
     * scrollbars for one document.
     *
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('preview')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.preview.label'))
                // Read out of the browser rather than off the server's copy of the state, the
                // same way the source view reads it and for the same reason: the last
                // keystrokes may not have been synced yet, and they are usually the ones
                // somebody opened a preview to look at.
                ->action(arguments: '{ html: $getEditor().getHTML() }')
                ->icon(Icons::get('preview')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            PreviewAction::make(),
        ];
    }
}
