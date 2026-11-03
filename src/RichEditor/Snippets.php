<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ready-made content a field offers to put where the caret is: a signature, a disclaimer, the
 * skeleton of a report. Not the same thing as a custom block - that is structure with fields
 * to fill in, kept as one piece; a snippet is text, and once it is in, it is the document's
 * like anything typed.
 *
 * A template is the same thing at a different moment: a snippet put into an empty field, or a
 * field's `default()` on a form that creates a record.
 */
final class Snippets
{
    /**
     * The toolbar name of one snippet, which is also what the dropdown and the slash menu
     * address it by - so a bar that wants one snippet as a button of its own names it.
     */
    public static function toolName(string $key): string
    {
        return 'snippet'.Str::studly($key);
    }

    /**
     * Every snippet as a label and its content, from what a project wrote: a key and the
     * content, or a key and both.
     *
     *     ['signature' => '<p>Kind regards</p>']
     *     ['signature' => ['label' => 'Signature', 'content' => '<p>Kind regards</p>']]
     *
     * The key names the tool, so it is a name: a letter, then letters, digits and
     * underscores. Anything else is refused with the key in the message, because a snippet
     * that silently did not appear is a worse way to find out than an exception.
     *
     * @param  array<mixed>  $snippets
     * @return array<string, array{label: string, content: string|array<mixed>}>
     */
    public static function normalize(array $snippets): array
    {
        $normalized = [];
        $tools = [];

        foreach ($snippets as $key => $snippet) {
            if (! is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'A snippet is keyed by a name - a letter, then letters, digits and underscores, such as [signature] - and [%s] is not one. Give its label as [\'label\' => ...].',
                    $key,
                ));
            }

            // A definition says `label` or `content`; a document says `type`.
            $definition = is_array($snippet) && (array_key_exists('content', $snippet) || array_key_exists('label', $snippet));
            $content = $definition ? ($snippet['content'] ?? null) : $snippet;

            if (! is_string($content) && ! is_array($content)) {
                throw new InvalidArgumentException("The snippet [{$key}] has no content: give it markup, or a document as an array.");
            }

            // `legal_note` and `legalNote` are one tool, and the second would take the first
            // one's place in the menus without a word.
            $tool = self::toolName($key);

            if (isset($tools[$tool])) {
                throw new InvalidArgumentException("The snippets [{$tools[$tool]}] and [{$key}] would be the same tool, [{$tool}]. Name one of them differently.");
            }

            $tools[$tool] = $key;

            $label = $definition ? ($snippet['label'] ?? null) : null;

            $normalized[$key] = [
                'label' => is_string($label) && $label !== '' ? $label : Str::headline($key),
                'content' => $content,
            ];
        }

        return $normalized;
    }
}
