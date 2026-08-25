<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * The named styles a field offers, read out of the project's configuration.
 *
 * A style is a label an editor picks and a set of CSS classes the page gets, which is the
 * one thing this package can do that a generic editor cannot: the classes belong to the
 * front end's design system, so a project decides what they are and nothing here has an
 * opinion. Shipped empty for exactly that reason - an editor offering styles nobody
 * designed is worse than one offering none.
 *
 * Two scopes, because there are two mechanisms underneath. A `block` style is an attribute
 * on the paragraph or heading the caret sits in, and a block may carry one; an `inline`
 * style is a mark on the selected text, and a selection may carry one. Both are exclusive:
 * picking a second replaces the first, the way a heading level does. A project that wants
 * two of its classes together writes one entry holding both.
 *
 * Nothing here is thrown. An entry the editor could never apply - no label, no classes, a
 * scope that is not one of the two, a class that could not be a class - is left out, the
 * way an unknown tool name is left out of the overflow menu. A missing entry is visible;
 * an exception on a page that only wanted to render an article is not.
 */
class Styles
{
    /**
     * The blocks a style can sit on, which is the same list that carries a direction: what
     * the caret can be inside and what a class means something on.
     *
     * @var array<int, string>
     */
    public const BLOCK_TYPES = ['paragraph', 'heading', 'blockquote', 'listItem', 'codeBlock'];

    /**
     * @var array<int, string>
     */
    public const SCOPES = ['block', 'inline'];

    /**
     * Characters that cannot appear in a class attribute, so their presence says the
     * configuration is wrong rather than that somebody is attacking.
     *
     * The check is this narrow on purpose. A real design system is written in Tailwind more
     * often than not, whose class names carry colons, slashes, square brackets, dots and
     * leading hyphens - a pattern tight enough to feel reassuring would reject most of a
     * project's own classes. Nothing is riding on it either way: the value is escaped on
     * the way into the attribute, and a class name has no CSS semantics of its own the way
     * a `style` attribute does.
     */
    protected const IMPOSSIBLE_CHARACTERS = '/["\'<>&]/';

    /**
     * Every style the project declared, in the order it declared them.
     *
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public static function all(): array
    {
        return static::resolve((array) config('filament-advanced-rich-editor.styles', []));
    }

    /**
     * What one field offers: its own list where it has one, the project's otherwise. An
     * empty list is a field saying it wants none, which is different from saying nothing.
     *
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public static function for(AdvancedRichEditor $editor): array
    {
        $styles = $editor->getStyles();

        return ($styles === null) ? static::all() : static::resolve($styles);
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public static function block(?AdvancedRichEditor $editor = null): array
    {
        return static::scoped('block', $editor);
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public static function inline(?AdvancedRichEditor $editor = null): array
    {
        return static::scoped('inline', $editor);
    }

    /**
     * The entries of one scope out of a list that is already resolved. What the extensions
     * ask for: they are handed a list rather than a source, because a field and the project
     * are two different sources and only one of them is in force at a time.
     *
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>  $styles
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public static function ofScope(array $styles, string $scope): array
    {
        return array_values(array_filter(
            $styles,
            static fn (array $style): bool => $style['scope'] === $scope,
        ));
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    protected static function scoped(string $scope, ?AdvancedRichEditor $editor): array
    {
        return static::ofScope(($editor === null) ? static::all() : static::for($editor), $scope);
    }

    /**
     * @param  array<string, mixed>  $styles
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    protected static function resolve(array $styles): array
    {
        $resolved = [];

        foreach ($styles as $key => $style) {
            $entry = static::entry((string) $key, $style);

            if ($entry !== null) {
                $resolved[] = $entry;
            }
        }

        return $resolved;
    }

    /**
     * @return array{key: string, label: string, class: string, scope: string, types: array<int, string>}|null
     */
    protected static function entry(string $key, mixed $style): ?array
    {
        if (blank($key) || ! is_array($style)) {
            return null;
        }

        $label = is_string($style['label'] ?? null) ? trim($style['label']) : '';
        $class = static::classes($style['class'] ?? null);
        $scope = is_string($style['scope'] ?? null) ? strtolower(trim($style['scope'])) : 'block';

        if (blank($label) || $class === null || ! in_array($scope, static::SCOPES, strict: true)) {
            return null;
        }

        // A mark has no node types to sit on, so an inline style naming some is answered
        // with none rather than with an argument about it.
        if ($scope === 'inline') {
            return ['key' => $key, 'label' => $label, 'class' => $class, 'scope' => $scope, 'types' => []];
        }

        $types = static::types($style['types'] ?? null);

        // An entry the editor could never apply anywhere is not an entry.
        return ($types === [])
            ? null
            : ['key' => $key, 'label' => $label, 'class' => $class, 'scope' => $scope, 'types' => $types];
    }

    /**
     * The class list as one string, or null where there is nothing usable in it.
     */
    protected static function classes(mixed $class): ?string
    {
        if (! is_string($class)) {
            return null;
        }

        // Whitespace is the separator between classes, so what kind it is carries no
        // meaning and a configuration file may wrap a long list over two lines.
        $class = trim((string) preg_replace('/\s+/u', ' ', $class));

        if (blank($class) || preg_match(static::IMPOSSIBLE_CHARACTERS, $class) === 1) {
            return null;
        }

        return $class;
    }

    /**
     * @return array<int, string>
     */
    protected static function types(mixed $types): array
    {
        if (! is_array($types)) {
            return static::BLOCK_TYPES;
        }

        return array_values(array_intersect(static::BLOCK_TYPES, array_filter($types, 'is_string')));
    }
}
