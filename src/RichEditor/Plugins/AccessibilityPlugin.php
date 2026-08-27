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
 * What is wrong with this document, said while there is still somebody to say it to.
 *
 * No PHP extension and no mark: a check marks nothing that is stored, and a picture that
 * was given alt text is an ordinary picture by the time it is saved. So there is nothing to
 * parse, nothing to sanitise and nothing to render on the way back out.
 *
 * What crosses over is everything the browser cannot decide for itself, and there is more
 * of it here than in any other plugin in this package - which is the reason the check lives
 * half in PHP at all:
 *
 * The weak link phrases are the clearest case. "Click here" is not a fact about the web, it
 * is a fact about English, and a check that shipped the English list to a German editor
 * would find nothing and say the document was fine. They come from the translation files,
 * and a project adds its own in the config.
 *
 * The palette is the second. Filament stores the name of a colour rather than the colour -
 * `data-color="ink"` - which is the right way round, and it means the browser has a name
 * and no way to turn it into three channels. The map crosses over so the ratio can be
 * worked out at all.
 */
class AccessibilityPlugin implements RichContentPlugin
{
    /**
     * Every rule, and the only names `->accessibilityRules()` accepts. Kept here rather
     * than in the config file so that a name nobody implements cannot be switched on.
     *
     * @var array<int, string>
     */
    public const RULES = [
        'missing_alt',
        'empty_link',
        'weak_link_text',
        'skipped_heading',
        'table_without_header',
        'weak_contrast',
    ];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function getSettings(array $settings): array
    {
        $line = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.accessibility.{$key}");

        return [
            ...$settings,
            'labels' => [
                'title' => $line('title'),
                'close' => $line('close'),
                'empty' => $line('empty'),
                // Two numbers in one string, because the second one is what makes the first
                // mean anything: 3.1 is a fact and "3.1 of the 4.5 needed" is an answer.
                'ratio' => $line('ratio'),
                'rules' => [
                    'missing_alt' => $line('rules.missing_alt'),
                    'empty_link' => $line('rules.empty_link'),
                    'weak_link_text' => $line('rules.weak_link_text'),
                    'skipped_heading' => $line('rules.skipped_heading'),
                    'table_without_header' => $line('rules.table_without_header'),
                    'weak_contrast' => $line('rules.weak_contrast'),
                ],
            ],
            'icons' => [
                'grip' => generate_icon_html(Icons::get('accessibility_grip'))?->toHtml() ?? '',
                'close' => generate_icon_html(Icons::get('accessibility_close'))?->toHtml() ?? '',
            ],
        ];
    }

    /**
     * The phrases a link may not consist of, in the language the panel is being read in.
     *
     * The translated list and the project's own, because both are right: a project knows
     * the wording its own house style keeps producing, and neither list is a substitute for
     * the other.
     *
     * @return array<int, string>
     */
    public static function getWeakPhrases(): array
    {
        $translated = __('filament-advanced-rich-editor::advanced-rich-editor.accessibility.weak_link_phrases');

        return array_values(array_unique([
            ...(is_array($translated) ? $translated : []),
            ...(array) (config('filament-advanced-rich-editor.accessibility.weak_link_phrases') ?? []),
        ]));
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/accessibility', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('accessibility')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.accessibility.title'))
                // Nothing is handed over: the panel belongs to the field, and what it lists
                // is worked out from the document at the moment it opens.
                ->jsHandler('$getEditor()?.commands.openAccessibilityReport()')
                ->icon(Icons::get('accessibility')),
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
