<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions;

use DOMElement;
use Tiptap\Core\Extension;

/**
 * The address a picture points at, on the way through the schema.
 *
 * It rides on the image as `data-href` rather than as an `<a>` around it, the same bargain
 * the caption makes and for the same reason: the structure is not something an attribute can
 * build, and rebuilding Filament's image node to get one would mean owning its resizing, its
 * uploads and its node view for the sake of a link. `LinkedImages` builds the anchor when the
 * page is rendered, out of exactly these attributes.
 *
 * `data-href` is not on Filament's sanitiser allow list and does not need to be: what is
 * stored is never sanitised - only what is rendered is, and by then it has become an `href`
 * on an `<a>`, which the sanitiser then judges on its own terms.
 *
 * The address is whitelisted by scheme here anyway, rather than left to that judgement.
 * `toUnsafeHtml()` never meets the sanitiser, and a `javascript:` address reaching a page
 * through it is the one failure in this file worth preventing twice.
 */
class ImageLink extends Extension
{
    /**
     * @var string
     */
    public static $name = 'arteImageLink';

    /**
     * The schemes a picture may point at.
     *
     * The four a document actually uses. Anything else - `javascript:`, `data:`, `vbscript:`
     * - is dropped rather than escaped, the decision every other value this package writes
     * into markup makes: there is no correct escaping for "this was meant to be an address".
     *
     * @var array<int, string>
     */
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['image'],
                'attributes' => [
                    'href' => [
                        'parseHTML' => static function ($DOMNode): ?string {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return null;
                            }

                            return static::normalise($DOMNode->getAttribute('data-href'));
                        },
                        'renderHTML' => static function ($attributes): array {
                            $href = static::normalise(static::read($attributes, 'href'));

                            return $href === null ? [] : ['data-href' => $href];
                        },
                    ],

                    // Kept beside the address rather than folded into it: a link that opens
                    // in a new tab is the same link, and somebody clearing the tab setting
                    // is not clearing the link.
                    'hrefNewTab' => [
                        'parseHTML' => static function ($DOMNode): bool {
                            if (! ($DOMNode instanceof DOMElement)) {
                                return false;
                            }

                            return $DOMNode->getAttribute('data-href-new-tab') === 'true';
                        },
                        'renderHTML' => static function ($attributes): array {
                            return static::read($attributes, 'hrefNewTab') === true
                                ? ['data-href-new-tab' => 'true']
                                : [];
                        },
                    ],
                ],
            ],
        ];
    }

    /**
     * An address a picture may point at, or nothing.
     *
     * Relative addresses and fragments pass: `/articles/7`, `../up`, `#section` are what a
     * link inside a site is written as, and none of them carries a scheme to check. What is
     * checked is anything that does carry one, and a string that looks like it wants to.
     */
    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $href = trim($value);

        if ($href === '') {
            return null;
        }

        // No scheme at all: a path, a fragment or a query. Nothing to allow or refuse, and
        // a browser resolves it against the page it is on.
        //
        // The colon is what decides, and it is looked for before the first slash on purpose:
        // `/a:b` is a path with a colon in it, `javascript:alert(1)` is not a path at all.
        $colon = strpos($href, ':');
        $slash = strpos($href, '/');

        if ($colon === false || ($slash !== false && $slash < $colon)) {
            return static::withoutControlCharacters($href);
        }

        $scheme = strtolower(substr($href, 0, $colon));

        return in_array($scheme, static::SCHEMES, strict: true)
            ? static::withoutControlCharacters($href)
            : null;
    }

    /**
     * Whitespace and control characters taken out before the scheme is read.
     *
     * `java\nscript:` is the oldest trick against a scheme check that reads the string as it
     * arrives: browsers strip those characters before resolving an address, so a check that
     * does not strip them first is reading a different string than the browser will.
     */
    protected static function withoutControlCharacters(string $href): string
    {
        return preg_replace('/[\x00-\x20\x7F]/', '', $href) ?? '';
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
