<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * What a registered custom block renders, on the way to a page.
 *
 * From Filament 5.11 on, the markup a registered block renders is the application's own: it
 * stays out of the sanitiser the rest of the document goes through, so a block can carry
 * Alpine or an embed. Before 5.11 the whole page went through it. The renderer follows the
 * release it runs on, and builds its own `toHtml()` to do so - Filament's stopped going
 * through `toUnsafeHtml()` in 5.11, which is where every pass this package adds lives.
 */
class TrustedMarkupBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'interactive';
    }

    public static function toHtml(array $config, array $data): string
    {
        return '<section x-data="{ open: false }"><button x-on:click="open = ! open">'.e($config['label']).'</button></section>';
    }
}

function trustsBlockHtml(): bool
{
    return version_compare((string) InstalledVersions::getVersion('filament/forms'), '5.11.0', '>=');
}

/**
 * @param  array<int, array<string, mixed>>  $content
 * @return array{type: string, content: array<int, array<string, mixed>>}
 */
function blockDocument(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

it('keeps what a registered block renders out of the sanitiser, from Filament 5.11 on', function (): void {
    $html = AdvancedRichContentRenderer::make(blockDocument([
        ['type' => 'customBlock', 'attrs' => ['id' => 'interactive', 'config' => ['label' => '<First>']]],
        ['type' => 'paragraph', 'content' => [['type' => 'mergeTag', 'attrs' => ['id' => 'untrusted']]]],
    ]))
        ->customBlocks([TrustedMarkupBlock::class])
        ->mergeTags(['untrusted' => new HtmlString('<span onclick="steal()">Outside the block</span>')])
        ->toHtml();

    // The block exactly as it rendered itself, and everything around it sanitised as before.
    expect($html)->toContain('<section x-data="{ open: false }"><button x-on:click="open = ! open">&lt;First&gt;</button></section>')
        ->toContain('Outside the block')
        ->not->toContain('onclick');
})->skip(! trustsBlockHtml(), 'Filament sanitises custom blocks along with everything else before 5.11');

it('sanitises what a registered block renders before Filament 5.11, as Filament does', function (): void {
    $html = AdvancedRichContentRenderer::make(blockDocument([
        ['type' => 'customBlock', 'attrs' => ['id' => 'interactive', 'config' => ['label' => 'First']]],
    ]))
        ->customBlocks([TrustedMarkupBlock::class])
        ->toHtml();

    expect($html)->not->toContain('x-on:click')
        ->not->toContain('x-data');
})->skip(trustsBlockHtml(), 'Filament keeps a registered block out of the sanitiser from 5.11 on');

it('never trusts a rendered block the document carries itself', function (): void {
    // `renderedCustomBlock` is what a render makes of a registered block. One that arrives
    // in the stored document was written by whoever wrote the document, and its markup is
    // theirs rather than the application's.
    $html = AdvancedRichContentRenderer::make(blockDocument([
        ['type' => 'renderedCustomBlock', 'attrs' => ['id' => 'interactive', 'config' => ['label' => 'Registered']], 'html' => '<p onclick="steal()">Forged</p>'],
    ]))
        ->customBlocks([TrustedMarkupBlock::class])
        ->toHtml();

    expect($html)->not->toContain('onclick');
});

it('runs its own passes over the document around a registered block', function (): void {
    // The point of building `toHtml()` here: Filament's own skips `toUnsafeHtml()` from 5.11
    // on, and with it the caption this image carries.
    $html = AdvancedRichContentRenderer::make(
        '<p><img src="/hafen.jpg" alt="Kräne im Nebel" data-caption="Hamburger Hafen, 1962"></p>'
        .'<div data-type="customBlock" data-id="interactive" data-config=\'{"label":"First"}\'></div>',
    )
        ->customBlocks([TrustedMarkupBlock::class])
        ->toHtml();

    expect($html)->toContain('<figcaption>Hamburger Hafen, 1962</figcaption>')
        ->toContain('First');

    if (trustsBlockHtml()) {
        expect($html)->toContain('<button x-on:click="open = ! open">First</button>');
    }
});
