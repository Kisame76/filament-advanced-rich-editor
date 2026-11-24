<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\PageBreak;
use Tiptap\Core\Extension;

/**
 * The page break: one node and the tool that inserts it. Where it is offered - the "More"
 * dropdown and the slash menu - is the configuration's, as for every other tool.
 */
class PageBreakPlugin implements RichContentPlugin
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
        return [app(PageBreak::class)];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        // The second argument is required: `AssetManager::getScriptSrc()` falls back to the
        // `app` package and would throw for assets this package registered under its own
        // name.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/page-break', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('pageBreak')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.page_break'))
                ->jsHandler('$getEditor()?.chain().focus().setPageBreak().run()')
                ->icon(Icons::get('page_break')),
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
