<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * A one row table, from the widths its cells carry. `null` is a column nobody dragged.
 *
 * @param  array<int, ?string>  $widths
 */
function tableWith(array $widths): string
{
    $cells = '';

    foreach ($widths as $index => $width) {
        $attribute = ($width === null) ? '' : ' data-colwidth="'.$width.'"';
        $span = str_contains((string) $width, ',') ? ' colspan="2"' : ' colspan="1"';

        $cells .= '<td rowspan="1"'.$span.$attribute.'><p>'.$index.'</p></td>';
    }

    return '<table><tbody><tr>'.$cells.'</tr></tbody></table>';
}

it('renders a dragged column width as a width the page can use', function (): void {
    // The width is in the document and survives the editor's own round trip, but never
    // reaches the page: `data-colwidth` is not on the sanitiser's list, and an attribute is
    // not something CSS can read as a width anyway. A `<colgroup>` is what ProseMirror
    // itself draws, and both it and `style` come through the sanitiser.
    $html = AdvancedRichContentRenderer::make(tableWith(['220', null]))->toHtml();

    expect($html)->toContain('<colgroup>')
        ->toContain('width: 220px')
        // Without a fixed layout a column width is a suggestion the browser overrides as
        // soon as the text is wider, and the page stops looking like the editor.
        ->toContain('table-layout: fixed');
});

it('leaves a table nobody resized exactly as it was', function (): void {
    $stored = tableWith([null, null]);

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())->toBe($stored);
});

it('leaves a column without a width to the browser', function (): void {
    $html = AdvancedRichContentRenderer::make(tableWith(['220', null]))->toHtml();

    // Self-closing, because that is how the sanitiser serialises a void element.
    expect($html)->toContain('<colgroup><col style="width: 220px;" /><col /></colgroup>');
});

it('spreads a width across the columns a cell spans', function (): void {
    // `colwidth` carries one entry per column the cell covers, so a merged cell decides the
    // width of every column under it.
    $html = AdvancedRichContentRenderer::make(tableWith(['120,80']))->toHtml();

    expect($html)->toContain('<colgroup><col style="width: 120px;" /><col style="width: 80px;" /></colgroup>');
});

it('takes the attribute off the cells', function (): void {
    // It says something the reader is either shown properly or not at all - and the
    // sanitiser would drop it a moment later regardless.
    expect(AdvancedRichContentRenderer::make(tableWith(['220', null]))->toHtml())
        ->not->toContain('data-colwidth');
});

it('reads the widths off the first row, the way the editor draws them', function (): void {
    // ProseMirror builds its `<colgroup>` from the first row and ignores the rest, so a
    // width sitting only on a later row is not one the editor is showing either.
    $stored = '<table><tbody>'
        .'<tr><td rowspan="1" colspan="1"><p>a</p></td></tr>'
        .'<tr><td rowspan="1" colspan="1" data-colwidth="300"><p>b</p></td></tr>'
        .'</tbody></table>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())
        ->not->toContain('<colgroup>')
        ->not->toContain('table-layout');
});

it('keeps the width through the round trip a save goes through', function (): void {
    // Content is parsed on hydration and again on dehydration. This is the half that
    // already worked, and it has to keep working: the rendering fix must not touch what is
    // stored, or the editor would lose the width the page just started showing.
    $stored = tableWith(['220', null]);

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect($once)->toContain('data-colwidth="220"')
        ->and($twice)->toBe($once);
});
