<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\Concerns;

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;

/**
 * A button that stays pressed while what it switches is on - the invisible characters, the
 * block outlines and the focus mode, which are ways of looking at the document, and the lock,
 * which is a state the selection is in.
 */
trait BuildsToggles
{
    /**
     * Strict against `true` in `$isOn`, so a field whose script never arrived - where
     * `$getEditor()` or the storage is still undefined - cannot render a button that says something
     * is on.
     *
     * `aria-pressed` is written here as well as being asked for by `toggle()`, because these
     * ship in the tools menu, and a tool drawn as a dropdown option gets the active class
     * from Filament and no `aria-pressed` at all. It names `editorUpdatedAt`: Alpine
     * re-evaluates a binding when something it read changes, and the editor's storage is a
     * plain object it does not watch. On the bar Filament writes its own and this stands
     * down, because `ComponentAttributeBag::merge()` treats these as defaults. The format
     * brush measured both halves of that; see `FormatBrushPlugin`.
     *
     * @param  string  $key  the translation key under `tools` and the icon's name
     */
    protected function toggleTool(string $name, string $key, string $command, string $isOn): RichEditorTool
    {
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
}
