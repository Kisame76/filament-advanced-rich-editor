<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks;

use DOMElement;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use Tiptap\Marks\Link as BaseLink;

/**
 * Filament's link mark, widened by the attributes a link in a real document carries.
 *
 * Filament declares `href`, `target`, `rel` and `class`. Content is re-parsed on every
 * hydration and again on every save, and the parser keeps only what something declares -
 * so a `hreflang` or a `referrerpolicy` written into the source code view is gone the
 * first time the record is reopened, with nothing to say it happened.
 *
 * This *replaces* the mark rather than joining it. Two extensions of the same name are
 * both applied, and the result is a link nested inside a link - valid to neither the
 * browser nor anyone reading the markup. The renderer swaps it out by name, carrying the
 * options across so the protocol allow list Filament configured still applies.
 */
class Link extends BaseLink
{
    /**
     * The policies the HTML specification defines. Anything else is not a stricter policy,
     * it is no policy - the browser falls back to its default and the author is left
     * believing the referrer is withheld.
     *
     * @var array<int, string>
     */
    public const REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'origin',
        'origin-when-cross-origin',
        'same-origin',
        'strict-origin',
        'strict-origin-when-cross-origin',
        'unsafe-url',
    ];

    /**
     * @return array<string, mixed>
     */
    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),

            // The language of the page behind the link, which is what tells a browser and
            // a search engine that this one leads out of the current language.
            'hreflang' => [],

            'referrerpolicy' => [
                'parseHTML' => static fn ($DOMNode): ?string => static::normaliseReferrerPolicy(
                    $DOMNode instanceof DOMElement ? $DOMNode->getAttribute('referrerpolicy') : null,
                ),
                'renderHTML' => static function ($attributes): array {
                    $policy = static::normaliseReferrerPolicy(static::read($attributes, 'referrerpolicy'));

                    return $policy === null ? [] : ['referrerpolicy' => $policy];
                },
            ],

            // An anchor on the link itself, so something can link to this spot in the text.
            // Validated the same way a heading's is: an id with a space in it survives the
            // sanitiser and is still not a fragment any browser will jump to.
            'id' => [
                'parseHTML' => static fn ($DOMNode): ?string => Anchor::normalise(
                    $DOMNode instanceof DOMElement ? $DOMNode->getAttribute('id') : null,
                ),
                'renderHTML' => static function ($attributes): array {
                    $id = Anchor::normalise(static::read($attributes, 'id'));

                    return $id === null ? [] : ['id' => $id];
                },
            ],
        ];
    }

    public static function normaliseReferrerPolicy(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $policy = strtolower(trim($value));

        return in_array($policy, static::REFERRER_POLICIES, strict: true) ? $policy : null;
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
