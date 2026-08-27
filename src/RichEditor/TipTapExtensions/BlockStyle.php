<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;
use Tiptap\Core\Extension;

/**
 * The PHP half of the named style a block carries.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser only keeps
 * attributes that something declares - so without this a styled paragraph would be a plain
 * one again the first time the record was reopened.
 *
 * Two things are written, and both are needed. The classes are what the page uses, and the
 * key is what the next parse reads: a project that edits the class list of a style in its
 * configuration then finds every existing document following along, where a document that
 * only carried classes would keep the old ones until a save quietly dropped them. The key
 * travels in `data-style`, which Filament's sanitiser does not pass through - so it reaches
 * the database and not the reader, which is exactly the split that is wanted.
 *
 * The classes are a fallback on the way in, for content pasted out of a rendered page or
 * written before a project had keys. All of a style's classes have to be present for it to
 * be the one; the first style that matches wins.
 *
 * Registered as a GLOBAL attribute rather than by redefining the paragraph and the heading,
 * for the same reason the direction is: TipTap merges global attributes into the schema of
 * the types they name, so Filament's own nodes keep their definition.
 */
class BlockStyle extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteBlockStyle';

    /**
     * Prefixed rather than called `style`, because this is merged into nodes that Filament
     * and TipTap both own and a bare name is a collision waiting to be found.
     */
    public const ATTRIBUTE = 'arteStyle';

    /**
     * @return array<string, mixed>
     */
    public function addOptions(): array
    {
        return [
            'styles' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        $styles = $this->styles();

        if ($styles === []) {
            return [];
        }

        // Every block type, and not the union of the ones the configured styles name.
        // Which styles a particular block is *offered* is a question for the picker; the
        // schema's question is what a document may carry, and the answer to that is
        // "whatever it already carries" - content that has a style is better shown than
        // silently stripped.
        //
        // The union was the narrower answer and it disagreed with the browser, which
        // declares the attribute over all five. Style a paragraph, turn it into a heading,
        // save: the editor kept the style and this half threw it away. A project that
        // narrows a style to paragraphs is saying where the picker may offer it, not that
        // the same words stop being a lead the moment they become a heading.
        return [
            [
                'types' => Styles::BLOCK_TYPES,
                'attributes' => [
                    static::ATTRIBUTE => [
                        'parseHTML' => fn ($DOMNode): ?string => static::read($styles, $DOMNode),
                        'renderHTML' => function ($attributes) use ($styles): array {
                            $key = static::attribute($attributes, static::ATTRIBUTE);
                            $style = static::find($styles, is_string($key) ? $key : null);

                            return ($style === null)
                                ? []
                                : ['data-style' => $style['key'], 'class' => $style['class']];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    protected function styles(): array
    {
        $styles = $this->options['styles'] ?? [];

        return is_array($styles) ? array_values($styles) : [];
    }

    /**
     * The key an element carries, by its own name where there is one and by its classes
     * where there is not.
     *
     * @param  array<int, array<string, mixed>>  $styles
     */
    public static function read(array $styles, mixed $DOMNode): ?string
    {
        if (! ($DOMNode instanceof DOMElement)) {
            return null;
        }

        $declared = static::find($styles, trim($DOMNode->getAttribute('data-style')));

        if ($declared !== null) {
            return $declared['key'];
        }

        return static::match($styles, $DOMNode->getAttribute('class'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $styles
     * @return array<string, mixed>|null
     */
    public static function find(array $styles, ?string $key): ?array
    {
        if (blank($key)) {
            return null;
        }

        foreach ($styles as $style) {
            if (($style['key'] ?? null) === $key) {
                return $style;
            }
        }

        return null;
    }

    /**
     * The first style whose classes are all present. Order decides, which is the order the
     * project wrote them in.
     *
     * @param  array<int, array<string, mixed>>  $styles
     */
    public static function match(array $styles, string $class): ?string
    {
        $present = preg_split('/\s+/u', trim($class), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($present === []) {
            return null;
        }

        foreach ($styles as $style) {
            $wanted = preg_split('/\s+/u', (string) ($style['class'] ?? ''), flags: PREG_SPLIT_NO_EMPTY) ?: [];

            if ($wanted !== [] && array_diff($wanted, $present) === []) {
                return (string) $style['key'];
            }
        }

        return null;
    }

    public static function attribute(mixed $attributes, string $key): mixed
    {
        if (is_array($attributes)) {
            return $attributes[$key] ?? null;
        }

        if (is_object($attributes)) {
            return $attributes->{$key} ?? null;
        }

        return null;
    }
}
