<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * The editor half of the widened link, and of the heading anchor.
 *
 * Both are attributes on nodes Filament already owns, and both are dropped by the parser
 * unless something declares them - so each needs a half on either side of the round trip.
 *
 * No PHP extension is registered here. The PHP half of the link is a *replacement* for
 * Filament's mark rather than an addition to it, which the renderer does by name: two
 * extensions called `link` are both applied, and a link inside a link is not markup
 * anybody wanted. The heading anchor is declared by the renderer for the same reason it is
 * declared unconditionally - stored content should keep an anchor it already carries.
 *
 * The tool and the dialog live on the field, because both replace ones Filament registers.
 */
class LinkPlugin implements RichContentPlugin
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
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/link-attributes', 'kisame76/filament-advanced-rich-editor'),
            FilamentAsset::getScriptSrc('advanced-rich-editor/anchor', 'kisame76/filament-advanced-rich-editor'),
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
