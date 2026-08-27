<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of a picture that carries no meaning: a divider, a texture, a flourish beside
 * a heading that the words already say.
 *
 * The difference between such a picture and one somebody forgot to describe is not visible
 * in the markup unless it is written down. Both have an empty `alt`, and a screen reader
 * treats them differently only when told: `role="presentation"` beside the empty `alt` is
 * the pair that says "skip this", and an empty `alt` on its own is what a checker has to
 * report as a mistake, because it cannot tell which of the two it is looking at.
 *
 * A global attribute on the image node, the same mechanism the rotation and the placement
 * use and for the same reason `ImageFloat` gives: `Tiptap\Core\Schema` merges global
 * attributes into the list the parser and the serialiser both walk, so this survives without
 * redefining Filament's image node.
 *
 * `role` rides as itself rather than inside the `style` the placement uses, because Symfony's
 * sanitiser has it on its own safe list - checked rather than assumed, since Filament's
 * additions name `width` and `height` for images and say nothing about `role`.
 *
 * `presentation` is written and `none` is read as well: they are synonyms in ARIA, and a
 * document pasted from somewhere else may spell it either way.
 */
class ImageDecorative extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteImageDecorative';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'decorative' => [
                        'parseHTML' => function ($DOMNode): bool {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return false;
                            }

                            return static::isPresentational($DOMNode->getAttribute('role'));
                        },
                        'renderHTML' => function ($attributes): array {
                            if (static::read($attributes, 'decorative') !== true) {
                                return [];
                            }

                            // The role only. The empty `alt` that belongs beside it cannot
                            // be written from here - `tiptap-php` drops any attribute whose
                            // value is blank, which is right for every other attribute and
                            // exactly wrong for this one - so `DecorativeImages` puts it on
                            // afterwards, and drops any description left behind with it.
                            return ['role' => 'presentation'];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * Whether a `role` says the picture carries nothing.
     *
     * `none` is ARIA's own synonym for `presentation`, and the one a document arriving from
     * another editor is as likely to spell.
     */
    public static function isPresentational(mixed $role): bool
    {
        if (! is_string($role)) {
            return false;
        }

        return in_array(strtolower(trim($role)), ['presentation', 'none'], strict: true);
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
