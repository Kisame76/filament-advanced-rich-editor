<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Cleaning what arrives from the clipboard.
 *
 * No PHP extension, no mark and no tool: by the time a paste reaches the server it is an
 * ordinary document, and what this decides was decided in the browser, before ProseMirror
 * ever parsed the markup. So there is nothing here to store, nothing to sanitise and
 * nothing to render on the way back out - the same shape as the emoji picker and the find
 * bar, and for the same reason.
 *
 * What does cross over is the one thing the browser cannot know: which style properties a
 * project wants a paste to keep. Everything else about the cleaning is a fact about Word
 * and Google Docs rather than a decision, and facts belong in the file that has to know
 * them.
 */
class PasteCleanupPlugin implements RichContentPlugin
{
    /**
     * The style properties a cleaned paste keeps where a project named none.
     *
     * Written here rather than in the field, so the config file's own copy is a publishable
     * statement of the default and not a second decision that can drift from it. Both are
     * structure wearing a style attribute rather than typography: the alignment is the one
     * thing in Word's `style` whose absence a reader would notice, and the shape of an embed
     * is what the embed is.
     *
     * @var array<int, string>
     */
    public const DEFAULT_KEEP_STYLES = ['text-align', 'aspect-ratio'];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The settings the extension reads off the editor element.
     *
     * A list rather than a flag, because "keep nothing" and "keep the alignment" are both
     * reasonable and only a project knows which of the two it is.
     *
     * @param  array<int, string>  $keepStyles
     * @return array<string, mixed>
     */
    public static function getSettings(array $keepStyles): array
    {
        return [
            'keepStyles' => array_values($keepStyles),
        ];
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/paste-cleanup', 'kisame76/filament-advanced-rich-editor'),
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
