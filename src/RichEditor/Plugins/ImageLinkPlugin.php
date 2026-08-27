<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\ImageLinkAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;
use Tiptap\Core\Extension;

/**
 * Where a picture points.
 *
 * A tool and a dialog rather than a toggle, because the answer is an address somebody has to
 * type. The tool reads what is already there off the node under the selection and hands it
 * to the dialog, so opening it on a linked picture shows the link rather than an empty field.
 */
class ImageLinkPlugin implements RichContentPlugin
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
            app(ImageLink::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/image-link', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('imageLink')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.label'))
                ->icon(Icons::get('image_link'))
                // Read off the node under the selection rather than through
                // `getAttributes()`, the same way the placement reads it and for the reason
                // written there: focusing the editor collapses the node selection a click on
                // a picture produced, and once it is a caret the lookup comes back empty.
                ->action(arguments: '{'
                    .'href: $getEditor()?.state?.doc?.nodeAt($getEditor()?.state?.selection?.from)?.attrs?.href,'
                    .'newTab: $getEditor()?.state?.doc?.nodeAt($getEditor()?.state?.selection?.from)?.attrs?.hrefNewTab'
                    .'}')
                // Filament decides active by asking `editor.isActive(<tool name>)`, which
                // only ever recognises a node or a mark, and this is a global attribute on
                // the image node.
                ->activeJsExpression(
                    'editorUpdatedAt && !! $getEditor()?.state?.doc?.nodeAt($getEditor()?.state?.selection?.from)?.attrs?.href',
                ),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            ImageLinkAction::make(),
        ];
    }
}
