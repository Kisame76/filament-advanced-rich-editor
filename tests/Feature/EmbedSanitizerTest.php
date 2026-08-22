<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedHostSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Filament binds one shared sanitiser config for the whole application, and the package's
 * service provider extends it while booting. Rebuilding it here would test a config that
 * nothing uses, so these go through the container's own.
 */
function sanitize(string $html): string
{
    return str($html)->sanitizeHtml()->toString();
}

it('leaves an iframe out of the page while the extension is off', function (): void {
    // Filament's sanitiser drops `<iframe>`, which is the right default: an editor that
    // frames arbitrary pages is one nobody asked for. This package only widens it when a
    // project says so.
    config()->set('filament-advanced-rich-editor.embed.sanitizer', false);

    expect(sanitize('<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'))
        ->not->toContain('<iframe');
})->skip('the sanitiser config is built once while booting, so this needs its own process');

it('keeps an embed from a host on the list', function (): void {
    $html = '<div class="fi-arte-embed" data-type="embed" style="aspect-ratio: 16 / 9;">'
        .'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Video" loading="lazy" allowfullscreen></iframe>'
        .'</div>';

    expect(sanitize($html))
        ->toContain('<iframe')
        ->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('aspect-ratio')
        ->toContain('loading="lazy"');
});

it('drops the source of an iframe pointing anywhere else', function (): void {
    // The stored HTML is what a database holds, and a database is not only written by this
    // editor. An iframe that arrived some other way keeps its element and loses its target.
    expect(sanitize('<iframe src="https://attacker.test/steal"></iframe>'))
        ->not->toContain('attacker.test');
});

it('refuses a host that merely ends in an allowed one', function (): void {
    expect(sanitize('<iframe src="https://youtube.com.attacker.test/embed/x"></iframe>'))
        ->not->toContain('attacker.test');
});

it('allows a subdomain of a host on the list', function (): void {
    expect(EmbedHostSanitizer::allows('player.vimeo.com', ['vimeo.com']))->toBeTrue()
        ->and(EmbedHostSanitizer::allows('vimeo.com', ['vimeo.com']))->toBeTrue()
        ->and(EmbedHostSanitizer::allows('evilvimeo.com', ['vimeo.com']))->toBeFalse()
        ->and(EmbedHostSanitizer::allows('vimeo.com.attacker.test', ['vimeo.com']))->toBeFalse();
});

it('empties a source that is not a url with a host at all', function (): void {
    // Emptied rather than returned as `null`, which is what the interface documents for
    // dropping an attribute. Only `symfony/html-sanitizer` v8 stops the chain on `null`;
    // before that the `null` reaches the next sanitiser for the same attribute, whose
    // signature will not take it, and the whole render dies instead of one source being
    // dropped. Requiring v8 is not open to us either - Laravel 11 and 12 ship Symfony 7.
    //
    // Nothing loads from an empty source, so what a page does is the same; what changes is
    // that it survives both versions.
    $sanitizer = new EmbedHostSanitizer(['youtube.com']);
    $config = new HtmlSanitizerConfig;

    expect($sanitizer->sanitizeAttribute('iframe', 'src', 'javascript:alert(1)', $config))->toBe('')
        ->and($sanitizer->sanitizeAttribute('iframe', 'src', '/relative/path', $config))->toBe('')
        ->and($sanitizer->sanitizeAttribute('iframe', 'src', 'https://attacker.test/x', $config))->toBe('')
        // ...and a host on the list still comes back untouched.
        ->and($sanitizer->sanitizeAttribute('iframe', 'src', 'https://youtube.com/embed/x', $config))
        ->toBe('https://youtube.com/embed/x');
});
