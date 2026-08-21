<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

it('leaves Filament\'s own renderer alone', function (): void {
    // Installing a package must not change what every other package's content renders as.
    expect(RichContentRenderer::make('<p>Body</p>'))
        ->not->toBeInstanceOf(AdvancedRichContentRenderer::class);
});

it('takes the renderer over where the project asks it to', function (): void {
    // One line in a service provider, so `$record->content` on a model rich content
    // attribute - which builds Filament's renderer itself and takes no arguments - also
    // gets the anchors and the Markdown.
    AdvancedRichContentRenderer::bind();

    expect(RichContentRenderer::make('<p>Body</p>'))
        ->toBeInstanceOf(AdvancedRichContentRenderer::class);
});

it('still renders the content it was handed after taking over', function (): void {
    AdvancedRichContentRenderer::bind();

    expect(RichContentRenderer::make('<h2>Intro</h2>')->toHtml())->toBe('<h2>Intro</h2>');
});
