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
 * The brush that carries formatting from one passage to another.
 *
 * Word's format painter, TinyMCE's permanent pen. What it copies is how a passage looks,
 * never the passage - so nothing it does can be seen in a stored document that was not
 * already expressible by selecting the text and pressing the buttons by hand.
 *
 * No PHP extension and no mark, for the reason the case switcher has none: what the brush
 * puts down is marks the schema already declares, so there is nothing to parse, nothing for
 * the sanitiser to allow and nothing to teach the renderer. Switching the feature off later
 * leaves every passage already brushed exactly as it is.
 */
class FormatBrushPlugin implements RichContentPlugin
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/format-brush', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('formatBrush')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.format_brush.label'))
                ->jsHandler('$getEditor()?.chain().focus().cycleFormatBrush().run()')
                ->icon(Icons::get('format_brush'))
                // A toggle, so the pressed state reaches a screen reader as `aria-pressed`
                // rather than only as a colour.
                ->toggle()
                // Loose rather than strict, and the difference is the whole guard. Filament
                // wraps this as `editorUpdatedAt && (...)`, and `$getEditor()` is undefined
                // until the editor finishes loading - so `!== null` reads true on a field
                // whose script never arrived, and every brush button would render armed,
                // announcing `aria-pressed="true"` for a button that does nothing.
                // `!= null` is false for both null and undefined.
                ->activeJsExpression('$getEditor()?.storage?.arteFormatBrush?.picked != null')
                // Which of the two armed states it is, for the stylesheet and for anyone
                // reading the DOM. `x-bind:class` is not available: `RichEditorTool` writes
                // its own and `ComponentAttributeBag::merge()` treats these as defaults.
                //
                // `editorUpdatedAt` is named, and it is not decoration. Alpine re-evaluates
                // a binding when something it *read* changes, and the editor's storage is a
                // plain object it does not watch - so without a reference to the reactive
                // property this binding is evaluated once, at first paint, and then never
                // again. Measured in a running panel: the button lit and unlit correctly
                // while this attribute stayed empty through all three states, because
                // Filament wraps its own expression in `editorUpdatedAt && (...)` and has
                // no reason to wrap anybody else's.
                //
                // `aria-pressed` is written here as well as being asked for by `toggle()`,
                // and that is not a belt-and-braces: measured on the rendered button, a
                // tool placed in the overflow menu goes through the dropdown-option path,
                // which draws the active class and no `aria-pressed` at all. Since the
                // brush ships with no button of its own, the menu is where it is put - so
                // without this line the state a sighted user sees is the one a screen
                // reader never hears. On the bar Filament writes its own and this stands
                // down, because `ComponentAttributeBag::merge()` treats these as defaults.
                ->extraAttributes([
                    'x-bind:data-arte-brush' => "editorUpdatedAt ? (\$getEditor()?.storage?.arteFormatBrush?.mode ?? '') : ''",
                    'x-bind:aria-pressed' => "editorUpdatedAt && \$getEditor()?.storage?.arteFormatBrush?.picked != null ? 'true' : 'false'",
                ], merge: true),
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
