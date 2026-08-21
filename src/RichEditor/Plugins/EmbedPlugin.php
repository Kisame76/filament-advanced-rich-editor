<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\EmbedAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;
use Tiptap\Core\Extension;

/**
 * Video embeds, on both sides of the round trip.
 *
 * The renderer declares the node unconditionally - stored content should keep a video
 * whether or not this render was told about embeds - so what this adds is the editor half:
 * the script, the button and the dialog behind it.
 */
class EmbedPlugin implements RichContentPlugin
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
            app(Embed::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/embed', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('embed')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.label'))
                ->icon(Icons::get('embed'))
                ->action(arguments: '{ provider: $getEditor().getAttributes(\'embed\')?.provider, id: $getEditor().getAttributes(\'embed\')?.id, start: $getEditor().getAttributes(\'embed\')?.start, title: $getEditor().getAttributes(\'embed\')?.title, ratio: $getEditor().getAttributes(\'embed\')?.ratio }')
                ->activeKey('embed'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            EmbedAction::make(),
        ];
    }
}
