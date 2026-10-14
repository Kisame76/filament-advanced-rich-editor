<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
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
            $this->view('invisibleCharacters', 'characters', 'invisible_characters', 'toggleInvisibleCharacters'),
            $this->view('blockOutlines', 'blocks', 'block_outlines', 'toggleBlockOutlines'),
        ];
    }

    /**
     * One of the two views as a toggle.
     *
     * Strict against `true`, so a field whose script never arrived - where `$getEditor()` or
     * the storage is still undefined - cannot render a button that says a view is on.
     *
     * `aria-pressed` is written here as well as being asked for by `toggle()`, because these
     * ship in the tools menu, and a tool drawn as a dropdown option gets the active class
     * from Filament and no `aria-pressed` at all. It names `editorUpdatedAt`: Alpine
     * re-evaluates a binding when something it read changes, and the editor's storage is a
     * plain object it does not watch. On the bar Filament writes its own and this stands
     * down, because `ComponentAttributeBag::merge()` treats these as defaults. The format
     * brush measured both halves of that; see `FormatBrushPlugin`.
     */
    protected function view(string $name, string $view, string $key, string $command): RichEditorTool
    {
        $isOn = "\$getEditor()?.storage?.arteInvisibles?.{$view} === true";

        return RichEditorTool::make($name)
            ->label(__("filament-advanced-rich-editor::advanced-rich-editor.tools.{$key}.label"))
            ->jsHandler("\$getEditor()?.chain().focus().{$command}().run()")
            ->icon(Icons::get($key))
            ->toggle()
            ->activeJsExpression($isOn)
            ->extraAttributes([
                'x-bind:aria-pressed' => "editorUpdatedAt && {$isOn} ? 'true' : 'false'",
            ], merge: true);
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
