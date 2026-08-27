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
 * The special characters picker: dashes, quotation marks, currencies, mathematics, arrows,
 * marks, and the accented and Greek letters a keyboard cannot reach.
 *
 * There is no PHP extension and no mark, for the reason the emoji picker has none: these
 * are characters, so they are inserted as text and travel through the sanitiser, the save
 * and `RichContentRenderer` like any other letter. Nothing has to be taught about them on
 * the way back out.
 *
 * The picker is the same popup the emoji one uses - see `resources/js/glyph-picker.js` -
 * and it is owned by the JS extension rather than being a component of this package's own,
 * because a tool has to survive being put inside a dropdown and a dropdown renders button
 * names, nothing else. The button therefore hands its own element to the picker so the
 * popup knows what to sit under, together with the strings it draws.
 */
class CharactersPlugin implements RichContentPlugin
{
    /**
     * The tabs, in the order they are drawn. `recent` is not a group of the list but of the
     * reader's own history, which is why it is first: a document that needs an en dash
     * usually needs a second one.
     *
     * @var array<int, string>
     */
    public const TABS = ['recent', 'punctuation', 'currency', 'math', 'arrows', 'symbols', 'latin', 'greek'];

    public static function make(): static
    {
        return app(static::class);
    }

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
            'label' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.label'),
            'search' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.search'),
            'empty' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.empty'),
            'emptyRecent' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.empty_recent'),
            'close' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.close'),
            'closeIcon' => generate_icon_html(Icons::get('characters_close'))?->toHtml() ?? '',
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
                'label' => __("filament-advanced-rich-editor::advanced-rich-editor.tools.characters.groups.{$key}"),
                // Drawn icons rather than a representative character: a row of eight
                // glyphs reads as content, and an icon can be swapped like every other one
                // this package draws.
                'icon' => generate_icon_html(Icons::get("characters_{$key}"))?->toHtml() ?? '',
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
        // Only the extension is listed. The popup it shares with the emoji picker and the
        // list of characters are both imported by it - the first as soon as it loads, the
        // second the first time the picker opens.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/characters', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $labels = Js::from(static::getLabels())->toHtml();

        return [
            RichEditorTool::make('characters')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.characters.label'))
                ->jsHandler("\$getEditor()?.chain().focus().openCharacterPicker(\$event.currentTarget, {$labels}).run()")
                ->icon(Icons::get('characters')),
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
