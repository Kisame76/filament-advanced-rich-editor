<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * A long text, cut to a length a teaser or a meta description can hold.
 *
 * A pure function over a string, deliberately: the hard part of an excerpt is where the
 * cut falls, and that is decided by counting characters and looking for spaces - neither
 * of which needs a document, a renderer or a container to be checked. What builds the
 * text is `AdvancedRichContentRenderer::toExcerpt()`; what shortens it is here.
 */
class Excerpt
{
    /**
     * Whitespace that cannot end an excerpt, plus the punctuation that reads as a typo in
     * front of an ellipsis. A comma, a colon or a dash is the middle of a sentence said
     * out loud; a full stop is not, which is why it is not on this list.
     */
    protected const TRAILING = " \t\n\r\0\x0B,;:·-–—/|";

    /**
     * What a finished sentence ends with.
     *
     * An ellipsis after one of these is two marks doing one job - "Der Satz ist zu Ende.…"
     * - so the ellipsis is left off. The cut lands here more often than it looks: it backs
     * up to the last space, and the word before a space is regularly the last of a
     * sentence.
     */
    protected const SENTENCE_END = ['.', '!', '?', '…', '。', '！', '？'];

    public static function from(string $text, int $characters, string $end = '…'): string
    {
        $text = static::collapse($text);

        if ($characters < 1 || $text === '') {
            return '';
        }

        if (mb_strlen($text) <= $characters) {
            return $text;
        }

        $cut = mb_substr($text, 0, $characters);

        // Only when the cut fell inside a word. A cut whose next character is a space
        // already sits on a word boundary, and backing up from there would drop a whole
        // word that fit.
        if (mb_substr($text, $characters, 1) !== ' ') {
            $lastSpace = mb_strrpos($cut, ' ');

            // A single word longer than the whole budget has no boundary to back up to,
            // and half of it is a better answer than none of it.
            if ($lastSpace !== false && $lastSpace > 0) {
                $cut = mb_substr($cut, 0, $lastSpace);
            }
        }

        $cut = rtrim($cut, static::TRAILING);

        if ($cut === '') {
            return '';
        }

        return static::endsASentence($cut) ? $cut : $cut.$end;
    }

    /**
     * One line of running text out of however many lines and blocks it arrived as.
     *
     * `\s` does not match a non-breaking space, and this package ships a button that
     * inserts one - the German typography it exists for puts one on a page several times
     * over. `\p{Z}` is every space separator Unicode knows, which is the class that
     * actually describes "somewhere a line may be broken".
     */
    protected static function collapse(string $text): string
    {
        return trim((string) preg_replace('/[\p{Z}\s]+/u', ' ', $text));
    }

    protected static function endsASentence(string $text): bool
    {
        return in_array(mb_substr($text, -1), static::SENTENCE_END, strict: true);
    }
}
