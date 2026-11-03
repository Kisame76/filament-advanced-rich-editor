<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\LinkPreviewAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\DocumentLinks;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkPreviews;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\LinkPreview;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Psr\Http\Message\ResponseInterface;

/**
 * A link drawn as a card: what the page says about itself, read once when the card is made and
 * kept in the document - so a page view never asks anybody else's server for anything.
 */

/** A one-pixel PNG, as small as a real favicon is not. */
const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

/**
 * @param  array<string, mixed>  $attrs
 */
function linkCard(array $attrs): string
{
    return AdvancedRichContentRenderer::make(['type' => 'doc', 'content' => [
        ['type' => 'linkPreview', 'attrs' => $attrs],
    ]])->toHtml();
}

describe('the card', function (): void {
    it('draws the title, the description and the site, with the icon inside it', function (): void {
        $html = linkCard([
            'href' => 'https://laravel.com/docs',
            'title' => 'Laravel - The PHP Framework',
            'description' => 'Laravel is a web application framework.',
            'site' => 'Laravel',
            'icon' => 'data:image/png;base64,'.TINY_PNG,
        ]);

        expect($html)->toContain('<a class="fi-arte-link-preview" data-type="link-preview" href="https://laravel.com/docs"')
            ->toContain('>Laravel - The PHP Framework</span>')
            ->toContain('>Laravel is a web application framework.</span>')
            // The sanitiser writes the `+` and `=` of base64 as entities, which a browser reads
            // back as the same address.
            ->and(html_entity_decode($html))->toContain('src="data:image/png;base64,'.TINY_PNG.'"')
            // Decorative: the name of the site is beside it. An empty `alt`, which a document
            // writes as the bare attribute.
            ->toMatch('/<img class="fi-arte-link-preview-icon"[^>]* role="presentation"[^>]* alt(="")?[ \/>]/')
            // Its shape travels with it: the page it lands on never loaded this package's CSS.
            ->toContain('style="'.LinkPreview::CARD_STYLE.'"');
    });

    it('survives being read back and written out again, unchanged', function (): void {
        // A save hands the editor its document back as markup, and the card has to come back
        // as the card - not as underlined text, which the link mark would make of it.
        $html = linkCard([
            'href' => 'https://laravel.com/docs',
            'title' => 'Laravel',
            'description' => 'A framework.',
            'site' => 'Laravel',
            'icon' => 'data:image/png;base64,'.TINY_PNG,
        ]);

        expect(AdvancedRichContentRenderer::make($html)->toHtml())->toBe($html);
    });

    it('falls back to the address and the host for what it was not told', function (): void {
        $html = linkCard(['href' => 'https://www.example.com/a/page']);

        expect($html)->toContain('fi-arte-link-preview-title" style="'.LinkPreview::TITLE_STYLE.'">https://www.example.com/a/page</span>')
            ->toContain('>example.com</span>')
            ->not->toContain('fi-arte-link-preview-description')
            ->not->toContain('<img');
    });

    it('reads a card somebody wrote by hand', function (): void {
        $html = AdvancedRichContentRenderer::make('<a data-type="link-preview" href="https://example.com/">Example</a>')->toHtml();

        expect($html)->toContain('class="fi-arte-link-preview"')
            ->toContain('>example.com</span>');
    });

    it('escapes what the page said about itself', function (): void {
        $html = linkCard(['href' => 'https://example.com/', 'title' => '<script>alert(1)</script>', 'site' => 'A & B']);

        expect($html)->not->toContain('<script>')
            ->toContain('&lt;script&gt;')
            ->toContain('A &amp; B');
    });

    it('draws nothing for an address that is not a page', function (string $href): void {
        expect(linkCard(['href' => $href, 'title' => 'Click']))->not->toContain('fi-arte-link-preview');
    })->with([
        'a script' => ['javascript:alert(1)'],
        'a hidden scheme' => ["java\nscript:alert(1)"],
        'a path' => ['/about'],
        'a data address' => ['data:text/html;base64,PHNjcmlwdD4='],
    ]);

    it('keeps an icon only as a small picture in a data address', function (mixed $icon, bool $kept): void {
        expect(LinkPreview::icon($icon) !== null)->toBe($kept);
    })->with([
        'a png' => ['data:image/png;base64,'.TINY_PNG, true],
        'an svg' => ['data:image/svg+xml;base64,PHN2Zy8+', true],
        'an icon' => ['data:image/x-icon;base64,AAABAA==', true],
        'somebody else\'s address' => ['https://example.com/favicon.ico', false],
        'a page in a data address' => ['data:text/html;base64,PHNjcmlwdD4=', false],
        'not base64' => ['data:image/svg+xml,<svg onload="alert(1)"/>', false],
        'too large' => ['data:image/png;base64,'.str_repeat('A', LinkPreview::ICON_LENGTH), false],
        'nothing' => [null, false],
    ]);

    it('is drawn in the same shape by the editor', function (): void {
        // Two halves, one card: `link-preview.js` writes these while somebody writes, and a
        // card that changed shape on save would be one nobody trusts.
        $script = (string) file_get_contents(__DIR__.'/../../resources/js/link-preview.js');

        foreach (['CARD_STYLE', 'TITLE_STYLE', 'DESCRIPTION_STYLE', 'SITE_STYLE', 'ICON_STYLE'] as $style) {
            expect($script)->toContain(constant(LinkPreview::class.'::'.$style));
        }

        expect($script)->toContain('title: '.LinkPreview::TITLE_LENGTH)
            ->toContain('description: '.LinkPreview::DESCRIPTION_LENGTH)
            ->toContain('site: '.LinkPreview::SITE_LENGTH)
            ->toContain('icon: '.LinkPreview::ICON_LENGTH);
    });

    it('is a link the link check finds, in either shape', function (): void {
        $tree = ['type' => 'doc', 'content' => [['type' => 'linkPreview', 'attrs' => ['href' => 'https://example.com/card']]]];

        expect(DocumentLinks::of($tree))->toBe(['https://example.com/card'])
            ->and(DocumentLinks::of(linkCard(['href' => 'https://example.com/card'])))->toBe(['https://example.com/card']);
    });
});

