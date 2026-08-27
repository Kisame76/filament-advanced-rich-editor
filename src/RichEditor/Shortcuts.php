<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * The keyboard shortcuts a field answers to.
 *
 * Every entry was read out of the editor build Filament ships - or, for the handful this
 * package binds itself, out of the extension that binds it - rather than copied from
 * someone's documentation, because a shortcut list that lies is worse than none. What is
 * listed, though, follows the field: a heading level the field does not offer has no
 * shortcut to mention, and neither does a task list that is switched off.
 *
 * The heading rows are the one place the list is narrower than the editor. TipTap
 * registers the shortcut for all six levels whatever the field offers, so `Mod+Alt+5` on a
 * field that stops at four still writes an `h5` - one the toolbar has no button to take
 * back. Listing it would be advertising that, so the list names the levels the field has.
 *
 * The keys stay tokens (`Mod`, `Alt`, `Shift`) all the way to the browser. Whether `Mod`
 * is drawn as ⌘ or as Ctrl is a question about the machine looking at the screen, and PHP
 * has never met it.
 */
class Shortcuts
{
    /**
     * Tool name => keys, for the tools whose shortcut is fixed. Headings are absent: their
     * keys are built from the levels the field offers.
     *
     * @var array<string, array<int, string>>
     */
    protected const KEYS = [
        'bold' => ['Mod', 'B'],
        'italic' => ['Mod', 'I'],
        'underline' => ['Mod', 'U'],
        'strike' => ['Mod', 'Shift', 'S'],
        'code' => ['Mod', 'E'],
        'highlight' => ['Mod', 'Shift', 'H'],
        'paragraph' => ['Mod', 'Alt', '0'],
        'bulletList' => ['Mod', 'Shift', '8'],
        'orderedList' => ['Mod', 'Shift', '7'],
        'taskList' => ['Mod', 'Shift', '9'],
        'blockquote' => ['Mod', 'Shift', 'B'],
        'codeBlock' => ['Mod', 'Alt', 'C'],
        // The outer two are answered by this package rather than by TipTap, which binds
        // these keys to `left` and `right` while Filament configures the extension with
        // `start` and `end`. See `AlignmentPlugin` - and note that the middle two are
        // TipTap's own, because `center` and `justify` are spelled the same either way.
        'alignStart' => ['Mod', 'Shift', 'L'],
        'alignCenter' => ['Mod', 'Shift', 'E'],
        'alignEnd' => ['Mod', 'Shift', 'R'],
        'alignJustify' => ['Mod', 'Shift', 'J'],
        'undo' => ['Mod', 'Z'],
        'redo' => ['Mod', 'Shift', 'Z'],
    ];

    /**
     * The order the list reads in: what marks the text, then what the block is, then how it
     * sits, then what moves. Tools outside this order are not listed - a shortcut nobody can
     * see a button for is still a shortcut, but a list of everything is a list nobody reads.
     *
     * @var array<int, string>
     */
    protected const ORDER = [
        'bold', 'italic', 'underline', 'strike', 'code', 'highlight',
        'paragraph', 'bulletList', 'orderedList', 'taskList', 'blockquote', 'codeBlock',
        'alignStart', 'alignCenter', 'alignEnd', 'alignJustify',
        'undo', 'redo',
    ];

    /**
     * @return array<int, array{label: string, keys: array<int, string>}>
     */
    public static function for(AdvancedRichEditor $editor): array
    {
        $tools = $editor->getTools();

        $rows = [];

        foreach (static::ORDER as $name) {
            if (! array_key_exists($name, $tools)) {
                continue;
            }

            $rows[] = [
                'label' => (string) $tools[$name]->getLabel(),
                'keys' => static::KEYS[$name],
            ];

            // The headings sit where the paragraph does, since they are the same choice.
            if ($name === 'paragraph') {
                foreach ($editor->getHeadingLevels() as $level) {
                    if (! array_key_exists("h{$level}", $tools)) {
                        continue;
                    }

                    $rows[] = [
                        'label' => (string) $tools["h{$level}"]->getLabel(),
                        'keys' => ['Mod', 'Alt', (string) $level],
                    ];
                }
            }
        }

        return [...$rows, ...static::editing($editor)];
    }

    /**
     * The keys that no single button accounts for: either they belong to no button at all
     * and are part of how the editor behaves, or one button answers to more than one of
     * them and neither row is called what the button is called.
     *
     * @return array<int, array{label: string, keys: array<int, string>}>
     */
    protected static function editing(AdvancedRichEditor $editor): array
    {
        $line = fn (string $key, array $keys): array => [
            'label' => __("filament-advanced-rich-editor::advanced-rich-editor.help.editing.{$key}"),
            'keys' => $keys,
        ];

        $rows = [$line('line_break', ['Shift', 'Enter'])];

        // Nothing in this package binds it and nothing needs to. Chrome, Edge and Firefox
        // read Mod+Shift+V as paste-and-match-style, and ProseMirror's own paste handler
        // takes the text half whenever Shift is down, so the markup is gone twice over.
        //
        // Safari is the exception and the list does not say so: WebKit maps no command to
        // Cmd+Shift+V at all - its paste-and-match-style is Cmd+Alt+Shift+V - so no paste
        // event is fired and the key does nothing. The row is kept as it is because the
        // key is right on every browser that has a keyboard shortcut for this, and a row
        // that named both would be naming one that is wrong everywhere else.
        $rows[] = $line('paste_plain', ['Mod', 'Shift', 'V']);

        $tools = $editor->getTools();

        if (array_key_exists('bulletList', $tools) || array_key_exists('orderedList', $tools)) {
            $rows[] = $line('indent_list', ['Tab']);
            $rows[] = $line('outdent_list', ['Shift', 'Tab']);
        }

        if (array_key_exists('table', $tools)) {
            $rows[] = $line('next_cell', ['Tab']);
        }

        // One window, two ways in: the first opens it to search, the second opens it with
        // the replacing row already out. Mod+Alt+F rather than the Ctrl+H that is bound
        // alongside it, because this is the pair that works on both platforms - Cmd+H is
        // taken by macOS, and a list naming a key that does nothing is worse than none.
        if (array_key_exists('find', $tools)) {
            $rows[] = $line('find', ['Mod', 'F']);
            $rows[] = $line('find_replace', ['Mod', 'Alt', 'F']);
        }

        return $rows;
    }
}
