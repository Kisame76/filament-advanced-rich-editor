<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;

/**
 * Markdown read back into a document, which is the mirror of `toMarkdown()`.
 *
 * The export had a converter to teach and this has three, because the direction is the
 * dangerous one: what `toMarkdown()` gets wrong is a string somebody reads, and what this
 * gets wrong is a column somebody stores. Markdown says more than this schema can hold -
 * footnotes, raw HTML, a `javascript:` url - and every one of those has a way of arriving
 * that looks like content and is not.
 *
 * @param  array<string, mixed>  $options
 * @param  array<int, ExtensionInterface>  $extensions
 * @return array<string, mixed>
 */
function importMarkdown(string $markdown, array $options = [], array $extensions = []): array
{
    return AdvancedRichContentRenderer::make()->fromMarkdown($markdown, $options, $extensions);
}

/**
 * The imported document as the markup a page would draw, so a test can say what arrived
 * rather than which nodes it arrived in.
 *
 * @param  array<string, mixed>  $options
 * @param  array<int, ExtensionInterface>  $extensions
 */
function importedHtml(string $markdown, array $options = [], array $extensions = []): string
{
    return AdvancedRichContentRenderer::make(importMarkdown($markdown, $options, $extensions))->toHtml();
}

/**
 * The types of the document's own children, which is where an invalid document shows.
 *
 * @param  array<string, mixed>  $document
 * @return array<int, string>
 */
function topLevelTypes(array $document): array
{
    return array_map(fn (array $node): string => $node['type'] ?? '', $document['content'] ?? []);
}

/**
 * Whether every list in a document holds only items of its own kind.
 *
 * @param  array<string, mixed>|array<int, mixed>  $node
 */
function listItemsAreHousedCorrectly(array $node): bool
{
    $kinds = ['taskList' => 'taskItem', 'bulletList' => 'listItem', 'orderedList' => 'listItem'];
    $type = $node['type'] ?? null;

    foreach ($node['content'] ?? [] as $child) {
        if (! is_array($child)) {
            continue;
        }

        if (isset($kinds[$type]) && ($child['type'] ?? null) !== $kinds[$type]) {
            return false;
        }

        if (! listItemsAreHousedCorrectly($child)) {
            return false;
        }
    }

    return true;
}

it('reads a markdown document into the document the editor stores', function (): void {
    $document = importMarkdown("## Intro\n\nSome **bold** text.");

    expect($document['type'])->toBe('doc')
        ->and(topLevelTypes($document))->toBe(['heading', 'paragraph'])
        ->and(importedHtml("## Intro\n\nSome **bold** text."))
        ->toContain('<h2', 'Intro', '<strong>bold</strong>');
});

it('keeps the boxes of a task list ticked', function (): void {
    // The mirror of the export, and the same reason: a task list is a list of decisions and
    // the decisions are the boxes. GitHub-flavoured Markdown writes `- [x]` as a disabled
    // `<input type="checkbox">`, which is markup no rich text schema has a node for - so
    // left alone the state, the only part anybody was tracking, is dropped on the way in.
    // Asked of the document rather than of the page, because the page is not where the
    // answer lives: Filament's sanitiser drops `data-checked` on the way out and the state
    // rides to the browser on a class instead. What was imported is what was stored.
    $document = json_encode(importMarkdown("- [x] Shipped\n- [ ] Open"));

    expect($document)->toContain('"type":"taskList"')
        ->and($document)->toContain('"checked":true')
        ->and($document)->toContain('"checked":false')
        ->and($document)->toContain('Shipped', 'Open');
});

it('leaves an ordinary list item alone', function (): void {
    $document = importMarkdown("- One\n- Two");

    expect(json_encode($document))->not->toContain('taskList')
        ->and(importedHtml("- One\n- Two"))->toContain('<li>', 'One', 'Two');
});

it('needs no plugin to read one', function (): void {
    // Written after the opposite was assumed and measured false. `TaskListPlugin` puts the
    // button on the toolbar; the *renderer* declares the two nodes unconditionally, on the
    // rule this class states six times over - a renderer that has to be told is one that
    // drops the thing the day somebody forgets to say so. So an import needs no more
    // permission than a table does.
    expect(json_encode(importMarkdown('- [x] Shipped')))->toContain('"type":"taskItem"');
});

