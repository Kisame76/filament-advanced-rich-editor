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
 * Changing the case of the selection: Sentence case, lower case, UPPER CASE.
 *
 * No PHP extension and no mark, for the reason the special characters picker has none: what
 * comes out is ordinary text, so there is nothing to parse, nothing for the sanitiser to
 * allow and nothing to teach the renderer on the way back out. Switching the feature off
 * later leaves every word already changed exactly as it is.
 *
 * One tool per mode rather than one tool that takes an argument. That is what lets the three
 * appear in a dropdown, in the overflow menu and in the slash menu without any of them
 * knowing about the others - a dropdown renders button names and nothing else - and it is
 * the same shape `Callouts` and `LineHeight` use for the same reason.
 *
 * Shipped with no button at all - not on the bar, not in the overflow menu. Most documents
 * never change the case of anything, and `more` is finite. What ships is `Shift+F3` and the
 * row naming it in the help dialog, plus the means to place the buttons: the three names in
 * `more`, or the `textCase` token on the bar or in the selection bubble. Registering the
 * tools without showing them is what makes both of those possible.
 */
class TextCasePlugin implements RichContentPlugin
{
    /**
     * The modes, in the order `Shift+F3` walks them. The JavaScript half holds the same list
     * in the same order, and `TextCaseTest` holds the two together.
     *
     * @var array<int, string>
     */
    public const MODES = ['sentence', 'lower', 'upper'];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The tool name for a mode, which is also the name a toolbar, a dropdown and the slash
     * menu address it by.
     */
    public static function toolName(string $mode): ?string
    {
        return in_array($mode, static::MODES, strict: true)
            ? 'textCase'.ucfirst($mode)
            : null;
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/text-case', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        // Aa, AB, ab. Lucide draws the three as the thing each one produces, which is what
        // lets them be told apart at a glance - and the labels are set in their own case
        // besides, so the pair says it twice rather than relying on either alone.
        return array_map(
            static fn (string $mode): RichEditorTool => RichEditorTool::make(static::toolName($mode))
                ->label(__("filament-advanced-rich-editor::advanced-rich-editor.tools.text_case.{$mode}"))
                // The command reports failure on an empty selection, which is what leaves a
                // key press to fall through rather than silently doing nothing.
                ->jsHandler("\$getEditor()?.chain().focus().setTextCase('{$mode}').run()")
                ->icon(Icons::get("text_case_{$mode}")),
            static::MODES,
        );
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
