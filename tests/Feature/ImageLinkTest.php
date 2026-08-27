<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageLinkPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;

$render = fn (string $html): string => AdvancedRichContentRenderer::make($html)->toHtml();

it('wraps a picture that points somewhere', function () use ($render): void {
    $html = '<p><img src="/cat.jpg" data-href="/articles/7" /></p>';

    expect($render($html))
        ->toContain('<a href="/articles/7"')
        // The attribute says something the reader either gets as a link or does not get at
        // all, so it never reaches the page itself.
        ->not->toContain('data-href');
});

it('leaves a picture that points nowhere alone', function () use ($render): void {
    expect($render('<p><img src="/cat.jpg" /></p>'))->not->toContain('<a ');
});

it('opens in a new tab only where that was asked for, and never barefoot', function () use ($render): void {
    $html = '<p><img src="/cat.jpg" data-href="https://example.com" data-href-new-tab="true" /></p>';

    // `rel` is written whether or not anybody asked, the same reasoning the text link
    // makes: a new tab hands the opened page a handle on the window that opened it.
    expect($render($html))
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->and($render('<p><img src="/cat.jpg" data-href="https://example.com" /></p>'))
        ->not->toContain('target=');
});

it('puts the link inside the figure a caption builds, not around it', function () use ($render): void {
    // A caption is text about the picture rather than part of what is being linked, and an
    // anchor around the pair makes the words a click target nobody aimed at.
    $rendered = $render('<p><img src="/cat.jpg" data-caption="Eine Katze" data-href="/cat" /></p>');

    expect($rendered)->toContain('<figure')
        ->and($rendered)->toMatch('/<figure[^>]*>\s*<a href="\/cat">\s*<img/')
        ->and($rendered)->not->toMatch('/<a[^>]*>\s*<figure/');
});

it('refuses an address that is not one', function (): void {
    // The value ends up in an `href`. `toUnsafeHtml()` never meets the sanitiser, so a
    // scheme that runs code has to be refused here rather than downstream.
    foreach ([
        'javascript:alert(1)',
        'JavaScript:alert(1)',
        "java\nscript:alert(1)",
        'data:text/html;base64,PHNjcmlwdD4=',
        'vbscript:msgbox(1)',
    ] as $dangerous) {
        expect(ImageLink::normalise($dangerous))->toBeNull();
    }
});

it('keeps the addresses a document is actually written with', function (): void {
    foreach ([
        'https://example.com/a',
        'http://example.com',
        'mailto:someone@example.com',
        'tel:+491234',
        '/articles/7',
        '../up',
        '#section',
        // A path with a colon in it is a path, not a scheme: the slash comes first.
        '/a:b',
    ] as $fine) {
        expect(ImageLink::normalise($fine))->toBe($fine);
    }
});

it('does not put a link inside a link', function () use ($render): void {
    // Markup somebody wrote in the source view. A second anchor around it is something no
    // browser and no reader can make sense of.
    $rendered = $render('<p><a href="/outer"><img src="/cat.jpg" data-href="/inner" /></a></p>');

    expect(substr_count($rendered, '<a '))->toBe(1)
        ->and($rendered)->toContain('/outer')
        ->and($rendered)->not->toContain('data-href');
});

it('loses the link without this package, which is why the extension exists', function (): void {
    // Filament's own renderer, which has never heard of the attribute.
    expect(RichContentRenderer::make('<p><img src="/cat.jpg" data-href="/a" /></p>')->toHtml())
        ->not->toContain('<a ');
});

it('survives the php round trip', function (): void {
    // The attribute has to come back out of the schema, or reopening a record quietly drops
    // every link somebody put on a picture. Asked of the parsed document rather than of
    // rendered markup: by the time markup exists the pass has already turned the attribute
    // into an anchor, which would prove nothing about the parsing.
    $document = AdvancedRichContentRenderer::make('<p><img src="/cat.jpg" data-href="/a" data-href-new-tab="true" /></p>')
        ->toArray();

    $image = $document['content'][0]['content'][0];

    expect($image['type'])->toBe('image')
        ->and($image['attrs']['href'])->toBe('/a')
        ->and($image['attrs']['hrefNewTab'])->toBeTrue();
});

it('drops a dangerous address on the way in as well as on the way out', function (): void {
    // The parse side matters on its own: a document could arrive from a paste, a source
    // code edit or a database somebody else writes to, and the schema is the first thing
    // it meets.
    $document = AdvancedRichContentRenderer::make('<p><img src="/cat.jpg" data-href="javascript:alert(1)" /></p>')
        ->toArray();

    expect($document['content'][0]['content'][0]['attrs']['href'] ?? null)->toBeNull();
});

it('registers the plugin only where a field asked for the button', function (): void {
    expect(pluginNames(editor()))->toContain(ImageLinkPlugin::class)
        ->and(pluginNames(editor()->imageLink(false)))->not->toContain(ImageLinkPlugin::class);
});

it('puts the button with the description and the mark, not with the placements', function (): void {
    // All three are about what the picture means rather than where it sits.
    $buttons = editor()->getDefaultFloatingToolbars()['image'];

    expect($buttons)->toContain('imageLink')
        ->and(array_search('imageLink', $buttons, strict: true))
        ->toBeGreaterThan(array_search('imageDecorative', $buttons, strict: true))
        ->and(editor()->imageLink(false)->getDefaultFloatingToolbars()['image'])
        ->not->toContain('imageLink');
});
