<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Languages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Language;
use Tiptap\Core\Extension;

/**
 * The language a passage is written in.
 *
 * One tool per language plus the one that takes the marking off, for the reason the callout
 * kinds are one tool each: a dropdown renders button names, the slash menu lists registered
 * tools, and the lit-up state is a tool's own. A single tool with a list behind it could be
 * none of those things.
 */
class LanguagePlugin implements RichContentPlugin
{
    /**
     * @var array<int, array{code: string, label: string}>
     */
    protected array $languages = [];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @param  array<int, array{code: string, label: string}>  $languages
     */
    public function languages(array $languages): static
    {
        $this->languages = array_values($languages);

        return $this;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(Language::class),
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/language', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $tools = [
            // First in the list, the way the style picker puts its own way out at the top:
            // a dropdown offering only languages has no way back to the language of the
            // page, which is what most of a document is written in.
            RichEditorTool::make(Languages::CLEAR)
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.language_none'))
                ->jsHandler('$getEditor()?.chain().focus().unsetLanguage().run()')
                // There is no state called "no language" for a button to light up in.
                ->activeStyling(false)
                ->icon(Icons::get('language_none')),
        ];

        foreach ($this->getLanguages() as $language) {
            // `Js::from()` rather than plain quotes: the code is already checked against
            // `Languages::CODE` on the way in, and this is the second lock on the one place
            // a code becomes executable text.
            $argument = (string) Js::from($language['code']);

            $tools[] = RichEditorTool::make(Languages::toolName($language['code']))
                ->label($language['label'])
                ->jsHandler("\$getEditor()?.chain().focus().toggleLanguage({$argument}).run()")
                ->activeKey('language')
                ->activeOptions(['code' => $language['code']])
                ->icon(Icons::get('language'));
        }

        return $tools;
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
