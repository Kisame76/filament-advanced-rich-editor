<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Concerns\ReadsAttributes;
use Tiptap\Core\Extension;

/**
 * The other sizes of a picture, offered to the browser beside the picture itself.
 *
 * Written onto the node while the page is rendered, from the media row behind the picture,
 * and never read back: the parse always answers nothing. A `srcset` is a list of addresses
 * Symfony's sanitiser does not look at - it checks `src`, not this - so one that came in with
 * the stored markup would reach the page exactly as it was written.
 *
 * `sizes` goes with it, because a list of widths means nothing to a browser that is not told
 * how wide the picture will be drawn. It is worked out from the width the picture carries.
 */
class ImageSrcset extends Extension
{
    use ReadsAttributes;

    /**
     * @var string
     */
    public static $name = 'arteImageSrcset';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'srcset' => [
                        'parseHTML' => static fn (): null => null,
                        'renderHTML' => static function ($attributes): array {
                            $srcset = static::read($attributes, 'srcset');

                            if (! is_string($srcset) || $srcset === '') {
                                return [];
                            }

                            return [
                                'srcset' => $srcset,
                                'sizes' => static::sizes(static::read($attributes, 'width'), static::read($attributes, 'loading')),
                            ];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * How wide the picture will be drawn, as far as the document says.
     *
     * A width in pixels is the most it is drawn at, and less on a screen narrower than that,
     * where the usual `max-width: 100%` holds it to the column. Anything else - no width, or a
     * share of a column nobody here knows the width of - leaves the screen's width, which is
     * never too small.
     *
     * A lazy picture is laid out before it loads, so a browser that can measure it does that
     * first; the rest of the list is for the browsers that cannot yet. Only beside a width in
     * pixels: with none, the box a picture that has not loaded yet is measured at is the
     * browser's default, three hundred pixels wide.
     */
    public static function sizes(mixed $width, mixed $loading): string
    {
        $pixels = (is_int($width) || is_float($width) || is_string($width))
            && preg_match('/^\s*(\d+(?:\.\d+)?)(?:px)?\s*$/i', (string) $width, $matches)
                ? $matches[1]
                : null;

        if ($pixels === null) {
            return '100vw';
        }

        $sizes = "(max-width: {$pixels}px) 100vw, {$pixels}px";

        return ($loading === 'lazy') ? "auto, {$sizes}" : $sizes;
    }
}
