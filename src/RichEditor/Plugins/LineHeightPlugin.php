<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Tiptap\Core\Extension;

/**
 * Line spacing, on both sides of the editor.
 *
 * One tool per configured spacing rather than a stepper: the numbers that matter are a
 * handful of named ones, and a named list is what a dropdown can show a checkmark in.
 * Picking the spacing a block already has takes it back off, so there is always a way back
 * to whatever the theme sets.
 *
 * The tools are built from the field's own list, which is why the plugin is constructed
 * with it: two fields on one page may offer different spacings, and a tool that is not
 * registered is silently dropped from the dropdown rather than breaking it.
 */
class LineHeightPlugin implements RichContentPlugin
{
    /**
     * @param  array<int, string>  $values
     */
    final public function __construct(
        protected array $values = [],
    ) {}

    /**
     * @param  array<int, mixed>  $values
     */
    public static function make(array $values = []): static
    {
        return new static(LineHeight::values($values));
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(LineHeight::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/line-height', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return array_values(array_filter(array_map(
            static function (string $value): ?RichEditorTool {
                $name = LineHeight::toolName($value);

                if ($name === null) {
                    return null;
                }

                return RichEditorTool::make($name)
                    ->label(static::getLabel($value))
                    ->jsHandler("\$getEditor()?.chain().focus().toggleLineHeight('{$value}').run()")
                    ->activeJsExpression("\$getEditor()?.isActive({ lineHeight: '{$value}' })");
            },
            $this->values,
        )));
    }

    /**
     * `1` and `2` are the two spacings people name rather than measure, so they are labelled
     * the way a word processor labels them - with the number still in the label, because the
     * list they sit in is a list of numbers.
     */
    public static function getLabel(string $value): string
    {
        $key = match ($value) {
            '1' => 'single',
            '2' => 'double',
            default => null,
        };

        if ($key !== null) {
            return __("filament-advanced-rich-editor::advanced-rich-editor.tools.line_height.{$key}");
        }

        return __('filament-advanced-rich-editor::advanced-rich-editor.tools.line_height.value', [
            'value' => $value,
        ]);
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
