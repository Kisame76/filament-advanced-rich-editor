<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\ReferrerPolicySanitizer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The two link attributes Symfony's sanitiser stopped counting as safe in 8.1.12, and that
 * this package writes: `download` on a file card, `referrerpolicy` from the link dialog. Let
 * back through on links - the policy only where it withholds the address of the page.
 */
it('keeps a file card\'s download and a policy that withholds, through the sanitiser', function (): void {
    $html = AdvancedRichContentRenderer::make(
        '<p><a href="/files/report.pdf" download="report.pdf">Report</a> <a href="https://example.com" referrerpolicy="no-referrer">Away</a></p>',
    )->toHtml();

    expect($html)->toContain('download="report.pdf"')
        ->toContain('referrerpolicy="no-referrer"');
});

it('drops the policies that hand the whole address of the page to another site', function (string $policy): void {
    $html = AdvancedRichContentRenderer::make("<p><a href=\"https://example.com\" referrerpolicy=\"{$policy}\">Away</a></p>")->toHtml();

    expect($html)->not->toContain('referrerpolicy');
})->with(['unsafe-url', 'no-referrer-when-downgrade']);

it('lets every withholding policy through, and nothing else', function (): void {
    $sanitizer = new ReferrerPolicySanitizer;
    $config = new HtmlSanitizerConfig;

    foreach (Link::PRIVATE_REFERRER_POLICIES as $policy) {
        expect($sanitizer->sanitizeAttribute('a', 'referrerpolicy', strtoupper($policy), $config))->toBe($policy);
    }

    expect($sanitizer->sanitizeAttribute('a', 'referrerpolicy', 'unsafe-url', $config))->toBeNull()
        ->and($sanitizer->sanitizeAttribute('a', 'referrerpolicy', 'whatever', $config))->toBeNull()
        ->and($sanitizer->getSupportedElements())->toBe(['a']);
});

it('offers in the link dialog only what the sanitiser keeps', function (): void {
    expect(Link::PRIVATE_REFERRER_POLICIES)->not->toContain('unsafe-url', 'no-referrer-when-downgrade')
        ->and(array_diff(Link::PRIVATE_REFERRER_POLICIES, Link::REFERRER_POLICIES))->toBe([]);
});
