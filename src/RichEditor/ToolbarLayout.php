<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * Translates the string tokens used in the package's toolbar configuration
 * into the objects the editor renders.
 *
 * Tokens keep the configuration file free of PHP objects, which is what makes
 * `config/filament-advanced-rich-editor.php` cacheable.
 */
class ToolbarLayout
{
    /**
     * Replaces every known token in a (possibly nested) toolbar array. Real
     * button names and objects are passed through untouched - Filament itself
     * raises a descriptive `LogicException` for unknown button names, so
     * guessing here would only produce worse error messages.
     *
     * @param  array<mixed>  $items
     * @return array<int, mixed>
     */
    public static function resolve(array $items, AdvancedRichEditor $editor): array
    {
        $tokens = static::tokens();

        $resolved = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $resolved[] = static::resolve($item, $editor);

                continue;
            }

            if (! is_string($item) || (! array_key_exists($item, $tokens))) {
                $resolved[] = $item;

                continue;
            }

            $value = static::resolveToken($tokens[$item], $editor);

            // A token is allowed to expand into several items. They are
            // spliced into the surrounding array instead of being nested, so
            // that a token never accidentally introduces a new toolbar group.
            // The expansion is deliberately not resolved again, which keeps
            // self-referencing tokens from looping forever.
            if (is_array($value)) {
                foreach ($value as $expandedItem) {
                    $resolved[] = $expandedItem;
                }

                continue;
            }

            $resolved[] = $value;
        }

        return $resolved;
    }

    /**
     * The built-in tokens with the ones registered in the config file merged
     * over them, so that a project can replace `headings` or `lists` with its
     * own dropdown without having to rename the token everywhere it is used.
     *
     * @return array<string, mixed>
     */
    public static function tokens(): array
    {
        return [
            'divider' => static fn (AdvancedRichEditor $editor): object => ToolbarDivider::make(),
            // Not a thing on the bar but a place in it: everything after the first one is
            // pinned to an edge instead of being aligned with the rest.
            'pin' => static fn (AdvancedRichEditor $editor): object => ToolbarPin::make(),
            'headings' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::headings($editor->getHeadingLevels(), $editor->hasHeadingParagraph()),
            'lists' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::lists($editor->getListTypes()),
            'alignment' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::alignment($editor->getAlignments()),
            // Nothing to pick from means no trigger, the same way the overflow menu and the
            // colour pickers vanish when what they open onto is empty.
            'lineHeight' => static function (AdvancedRichEditor $editor): object|array {
                if (! $editor->hasLineHeight()) {
                    return [];
                }

                $values = $editor->getLineHeights();

                return $values === [] ? [] : ToolbarDropdown::lineHeight($values);
            },
            // Nothing to open onto means no trigger at all, the same way the colour
            // pickers vanish when their feature is switched off.
            'more' => static function (AdvancedRichEditor $editor): object|array {
                $tools = $editor->getMoreTools();

                return $tools === [] ? [] : ToolbarDropdown::more($tools);
            },
            // Nothing rather than an empty menu, and "nothing" counts a list whose every
            // entry belongs to a feature this field switched off: searching, the check, the
            // source view and the shortcut list are each removable, so all four can be gone
            // while the list naming them is still as long as it ever was. A trigger opening
            // onto nothing is worse than no trigger.
            'tools' => static function (AdvancedRichEditor $editor): object|array {
                $tools = $editor->getToolsMenu();

                $available = array_filter(
                    $tools,
                    static fn (string $name): bool => array_key_exists($name, $editor->getTools()),
                );

                return $available === [] ? [] : ToolbarDropdown::tools($tools);
            },
            'textColor' => static fn (AdvancedRichEditor $editor): object|array => $editor->hasTextColor()
                ? ToolbarColorPicker::text($editor->getTextColorsForPicker(), $editor->hasCustomColors())
                : [],
            'fullscreen' => static fn (AdvancedRichEditor $editor): object|array => $editor->hasFullscreen()
                ? ToolbarFullscreen::make()
                : [],
            // A plain button name, but a token all the same: an unregistered name in a
            // toolbar group is an exception, so the switch has to reach the layout too.
            'sourceCode' => static fn (AdvancedRichEditor $editor): string|array => $editor->hasSourceCode()
                ? 'sourceCode'
                : [],
            'help' => static fn (AdvancedRichEditor $editor): string|array => $editor->hasHelp()
                ? 'help'
                : [],
            'find' => static fn (AdvancedRichEditor $editor): string|array => $editor->hasFind()
                ? 'find'
                : [],
            'accessibility' => static fn (AdvancedRichEditor $editor): string|array => $editor->hasAccessibility()
                ? 'accessibility'
                : [],
            'textBackground' => static fn (AdvancedRichEditor $editor): object|array => $editor->hasTextBackground()
                ? ToolbarColorPicker::background($editor->getBackgroundColors(), $editor->hasCustomColors())
                : [],
            // Shipped empty, so most projects never see this at all - and one with no
            // styles gets no trigger rather than a button opening onto nothing.
            'styles' => static function (AdvancedRichEditor $editor): object|array {
                $styles = Styles::for($editor);

                return $styles === [] ? [] : ToolbarStylePicker::make()->styles($styles);
            },
            // Nothing to pick from means no picker: a project with no fonts of its own and
            // the generic stacks switched off would otherwise get an empty menu.
            'fontFamily' => static function (AdvancedRichEditor $editor): object|array {
                if (! $editor->hasFontPicker()) {
                    return [];
                }

                $fonts = Fonts::for($editor);

                return $fonts === []
                    ? []
                    : ToolbarFontPicker::make()->fonts($fonts)->styleSheet(Fonts::styleSheet($editor));
            },
            // Expands to nothing when the feature is off, so the same toolbar layout
            // works for fields with and without the stepper.
            'fontSize' => static function (AdvancedRichEditor $editor): object|array {
                if (! $editor->hasFontSize()) {
                    return [];
                }

                $options = $editor->getFontSizeOptions();

                return ToolbarFontSize::make()
                    ->min($options['min'])
                    ->max($options['max'])
                    ->step($options['step'])
                    ->sizes($options['sizes'])
                    ->defaultSize($options['default']);
            },
            ...(config('filament-advanced-rich-editor.tokens') ?? []),
        ];
    }

    protected static function resolveToken(mixed $token, AdvancedRichEditor $editor): mixed
    {
        if ($token instanceof Closure) {
            // Evaluated through the editor so that tokens can also inject the
            // usual Filament utilities such as `$get` or `$record`.
            return $editor->evaluate($token, [
                'editor' => $editor,
            ]);
        }

        // A class-string with a static `make()` is the second supported token
        // shape, because that is what a config file can express literally.
        if (is_string($token) && class_exists($token) && method_exists($token, 'make')) {
            return $token::make();
        }

        return $token;
    }
}
