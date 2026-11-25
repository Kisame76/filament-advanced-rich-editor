<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * A link's `referrerpolicy`, kept only where it withholds.
 *
 * Symfony's sanitiser stopped counting the attribute as safe (8.1.12): `unsafe-url` hands the
 * whole address of the page - its path, its query, a token in it - to whatever site the link
 * leads to. What the link dialog is for is the other direction, keeping that back; so the
 * policies that do are allowed back, and the two that send more than a browser does by
 * default are dropped. See `Link::PRIVATE_REFERRER_POLICIES`.
 */
class ReferrerPolicySanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['referrerpolicy'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $policy = strtolower(trim($value));

        return in_array($policy, Link::PRIVATE_REFERRER_POLICIES, strict: true) ? $policy : null;
    }
}
