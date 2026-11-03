<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use League\HTMLToMarkdown\HtmlConverter;
use Phiki\Phiki;

/**
 * What a project is told that asks for something a suggested package does: which package, and
 * the line that installs it. Only to be seen where the package is absent - which in this
 * repository is the `minimal` job in CI, and nowhere else.
 */
it('says what to install to render Markdown', function (): void {
    expect(fn () => AdvancedRichContentRenderer::make('<p>Text</p>')->toMarkdown())
        ->toThrow(RuntimeException::class, 'composer require league/html-to-markdown');
})->skip(class_exists(HtmlConverter::class), 'league/html-to-markdown is installed.');

it('says what to install to colour code', function (): void {
    // Phiki carries every grammar and every theme, which is nine megabytes nobody who does
    // not colour code should be made to carry.
    expect(fn () => AdvancedRichContentRenderer::make('<pre><code class="language-php">echo 1;</code></pre>')->highlightCode()->toHtml())
        ->toThrow(RuntimeException::class, 'composer require phiki/phiki');
})->skip(class_exists(Phiki::class), 'phiki/phiki is installed.');
