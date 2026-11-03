<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\Concerns\BuildsToggles;
use Tiptap\Core\Extension;

/**
 * The two views that show what a document holds and does not draw.
 *
 * Word's ¶ button and TinyMCE's visual characters on one side: the spaces that may not
 * break, the soft hyphen, the characters with no width, every line break and the end of
 * every paragraph. CKEditor's and TinyMCE's visual blocks on the other: a dashed outline
 * round every block, with the element it is written as. Two buttons rather than one,
 * because the second is loud - three labels at the start of every list item - and the
 * first is something a person editing all day leaves on.
 *
 * No PHP extension, for the reason the search has none: both views are decorations and a
 * class on the editor, which ProseMirror draws and never serialises. Nothing stored changes
 * with them on, and switching the feature off later changes nothing that was ever written.
 */
class InvisiblesPlugin implements RichContentPlugin
{
    use BuildsToggles;

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
        // The second argument is required: `AssetManager::getScriptSrc()` falls back to the
        // `app` package and would throw for assets this package registered under its own
        // name.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/invisibles', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            $this->toggleTool('invisibleCharacters', 'invisible_characters', 'toggleInvisibleCharacters', '$getEditor()?.storage?.arteInvisibles?.characters === true'),
            $this->toggleTool('blockOutlines', 'block_outlines', 'toggleBlockOutlines', '$getEditor()?.storage?.arteInvisibles?.blocks === true'),
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
