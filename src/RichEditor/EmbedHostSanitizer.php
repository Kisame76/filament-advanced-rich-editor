<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Narrows `<iframe src>` back down to the hosts a video can come from.
 *
 * Filament sanitises rendered content with Symfony's `HtmlSanitizer`, whose safe element
 * list has no `<iframe>` in it - which is the right default, and which also removes every
 * embed. Allowing the element back is therefore a decision a project makes, and it is only
 * defensible together with this: an iframe may point at the video hosts and nowhere else.
 *
 * Stored HTML is what a database holds, and a database is not written only by this editor.
 * The node this package renders already refuses to build an iframe for a host that is not
 * on the list, so this is the second line rather than the only one - it catches markup that
 * arrived some other way.
 *
 * A note on living with other packages: Symfony chains every sanitiser registered for the
 * same element and attribute, and each one may only narrow what the last returned. Two
 * packages that both allowlist `iframe src` therefore end up with the intersection of their
 * lists, and each other's embeds disappear. If something else in the project embeds
 * iframes, add its hosts to `embed.allowed_hosts` rather than expecting both to work.
 */
class EmbedHostSanitizer implements AttributeSanitizerInterface
{
    /**
     * @param  array<int, string>  $hosts
     */
    public function __construct(protected array $hosts = []) {}

    /**
     * @return array<int, string>
     */
    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    /**
     * An empty string rather than `null` for a source that may not stay.
     *
     * `null` is what the interface documents for "drop this attribute", and from
     * `symfony/html-sanitizer` v8 it does exactly that: `DomVisitor` stops the chain there.
     * Before v8 there is no such guard, so the `null` is handed to the next sanitiser
     * registered for the same attribute - Symfony's own `UrlAttributeSanitizer`, whose
     * signature is a non-nullable string - and rendering stored content that carries a
     * foreign iframe dies with a TypeError instead of dropping its source.
     *
     * An empty string travels through both. Nothing loads from it, every later sanitiser in
     * the chain gets the string it expects, and the host that was rejected is gone either
     * way. Requiring v8 outright was the other option and a worse one: it is unresolvable on
     * Laravel 11 and 12, whose Symfony components are 7.x.
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $host = parse_url($value, PHP_URL_HOST);

        // No host means a relative path, a `javascript:` URL or something unparseable.
        // None of those is a video, and all of them are worth dropping.
        if (! is_string($host)) {
            return '';
        }

        return static::allows($host, $this->hosts) ? $value : '';
    }

    /**
     * Whether a host is one of the allowed ones, or a subdomain of one.
     *
     * The dot in front of the suffix is what separates `player.vimeo.com` from
     * `evilvimeo.com`, and comparing the whole host rather than searching inside it is what
     * keeps `vimeo.com.attacker.test` out - a domain anybody can register.
     *
     * @param  array<int, string>  $hosts
     */
    public static function allows(string $host, array $hosts): bool
    {
        $host = strtolower(trim($host, '.'));

        foreach ($hosts as $allowed) {
            $allowed = strtolower(trim((string) $allowed, '.'));

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }
}
