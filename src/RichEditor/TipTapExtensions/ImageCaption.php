<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The caption an image carries, on the way through the schema.
 *
 * It rides on the image as `data-caption` rather than as a `<figure>` around it, because
 * the structure is not something an attribute can build - and rebuilding Filament's image
 * node to get one would mean owning its resizing, its uploads and its node view for the
 * sake of one line of text. The figure is built when the page is rendered, by
 * `ImageCaptions`, out of exactly this attribute.
 *
 * `data-caption` is not on Filament's sanitiser allow list, and it does not need to be:
 * what is stored is never sanitised - only what is rendered is, and by then the attribute
 * has become a `<figcaption>`.
 */
class ImageCaption extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteImageCaption';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'caption' => [
                        'parseHTML' => static function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::normalise($DOMNode->getAttribute('data-caption'));
                        },
                        'renderHTML' => static function ($attributes): array {
                            $caption = static::normalise(static::read($attributes, 'caption'));

                            return $caption === null ? [] : ['data-caption' => $caption];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * A caption of nothing but spaces is no caption: it would render as an empty line under
     * the picture, which reads as a mistake rather than as a caption nobody wrote.
     */
    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $caption = trim($value);

        return $caption === '' ? null : $caption;
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
