<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\StatisticsAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * The statistics button and the dialog behind it.
 *
 * Nothing on either side of the schema: the dialog reads the document that is already
 * there and says how long it is. Nothing is stored either way, so a field that switches
 * the tool off keeps every document that was ever measured with it.
 */
class StatisticsPlugin implements RichContentPlugin
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
            RichEditorTool::make('statistics')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.statistics.label'))
                ->action()
                ->icon(Icons::get('statistics')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            StatisticsAction::make(),
        ];
    }
}
