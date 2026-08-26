<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Callouts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Callout;
use Tiptap\Core\Extension;

/**
 * The note, tip, warning and danger boxes.
 *
 * One tool per variant. A single tool with a choice hidden behind it could not be grouped
 * by `ToolbarDropdown`, listed by `SlashMenu` or lit up by the caret sitting in one - all
 * three of those are built on registered tools, and this way the button, the menu entry
 * and the active state are one thing described once.
 */
class CalloutPlugin implements RichContentPlugin
{
    /**
     * @var array<int, string>
     */
    protected array $variants = Callouts::VARIANTS;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @param  array<int, mixed>  $variants
     */
    public function variants(array $variants): static
    {
        $this->variants = Callouts::normalize($variants);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        // The class is baked into the rendered markup rather than added by a stylesheet
        // selector, because saved callouts are also rendered outside a form - by
        // `RichContentRenderer`, a text entry, or a front end that has never heard of this
        // package. Both halves emit the same classes, so one stylesheet covers every place
        // the content ends up.
        return [
            app(Callout::class),
        ];
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/callout', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return array_map(
            static function (string $variant): RichEditorTool {
                // `Js::from()` rather than plain quotes: the name is already checked
                // against `Callouts::NAME` on the way in, and this is the second lock on
                // the one place a variant becomes executable text.
                $argument = (string) Js::from($variant);

                return RichEditorTool::make(Callouts::toolName($variant))
                    ->label(Callouts::label($variant))
                    ->jsHandler("\$getEditor()?.chain().focus().toggleCallout({$argument}).run()")
                    ->activeKey('callout')
                    ->activeOptions(['variant' => $variant])
                    ->icon(Callouts::icon($variant));
            },
            $this->getVariants(),
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
