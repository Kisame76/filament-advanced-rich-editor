<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * The attributes of the first `<iframe>` in a fragment, and how many wrappers there are.
 *
 * @return array{count: int, wrapper: array<string, string>, iframe: array<string, string>}
 */
function embeds(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $read = static function (?DOMElement $element): array {
        $attributes = [];

        foreach ($element?->attributes ?? [] as $attribute) {
            $attributes[$attribute->nodeName] = $attribute->nodeValue;
        }

        ksort($attributes);

        return $attributes;
    };

    $wrappers = (new DOMXPath($document))->query('//div[@data-type="embed"]');

    return [
        'count' => $wrappers->count(),
        'wrapper' => $read($wrappers->item(0)),
        'iframe' => $read($document->getElementsByTagName('iframe')->item(0)),
    ];
}

it('renders a stored embed as a framed video', function (): void {
    $stored = '<div class="fi-arte-embed" data-type="embed" style="aspect-ratio: 16 / 9;">'
        .'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>'
        .'</div>';

    $rendered = embeds(AdvancedRichContentRenderer::make($stored)->toHtml());

    expect($rendered['count'])->toBe(1)
        ->and($rendered['iframe']['src'])->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and($rendered['iframe']['loading'])->toBe('lazy')
        ->and($rendered['iframe']['allowfullscreen'])->toBe('true')
        ->and($rendered['wrapper']['style'])->toContain('aspect-ratio: 16 / 9');
});

it('survives the round trip a save goes through', function (): void {
    // Content is re-parsed on hydration and again on dehydration. An embed the parser
    // cannot read back is one that vanishes the first time the record is reopened.
    $stored = '<div class="fi-arte-embed" data-type="embed" style="aspect-ratio: 4 / 3;">'
        .'<iframe src="https://player.vimeo.com/video/76979871" title="A film"></iframe>'
        .'</div>';

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect(embeds($once)['iframe']['src'])->toBe('https://player.vimeo.com/video/76979871')
        ->and(embeds($once)['iframe']['title'])->toBe('A film')
        ->and(embeds($once)['wrapper']['style'])->toContain('4 / 3')
        ->and($twice)->toBe($once);
});

it('rebuilds the embed url rather than trusting the one it was given', function (): void {
    // A watch link cannot be framed - YouTube answers "refused to connect" - and this is
    // where a document written by hand, or by an import, gets put right.
    $stored = '<div data-type="embed"><iframe src="https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;t=90"></iframe></div>';

    expect(embeds(AdvancedRichContentRenderer::make($stored)->toHtml())['iframe']['src'])
        ->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90');
});

it('renders nothing at all for a host it does not know', function (): void {
    // The sanitiser would drop the `src` and leave an empty frame behind. Refusing to build
    // the element is the honest answer: there is no video here.
    $stored = '<div data-type="embed"><iframe src="https://attacker.test/steal"></iframe></div>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->not->toContain('<iframe')
        ->not->toContain('attacker.test');
});

it('claims only the wrappers that are its own', function (): void {
    // `data-type` is the one data attribute Filament's sanitiser keeps, so several things
    // ride on it - grids, grid columns, custom blocks. The parser cannot express a value in
    // its selector, so a rule matching the attribute alone would swallow all of them and
    // take their contents with it.
    $wrapped = '<div data-type="grid"><p>Kept</p></div>';

    expect(AdvancedRichContentRenderer::make($wrapped)->toHtml())->toContain('Kept');
});

it('is not an embed without something to frame', function (): void {
    // The wrapper says embed and there is no video in it: a document half-written by hand,
    // or one whose iframe a stricter sanitiser removed. Its text is not thrown away.
    $empty = '<div data-type="embed"><p>Left over</p></div>';

    expect(AdvancedRichContentRenderer::make($empty)->toHtml())->toContain('Left over');
});

it('renders a video that needs no stylesheet to have a shape', function (): void {
    // This package's stylesheet is loaded into the admin panel, not into the page the
    // content ends up on. An embed arriving there with only a class on it is a 300x150 box
    // in the corner - the shape is carried inline, where it travels with the markup.
    $stored = '<div data-type="embed"><iframe src="https://youtu.be/dQw4w9WgXcQ"></iframe></div>';

    $rendered = embeds(AdvancedRichContentRenderer::make($stored)->toHtml());

    expect($rendered['wrapper']['style'])->toContain('width: 100%')
        ->and($rendered['wrapper']['style'])->toContain('aspect-ratio: 16 / 9')
        ->and($rendered['iframe']['style'])->toContain('width: 100%')
        ->and($rendered['iframe']['style'])->toContain('height: 100%')
        ->and($rendered['iframe']['style'])->toContain('border: 0');
});
