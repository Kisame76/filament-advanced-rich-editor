<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ImageDecorativePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageDecorative;

/**
 * The attributes of the first `<img>` in a fragment, as a map.
 *
 * Read back rather than matched as a string, the decision `imageStyle()` makes next door and
 * for the same reason: the order a schema writes attributes in carries no meaning.
 *
 * @return array<string, string>
 */
function imageAttributes(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $image = $document->getElementsByTagName('img')->item(0);

    if ($image === null) {
        return [];
    }

    $attributes = [];

    foreach (iterator_to_array($image->attributes) as $attribute) {
        $attributes[$attribute->name] = $attribute->value;
    }

    return $attributes;
}

it('keeps a picture that was marked as carrying nothing', function (): void {
    $html = '<p><img src="/divider.png" alt="" role="presentation" /></p>';

    expect(imageAttributes(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toMatchArray(['role' => 'presentation', 'alt' => '']);
});

it('reads none as the synonym it is, and writes the one spelling back', function (): void {
    // ARIA has two words for this and a document arriving from another editor may carry
    // either. What leaves is always the same one, so the stored markup does not depend on
    // which editor a paragraph was written in.
    $html = '<p><img src="/divider.png" alt="" role="none" /></p>';

    expect(imageAttributes(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toMatchArray(['role' => 'presentation']);
});

it('writes the empty description alongside the role', function (): void {
    // The role on its own is half the pair, and the missing half is the one a screen reader
    // acts on: a picture with a role and a description is still read out.
    $html = '<p><img src="/divider.png" alt="Ein Trenner" role="presentation" /></p>';

    expect(imageAttributes(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toMatchArray(['role' => 'presentation', 'alt' => '']);
});

it('leaves an ordinary picture its description', function (): void {
    $html = '<p><img src="/cat.jpg" alt="Eine Katze" /></p>';

    expect(imageAttributes(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toMatchArray(['alt' => 'Eine Katze'])
        ->not->toHaveKey('role');
});

it('ignores a role that says something else', function (): void {
    // `role="button"` on a picture is somebody building a control out of it, which is a
    // different claim entirely and none of this extension's business.
    $html = '<p><img src="/cat.jpg" alt="Eine Katze" role="button" /></p>';

    expect(ImageDecorative::isPresentational('button'))->toBeFalse()
        ->and(imageAttributes(AdvancedRichContentRenderer::make($html)->toHtml()))
        ->toMatchArray(['alt' => 'Eine Katze']);
});

it('loses the mark without this package, which is why the extension exists', function (): void {
    // Filament's own renderer, which has never heard of the attribute.
    $html = '<p><img src="/divider.png" alt="" role="presentation" /></p>';

    expect(imageAttributes(RichContentRenderer::make($html)->toHtml()))->not->toHaveKey('role');
});

it('ships off, because the check it pays for ships off too', function (): void {
    // The mark buys one thing: an accessibility check that stops reporting a deliberate
    // empty `alt` as a forgotten one. That check is off unless a project asks, so on an
    // ordinary field this button has no visible effect at all.
    expect(pluginNames(editor()))->not->toContain(ImageDecorativePlugin::class)
        ->and(editor()->getDefaultFloatingToolbars()['image'])->not->toContain('imageDecorative');
});

it('registers the plugin where a field asked for the button', function (): void {
    expect(pluginNames(editor()->imageDecorative()))->toContain(ImageDecorativePlugin::class);
});

it('puts the switch beside the alt text where it is wanted', function (): void {
    $buttons = editor()->imageDecorative()->getDefaultFloatingToolbars()['image'];

    expect($buttons)->toContain('imageDecorative')
        ->and(array_search('imageDecorative', $buttons, strict: true))
        ->toBeGreaterThan(array_search('imageLink', $buttons, strict: true) - 2);
});

it('renders a marked picture whether or not any field offers the button', function (): void {
    // The rendering half is not gated: a document marked elsewhere keeps its role on the
    // page, which is the rule this package states four times over in the renderer.
    expect(imageAttributes(AdvancedRichContentRenderer::make('<p><img src="/d.png" alt="" role="presentation" /></p>')->toHtml()))
        ->toMatchArray(['role' => 'presentation']);
});
