<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileTypes;

/**
 * An uploaded document, drawn as a card rather than as an address: what the markup says,
 * what survives the round trip, and what survives the sanitiser - which is the half that
 * decides whether any of it reaches a reader.
 */
$render = fn (string $html): string => AdvancedRichContentRenderer::make($html)->toHtml();

it('draws a card with the kind, the name and the size on it', function () use ($render): void {
    $html = $render('<a href="/storage/q3.pdf" download="Bericht Q3.pdf" class="fi-arte-file">'
        .'<span class="fi-arte-file-name">Bericht Q3.pdf</span>'
        .'<span class="fi-arte-file-size">1,2 MB</span></a>');

    expect($html)->toContain('class="fi-arte-file"')
        ->and($html)->toContain('href="/storage/q3.pdf"')
        // The name, so the browser saves the file under it rather than under the hashed one
        // the disk gave it.
        ->and($html)->toContain('download="Bericht Q3.pdf"')
        ->and($html)->toContain('>PDF</span>')
        ->and($html)->toContain('>Bericht Q3.pdf</span>')
        ->and($html)->toContain('>1,2 MB</span>');
});

it('reads a plain download link as a card, so nothing has to be migrated', function () use ($render): void {
    // `download` is the attribute that says "this is a file to take away", which is why it
    // is the parse rule: a link written by hand or by another editor is already this node.
    $html = $render('<a href="/files/handbuch.docx" download>Handbuch</a>');

    expect($html)->toContain('class="fi-arte-file"')
        ->and($html)->toContain('>Handbuch</span>')
        // The name has no ending; the address does, and the tile is entitled to read it.
        ->and($html)->toContain('>DOCX</span>');
});

it('reads its own card back unchanged', function () use ($render): void {
    $once = $render('<a href="/storage/notes.zip" download="Archiv.zip" class="fi-arte-file">'
        .'<span class="fi-arte-file-name">Archiv.zip</span>'
        .'<span class="fi-arte-file-size">840 KB</span></a>');

    expect($render($once))->toBe($once);
});

it('leaves a link that is not a download alone', function () use ($render): void {
    $html = $render('<p><a href="/files/handbuch.docx">Handbuch</a></p>');

    expect($html)->not->toContain('fi-arte-file')
        ->and($html)->toContain('<a href="/files/handbuch.docx">Handbuch</a>');
});

it('refuses an address a browser would run instead of fetch', function () use ($render): void {
    // The whole of the attack, and the same guard the player has: a scheme that is not a
    // way of fetching a file turns a card into a script.
    expect($render('<a href="javascript:alert(1)" download>x</a>'))->not->toContain('fi-arte-file');
    expect($render('<a href="java&#10;script:alert(1)" download>x</a>'))->not->toContain('fi-arte-file');
});

it('drops a card with nothing behind it rather than drawing a dead one', function () use ($render): void {
    expect($render('<a download="a.pdf" class="fi-arte-file"><span class="fi-arte-file-name">a.pdf</span></a>'))
        ->not->toContain('fi-arte-file');
});

it('leaves out the size line where nobody knows the size', function () use ($render): void {
    // The roadmap's own caveat: "icon, name and size" presumes a library, and a hand-typed
    // address knows no size. A card reading "unknown" spends a line saying nothing.
    $html = $render('<a href="/files/a.pdf" download>a.pdf</a>');

    expect($html)->not->toContain('fi-arte-file-size')
        ->and($html)->toContain('fi-arte-file-name');
});

it('carries the attachment id the save walks', function () use ($render): void {
    // The data-loss guard: an id nothing walks is an id `cleanUpFileAttachments()` does not
    // spare, so the file goes while the document keeps pointing at it. See
    // `Media/FileAttachments::TYPES`.
    expect($render('<a href="/storage/a.pdf" download="a.pdf" data-id="doc-1">a.pdf</a>'))
        ->toContain('data-id="doc-1"');
});

it('survives the sanitiser whole, which is the only thing that matters', function () use ($render): void {
    // Measured rather than assumed, and it is why the tile holds letters and not a drawing:
    // `svg` is not on Symfony's safe element list, so an icon would be removed entirely and
    // the card would reach the page as a coloured gap.
    $sanitised = Str::sanitizeHtml($render(
        '<a href="/storage/q3.pdf" download="Bericht Q3.pdf" data-id="doc-1" class="fi-arte-file">'
        .'<span class="fi-arte-file-name">Bericht Q3.pdf</span>'
        .'<span class="fi-arte-file-size">1,2 MB</span></a>'
    ));

    expect($sanitised)->toContain('download="Bericht Q3.pdf"')
        ->and($sanitised)->toContain('data-id="doc-1"')
        ->and($sanitised)->toContain('class="fi-arte-file"')
        ->and($sanitised)->toContain('>PDF</span>')
        ->and($sanitised)->toContain('>1,2 MB</span>')
        // The shape travels in `style`, because the page the document ends up on never
        // loads this package's stylesheet.
        ->and($sanitised)->toContain('background-color: #dc2626');
});

