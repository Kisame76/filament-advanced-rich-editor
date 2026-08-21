<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * The emoji picker.
 *
 * There is no PHP extension and no mark: an emoji is a Unicode character, so it is inserted
 * as text and travels through the sanitiser, the save and `RichContentRenderer` like any
 * other letter. Nothing has to be taught about it on the way back out.
 *
 * The picker is a popup owned by the JS extension rather than a component of this package's
 * own, because a tool has to survive being put inside a dropdown - and a dropdown renders
 * button names, nothing else. The button therefore hands its own element to the picker so
 * the popup knows what to sit under, together with the strings it draws, which have to
 * cross into JavaScript somewhere and this is the one place that knows the locale.
 */
class EmojiPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The tabs, in the order they are drawn. `recent` is not a group of the emoji list but
     * of the reader's own history, which is why it is first: the same handful of emoji get
     * reached for again and again. The rest are the groups the bundled list is keyed by.
     *
     * @var array<int, string>
     */
    public const TABS = ['recent', 'smileys', 'nature', 'food', 'activities', 'travel', 'objects', 'symbols', 'flags'];

    /**
     * The picker's own strings and icons. It is built in the browser, so everything it
     * draws has to cross over from here - this is the one place that knows the locale and
     * the icon registry.
     *
     * @return array<string, mixed>
     */
    public static function getLabels(): array
    {
        return [
            'label' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.label'),
            'search' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.search'),
            'empty' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.empty'),
            'emptyRecent' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.empty_recent'),
            'close' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.close'),
            'closeIcon' => generate_icon_html(Icons::get('emoji_close'))?->toHtml() ?? '',
            'tabs' => static::getTabs(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public static function getTabs(): array
    {
        return array_map(
            static fn (string $key): array => [
                'key' => $key,
                'label' => __("filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.groups.{$key}"),
                // Drawn icons rather than a representative emoji: a row of nine coloured
                // faces reads as content, and an icon can be swapped like every other one
                // this package draws.
                'icon' => generate_icon_html(Icons::get("emoji_{$key}"))?->toHtml() ?? '',
            ],
            static::TABS,
        );
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
        // Only the extension is listed. The list of emoji is a second asset, imported by
        // this one the first time the picker opens, so an editor nobody clicks that button
        // in never pays for 60 KB of it.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/emoji', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $labels = Js::from(static::getLabels())->toHtml();

        return [
            RichEditorTool::make('emoji')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.emoji.label'))
                ->jsHandler("\$getEditor()?.chain().focus().openEmojiPicker(\$event.currentTarget, {$labels}).run()")
                ->icon(Icons::get('emoji')),
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
