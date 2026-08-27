<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Whether a TipTap document puts anything on the page.
 *
 * An empty editor is not an empty value. TipTap always keeps at least one paragraph in the
 * document, so a field nobody typed into arrives at the validator as a `doc` holding one
 * empty paragraph — an array with three keys, which Laravel's `required` is perfectly happy
 * with. Filament covers exactly that one shape; a second empty paragraph, a stray space or
 * a line break all get past it, and so does the same document in its markup form.
 *
 * The rule here is stated the other way round, and only once: a document is blank when it
 * holds nothing but paragraphs, line breaks and whitespace. Every other node — a list, a
 * heading, a rule, an image, a table, a custom block — is something a reader would see, and
 * a document holding one is not empty.
 *
 * That direction is the point. The list below is short and stops growing, while a list of
 * nodes that count as content would have to be extended for every node this package or a
 * project ever adds, and would silently reject somebody's content on the day it was
 * forgotten. Here an unknown node counts as content, so the mistake this makes is letting
 * an empty-looking document through — visible, harmless and reported — rather than
 * throwing away a document that had something in it.
 */
final class DocumentContent
{
    /**
     * The node types that put nothing on the page when they hold nothing themselves.
     *
     * `heading` is deliberately absent even though an empty one renders as nothing: it
     * takes a deliberate keystroke to make, and the safe direction is to believe it.
     *
     * @var array<int, string>
     */
    private const BLANK_TYPES = ['doc', 'paragraph', 'hardBreak', 'text'];

    /**
     * The characters that are whitespace to a reader but not to `trim()`: the non-breaking
     * space every paste from Word leaves behind, the zero width space, and the byte order
     * mark that arrives with a file somebody saved as UTF-8 with a signature.
     */
    private const BLANK_CHARACTERS = '/[\s\x{00A0}\x{200B}\x{FEFF}]+/u';

    /**
     * @param  array<string, mixed>  $document
     */
    public static function isBlank(array $document): bool
    {
        foreach (self::nodes($document) as $node) {
            $type = $node['type'] ?? null;

            if (! in_array($type, self::BLANK_TYPES, strict: true)) {
                return false;
            }

            if ($type === 'text' && self::hasVisibleText($node['text'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every node in the tree, the document itself included.
     *
     * @param  array<string, mixed>  $node
     * @return iterable<array<string, mixed>>
     */
    private static function nodes(array $node): iterable
    {
        yield $node;

        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                yield from self::nodes($child);
            }
        }
    }

    private static function hasVisibleText(mixed $text): bool
    {
        if (! is_string($text)) {
            return false;
        }

        return preg_replace(self::BLANK_CHARACTERS, '', $text) !== '';
    }
}
