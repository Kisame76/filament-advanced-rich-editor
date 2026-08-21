<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageCaption;
use Tiptap\Core\Extension;

/**
 * The caption an image carries.
 *
 * Registered on its own rather than with the resizing, because a caption is worth having in
 * a field where nothing may be dragged - and it is written into the schema, so a field that
 * stopped declaring it would drop every caption already written on the next save.
 *
 * The `<figure>` the caption becomes is built when the page is rendered; see
 * `ImageCaptions`. The field it is typed into is part of the image toolbar's text panel.
 */
class ImageCaptionPlugin implements RichContentPlugin
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
            app(ImageCaption::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/image-caption', 'kisame76/filament-advanced-rich-editor'),
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
