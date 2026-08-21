<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * The keyboard shortcuts a field answers to.
 *
 * Every entry was read out of the editor build Filament ships rather than copied from
 * someone's documentation, because a shortcut list that lies is worse than none. What is
 * listed, though, follows the field: a heading level the field does not offer has no
 * shortcut to mention, and neither does a task list that is switched off.
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
     * The keys that belong to no button: they are part of how the editor behaves rather
     * than of what it can be told to do.
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

        $tools = $editor->getTools();

        if (array_key_exists('bulletList', $tools) || array_key_exists('orderedList', $tools)) {
            $rows[] = $line('indent_list', ['Tab']);
            $rows[] = $line('outdent_list', ['Shift', 'Tab']);
        }

        if (array_key_exists('table', $tools)) {
            $rows[] = $line('next_cell', ['Tab']);
        }

        return $rows;
    }
}