describe('the field', function (): void {
    it('offers no cards unless asked to', function (): void {
        // A card is a recommendation or a reading list - a few kinds of writing use a lot of
        // them and most use none, and making one has the server read the page.
        expect(editor()->hasLinkPreviews())->toBeFalse()
            ->and(editor()->getTools())->not->toHaveKey('linkPreview');

        config()->set('filament-advanced-rich-editor.link.previews', true);

        expect(editor()->hasLinkPreviews())->toBeTrue()
            ->and(editor()->linkPreviews(false)->hasLinkPreviews())->toBeFalse();
    });

    it('is found in the slash menu and not on the bar once it is on', function (): void {
        $names = array_merge(...array_map(
            static fn (array $group): array => array_column($group['items'], 'name'),
            SlashMenu::for(editor()->linkPreviews())['groups'],
        ));

        expect(editor()->linkPreviews()->getTools())->toHaveKeys(['linkPreview', 'linkPreviewEdit', 'linkPreviewDelete'])
            ->and($names)->toContain('linkPreview')
            ->and(array_merge(...toolbarShape(editor()->linkPreviews())))->not->toContain('linkPreview')
            ->and(toolbarShape(editor()->linkPreviews()->toolbarButtons([['bold', 'linkPreview']])))->toBe([['bold', 'linkPreview']])
            // A field that switched cards off and still names the button keeps rendering.
            ->and(toolbarShape(editor()->toolbarButtons([['bold', 'linkPreview']])))->toBe([['bold']]);
    });

    it('turns the form into the card it inserts', function (): void {
        expect(LinkPreviewAction::attributesFrom([
            'href' => ' https://example.com/page ',
            'title' => "  A   page\n",
            'description' => '',
            'site' => null,
            'icon' => 'https://example.com/favicon.ico',
        ]))->toBe([
            'href' => 'https://example.com/page',
            'title' => 'A page',
            'description' => null,
            'site' => null,
            'icon' => null,
        ])->and(LinkPreviewAction::attributesFrom(['href' => 'mailto:a@example.com']))->toBeNull();
    });
});

