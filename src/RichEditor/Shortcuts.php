<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use InvalidArgumentException;
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
 *
 * A project can move them. `->shortcuts()` and the `shortcuts` config give a tool a different
 * key, a first one, or none, and both the list and the editor follow: `keys()` works out what
 * each tool answers to, `for()` lists that, and `bindings()` tells the script what to bind
 * for the editor to agree.
 */
class Shortcuts
{
    /**
     * Tool name => the key it ships with. Headings are absent: there are six of them, and
     * `shipped()` adds them. The last three are this package's and are listed with the
     * editing keys, under names of their own, rather than in the order below.
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
        'find' => ['Mod', 'F'],
        'indent' => ['Mod', ']'],
        'outdent' => ['Mod', '['],
    ];

    /**
     * Keys a tool ships with besides the one the list shows. Never listed - one key a row is
     * what keeps the list short enough to read - but taken away with the shown one when the
     * tool loses it, or `Mod+Y` would go on redoing on a field that moved redo elsewhere.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    protected const ALSO_BOUND = [
        'redo' => [['Mod', 'Y']],
    ];

    /**
     * The modifiers a project may write, as they are drawn. `Cmd` is not one of them: a key
     * written for a Mac would do nothing anywhere else, and `Mod` is the one that means ⌘
     * there and Ctrl everywhere else.
     *
     * @var array<string, string>
     */
    protected const MODIFIERS = [
        'mod' => 'Mod',
        'ctrl' => 'Ctrl',
        'alt' => 'Alt',
        'shift' => 'Shift',
    ];

    /**
     * The keys with a name rather than a character, as the browser names them.
     *
     * @var array<int, string>
     */
    protected const NAMED = [
        'Enter', 'Tab', 'Space', 'Backspace', 'Delete', 'Escape',
        'Home', 'End', 'PageUp', 'PageDown',
        'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
    ];

    /**
     * The order the list reads in: what marks the text, then what the block is, then how it
     * sits, then what moves. Shipped keys outside this order are not listed - a shortcut
     * nobody can see a button for is still a shortcut, but a list of everything is a list
     * nobody reads. A key a project gave a tool is listed after these: somebody chose it.
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
        $keys = static::keys($editor);

        $names = [];

        foreach (static::ORDER as $name) {
            $names[] = $name;

            // The headings sit where the paragraph does, since they are the same choice.
            if ($name === 'paragraph') {
                foreach ($editor->getHeadingLevels() as $level) {
                    $names[] = "h{$level}";
                }
            }
        }

        $rows = [];

        foreach ([...$names, ...array_keys(array_diff_key($keys, static::shipped()))] as $name) {
            if (isset($keys[$name])) {
                $rows[] = ['label' => (string) $tools[$name]->getLabel(), 'keys' => $keys[$name]];
            }
        }

        return [...$rows, ...static::editing($editor, $keys)];
    }

    /**
     * The key each of the field's tools answers to: the shipped ones, then what the config
     * changed, then what the field did. A key given to a tool is taken from whichever tool
     * had it, so the later claim wins and a field can take back a key the config handed out.
     *
     * A tool the field does not have is skipped rather than raised, as in the slash menu: the
     * config names what a project would like, and which tools a field has is its own
     * business. A key that cannot be bound is raised, because that is always a mistake.
     *
     * @return array<string, array<int, string>>
     */
    public static function keys(AdvancedRichEditor $editor): array
    {
        $tools = $editor->getTools();
        $keys = array_intersect_key(static::shipped(), $tools);

        foreach ($editor->getShortcuts() as $name => $written) {
            if (! array_key_exists($name, $tools)) {
                continue;
            }

            unset($keys[$name]);

            if ($written === null || $written === false) {
                continue;
            }

            $key = static::parse($written, (string) $name);

            $keys = array_filter($keys, static fn (array $other): bool => $other !== $key);
            $keys[$name] = $key;
        }

        return $keys;
    }

    /**
     * What the script binds for the editor to answer to `keys()`, in ProseMirror's notation:
     * a key on a tool that did not ship with it runs that tool's handler, and a key a tool
     * shipped with and has lost does nothing at all - it must not reach the extension that
     * bound it. Empty where nothing moved, and nothing is loaded for an empty list.
     *
     * @return array<int, array{keys: string, tool: string, handler: ?string}>
     */
    public static function bindings(AdvancedRichEditor $editor): array
    {
        $tools = $editor->getTools();
        $keys = static::keys($editor);
        $shipped = array_intersect_key(static::shipped(), $tools);

        $bound = [];

        foreach ($keys as $name => $key) {
            $bound[static::notation($key)] = $name;
        }

        $bindings = [];

        foreach ($bound as $notation => $name) {
            if (($shipped[$name] ?? null) !== $keys[$name]) {
                $bindings[] = ['keys' => $notation, 'tool' => $name, 'handler' => (string) $tools[$name]->getJsHandler()];
            }
        }

        foreach ($shipped as $name => $key) {
            if (($keys[$name] ?? null) === $key) {
                continue;
            }

            foreach ([$key, ...static::ALSO_BOUND[$name] ?? []] as $lost) {
                if (! array_key_exists($notation = static::notation($lost), $bound)) {
                    $bindings[] = ['keys' => $notation, 'tool' => $name, 'handler' => null];
                }
            }
        }

        return $bindings;
    }

