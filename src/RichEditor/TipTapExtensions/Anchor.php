<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The `id` attribute that makes a heading linkable.
 *
 * Content is stored as HTML and re-parsed on every render, and the parser keeps only the
 * attributes something declares. TipTap's own heading declares `level` and nothing else,
 * so an anchor written into a document - by the source code view, by an import, or by
 * this package's own renderer - is dropped again the moment the document is parsed.
 * Declaring the attribute here is what makes it survive.
 *
 * A global attribute rather than a redefined heading node: Filament's `Heading` keeps its
 * own definition, and the JS half can mirror this with the same mechanism.
 *
 * The value is validated rather than passed through. `id` is on the sanitiser's safe list,
 * so an id with a space in it would reach the page untouched - and `#not valid` is not a
 * fragment any browser will jump to. An unusable id is therefore dropped, which leaves the
 * heading exactly as it would have been without one.
 */
class Anchor extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteAnchor';

    /**
     * The types that carry an anchor.
     *
     * Headings only. Every other block is addressable through the heading above it, and
     * an `id` on a paragraph is a promise the editor has no way to keep stable while the
     * text around it is rewritten.
     *
     * @var array<int, string>
     */
    public const TYPES = ['heading'];

    /**
     * HTML5 allows any non-empty id without whitespace. This is deliberately narrower:
     * what this package generates is a slug, and what a person types by hand belongs in a
     * URL, where anything outside this set has to be percent-encoded to survive the trip.
     */
    public const PATTERN = '/^[A-Za-z0-9_\-.:]+$/';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => static::TYPES,
                'attributes' => [
                    'id' => [
                        'parseHTML' => static function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::normalise($DOMNode->getAttribute('id'));
                        },
                        'renderHTML' => static function ($attributes): array {
                            $id = static::normalise(static::read($attributes, 'id'));

                            return $id === null ? [] : ['id' => $id];
                        },
                    ],
                ],
            ],
        ];
    }

    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $id = trim($value);

        return preg_match(static::PATTERN, $id) === 1 ? $id : null;
    }

    protected static function read(mixed $attributes, string $key): mixed
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
