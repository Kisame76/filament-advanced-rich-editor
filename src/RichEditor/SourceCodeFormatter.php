<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Lays a document's HTML out so it can be read.
 *
 * A saved document is one long line - the editor writes no whitespace it was not given -
 * and a wall of markup is not something anyone finds a heading in. This puts every block on
 * a line of its own and indents by nesting depth.
 *
 * The one rule it will not break: inline content is never touched. Whitespace between
 * inline elements is part of the sentence, so `<p>a <em>b</em></p>` stays on one line, and
 * anything inside `<pre>` is copied through as it was written, where the whitespace IS the
 * content. Only whitespace between block tags is invented, and the parser drops that again
 * on the way back in - which is what makes the layout free.
 */
class SourceCodeFormatter
{
    protected const INDENT = '  ';

    /**
     * The blocks the editor can produce. Anything else is treated as inline and left where
     * it was found, which is the safe way round: a wrong guess about a block only costs a
     * line break, a wrong guess about an inline element would rewrite the text.
     *
     * @var array<int, string>
     */
    protected const BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'caption', 'colgroup', 'details', 'div',
        'dl', 'dd', 'dt', 'figure', 'figcaption', 'footer', 'form', 'h1', 'h2', 'h3', 'h4',
        'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section',
        'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];

    /**
     * Blocks that never contain other blocks, so they stay on one line with their content.
     *
     * @var array<int, string>
     */
    protected const VOIDS = ['hr', 'br', 'img', 'input', 'col'];

    public static function format(string $html): string
    {
        if (blank(trim($html))) {
            return '';
        }

        $out = '';
        $depth = 0;

        // One entry per open block: whether a block has been opened inside it. A block that
        // only ever held text closes on the same line it opened, which is what keeps a
        // paragraph a paragraph.
        $stack = [];

        foreach (static::tokenise($html) as $token) {
            if (! str_starts_with($token, '<')) {
                $out .= $token;

                continue;
            }

            // Copied through untouched, whitespace and all.
            if (str_starts_with($token, '<pre')) {
                $out .= static::open($out, $depth).$token;

                continue;
            }

            $name = static::nameOf($token);

            if (! in_array($name, static::BLOCKS, strict: true)) {
                $out .= $token;

                continue;
            }

            if (in_array($name, static::VOIDS, strict: true)) {
                $stack[array_key_last($stack)] = true;
                $out .= static::open($out, $depth).$token;

                continue;
            }

            if (str_starts_with($token, '</')) {
                $depth = max(0, $depth - 1);

                // Only a block that held blocks gets its closing tag on a line of its own.
                $out .= (array_pop($stack) ? static::open($out, $depth) : '').$token;

                continue;
            }

            if ($stack !== []) {
                $stack[array_key_last($stack)] = true;
            }

            $out .= static::open($out, $depth).$token;

            $depth++;
            $stack[] = false;
        }

        return trim($out, "\n");
    }

    /**
     * Splits into tags and the text between them, with `<pre>` blocks kept whole so their
     * insides are never seen as markup to lay out.
     *
     * @return array<int, string>
     */
    protected static function tokenise(string $html): array
    {
        $parts = preg_split(
            '/(<pre\b.*?<\/pre>|<[^>]+>)/is',
            $html,
            flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        return $parts === false ? [$html] : $parts;
    }

    protected static function nameOf(string $tag): string
    {
        preg_match('/^<\/?\s*([a-z0-9]+)/i', $tag, $matches);

        return strtolower($matches[1] ?? '');
    }

    /**
     * A line break and the indent for this depth - unless nothing has been written yet, or
     * the last thing written was already a line break.
     */
    protected static function open(string $out, int $depth): string
    {
        if ($out !== '' && ! str_ends_with($out, "\n")) {
            return "\n".str_repeat(static::INDENT, $depth);
        }

        return $out === '' ? '' : str_repeat(static::INDENT, $depth);
    }
}
