<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\FileCard;
use Tiptap\Core\Extension;

/**
 * The editor half of the document card.
 *
 * The renderer declares the node whatever a field says - a document somebody attached is
 * one the page should keep showing as a card - so what this adds is the script that lets the
 * editor draw one while it is being written, and the three ways to reach one.
 *
 * All three go through the media browser rather than a dialog of their own. A second door for
 * documents beside the browser would be the exact complaint the browser was built to settle,
 * so the card waited for the browser to take documents, and now the browser is the way in:
 * `file` opens it on the documents, and the bar over a selected card opens it again to swap
 * the file for another one.
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
        return [
            // The browser, opened on the documents. Registered and unplaced, the way the
            // film and sound buttons are: the way in is the slash menu, and naming `file`
            // on a bar or in `more` gives it a button.
            RichEditorTool::make('file')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.file.label'))
                ->icon(Icons::get('media_file'))
                ->action(
                    static fn (RichEditorTool $tool): string => MediaLibraryAction::nameFor($tool),
                    arguments: "{ kind: 'file' }",
                )
                ->activeKey('file'),

            // The browser again, from the bar over a selected card, with that card's file
            // already picked. Choosing another one replaces the card rather than adding a
            // second beside it - `replace` is what tells the dialog to make sure of that.
            RichEditorTool::make('fileReplace')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.file.replace'))
                ->icon(Icons::get('file_replace'))
                ->action(
                    static fn (RichEditorTool $tool): string => MediaLibraryAction::nameFor($tool),
                    arguments: "{ kind: 'file', replace: 'file', id: \$getEditor().getAttributes('file')?.id }",
                )
                ->activeStyling(false),

            RichEditorTool::make('fileDelete')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.file.delete'))
                ->jsHandler('$getEditor()?.chain().focus().deleteSelection().run()')
                ->activeStyling(false)
                ->icon(Icons::get('file_delete')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        // None. The dialog behind all three is the media browser, which the field registers
        // for itself - see `AdvancedRichEditor::getDefaultActions()`.
        return [];
    }
}