it('never writes loose text into a document', function (): void {
    // The one that would have shipped silently. Markdown carries raw HTML by design, and a
    // `<div>` inside a paragraph closes that paragraph while the markup is parsed - which
    // leaves the words after it as a bare text node directly under `doc`, where `block+`
    // says nothing of the sort belongs.
    //
    // Nothing raises over it on either side, which was measured after the opposite was
    // assumed, and it is the reason this is worth repairing rather than trusting: the two
    // sides carry on *differently*. The editor coerces the text into a paragraph as it
    // loads and the renderer writes it out where it found it, so the same document is two
    // paragraphs in the field and one paragraph plus a loose word on the page.
    $document = importMarkdown('Ein <div>roh</div> Wort');

    expect(topLevelTypes($document))->not->toContain('text')
        // Spelled out rather than asked in pieces, because what makes it wrong is the shape
        // of the whole: every word inside a block, and no text between two of them.
        ->and(importedHtml('Ein <div>roh</div> Wort'))->toBe('<p>Ein</p><p>roh Wort</p>');
});

it('keeps a footnote instead of reading it as a link', function (): void {
    // Without the extension CommonMark reads `[^1]: Die Fußnote.` as a link reference
    // definition: the note's text disappears and `Text[^1]` becomes a link pointing at it.
    // Both halves are wrong and neither is visible in a schema diff, because a link is
    // perfectly valid markup.
    $html = importedHtml("Text[^1] und mehr.\n\n[^1]: Die Fußnote.");

    expect($html)->toContain('Die Fußnote.')
        ->and($html)->toContain('<sup>')
        ->and($html)->not->toContain('href="Fu');
});

it('leaves none of the footnote plumbing behind', function (): void {
    // A footnote renders as a pair of anchors between a marker and a note, and the ids they
    // point at do not survive the schema - so kept, they are dead links and a stray glyph
    // that an author has to delete by hand. What a footnote *is* survives without them: a
    // superscript marker, a rule, and a numbered list of notes.
    $html = importedHtml("Text[^1].\n\n[^1]: Die Fußnote.");

    expect($html)->not->toContain('#fn:1')
        ->and($html)->not->toContain('#fnref:1')
        ->and($html)->not->toContain('↩')
        ->and($html)->toContain('<hr', '<ol');
});

it('refuses a url that only a script could mean', function (): void {
    // The schema already drops a `javascript:` link, and that is the whole of what stops
    // this from being missed: an image's `src` is an attribute rather than a mark, so it is
    // stored exactly as written. Nothing executes - browsers stopped running `javascript:`
    // in `src` long ago - but a column is not a place to keep it either.
    $document = importMarkdown('[klick](javascript:alert(1)) ![x](javascript:alert(1))');

    expect(json_encode($document))->not->toContain('javascript:')
        // The text and the picture stay; only the address goes.
        ->and(json_encode($document))->toContain('klick');
});

it('lets the caller overrule the options it defaults to', function (): void {
    $document = importMarkdown('![x](javascript:alert(1))', ['allow_unsafe_links' => true]);

    expect(json_encode($document))->toContain('javascript:');
});

it('lets the caller add an extension of its own', function (): void {
    expect(importedHtml('"zitiert"', extensions: [new SmartPunctExtension]))
        ->toContain('“zitiert”');
});

it('reads what github-flavoured markdown adds on top', function (): void {
    // `Str::markdown()` is the GFM converter, so tables, strikethrough and bare urls arrive
    // without anything being registered here. Tables in particular need no permission from
    // the toolbar - Filament's renderer declares them unconditionally.
    expect(importedHtml("| a | b |\n|---|---|\n| 1 | 2 |"))->toContain('<table', '<td')
        ->and(importedHtml('~~weg~~'))->toContain('<s>')
        ->and(importedHtml('https://example.test'))->toContain('href="https://example.test"');
});

