<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Phiki\Phiki;

it('leaves code alone until it is asked to colour it', function (): void {
    $stored = '<pre><code class="language-php">echo 1;</code></pre>';

    expect(AdvancedRichContentRenderer::make($stored)->toHtml())->toBe($stored);
});

it('colours a code block by the language it declares', function (): void {
    $stored = '<pre><code class="language-php">echo 1;</code></pre>';

    $html = AdvancedRichContentRenderer::make($stored)->highlightCode()->toHtml();

    expect($html)->toContain('<span')
        ->toContain('color:')
        ->toContain('echo')
        // The language stays readable in the markup, because a stylesheet or a copy button
        // on the page may want to know what it is looking at.
        ->toContain('language-php');
});

it('keeps the code itself exactly as it was written', function (): void {
    // Highlighting is decoration. A character that changed on the way through it would be
    // a wrong instruction on somebody's screen.
    $code = "if (\$a < 1 && \$b > 2) {\n    echo 'done';\n}";
    $stored = '<pre><code class="language-php">'.e($code).'</code></pre>';

    $html = AdvancedRichContentRenderer::make($stored)->highlightCode()->toHtml();
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES);

    expect(trim($text))->toBe($code);
});

it('leaves a code block without a language as plain text', function (): void {
    // Guessing the language is guessing. A block nobody labelled is shown as it is.
    $stored = '<pre><code>some words</code></pre>';

    expect(AdvancedRichContentRenderer::make($stored)->highlightCode()->toHtml())
        ->toBe($stored);
});

it('leaves a language nothing knows about as plain text', function (): void {
    $stored = '<pre><code class="language-klingon">nuqneH</code></pre>';

    expect(AdvancedRichContentRenderer::make($stored)->highlightCode()->toHtml())
        ->toContain('nuqneH')
        ->not->toContain('<span');
});

it('carries a second theme for dark mode when it is given two', function (): void {
    $stored = '<pre><code class="language-php">echo 1;</code></pre>';

    $html = AdvancedRichContentRenderer::make($stored)
        ->highlightCode(themes: ['light' => 'github-light', 'dark' => 'github-dark'])
        ->toHtml();

    // Both themes ride in the same markup: the light one as ordinary colours, the dark one
    // as custom properties a stylesheet swaps in. Rendering twice is the alternative, and
    // it doubles the page.
    expect($html)->toContain('--phiki-dark-color')
        ->toContain('phiki-themes');
});

it('leaves everything that is not code alone', function (): void {
    $stored = '<h2>Title</h2><p>Body <code>inline</code></p><pre><code class="language-php">echo 1;</code></pre>';

    $html = AdvancedRichContentRenderer::make($stored)->highlightCode()->toHtml();

    expect($html)->toContain('<h2>Title</h2>')
        ->toContain('<p>Body <code>inline</code></p>');
});

it('says what to install when the highlighter is missing', function (): void {
    // Phiki carries every grammar and every theme, which is nine megabytes nobody who does
    // not colour code should be made to carry.
    expect(class_exists(Phiki::class))->toBeTrue();
})->skip('the exception can only be seen where phiki is absent, which is not this suite');
