<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The PHP half of the image rotation.
 *
 * Registered as a global attribute on the image node, the same mechanism
 * `Tiptap\Extensions\TextAlign` uses: `Tiptap\Core\Schema` merges those into the attribute
 * list both the parser and the serialiser walk, so the angle survives without redefining
 * Filament's image node - a second node of that name would render a second tag on every
 * save.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser only harvests
 * attributes that something declares. Without this class the rotation would be dropped the
 * first time a record is reopened - which is also the security property that keeps a
 * hand-written `style` from surviving.
 */
class ImageRotate extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteImageRotate';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'rotate' => [
                        'parseHTML' => function ($DOMNode): ?int {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            $style = $DOMNode->getAttribute('style');

                            if (blank($style) || preg_match('/rotate\(\s*(-?[\d.]+)deg\s*\)/i', $style, $matches) !== 1) {
                                return null;
                            }

                            return static::normalise($matches[1]);
                        },
                        'renderHTML' => function ($attributes): array {
                            $angle = static::normalise(static::read($attributes, 'rotate'));

                            if ($angle === null) {
                                return [];
                            }

                            $declarations = ['transform: rotate('.$angle.'deg)'];

                            $width = static::read($attributes, 'width');
                            $height = static::read($attributes, 'height');

                            // A transform leaves the layout box alone, so a quarter turned
                            // image would keep its old footprint and overlap its
                            // surroundings. The margins make the box match what is drawn.
                            if (($angle % 180 !== 0) && is_numeric($width) && is_numeric($height)) {
                                $declarations[] = 'margin-block: '.(((float) $width - (float) $height) / 2).'px';
                                $declarations[] = 'margin-inline: '.(((float) $height - (float) $width) / 2).'px';
                            }

                            return ['style' => implode('; ', $declarations)];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * Quarter turns only: the value ends up in a `style` attribute that Filament's HTML
     * sanitiser passes through untouched, so it is whitelisted here rather than trusted.
     */
    protected static function normalise(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $quarters = ((int) round(((float) $value) / 90) * 90) % 360;

        if ($quarters < 0) {
            $quarters += 360;
        }

        return $quarters === 0 ? null : $quarters;
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
