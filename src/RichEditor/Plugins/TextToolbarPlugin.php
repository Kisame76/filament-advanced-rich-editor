<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Who decides when the bar over selected text is on screen.
 *
 * No tools, no marks, no schema: the buttons are assembled in `FloatsToolbars` and the bar
 * itself is Filament's. What this registers is one rule, and it replaces a rule Filament
 * hard-codes for this one key - `isFocused && isActive('paragraph') && ! selection.empty`.
 *
 * Two holes come out of the middle clause. In a heading the bar never appears, which is a
 * gap on an ordinary field and a hole on one with no toolbar at all, since `->notion()`
 * leaves the link, the colours and the styles nowhere else to be reached from. And a
 * picture selected inside a paragraph satisfies the outer two clauses, so the bar for
 * formatting words was drawn over the bar for laying the picture out - which the stylesheet
 * used to hide by hand.
 *
 * Registered only where the field actually draws the bar. A rule for a bar that is not
 * there would be a message addressed to nobody: harmless, since the bubble menu ignores a
 * message to a key it has no plugin for, but a script loaded for nothing all the same.
 */
class TextToolbarPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Nothing. Which node a mark may sit in is the schema's business and has not changed;
     * what changed is when a bar is shown, and that only exists in the browser.
     *
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/text-toolbar', 'kisame76/filament-advanced-rich-editor'),
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
