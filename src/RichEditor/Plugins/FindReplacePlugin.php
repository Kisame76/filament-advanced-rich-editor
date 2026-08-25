<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\generate_icon_html;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * Finding and replacing inside one field.
 *
 * No PHP extension and no mark: a search marks nothing that is stored, and a replacement is
 * ordinary text by the time the document is saved. So there is nothing to parse, nothing to
 * sanitise and nothing to render on the way back out - the same reason the emoji picker has
 * no PHP half.
 *
 * The bar is built in the browser, which is why every string it draws is listed here: this
 * is the one place that knows the locale and the icon registry. They travel on the editor
 * element as `data-arte-find`, not through the button's handler, because `Ctrl+F` opens the
 * bar as well and a handler nobody clicked would never have run.
 */
class FindReplacePlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Everything the bar draws.
     *
     * The counter crosses over as a sentence with two placeholders rather than as two
     * numbers: where they sit in it is a question about the language, and answering it in
     * JavaScript would mean writing a second, worse translation layer.
     *
     * @return array<string, mixed>
     */
    public static function getLabels(): array
    {
        $line = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.tools.find.{$key}");

        return [
            'label' => $line('label'),
            'find' => $line('find'),
            'replace' => $line('replace'),
            'previous' => $line('previous'),
            'next' => $line('next'),
            'replaceOne' => $line('replace_one'),
            'replaceAll' => $line('replace_all'),
            'close' => $line('close'),
            'matchCase' => $line('match_case'),
            'wholeWord' => $line('whole_word'),
            'noResults' => $line('no_results'),
            'count' => $line('count'),
            'icons' => [
                'previous' => generate_icon_html(Icons::get('find_previous'))?->toHtml() ?? '',
                'next' => generate_icon_html(Icons::get('find_next'))?->toHtml() ?? '',
                'close' => generate_icon_html(Icons::get('find_close'))?->toHtml() ?? '',
                'replace' => generate_icon_html(Icons::get('find_replace'))?->toHtml() ?? '',
                'grip' => generate_icon_html(Icons::get('find_grip'))?->toHtml() ?? '',
            ],
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/find-replace', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('find')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.find.label'))
                // No element is handed over and no strings are: the bar belongs to the
                // field rather than to the button, and it is opened by a key as often as
                // by a click.
                ->jsHandler('$getEditor()?.chain().focus().openFind().run()')
                ->icon(Icons::get('find')),
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
