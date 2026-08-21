<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\SourceCodeAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * The source view button and the modal behind it.
 *
 * Nothing is registered on either side of the schema: the source view reads and writes the
 * document the editor already has, so it needs no node, no mark and no script of its own.
 */
class SourceCodePlugin implements RichContentPlugin
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
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('sourceCode')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.label'))
                // Read out of the browser rather than off the server's copy of the state:
                // the last keystrokes may not have been synced yet, and they belong in the
                // source view too.
                ->action(arguments: '{ html: $getEditor().getHTML() }')
                ->icon(Icons::get('source_code')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            SourceCodeAction::make(),
        ];
    }
}
