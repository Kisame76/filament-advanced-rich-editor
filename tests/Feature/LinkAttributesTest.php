<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

it('carries every attribute a link needs through a round trip', function (): void {
    // Content is re-parsed on every hydration and again on every save, and the parser
    // keeps only what the schema declares. Filament's link mark knows `href`, `target`,
    // `rel` and `class` - so a language or a referrer policy is dropped the first time the
    // record is reopened, without anything saying so.
    $html = '<p><a href="/preise" target="_blank" rel="nofollow noopener" hreflang="de" referrerpolicy="no-referrer" id="ref">Preise</a></p>';

    expect(links(AdvancedRichContentRenderer::make($html)->toHtml()))->toBe([
        'count' => 1,
        'attributes' => [
            'href' => '/preise',
            'hreflang' => 'de',
            'id' => 'ref',
            'referrerpolicy' => 'no-referrer',
            'rel' => 'nofollow noopener',
            'target' => '_blank',
        ],
    ]);
});

it('renders one link rather than a link inside a link', function (): void {
    // Two extensions of the same name are both applied. Adding a widened link mark next to
    // Filament's - rather than in place of it - renders `<a><a>text</a></a>`, which is what
    // every attribute below would have been paid for with.
    expect(links(AdvancedRichContentRenderer::make('<p><a href="/x">L</a></p>')->toHtml())['count'])
        ->toBe(1);
});

it('drops a referrer policy that is not one', function (): void {
    // A misspelled policy is not a stricter policy - the browser falls back to its default
    // and the author is left believing the referrer is withheld.
    $html = '<p><a href="/x" referrerpolicy="no-referer">L</a></p>';

    expect(links(AdvancedRichContentRenderer::make($html)->toHtml())['attributes'])
        ->toBe(['href' => '/x']);
});

it('drops an anchor on a link that could not be linked to', function (): void {
    $html = '<p><a href="/x" id="not valid">L</a></p>';

    expect(links(AdvancedRichContentRenderer::make($html)->toHtml())['attributes'])
        ->toBe(['href' => '/x']);
});

it('keeps the protocols the renderer was configured with', function (): void {
    // The allow list lives in the options of the mark being replaced. Rebuilding the mark
    // from scratch instead of carrying them across would quietly widen it.
    $html = '<p><a href="javascript:alert(1)">L</a></p>';

    expect(links(AdvancedRichContentRenderer::make($html)->linkProtocols(['https'])->toHtml())['count'])
        ->toBe(0);
});

it('carries the attributes through the field, which is where a save goes', function (): void {
    // The field parses on hydration and again on dehydration, through its own editor. An
    // attribute the renderer keeps but the field drops is one that survives being shown and
    // disappears on the next save.
    $html = '<p><a href="/preise" hreflang="de" referrerpolicy="same-origin">Preise</a></p>';

    $tiptap = editor()->getTipTapEditor()->setContent($html);

    expect(links($tiptap->getHTML())['attributes'])
        ->toBe(['href' => '/preise', 'hreflang' => 'de', 'referrerpolicy' => 'same-origin']);
});

it('goes back to what Filament declares when the field turns the attributes off', function (): void {
    $html = '<p><a href="/preise" hreflang="de">Preise</a></p>';

    $tiptap = editor()->linkAttributes(false)->getTipTapEditor()->setContent($html);

    expect(links($tiptap->getHTML())['attributes'])->toBe(['href' => '/preise']);
});

it('reads the default for the attributes from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.link.attributes', false);

    expect(editor()->hasLinkAttributes())->toBeFalse()
        ->and(editor()->linkAttributes()->hasLinkAttributes())->toBeTrue();
});
