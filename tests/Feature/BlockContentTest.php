<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast as FilamentRichEditorStateCast;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\BlockContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts\RichEditorStateCast;

/**
 * Markup this editor never wrote, put into the shape its schema holds before the browser sees it.
 *
 * Every expectation below was measured, not reasoned about: the markup was parsed through the
 * field's cast and the result checked against the schema a running browser builds, with every
 * tool on. Each shape listed here failed that check, and the browser takes it anyway - it reads
 * the document Filament hands it without checking - until a click or a keystroke trips over it:
 * "Called contentMatchAt on a node with invalid content". What it is fitted into is what the
 * browser's own parser makes of the same markup.
 */

/**
 * The document the editor is handed, as a line that reads like the tree: `doc[paragraph["Hi"]]`.
 */
function castShape(mixed $state): string
{
    $document = app(RichEditorStateCast::class, ['richEditor' => editor()])->set($state);

    return nodeShape($document);
}

/**
 * @param  array<string, mixed>  $node
 */
function nodeShape(array $node): string
{
    if (($node['type'] ?? null) === 'text') {
        return json_encode($node['text'], JSON_UNESCAPED_UNICODE);
    }

    $children = array_map(
        static fn (mixed $child): string => nodeShape((array) $child),
        $node['content'] ?? [],
    );

    return $node['type'].($children === [] ? '' : '['.implode(', ', $children).']');
}

it('puts a picture standing on its own into a paragraph', function (): void {
    // The one that was found: a picture is inline, `doc` holds only blocks, and the editor
    // could neither select the picture nor show its toolbar.
    expect(castShape('<p>Text</p><img src="/a.png">'))->toBe('doc[paragraph["Text"], paragraph[image]]')
        ->and(castShape('<img src="/a.png"><p>Text</p>'))->toBe('doc[paragraph[image], paragraph["Text"]]');
});

it('fits markup from elsewhere the way the browser would have', function (string $html, string $shape): void {
    expect(castShape($html))->toBe($shape);
})->with([
    // A `<div>` is no node of this schema: tiptap-php drops it and leaves its words at the top.
    'text in a div' => ['<div>Text in einem div</div>', 'doc[paragraph["Text in einem div"]]'],
    'a line break between paragraphs' => ['<p>a</p><br><p>b</p>', 'doc[paragraph["a"], paragraph[hardBreak], paragraph["b"]]'],
    'a table cell without a paragraph' => [
        '<table><tr><th>Spalte</th></tr><tr><td>Eins</td></tr></table>',
        'doc[table[tableRow[tableHeader[paragraph["Spalte"]]], tableRow[tableCell[paragraph["Eins"]]]]]',
    ],
    'an empty table cell' => [
        '<table><tr><td></td><td>b</td></tr></table>',
        'doc[table[tableRow[tableCell[paragraph], tableCell[paragraph["b"]]]]]',
    ],
    'a quotation without a paragraph' => ['<blockquote>Ein Zitat</blockquote>', 'doc[blockquote[paragraph["Ein Zitat"]]]'],
    'an empty quotation' => ['<blockquote></blockquote>', 'doc[blockquote[paragraph]]'],
    'a picture opening a list item' => [
        '<ul><li><img src="/a.png"> mit Text</li></ul>',
        'doc[bulletList[listItem[paragraph[image, " mit Text"]]]]',
    ],
    'an empty list item' => ['<ul><li></li></ul>', 'doc[bulletList[listItem[paragraph]]]'],
]);

it('keeps a nested list beside its item\'s words rather than inside them', function (): void {
    // Filament's own repair of a list item wraps everything in it into one paragraph once its
    // first child is text - the nested list included, which is a block inside a paragraph and
    // no more valid than what it repaired. Every nested list written by anything but this
    // editor has this shape: `<li>One<ul>`.
    expect(castShape('<ul><li>One<ul><li>Two</li></ul></li></ul>'))
        ->toBe('doc[bulletList[listItem[paragraph["One"], bulletList[listItem[paragraph["Two"]]]]]]')
        ->and(castShape('<ol><li>One<ol><li>Two</li></ol>Tail</li></ol>'))
        ->toBe('doc[orderedList[listItem[paragraph["One"], orderedList[listItem[paragraph["Two"]]], paragraph["Tail"]]]]');
});

it('does the same for an item of a task list, which filament does not look at', function (): void {
    expect(castShape('<ul data-type="taskList"><li data-type="taskItem" data-checked="true">Done<ul><li>x</li></ul></li></ul>'))
        ->toBe('doc[taskList[taskItem[paragraph["Done"], bulletList[listItem[paragraph["x"]]]]]]');
});

it('gives a list item that opens with a block the paragraph it has to open with', function (): void {
    // `paragraph block*`. The browser's parser lifts the nested list out of the item instead,
    // which moves it; an empty line in front keeps it where it was written.
    expect(castShape('<ul><li><ul><li>x</li></ul></li></ul>'))
        ->toBe('doc[bulletList[listItem[paragraph, bulletList[listItem[paragraph["x"]]]]]]');
});

it('puts a mention standing on its own into a paragraph', function (): void {
    expect(castShape([
        'type' => 'doc',
        'content' => [
            ['type' => 'mention', 'attrs' => ['id' => '1', 'char' => '@']],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo']]],
        ],
    ]))->toBe('doc[paragraph[mention], paragraph["Hallo"]]');
});

it('puts a file card standing on its own into a paragraph', function (): void {
    // The card is inline too, so that it can sit in a sentence.
    expect(castShape('<p>Der Bericht:</p><a href="/storage/q3.pdf" download="Q3.pdf">Q3.pdf</a>'))
        ->toBe('doc[paragraph["Der Bericht:"], paragraph[file]]');
});

it('makes one paragraph of a run, not one each', function (): void {
    // They were a single line before the element around them was dropped. The browser splits a
    // caption from its picture here, because it still sees the `<figcaption>`; by the time
    // the document reaches PHP nothing says where one ended.
    expect(castShape('<figure><img src="/a.png"><figcaption>Bildunterschrift</figcaption></figure>'))
        ->toBe('doc[paragraph[image, "Bildunterschrift"]]');
});

it('leaves a document alone that the editor wrote', function (): void {
    // Inline beside inline is a sentence, and a heading or a paragraph holds exactly that.
    $html = '<h2>Titel <img src="/badge.svg"></h2><p>Ein <img src="/a.png"> Bild</p>'
        .'<ul><li><p>Eins</p><ul><li><p>Zwei</p></li></ul></li></ul>'
        .'<table><tbody><tr><td><p>a</p></td></tr></tbody></table>'
        .'<blockquote><p>Zitat</p></blockquote>';

    expect(app(RichEditorStateCast::class, ['richEditor' => editor()])->set($html))
        ->toBe((new FilamentRichEditorStateCast(editor()))->set($html));
});

it('never moves a node it does not know', function (): void {
    // A project's own node may be inline or a block, and nothing on this side says which.
    // Wrapping a block in a paragraph is the worse of the two mistakes, so it is not made.
    $document = (new BlockContent)->apply([
        'type' => 'doc',
        'content' => [
            ['type' => 'somethingOfAProject'],
            ['type' => 'paragraph'],
        ],
    ]);

    expect(nodeShape($document))->toBe('doc[somethingOfAProject, paragraph]');
});

it('writes no attributes of its own', function (): void {
    // What a paragraph declares is the schema's; the browser fills in whatever is missing.
    $document = (new BlockContent)->apply(['type' => 'doc', 'content' => [['type' => 'text', 'text' => 'a']]]);

    expect($document['content'][0])->toBe(['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]]);
});
