<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Phiki\Phiki;
use RuntimeException;
use Throwable;

/**
 * Colours the code blocks in rendered content.
 *
 * The colouring happens in PHP, on the page rather than in the editor. A highlighter in
 * the panel colours text that only its author ever sees, and the only one worth having in
 * a browser is measured in megabytes and needs a build step this package does not have.
 * What the reader sees is where it matters, and there it is free: the work happens once,
 * when the page is rendered.
 *
 * [Phiki](https://phiki.dev) does the work and is an optional dependency - it carries every
 * TextMate grammar and every theme, which is nine megabytes nobody who does not colour code
 * should be made to carry.
 *
 * The pass runs over the rendered HTML rather than over the document, because what a
 * highlighter produces is markup and a TipTap node can only return a tree of elements. It
 * runs before the sanitiser, so what it produces is sanitised like everything else.
 */
class CodeHighlighter
{
    /**
     * @param  string|BackedEnum  $theme  the single theme, used when no pair is given
     * @param  array{light: string|BackedEnum, dark: string|BackedEnum}|null  $themes
     */
    public function __construct(
        protected string|BackedEnum $theme = 'github-light',
        protected ?array $themes = null,
    ) {}

    public static function isAvailable(): bool
    {
        return class_exists(Phiki::class);
    }

    /**
     * Replaces every `<pre><code class="language-…">` with a coloured one.
     */
    public function apply(string $html): string
    {
        if (! static::isAvailable()) {
            throw new RuntimeException(
                'Highlighting code needs phiki/phiki. Install it with `composer require phiki/phiki`.',
            );
        }

        if (! str_contains($html, '<pre')) {
            return $html;
        }

        $document = new DOMDocument;

        // The wrapper id carries no `fi-arte-` prefix on purpose: it is scratch, it exists
        // only inside this throwaway document, and a name in the package's class namespace
        // would read like a style hook nothing styles.
        // The fragment is a piece of a page rather than a document, and it is already
        // encoded; the pi keeps DOMDocument from reading it as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-highlight-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $phiki = new Phiki;

        foreach (iterator_to_array((new DOMXPath($document))->query('//pre/code')) as $code) {
            if (! ($code instanceof DOMElement)) {
                continue;
            }

            $this->replace($document, $phiki, $code);
        }

        $root = $document->getElementById('arte-highlight-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    protected function replace(DOMDocument $document, Phiki $phiki, DOMElement $code): void
    {
        $language = $this->languageOf($code);
        $pre = $code->parentNode;

        // Guessing the language is guessing, and a block nobody labelled is shown as it is.
        if ($language === null || ! $pre instanceof DOMElement) {
            return;
        }

        try {
            // Cast: the call answers with a pending output object that renders on the way
            // to a string, rather than with the string itself.
            $highlighted = (string) $phiki->codeToHtml(
                $code->textContent,
                $language,
                $this->themes ?? $this->theme,
            );
        } catch (Throwable) {
            // An unknown language, or a grammar that could not be read. The block keeps the
            // code in it, which is the part anybody came for.
            return;
        }

        // Parsed as HTML in a document of its own and imported, rather than appended as
        // XML: what a highlighter writes is HTML, and `appendXML()` refuses anything that
        // is not well-formed XML - which HTML is under no obligation to be.
        $imported = $this->parse($document, $highlighted);

        if ($imported === null) {
            return;
        }

        $pre->parentNode?->replaceChild($imported, $pre);
    }

    /**
     * The highlighted markup as nodes belonging to the document being rewritten.
     */
    protected function parse(DOMDocument $document, string $html): ?\DOMNode
    {
        $snippet = new DOMDocument;

        $loaded = @$snippet->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-highlight-snippet">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        $wrapper = $loaded ? $snippet->getElementById('arte-highlight-snippet') : null;

        if (! $wrapper instanceof DOMElement) {
            return null;
        }

        $this->letCodeInheritColour($snippet);

        $fragment = $document->createDocumentFragment();

        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $fragment->appendChild($document->importNode($child, true));
        }

        return $fragment->hasChildNodes() ? $fragment : null;
    }

    /**
     * Puts the block's colour on the `<code>` as well as on the `<pre>`.
     *
     * The highlighter writes it once, on the `<pre>`, and everything inside takes it by
     * inheritance - which loses to any rule that names `code` directly. Filament's prose
     * styles do exactly that (`.fi-prose code { color: var(--prose-code-color) }`), and so
     * does Tailwind's typography plugin. In a dark panel that is white text over the light
     * theme's white background, and the tokens the theme gives no colour of their own - the
     * brackets, the commas, the spaces - disappear while the coloured ones stay. What that
     * reads as is a highlighter that swallowed half the syntax.
     *
     * `inherit` rather than the colour itself, so that a project swapping the pair over
     * still only has to swap it on the `<pre>` - the same reason the list marker is written
     * inline: inline beats a stylesheet, and one place to change beats two.
     */
    protected function letCodeInheritColour(DOMDocument $snippet): void
    {
        foreach (iterator_to_array($snippet->getElementsByTagName('code')) as $code) {
            $style = trim($code->getAttribute('style'));

            // First, so that anything the highlighter wrote for this element still wins:
            // the last declaration of a property is the one that counts.
            $code->setAttribute('style', trim('color: inherit;'.($style === '' ? '' : ' '.$style)));
        }
    }

    /**
     * The language a block declares, out of the `language-…` class TipTap writes.
     */
    protected function languageOf(DOMElement $code): ?string
    {
        preg_match('/(?:^|\s)language-([A-Za-z0-9_+-]+)/', $code->getAttribute('class'), $matches);

        return $matches[1] ?? null;
    }
}
