<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of where a picture sits: to the side with the text running past it, or in
 * the middle with the text above and below.
 *
 * A global attribute on the image node, the same mechanism the rotation uses and for the
 * same reason: `Tiptap\Core\Schema` merges global attributes into the list both the parser
 * and the serialiser walk, so the side survives without redefining Filament's image node -
 * a second node of that name renders a second tag on every save.
 *
 * The placement travels inside the inline `style`, because Filament's sanitiser keeps
 * `style` and drops any attribute that is not on its short allow list. Nothing in the stack
 * validates CSS, so both the placement and the gap beside it are whitelisted here rather
 * than trusted - the placement to three words, the gap to a plain CSS length.
 *
 * `center` is not a float, and cannot be: CSS has no way to run text down both sides of a
 * block. It is what every editor calls centre - the picture on its own line, in the middle,
 * with the text above and below - and it is written as the block and the automatic margins
 * that do that.
 *
 * The margins are written with it rather than left to a stylesheet: the page this lands on
 * has not loaded this package's CSS, and a floated picture with no gap has the text
 * touching it. A project that would rather decide the gap itself sets `images.float_gap`
 * to null and gets the bare `float`.
 */
class ImageFloat extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteImageFloat';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'float' => [
                        'parseHTML' => function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            $style = $DOMNode->getAttribute('style');

                            if (blank($style)) {
                                return null;
                            }

                            if (preg_match('/(?:^|[;\s])float\s*:\s*(left|right)/i', $style, $matches) === 1) {
                                return static::normalise($matches[1]);
                            }

                            // The automatic margin is what centres the picture, so it is
                            // what says it was centred. Read on its own rather than
                            // alongside the `display`, because a shorthand and a longhand
                            // spell the same thing and only one of them has to be there.
                            return preg_match('/(?:^|[;\s])margin-inline\s*:\s*auto/i', $style) === 1
                                ? 'center'
                                : null;
                        },
                        'renderHTML' => function ($attributes): array {
                            $placement = static::normalise(static::read($attributes, 'float'));

                            if ($placement === null) {
                                return [];
                            }

                            if ($placement === 'center') {
                                // A block, because an inline picture is centred by the
                                // paragraph around it and this has to work whatever that
                                // paragraph says.
                                return ['style' => 'display: block; margin-inline: auto'];
                            }

                            $declarations = ['float: '.$placement];

                            $gap = static::gap();

                            if ($gap !== null) {
                                // Logical properties, so the gap lands on the correct side
                                // of a picture inside a right-to-left passage as well.
                                $declarations[] = ($placement === 'left' ? 'margin-inline-end: ' : 'margin-inline-start: ').$gap;
                                $declarations[] = 'margin-block-end: '.$gap;
                            }

                            return ['style' => implode('; ', $declarations)];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * One of the three placements, or nothing at all.
     */
    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $placement = strtolower(trim($value));

        return in_array($placement, ['left', 'center', 'right'], strict: true) ? $placement : null;
    }

    /**
     * The gap between the picture and the text beside it.
     *
     * Whitelisted to a bare CSS length for the reason the class comment gives: it is
     * interpolated into a `style` attribute that nothing downstream inspects. A value that
     * is not a length is dropped rather than escaped - there is no correct escaping for
     * "this was meant to be a number".
     */
    public static function gap(): ?string
    {
        $gap = config('filament-advanced-rich-editor.images.float_gap', '1rem');

        if (blank($gap) || (! is_string($gap) && ! is_int($gap) && ! is_float($gap))) {
            return null;
        }

        $gap = trim((string) $gap);

        return preg_match('/^\d+(?:\.\d+)?(?:%|px|em|rem|vw|vh|vmin|vmax|pt|pc|cm|mm|in|ch|ex)?$/i', $gap) === 1
            ? (is_numeric($gap) ? $gap.'px' : $gap)
            : null;
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
