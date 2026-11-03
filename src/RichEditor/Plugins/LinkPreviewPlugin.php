<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkPreviewAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\LinkPreview;
use Tiptap\Core\Extension;

/**
 * Link cards, on the editor's side: the script, the tool and its dialog, and the bar over a
 * selected card. The renderer declares the node whatever a field offers, so a card written while
 * the tool was on is still a card on the page after it is switched off.
 */
class LinkPreviewPlugin implements RichContentPlugin
{
    /** The selected card, as the dialog reads it back in. */
    protected const SELECTED = '(({ href, title, description, site, icon }) => ({ href, title, description, site, icon }))($getEditor().getAttributes(\'linkPreview\') ?? {})';

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
            app(LinkPreview::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/link-preview', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.link_preview.';

        return [
            RichEditorTool::make('linkPreview')
                ->label(__($key.'label'))
                ->icon(Icons::get('link_preview'))
                ->action(arguments: static::SELECTED)
                ->activeKey('linkPreview'),

            RichEditorTool::make('linkPreviewEdit')
                ->label(__($key.'edit'))
                ->icon(Icons::get('link_preview_edit'))
                ->action('linkPreview', arguments: static::SELECTED)
                ->activeStyling(false),

            RichEditorTool::make('linkPreviewDelete')
                ->label(__($key.'delete'))
                ->jsHandler('$getEditor()?.chain().focus().deleteSelection().run()')
                ->activeStyling(false)
                ->icon(Icons::get('link_preview_delete')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            LinkPreviewAction::make(),
        ];
    }
}
