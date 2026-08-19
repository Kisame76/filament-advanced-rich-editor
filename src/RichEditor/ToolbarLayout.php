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
            'headings' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::headings($editor->getHeadingLevels()),
            'lists' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::lists($editor->getListTypes()),
            'alignment' => static fn (AdvancedRichEditor $editor): object => ToolbarDropdown::alignment($editor->getAlignments()),
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
