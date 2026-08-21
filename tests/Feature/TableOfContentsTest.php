<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TableOfContents;

it('nests a heading under the one it belongs to', function (): void {
    $toc = TableOfContents::make('<h2>Intro</h2><h3>Details</h3><h2>Outro</h2>')->asArray();

    expect($toc)->toBe([
        [
            'level' => 2,
            'text' => 'Intro',
            'id' => 'intro',
            'children' => [
                ['level' => 3, 'text' => 'Details', 'id' => 'details', 'children' => []],
            ],
        ],
        ['level' => 2, 'text' => 'Outro', 'id' => 'outro', 'children' => []],
    ]);
});

it('treats a skipped level as one step of nesting', function (): void {
    // Jumping from h2 to h4 is ordinary in a document nobody wrote to a style guide. The
    // reader means "this belongs under that", and a list that opened two levels for it
    // would be describing the markup rather than the document.
    $toc = TableOfContents::make('<h2>Intro</h2><h4>Aside</h4>')->levels([2, 3, 4])->asArray();

    expect($toc)->toHaveCount(1)
        ->and($toc[0]['children'])->toHaveCount(1)
        ->and($toc[0]['children'][0]['text'])->toBe('Aside')
        ->and($toc[0]['children'][0]['children'])->toBe([]);
});

it('closes back out when a heading outranks the one before it', function (): void {
    $toc = TableOfContents::make('<h3>Deep</h3><h2>Top</h2>')->levels([2, 3])->asArray();

    expect($toc)->toHaveCount(2)
        ->and($toc[0]['text'])->toBe('Deep')
        ->and($toc[1]['text'])->toBe('Top');
});

it('covers only the levels it was given', function (): void {
    $toc = TableOfContents::make('<h2>Kept</h2><h3>Skipped</h3>')->levels([2])->asArray();

    expect($toc)->toHaveCount(1)
        ->and($toc[0]['children'])->toBe([]);
});

it('has nothing to say about a document without headings', function (): void {
    expect(TableOfContents::make('<p>Body</p>')->asArray())->toBe([])
        ->and(TableOfContents::make('<p>Body</p>')->asHtml()->toHtml())->toBe('');
});

it('has nothing to say about no document at all', function (): void {
    expect(TableOfContents::make(null)->asArray())->toBe([]);
});

it('writes the list as nested ordered lists of links', function (): void {
    expect(TableOfContents::make('<h2>Intro</h2><h3>Details</h3>')->asHtml()->toHtml())
        ->toBe('<nav class="fi-arte-toc"><ol><li><a href="#intro">Intro</a><ol><li><a href="#details">Details</a></li></ol></li></ol></nav>');
});

it('escapes the heading text it puts into the list', function (): void {
    // The text comes out of a document a person typed into, and the list is built as a
    // string rather than through the sanitiser the content itself goes through.
    expect(TableOfContents::make('<h2>Tom &amp; Jerry &lt;3</h2>')->asHtml()->toHtml())
        ->toContain('>Tom &amp; Jerry &lt;3<');
});

it('links to exactly the anchors the rendered page carries', function (): void {
    // The bug this rules out: a list built by one slug algorithm and a page built by
    // another agree until two headings share a name, and then every duplicate link in
    // the list points at the wrong section - or at nothing.
    $content = '<h2>Setup</h2><h3>Setup</h3><h2>Setup</h2>';

    $anchors = str(AdvancedRichContentRenderer::make($content)->anchorHeadings()->toHtml())
        ->matchAll('/id="([^"]+)"/')
        ->all();

    $links = str(TableOfContents::make($content)->asHtml()->toHtml())
        ->matchAll('/href="#([^"]+)"/')
        ->all();

    expect($links)->toBe($anchors)
        ->and($links)->toBe(['setup', 'setup-2', 'setup-3']);
});

it('takes the class on the list from the caller', function (): void {
    // The list is drawn on the project's own page, which this package's stylesheet is not
    // loaded into, so the hook has to be renameable.
    expect(TableOfContents::make('<h2>Intro</h2>')->class('toc')->asHtml()->toHtml())
        ->toStartWith('<nav class="toc">');
});
