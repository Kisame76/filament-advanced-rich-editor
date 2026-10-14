<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\DescriptionDetails;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\DescriptionList;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\DescriptionTerm;
use Tiptap\Core\Extension;

/**
 * The definition list: `<dl>`, `<dt>` and `<dd>`, semantically right for a glossary.
 *
 * All three elements are ones the sanitiser already keeps, so this is the nodes and the
 * button and nothing else. The renderer declares the nodes as well, unconditionally: a list
 * stored by a field that had them still renders on a page that was never told so.
 */
class DescriptionListPlugin implements RichContentPlugin
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
            app(DescriptionList::class),
            app(DescriptionTerm::class),
            app(DescriptionDetails::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        // The second argument is required: `AssetManager::getScriptSrc()` falls back to the
        // `app` package and would throw for assets this package registered under its own
        // name. One script, because the list brings its two parts along.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/description-list', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('descriptionList')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.description_list'))
                ->jsHandler('$getEditor()?.chain().focus().toggleDescriptionList().run()')
                ->icon(Icons::get('description_list')),
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
