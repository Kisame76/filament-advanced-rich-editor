<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of the block direction.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser only keeps
 * attributes that something declares - so without `parseHTML` here a right-to-left
 * paragraph would read left to right again the first time the record is reopened. And
 * without `renderHTML` the direction would live in the editor but never reach a saved
 * document, which is where it actually matters.
 *
 * The value is whitelisted to the two directions rather than passed through. `dir` is on
 * the sanitiser's safe list, so nothing downstream would object to `dir="javascript:"` -
 * it simply would not be a direction.
 */
class TextDirection extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteTextDirection';

    /**
     * The block types that carry a direction, mirroring the JS extension's own list.
     *
     * @var array<int, string>
     */
    public const TYPES = ['paragraph', 'heading', 'blockquote', 'listItem', 'codeBlock'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => static::TYPES,
                'attributes' => [
                    'dir' => [
                        'parseHTML' => static function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::normalise($DOMNode->getAttribute('dir'));
                        },
                        'renderHTML' => static function ($attributes): array {
                            $dir = static::normalise(static::read($attributes, 'dir'));

                            return $dir === null ? [] : ['dir' => $dir];
                        },
                    ],
                ],
            ],
        ];
    }

    protected static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $dir = strtolower(trim($value));

        return in_array($dir, ['ltr', 'rtl'], strict: true) ? $dir : null;
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