    /**
     * A key as a project writes it, as the tokens the list draws: `Mod-Shift-K`, `mod+shift+k`
     * and `Shift-Mod-K` all come out as ['Mod', 'Shift', 'K'], which is also what lets two of
     * them be compared.
     *
     * One of Mod, Ctrl and Alt is required unless the key is a function key. Without one the
     * key types a character, and binding it would take that character away from the field.
     *
     * @return array<int, string>
     */
    public static function parse(mixed $written, string $tool): array
    {
        $parts = is_string($written) ? preg_split('/[-+](?!$)/', trim($written)) : false;
        $key = $parts === false ? null : static::key((string) array_pop($parts));
        $modifiers = [];

        foreach ($parts ?: [] as $part) {
            $modifiers[static::MODIFIERS[strtolower($part)] ?? '?'] = true;
        }

        $valid = $key !== null
            && ! isset($modifiers['?'])
            && (isset($modifiers['Mod']) || isset($modifiers['Ctrl']) || isset($modifiers['Alt']) || preg_match('/^F\d+$/', $key) === 1);

        if (! $valid) {
            throw new InvalidArgumentException(sprintf(
                'The shortcut for [%s] cannot be bound: %s. Write modifiers and a key joined by dashes, such as Mod-Shift-K. The modifiers are Mod (⌘ on a Mac, Ctrl elsewhere), Ctrl, Alt and Shift, and one of Mod, Ctrl or Alt is needed unless the key is F1 to F12.',
                $tool,
                is_string($written) ? "[{$written}]" : 'it is not a string',
            ));
        }

        return [...array_keys(array_intersect_key(array_flip(static::MODIFIERS), $modifiers)), $key];
    }

    /**
     * The key itself, named the way the list draws it, or null for one that cannot be bound.
     */
    protected static function key(string $key): ?string
    {
        if (preg_match('/^[!-~]$/', $key) === 1) {
            return strtoupper($key);
        }

        if (preg_match('/^f([1-9]|1[0-2])$/i', $key, $function) === 1) {
            return "F{$function[1]}";
        }

        foreach (static::NAMED as $name) {
            if (strcasecmp($name, $key) === 0) {
                return $name;
            }
        }

        return null;
    }

    /**
     * ProseMirror's spelling of a key: the same tokens, with a letter in lower case. Its
     * keymap reads `Mod-B` as Mod and a capital B, which arrives with Shift as well as with
     * Caps Lock - so it would answer `Mod-Shift-B`, which is somebody else's key. A lower
     * case one is still found with Caps Lock on, through the key code.
     *
     * @param  array<int, string>  $key
     */
    protected static function notation(array $key): string
    {
        $last = array_pop($key);

        return implode('-', [...$key, strlen($last) === 1 ? strtolower($last) : $last]);
    }

    /**
     * Every key the editor ships with, by tool, the six heading levels included.
     *
     * @return array<string, array<int, string>>
     */
    protected static function shipped(): array
    {
        $headings = [];

        foreach (range(1, 6) as $level) {
            $headings["h{$level}"] = ['Mod', 'Alt', (string) $level];
        }

        return [...static::KEYS, ...$headings];
    }

    /**
     * The keys that no single button accounts for: either they belong to no button at all
     * and are part of how the editor behaves, or one button answers to more than one of
     * them and neither row is called what the button is called.
     *
     * @param  array<string, array<int, string>>  $keys
     * @return array<int, array{label: string, keys: array<int, string>}>
     */
    protected static function editing(AdvancedRichEditor $editor, array $keys): array
    {
        // A key that is part of how the editor behaves stops being listed as that once a
        // project gives it to a tool - the tool is what answers it now, and has a row of its own.
        $line = fn (string $key, array $shortcut, bool $fixed = true): ?array => $fixed && in_array($shortcut, $keys, true)
            ? null
            : [
                'label' => __("filament-advanced-rich-editor::advanced-rich-editor.help.editing.{$key}"),
                'keys' => $shortcut,
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

        // Two keys, one pair of buttons and two meanings: inside a list they nest the item,
        // and everywhere else they move the block's own margin. The row names the move rather
        // than either meaning, because the key does whichever one the caret is standing in -
        // and, like the case row, it is the only way to reach the feature on a field that
        // was given no buttons for it.
        if (isset($keys['indent'])) {
            $rows[] = $line('indent_block', $keys['indent'], fixed: false);
        }

        if (isset($keys['outdent'])) {
            $rows[] = $line('outdent_block', $keys['outdent'], fixed: false);
        }

        if (array_key_exists('table', $tools)) {
            $rows[] = $line('next_cell', ['Tab']);
        }

        // One key, three tools, and no row is called what any of them is called: it walks
        // Sentence case, lower case and UPPER CASE the way Word does. It is also the only
        // way to reach the feature on a field that has not been given a button for it,
        // which is most of them - so a row here is not a nicety, it is the whole of how
        // anybody finds out the field can do this.
        if ($editor->hasTextCase()) {
            $rows[] = $line('change_case', ['Shift', 'F3']);
        }

        // One window, two ways in: the first opens it to search, the second opens it with
        // the replacing row already out. Mod+Alt+F rather than the Ctrl+H that is bound
        // alongside it, because this is the pair that works on both platforms - Cmd+H is
        // taken by macOS, and a list naming a key that does nothing is worse than none.
        if (array_key_exists('find', $tools)) {
            if (isset($keys['find'])) {
                $rows[] = $line('find', $keys['find'], fixed: false);
            }

            $rows[] = $line('find_replace', ['Mod', 'Alt', 'F']);
        }

        return array_values(array_filter($rows));
    }
}
