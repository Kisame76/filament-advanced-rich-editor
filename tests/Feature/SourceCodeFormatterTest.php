<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\SourceCodeFormatter;

it('puts every block on its own line', function (): void {
    expect(SourceCodeFormatter::format('<h2>Title</h2><p>Body</p><hr>'))
        ->toBe("<h2>Title</h2>\n<p>Body</p>\n<hr>");
});

it('indents what is nested', function (): void {
    expect(SourceCodeFormatter::format('<ul><li><p>one</p></li><li><p>two</p></li></ul>'))
        ->toBe(<<<'HTML'
        <ul>
          <li>
            <p>one</p>
          </li>
          <li>
            <p>two</p>
          </li>
        </ul>
        HTML);
});

it('leaves a line of prose alone', function (): void {
    // Breaking inside a block would change the text: whitespace between inline elements is
    // part of the sentence, not of the markup.
    $html = '<p>Ben &amp; Jerry sell <em>ice cream</em> <strong>daily</strong>.</p>';

    expect(SourceCodeFormatter::format($html))->toBe($html);
});

it('does not touch what is inside a code block', function (): void {
    // Whitespace in a `pre` is the content. Indenting it would rewrite the code.
    $html = "<pre><code>if (true) {\n    return 1\n}</code></pre>";

    expect(SourceCodeFormatter::format('<p>before</p>'.$html))
        ->toBe("<p>before</p>\n".$html);
});

it('formats the same document it was given', function (): void {
    $field = editor();

    $html = '<h2>Title</h2><p>Body <em>with</em> emphasis</p><ul><li><p>one</p></li></ul><blockquote><p>quoted</p></blockquote>';

    // The whole point of the source view is that what goes back in is what came out. The
    // parser drops whitespace between blocks, so the formatting has to survive the trip.
    expect($field->normaliseSourceHtml(SourceCodeFormatter::format($html)))
        ->toBe($field->normaliseSourceHtml($html));
});

it('survives markup it was not built for', function (): void {
    expect(SourceCodeFormatter::format(''))->toBe('')
        // An unclosed tag is not a reason to lose someone's document.
        ->and(SourceCodeFormatter::format('<p>open'))->toContain('open');
});
