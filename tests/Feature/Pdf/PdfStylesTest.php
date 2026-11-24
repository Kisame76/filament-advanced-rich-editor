<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfStyles;

/**
 * The inline styles the renderer writes, reduced to what DomPDF reads. Every case is one the
 * package's own markup carries: a figure, a floated or centred picture, a file card, a grid.
 */
it('spells logical sides as physical ones', function (string $style, string $expected): void {
    expect(PdfStyles::reduce($style))->toBe($expected);
})->with([
    'both inline margins' => ['margin-inline: auto', 'margin-left: auto; margin-right: auto;'],
    'start and end inline margins' => ['margin-inline: 0 1em', 'margin-left: 0; margin-right: 1em;'],
    'block margins' => ['margin-block: 4px', 'margin-top: 4px; margin-bottom: 4px;'],
    'a start margin' => ['margin-inline-start: 8px', 'margin-left: 8px;'],
    'an end margin' => ['margin-block-end: 8px', 'margin-bottom: 8px;'],
    'inline padding' => ['padding-inline: 2px 6px', 'padding-left: 2px; padding-right: 6px;'],
    'a start border' => ['border-inline-start: 3px solid #000', 'border-left: 3px solid #000;'],
    'a start border colour' => ['border-inline-start-color: #000', 'border-left-color: #000;'],
    'both inline borders' => ['border-inline: 1px solid #000', 'border-left: 1px solid #000; border-right: 1px solid #000;'],
    'a logical width' => ['inline-size: 120px', 'width: 120px;'],
    'a logical maximum width' => ['max-inline-size: 100%', 'max-width: 100%;'],
    'a logical height' => ['block-size: 40px', 'height: 40px;'],
    'a logical float' => ['float: inline-end', 'float: right;'],
    'a logical alignment' => ['text-align: start', 'text-align: left;'],
]);

it('writes rem as pixels against the browser default the styles were written for', function (): void {
    expect(PdfStyles::reduce('padding: 0.75rem 1rem; width: 2.25rem'))
        ->toBe('padding: 12px 16px; width: 36px;');
});

it('leaves em and percentages alone', function (): void {
    expect(PdfStyles::reduce('margin-left: 1.5em; width: 50%'))
        ->toBe('margin-left: 1.5em; width: 50%;');
});

it('turns flex and grid boxes into the block boxes CSS 2.1 has', function (string $display, string $expected): void {
    expect(PdfStyles::reduce("display: {$display}"))->toBe($expected);
})->with([
    'flex' => ['flex', 'display: block;'],
    'grid' => ['grid', 'display: block;'],
    'inline-flex' => ['inline-flex', 'display: inline-block;'],
    'inline-grid' => ['inline-grid', 'display: inline-block;'],
    'contents' => ['contents', ''],
    'block' => ['block', 'display: block;'],
]);

it('drops what only a flex or grid container reads', function (): void {
    expect(PdfStyles::reduce('display: flex; gap: 0.5rem; align-items: center; justify-content: center; flex: 0 0 auto; flex-direction: column; aspect-ratio: 16 / 9; color: #333'))
        ->toBe('display: block; color: #333;');
});

it('drops custom properties and the values a PDF engine cannot read', function (): void {
    expect(PdfStyles::reduce('--cols: repeat(2, minmax(0, 1fr)); color: var(--gray-600); background: color-mix(in oklab, red 10%, transparent); border-color: oklch(0.5 0.1 200); inline-size: fit-content; font-weight: 600'))
        ->toBe('font-weight: 600;');
});

it('drops an address, which would be a fetch', function (): void {
    expect(PdfStyles::reduce('background-image: url(http://169.254.169.254/latest); color: red'))
        ->toBe('color: red;');
});

it('keeps a data address from splitting the style it sits in', function (): void {
    // The semicolon inside `data:image/png;base64` ends nothing. Split there, the second half
    // would come back as a declaration of its own.
    expect(PdfStyles::reduce('background-image: url(data:image/png;base64,AAAA); color: red'))
        ->toBe('color: red;');
});

it('drops a fixed table layout, which DomPDF answers by ignoring every column width', function (): void {
    expect(PdfStyles::reduce('table-layout: fixed; width: 100%'))->toBe('width: 100%;');
});

it('lets a later declaration win, as a browser would', function (): void {
    expect(PdfStyles::reduce('margin-left: 4px; margin-inline-start: 8px'))->toBe('margin-left: 8px;');
});

it('ignores what is not a declaration', function (): void {
    expect(PdfStyles::reduce(' ; color ; : red; font-style: italic ;'))->toBe('font-style: italic;');
});

it('reduces the inline style of a file card to something with a box', function (): void {
    // The card as `FileCard` writes it. On screen a flex box; dropped to `inline` by DomPDF
    // its border would wrap the text line, not the card.
    $reduced = PdfStyles::reduce('box-sizing: border-box; display: inline-flex; align-items: center; vertical-align: top; gap: 0.75rem; max-width: 100%; margin: 0.25rem 0.5rem 0.25rem 0; padding: 0.75rem; border: 1px solid rgba(113, 113, 122, 0.25); border-radius: 0.5rem; text-decoration: none; color: inherit;');

    expect($reduced)
        ->toContain('display: inline-block;')
        ->toContain('margin: 4px 8px 4px 0;')
        ->toContain('padding: 12px;')
        ->toContain('border: 1px solid rgba(113, 113, 122, 0.25);')
        ->not->toContain('gap')
        ->not->toContain('align-items');
});
