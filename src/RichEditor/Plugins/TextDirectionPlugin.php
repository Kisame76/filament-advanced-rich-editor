<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\TextDirection;
use Tiptap\Core\Extension;

/**
 * Left-to-right and right-to-left blocks, on both sides of the editor.
 *
 * Two buttons rather than one: a toggle would have to guess what "off" means in a document
 * that mixes both, while two named directions each say the one thing they do - and picking
 * the direction a block already has takes it back off.
 */
class TextDirectionPlugin implements RichContentPlugin
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
            app(TextDirection::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/text-direction', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('ltr')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.direction.ltr'))
                ->jsHandler("\$getEditor()?.chain().focus().toggleBlockDirection('ltr').run()")
                ->activeJsExpression("\$getEditor()?.isActive({ dir: 'ltr' })")
                ->icon(Icons::get('direction_ltr')),
            RichEditorTool::make('rtl')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.direction.rtl'))
                ->jsHandler("\$getEditor()?.chain().focus().toggleBlockDirection('rtl').run()")
                ->activeJsExpression("\$getEditor()?.isActive({ dir: 'rtl' })")
                ->icon(Icons::get('direction_rtl')),
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