it('has nothing to read in an empty string', function (): void {
    // The mirror of `toMarkdown()` answering an empty record with an empty string. An empty
    // paragraph rather than an empty `content` array, because that is what Filament's own
    // state cast puts in a field nobody typed into - and because TipTap's PHP parser reads
    // the body of a parsed document without checking that there is one, so the empty string
    // has to be answered before it reaches there rather than after.
    foreach (['', '   ', "\n\n"] as $nothing) {
        expect(topLevelTypes(importMarkdown($nothing)))->toBe(['paragraph'])
            ->and(DocumentContent::isBlank(importMarkdown($nothing)))->toBeTrue();
    }
});

it('reads back what the export wrote', function (): void {
    // The two directions meet here, and a round trip is the only test that fails when
    // either one drifts.
    $markdown = "## Intro\n\nSome **bold** text.\n\n- [x] Shipped\n- [ ] Open";

    expect(AdvancedRichContentRenderer::make(importMarkdown($markdown))->toMarkdown())->toBe($markdown);
});

it('keeps the boxes of a task list whose items stand apart', function (): void {
    // A blank line between the items makes the list *loose*, and GitHub-flavoured Markdown
    // renders a loose item as `<li><p><input type="checkbox"> Fertig</p></li>` - the box one
    // level deeper than in a tight list. Read only as a direct child of the item it is
    // missed entirely, which loses the state of every box in a list written the ordinary way.
    $document = json_encode(importMarkdown("- [x] Fertig\n\n- [ ] Offen"));

    expect($document)->toContain('"type":"taskList"')
        ->and($document)->toContain('"checked":true')
        ->and($document)->toContain('"checked":false')
        // And the space that stood between the box and its label is gone with it. In a
        // loose item that space sits inside the paragraph rather than inside the item, so
        // trimming the item's first child - which is the paragraph - does nothing at all.
        ->and($document)->toContain('"text":"Fertig"')
        ->and($document)->not->toContain('" Fertig"');
});

it('splits a list that mixes boxes with plain bullets', function (): void {
    // `taskList` holds `taskItem+`. Marking the whole list because one item carried a box
    // puts a plain `listItem` inside it, which is not a document the schema describes - and
    // the plain item arrives with none of a task item's structure, so the stylesheet has
    // nothing to draw it with. Neither side coerces it, measured: the field and the page
    // agree on the same wrong shape.
    //
    // Each run keeps what it is instead. Splitting rather than converting, because a plain
    // bullet is not an unticked task - a list of things and a list of decisions are
    // different lists, and the author wrote both.
    $document = importMarkdown("- [x] angekreuzt\n- ganz normal\n- [ ] offen");

    expect(topLevelTypes($document))->toBe(['taskList', 'bulletList', 'taskList'])
        ->and(json_encode($document))->toContain('angekreuzt', 'ganz normal', 'offen');
});

it('never puts an item of one kind of list inside the other', function (): void {
    // The invariant behind the split, asked of the whole tree rather than of the shape a
    // single case happens to take.
    foreach ([
        "- [x] eins\n- zwei",
        "- eins\n- [ ] zwei",
        "- eins\n- [x] zwei\n- drei\n- [ ] vier",
        "- [x] nur\n- [ ] kästchen",
        "- gar\n- keine",
    ] as $markdown) {
        expect(listItemsAreHousedCorrectly(importMarkdown($markdown)))->toBeTrue(
            "A list built from \"{$markdown}\" holds an item of the wrong kind.",
        );
    }
});

it('never leaves loose text inside a block either', function (): void {
    // The repair used to walk the document's own children and stop. The cause it repairs -
    // an element the schema has no node for, unwrapped while the markup is parsed - happens
    // wherever that element was, and a `<div>` inside a quotation is not rarer than one
    // between two paragraphs.
    expect(importedHtml('> Ein <div>rohes</div> Wort'))
        ->toBe('<blockquote><p>Ein</p><p>rohes Wort</p></blockquote>');

    // And inside a list item, where the same raw block leaves the words split across three
    // children of the item.
    $document = importMarkdown("- Ein Absatz\n\n  <div>roh</div>\n\n  Wort");
    $item = $document['content'][0]['content'][0]['content'];

    expect(array_column($item, 'type'))->toBe(['paragraph', 'paragraph', 'paragraph']);
});

