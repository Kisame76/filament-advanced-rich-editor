<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageRotate;
use Tiptap\Core\Extension;

/**
 * Everything the editor adds around an image: the size readout and the aspect ratio switch
 * during a drag, the rotation attribute, and the visibility rule that lets the image
 * toolbar hold inputs.
 *
 * The size itself still travels as Filament's own `width` and `height` attributes; only the
 * rotation needs a counterpart on the PHP side, since nothing there knows about it.
 */
class ImageResizePlugin implements RichContentPlugin
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
            app(ImageRotate::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/image-resize', 'kisame76/filament-advanced-rich-editor'),
            FilamentAsset::getScriptSrc('advanced-rich-editor/image-rotate', 'kisame76/filament-advanced-rich-editor'),
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