it('names the kind and the colour off the ending, and falls back to the address', function (): void {
    expect(FileTypes::label('Bericht.pdf'))->toBe('PDF')
        ->and(FileTypes::label('Handbuch', '/files/a.docx'))->toBe('DOCX')
        ->and(FileTypes::label('Handbuch'))->toBe('FILE')
        ->and(FileTypes::label('/files/report.pdf?v=2'))->toBe('PDF')
        ->and(FileTypes::tint('a.pdf'))->toBe('#dc2626')
        ->and(FileTypes::tint('a.xlsx'))->toBe('#16a34a')
        ->and(FileTypes::tint('a.unknown'))->toBe(FileTypes::DEFAULT_TINT);
});

it('turns a byte count into the words a reader wants, once', function (): void {
    expect(ByteSize::format(0))->toBe('0 B')
        ->and(ByteSize::format(512))->toBe('512 B')
        // Laravel changes unit at nine tenths of the next one, not at the whole - so 940
        // bytes is "1 KB" here and "940 bytes" in a file manager. Left as the framework
        // has it: it is the same rounding every other size in a Laravel application uses,
        // and a card that rounded differently from the rest of the panel would be the
        // odd one out over a difference nobody acts on.
        ->and(ByteSize::format(940))->toBe('1 KB')
        ->and(ByteSize::format(1024 * 1024 * 3))->toBe('3.0 MB')
        ->and(ByteSize::format(-1))->toBeNull()
        ->and(ByteSize::format(null))->toBeNull()
        // Null rather than a placeholder: a card with no size line has one fewer thing on
        // it, which is exactly true of a file somebody linked to by address.
        ->and(ByteSize::format('not a number'))->toBeNull();
});

it('keeps a size only as the short label it is', function (): void {
    expect(ByteSize::label(' 1,2  MB '))->toBe('1,2 MB')
        ->and(ByteSize::label(''))->toBeNull()
        ->and(ByteSize::label(str_repeat('x', 40)))->toBeNull();
});

it('exports a card as the link and the name, and nothing else', function (): void {
    // Without a converter of its own a card is an `<a>` holding three spans, and the
    // converter runs them together: `[PDFQuartalsbericht Q3.pdf88 KB](/storage/…)`. The
    // kind and the size are drawn rather than written - a Markdown file claiming `88 KB`
    // about a file that has since been replaced is worse than one that never said.
    $markdown = AdvancedRichContentRenderer::make(
        '<p>Vorher</p>'
        .'<a href="/storage/q3.pdf" download="Quartalsbericht Q3.pdf">'
        .'<span class="fi-arte-file-name">Quartalsbericht Q3.pdf</span>'
        .'<span class="fi-arte-file-size">88 KB</span></a>'
        .'<a href="/files/handbuch.docx" download>Handbuch</a>'
    )->toMarkdown();

    expect($markdown)->toContain('[Quartalsbericht Q3.pdf](/storage/q3.pdf)')
        ->and($markdown)->toContain('[Handbuch](/files/handbuch.docx)')
        ->and($markdown)->not->toContain('88 KB')
        ->and($markdown)->not->toContain('PDF]');
});

it('leaves an ordinary link an ordinary link in Markdown', function (): void {
    // The trap the first version fell into: reading the link's own text as a name made
    // every link in the document a block on its own line.
    $markdown = AdvancedRichContentRenderer::make(
        '<p>Ein <a href="/x">gewöhnlicher Link</a> mitten im Satz.</p>'
    )->toMarkdown();

    expect($markdown)->toBe('Ein [gewöhnlicher Link](/x) mitten im Satz.');
});

it('keeps the parts of a card apart in plain text', function () use ($render): void {
    // What fills `<meta name="description">` and what a search index holds. `toText()`
    // reads the parsed node rather than the markup, and the parts of a card reach it as
    // the text of its spans - so without a separator between them the excerpt says
    // `Quartalsbericht Q3.pdf88 KB`. The space between the spans is that separator, and it
    // is invisible in the card because a flex container drops a white-space run between
    // two of its items.
    $card = $render('<a href="/storage/q3.pdf" download="Quartalsbericht Q3.pdf">'
        .'<span class="fi-arte-file-name">Quartalsbericht Q3.pdf</span>'
        .'<span class="fi-arte-file-size">88 KB</span></a>');

    $text = AdvancedRichContentRenderer::make($card)->toText();

    expect($text)->toContain('Quartalsbericht Q3.pdf')
        ->and($text)->not->toContain('Q3.pdf88');
});