it('leaves a document alone that was never broken', function (): void {
    // The guard on the repair, and the reason it is not simply "wrap every loose child".
    // `listItem`, `tableCell`, `blockquote`, `callout` and `taskItem` all hold bare text
    // when TipTap's parser is handed ordinary markup - `<ul><li>One</li></ul>` parses to
    // `listItem[text]` and the browser agrees - so a rule that wrapped those would rewrite
    // every document that ever passed through here rather than repair anything.
    //
    // Only the root is unconditional, and that is measured rather than assumed: the browser
    // wraps `doc[text]` in a paragraph while the renderer prints it bare, which is the one
    // place the field and the page disagree.
    $list = importMarkdown("- One\n- Two");
    $cell = importMarkdown("| a |\n|---|\n| 1 |");

    expect(json_encode($list))->not->toContain('paragraph')
        ->and(json_encode($cell))->not->toContain('paragraph');
});

it('leaves every shape that only looks like the damage', function (string $markdown, string $html): void {
    // The guard rail, and it is written from a rule that was wrong first. The obvious
    // reading of "loose content" is "inline content sitting beside a block", and measured
    // against 433 real Markdown files that rule rewrote 27 of them: a paragraph appeared
    // inside every heading with a badge in it, a caption was split away from its picture,
    // a space between two badges became a paragraph of its own, and every nested list in
    // every document gained a wrapper it never had. None of them was damaged. They are
    // simply what the parser produces, and what the browser holds - inline beside
    // non-inline is the shape of an ordinary document, not of a broken one.
    //
    // A *paragraph* beside the loose run is the shape of a broken one, because a paragraph
    // closed early by an element inside it always leaves its first half behind as one. With
    // that rule the same 433 documents give a single hit, and it is a real split.
    expect(importedHtml($markdown))->toBe($html);
})->with([
    'a nested list' => ["- One\n  - Two", '<ul><li>One<ul><li>Two</li></ul></li></ul>'],
    'a picture in a sentence' => [
        'Ein Bild ![Katze](/katze.png) im Text.',
        '<p>Ein Bild <img src="/katze.png" alt="Katze" /> im Text.</p>',
    ],
    'a heading with a badge' => [
        '# Monolog [![CI](https://ci.test/b.svg)](https://ci.test)',
        '<h1>Monolog <a href="https://ci.test"><img src="https://ci.test/b.svg" alt="CI" /></a></h1>',
    ],
]);

it('puts a picture of its own into a paragraph, the way the editor does', function (): void {
    // The other half of the same measurement, and the reason `image` and `mention` are on
    // the inline list at all. Filament declares both `inline: true`, read out of the
    // schema a running browser builds - so a picture that arrives as raw HTML rather than
    // as `![]()` lands under `doc` as its own child. The browser wraps it in a paragraph
    // while it loads and the renderer prints a bare `<img>` between two blocks, which is
    // the same divergence a loose word is. Four of those 433 documents open with one.
    expect(importedHtml("Ein Absatz.\n\n<img src=\"/a.png\" alt=\"x\">"))
        ->toBe('<p>Ein Absatz.</p><p><img src="/a.png" alt="x" /></p>');
});

it('reads a box that is not the first thing in its item as prose', function (): void {
    // Looking for the box anywhere inside the item is the wider rule that suggests itself,
    // and it is wrong twice. A checkbox is legal raw HTML, so one in a table cell or in a
    // second paragraph would promote a whole item - and its whole list - to a task list
    // nobody wrote; and a second box in one item would set the state last-one-wins, turning
    // a ticked item unticked. Both are silent.
    $promoted = json_encode(importMarkdown("- plain\n\n  | a |\n  |---|\n  | <input type=\"checkbox\"> |"));
    $flipped = json_encode(importMarkdown("- [x] a\n\n  <input type=\"checkbox\"> note"));

    expect($promoted)->not->toContain('taskItem')
        ->and($flipped)->toContain('"checked":true');
});

it('takes the box off a numbered list even though it cannot keep the state', function (): void {
    // `1. [x]` is a checkbox GitHub-flavoured Markdown really emits, and `taskList` is a
    // `<ul>` - so there is nowhere for the state to go and the item stays an ordinary one.
    // The box still has to be taken away by hand: left alone the parser drops it and keeps
    // the space that stood behind it, and the item is stored with an indent nobody typed.
    expect(importedHtml("1. [x] a\n2. [ ] b"))->toBe('<ol><li>a</li><li>b</li></ol>');
});