describe('reading the page', function (): void {
    beforeEach(function (): void {
        Http::preventStrayRequests();

        resolvingHosts([
            'example.com' => ['93.184.215.14'],
            'www.example.com' => ['93.184.215.14'],
            'cdn.example.com' => ['93.184.215.15'],
            'intranet.test' => ['10.0.0.8'],
        ]);
    });

    it('prefers what the page wrote for a card', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response('', 404),
            'example.com/*' => Http::response(<<<'HTML'
                <html><head>
                <title>Page title | Example</title>
                <meta name="description" content="The plain description.">
                <meta property="og:title" content="The card title">
                <meta property="og:description" content="  The card
                    description.  ">
                <meta property="og:site_name" content="Example Site">
                </head><body>Hi</body></html>
                HTML, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        expect(LinkPreviews::make()->read('https://example.com/page'))->toBe([
            'href' => 'https://example.com/page',
            'title' => 'The card title',
            'description' => 'The card description.',
            'site' => 'Example Site',
            'icon' => null,
        ]);
    });

    it('falls back to the title and the meta description', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response('', 404),
            'example.com/*' => Http::response('<title> Only a title </title><meta name="description" content="Only a description">', 200, ['Content-Type' => 'text/html']),
        ]);

        $preview = LinkPreviews::make()->read('https://example.com/');

        expect($preview['title'])->toBe('Only a title')
            ->and($preview['description'])->toBe('Only a description')
            ->and($preview['site'])->toBeNull();
    });

    it('reads a page in another character set', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response('', 404),
            'example.com/*' => Http::response(mb_convert_encoding('<title>Grüße aus Köln</title>', 'ISO-8859-1', 'UTF-8'), 200, ['Content-Type' => 'text/html; charset=ISO-8859-1']),
        ]);

        expect(LinkPreviews::make()->read('https://example.com/')['title'])->toBe('Grüße aus Köln');
    });

    it('cuts a title that is longer than a card', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response('', 404),
            'example.com/*' => Http::response('<title>'.str_repeat('a', 300).'</title>', 200, ['Content-Type' => 'text/html']),
        ]);

        expect(mb_strlen((string) LinkPreviews::make()->read('https://example.com/')['title']))->toBe(LinkPreview::TITLE_LENGTH + 1);
    });

    it('cuts by characters, the way the dialog and the editor count them', function (): void {
        // A Japanese title the dialog accepted kept its length in the document, rather than
        // being cut at half of it by a count of how wide it is drawn.
        $title = str_repeat('日本語', 50);

        expect(LinkPreview::text($title, LinkPreview::TITLE_LENGTH))->toBe($title)
            ->and(LinkPreview::text(str_repeat('語', 201), LinkPreview::TITLE_LENGTH))->toBe(str_repeat('語', 200).'…')
            ->and(LinkPreview::text("  A\n  title  ", 200))->toBe('A title');
    });

    it('keeps the top of a page larger than a card reads, where its head is', function (): void {
        // Stopped transfers hand back no response, so the status and headers are kept as they
        // arrive and the body is read from where it was written. `Http::fake()` never stops a
        // transfer, which is why the piece that puts them back together is asked directly.
        $cut = new class extends LinkPreviews
        {
            public static function assemble(?ResponseInterface $head, mixed $sink, int $bytes): ?Response
            {
                return self::cutShort($head, $sink, $bytes);
            }
        };

        $sink = fopen('php://temp', 'w+b');
        fwrite($sink, '<title>Long page</title>'.str_repeat('x', 3000));

        $page = $cut::assemble(new Psr7Response(200, ['Content-Type' => 'text/html']), $sink, 2000);

        expect($page?->body())->toHaveLength(2000)
            ->toStartWith('<title>Long page</title>')
            ->and($page?->header('Content-Type'))->toBe('text/html')
            // Nothing to keep from an answer that was not the page, or never said what it was.
            ->and($cut::assemble(new Psr7Response(404), $sink, 2000))->toBeNull()
            ->and($cut::assemble(null, $sink, 2000))->toBeNull();
    });

    it('brings the icon the page names, as a picture inside the card', function (): void {
        Http::fake([
            'cdn.example.com/icons/site.png' => Http::response(base64_decode(TINY_PNG), 200, ['Content-Type' => 'image/png']),
            'example.com/*' => Http::response('<link rel="shortcut icon" href="//cdn.example.com/icons/site.png"><title>T</title>', 200, ['Content-Type' => 'text/html']),
        ]);

        expect(LinkPreviews::make()->read('https://example.com/blog/post')['icon'])->toBe('data:image/png;base64,'.TINY_PNG);
    });

    it('asks for /favicon.ico where the page names no icon', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response(base64_decode(TINY_PNG), 200, ['Content-Type' => 'image/x-icon']),
            'example.com/*' => Http::response('<title>T</title>', 200, ['Content-Type' => 'text/html']),
        ]);

        // The type is read off the bytes rather than believed from the header.
        expect(LinkPreviews::make()->read('https://example.com/blog/post')['icon'])->toBe('data:image/png;base64,'.TINY_PNG);
    });

    it('leaves out an icon that is not a small picture', function (string $body): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response($body, 200, ['Content-Type' => 'image/png']),
            'example.com/*' => Http::response('<title>T</title>', 200, ['Content-Type' => 'text/html']),
        ]);

        expect(LinkPreviews::make()->read('https://example.com/')['icon'])->toBeNull();
    })->with([
        'a page' => ['<html><body>Not found</body></html>'],
        'a photograph' => [base64_decode(TINY_PNG).str_repeat("\0", 20_000)],
    ]);

    it('follows a redirect, and checks where it leads', function (): void {
        Http::fake([
            'example.com/favicon.ico' => Http::response('', 404),
            'example.com/old' => Http::response('', 301, ['Location' => '/new']),
            'example.com/new' => Http::response('<title>Moved here</title>', 200, ['Content-Type' => 'text/html']),
            'example.com/inside' => Http::response('', 302, ['Location' => 'http://intranet.test/secret']),
        ]);

        expect(LinkPreviews::make()->read('https://example.com/old')['title'])->toBe('Moved here')
            ->and(LinkPreviews::make()->read('https://example.com/inside'))->toBeNull();

        // The redirect into the private network was never followed.
        Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'intranet.test'));
    });

    it('reads nothing that is not a page on the public internet', function (string $href): void {
        expect(LinkPreviews::make()->read($href))->toBeNull();

        Http::assertNothingSent();
    })->with([
        'a private network' => ['http://intranet.test/'],
        'the machine itself' => ['http://127.0.0.1/'],
        'a host that does not resolve' => ['https://gone.test/'],
        'not a web address' => ['mailto:a@example.com'],
    ]);

    it('reads nothing from an answer that is not a page', function (int $status, string $type): void {
        Http::fake(['example.com/*' => Http::response('<title>T</title>', $status, ['Content-Type' => $type])]);

        expect(LinkPreviews::make()->read('https://example.com/file'))->toBeNull();
    })->with([
        'an error' => [404, 'text/html'],
        'a file' => [200, 'application/pdf'],
    ]);
});
