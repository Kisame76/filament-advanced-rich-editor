<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Straight quotes becoming the ones the language uses, three dots becoming an ellipsis, and
 * two hyphens becoming the dash that language sets.
 *
 * No tool and no button: this is not something anybody reaches for, it is something that
 * happens while they type. And no PHP extension either, for the reason the characters picker
 * has none — a typographic quote is a character, so it travels through the sanitiser, the
 * save and `RichContentRenderer` like any letter. Switching it off later leaves every
 * quotation already written exactly as it is.
 *
 * Which characters to write comes from `Typography` and reaches the module on the element,
 * not through a tool: an input rule fires without anybody having clicked anything - which is
 * also why this ships off. Everything else here gives a field something it can do; this
 * changes what somebody typed, and it is in the document from then on.
 */
class TypographyPlugin implements RichContentPlugin
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/typography', 'kisame76/filament-advanced-rich-editor'),
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
