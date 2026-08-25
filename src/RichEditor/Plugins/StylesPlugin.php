<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\StyleClass;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\BlockStyle;
use Tiptap\Core\Extension;

/**
 * The project's own named styles, on both sides of the editor.
 *
 * No tools: the styles are driven by the `styles` token's dropdown, which calls the two
 * commands the JavaScript halves add. A list of names is what a dropdown can show a
 * checkmark in, and one button per style would fill a toolbar with somebody's design
 * system.
 *
 * Built with the field's own list rather than reading the configuration itself, because two
 * fields on one page may offer different styles - and the schema a save is parsed through
 * has to be the one the toolbar offered from, or a style would be applied and then dropped
 * by the very parse that stores it.
 */
class StylesPlugin implements RichContentPlugin
{
    /**
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>  $styles
     */
    final public function __construct(
        protected array $styles = [],
    ) {}

    /**
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>  $styles
     */
    public static function make(array $styles = []): static
    {
        return new static($styles);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(BlockStyle::class, ['options' => ['styles' => Styles::ofScope($this->styles, 'block')]]),
            app(StyleClass::class, ['options' => ['styles' => Styles::ofScope($this->styles, 'inline')]]),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/block-style', 'kisame76/filament-advanced-rich-editor'),
            FilamentAsset::getScriptSrc('advanced-rich-editor/style-class', 'kisame76/filament-advanced-rich-editor'),
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
