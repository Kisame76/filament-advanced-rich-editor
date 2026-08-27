<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ListProperties as Values;
use Tiptap\Core\Extension;

/**
 * The PHP half of what a list is told about itself.
 *
 * Content is stored as HTML and re-parsed on every hydration, and the parser keeps only
 * what something declares - so without `parseHTML` here a list somebody set to start at 12
 * would start at 1 again the first time the record is reopened.
 *
 * Registered as global attributes rather than by redefining the two list nodes, exactly
 * like `TextDirection`: TipTap merges global attributes into the schema of the types they
 * name, so `Tiptap\Nodes\BulletList` and `Tiptap\Nodes\OrderedList` keep their definitions
 * and the JS half mirrors this with the same mechanism.
 *
 * `start` is deliberately not declared here: `Tiptap\Nodes\OrderedList` already brings it,
 * and two declarations of one attribute is one of them silently winning. Adding a second
 * `orderedList` node to guard it is not an option either - `DOMSerializer` breaks out of
 * its opening-tag loop on the first match but not out of its closing one, so a document
 * would render `</ol></ol>`.
 *
 * The two halves are therefore NOT symmetrical, which is worth saying out loud because
 * everything else in this package is: the browser's `orderedList` already declares `type`
 * as well and this one does not, so `list-properties.js` adds `reversed` there and nothing
 * else. What each half adds is what its own half was missing.
 */
class ListProperties extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteListProperties';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['bulletList'],
                'attributes' => [
                    'type' => static::typeAttribute('bulletList'),
                ],
            ],
            [
                'types' => ['orderedList'],
                'attributes' => [
                    'type' => static::typeAttribute('orderedList'),
                    'reversed' => [
                        'parseHTML' => static function (mixed $DOMNode): ?bool {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            // Null rather than the empty string a bare `reversed` reads
                            // back as, so "absent" and "present with no value" stay apart.
                            return Values::reversed(
                                $DOMNode->hasAttribute('reversed')
                                    ? $DOMNode->getAttribute('reversed')
                                    : null,
                            );
                        },
                        'renderHTML' => static function (mixed $attributes): array {
                            // The attribute's own name as its value: that is how a boolean
                            // attribute is written in XHTML and is read as present by every
                            // parser, this package's included.
                            return Values::reversed(static::read($attributes, 'reversed')) === true
                                ? ['reversed' => 'reversed']
                                : [];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function typeAttribute(string $listType): array
    {
        return [
            // The attribute first, then the CSS: a document written here carries both, and
            // one that arrived from Word or Google Docs carries only the second.
            'parseHTML' => static function (mixed $DOMNode) use ($listType): ?string {
                if (! ($DOMNode instanceof DOMElement)) {
                    return null;
                }

                return Values::type($DOMNode->getAttribute('type'), $listType)
                    ?? Values::fromStyle($DOMNode->getAttribute('style'), $listType);
            },
            // Written twice, and both are load-bearing. The attribute is what this package
            // parses and what a bare browser honours; the inline `list-style-type` is what
            // survives a stylesheet that sets one - which Filament's prose styles do, and
            // which the page a document ends up on may well do too.
            'renderHTML' => static function (mixed $attributes) use ($listType): array {
                $type = Values::type(static::read($attributes, 'type'), $listType);

                return $type === null ? [] : [
                    'type' => $type,
                    'style' => 'list-style-type: '.Values::CSS[$type].';',
                ];
            },
        ];
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
