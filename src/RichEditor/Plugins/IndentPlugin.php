<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Indent;
use Tiptap\Core\Extension;

/**
 * Indenting a block, on both sides of the editor.
 *
 * Two buttons and no dropdown: an indent is a step rather than a value, so what a person
 * wants is one more or one less of it, and a list of eight depths would be a list of
 * numbers nobody thinks in. It is also why there is no `activeJsExpression` on either -
 * they are not states a block is in, they are moves.
 *
 * The step and the depth are the field's, so the plugin is constructed with them and the
 * PHP extension is given them: two fields on one page may step differently, and a document
 * is read back on the grid the field that shows it uses.
 *
 * The same pair also indents a list, by nesting it rather than by margin - the caret
 * decides which of the two the button means. That is in `resources/js/indent.js`, because
 * it is a question about a selection and only the browser has one.
 */
class IndentPlugin implements RichContentPlugin
{
    final public function __construct(
        protected string $step = Indent::DEFAULT_STEP,
        protected int $max = Indent::DEFAULT_MAX,
    ) {}

    public static function make(mixed $step = null, mixed $max = null): static
    {
        return app(static::class, [
            'step' => Indent::step($step),
            'max' => Indent::max($max),
        ]);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(Indent::class, ['options' => ['step' => $this->step, 'max' => $this->max]]),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/indent', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('indent')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.indent.indent'))
                ->jsHandler('$getEditor()?.chain().focus().indentBlock().run()')
                ->icon(Icons::get('indent')),
            RichEditorTool::make('outdent')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.indent.outdent'))
                ->jsHandler('$getEditor()?.chain().focus().outdentBlock().run()')
                ->icon(Icons::get('outdent')),
        ];
    }

    /**
     * The step and the depth, for the view to hand to the script. Both halves have to agree
     * on them or a document is read at one grid and written at another.
     *
     * Static, the way the find bar's labels and the drag handle's settings are: the view
     * asks the field, and the field has already resolved both numbers - there is nothing a
     * constructed plugin would know that the two arguments do not say.
     *
     * @return array<string, mixed>
     */
    public static function getSettings(string $step, int $max): array
    {
        return [
            'step' => $step,
            'max' => $max,
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
