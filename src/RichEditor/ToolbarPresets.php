<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use LogicException;

/**
 * Named starting points for the four bars a field draws.
 *
 * A project that wants a comment box has to shrink the main toolbar, empty the overflow
 * and the tools menu, cut the selection bubble and turn attachments off - four decisions
 * that only make sense together, and that are otherwise made by copying the shipped
 * arrays and editing them until something breaks quietly.
 *
 * A preset is a fixed list rather than a copy of the configuration: `->preset('default')`
 * stays the bar this package ships even where a project has rebuilt its own `toolbar`.
 * That is the whole point - a starting point that moves is not one.
 *
 * A preset may name any subset of the five keys. What it leaves out is left to the
 * configuration, so a preset is free to speak about the main bar only.
 */
class ToolbarPresets
{
    /**
     * The bars a preset may answer for. Anything else in a registered preset is a typo,
     * because nothing would ever read it.
     *
     * @var array<int, string>
     */
    public const KEYS = ['toolbar', 'more', 'tools_menu', 'text_toolbar_buttons', 'file_attachments'];

    /**
     * The shipped presets with the ones registered in the config file merged over them, so
     * that a project can add its own house preset or replace a shipped one without
     * renaming it everywhere it is used.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $registered = config('filament-advanced-rich-editor.toolbar_presets') ?? [];

        if (! is_array($registered)) {
            throw new LogicException(
                '`filament-advanced-rich-editor.toolbar_presets` has to be an array of presets, and is a ['
                .get_debug_type($registered).'].'
            );
        }

        foreach ($registered as $name => $preset) {
            static::validate($name, $preset);
        }

        return [...static::shipped(), ...$registered];
    }

    /**
     * Whether one registered preset is shaped like a preset.
     *
     * Checked here rather than where it is read, because the reader is a toolbar accessor
     * halfway through rendering a page: a preset registered as a string fails there as
     * "cannot access offset of type string" inside Filament, and a preset with a
     * misspelled key fails not at all - the bar it meant to describe falls through to the
     * config file as though the preset had said nothing about it.
     */
    protected static function validate(mixed $name, mixed $preset): void
    {
        if (! is_string($name)) {
            throw new LogicException(
                "Toolbar presets are keyed by name, and [{$name}] is not a string."
            );
        }

        if (! is_array($preset)) {
            throw new LogicException(
                "Toolbar preset [{$name}] has to be an array of bars, and is a [".get_debug_type($preset).'].'
            );
        }

        $unknown = array_diff(array_keys($preset), static::KEYS);

        if ($unknown !== []) {
            throw new LogicException(
                "Toolbar preset [{$name}] names [".implode(', ', $unknown).'], which nothing reads. '
                .'A preset answers some or all of: '.implode(', ', static::KEYS).'.'
            );
        }
    }

    /**
     * The presets this package ships, without anything a project registered.
     *
     * Read on its own by the four bars, whose last fallback is `'default'`: the shipped bar
     * is written once here rather than once here and once in each reader. A project changes
     * that fallback through the `toolbar`, `more`, `tools_menu` and `text_toolbar_buttons`
     * keys, which is what those keys are for - redefining the `default` preset moves what
     * `->preset('default')` asks for, and nothing else.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function shipped(): array
    {
        return [
            /*
             * A single line of prose with a link and a list. No headings, because a field
             * this small is a paragraph rather than a document.
             */
            'minimal' => [
                'toolbar' => [
                    ['bold', 'italic', 'underline'],
                    'divider',
                    ['link', 'lists'],
                ],
                'more' => [],
                'tools_menu' => [],
                'text_toolbar_buttons' => ['bold', 'italic', 'link'],
                'file_attachments' => false,
            ],

            /*
             * What somebody writes underneath something else: the minimal bar plus the two
             * things a reply actually uses.
             */
            'comment' => [
                'toolbar' => [
                    ['bold', 'italic'],
                    'divider',
                    ['link', 'lists', 'blockquote'],
                    'divider',
                    ['emoji'],
                ],
                'more' => [],
                'tools_menu' => [],
                'text_toolbar_buttons' => ['bold', 'italic', 'link'],
                'file_attachments' => false,
            ],

            /*
             * An article: structure, pictures and the overflow, without the typographic
             * controls a house style has already decided.
             */
            'blog' => [
                'toolbar' => [
                    ['undo', 'redo'],
                    'divider',
                    ['headings', 'styles'],
                    'divider',
                    ['bold', 'italic', 'underline', 'link'],
                    'divider',
                    ['lists', 'mediaBrowser', 'callouts'],
                    'divider',
                    ['more'],
                    'pin',
                    ['tools'],
                ],
                'more' => [
                    'subscript', 'superscript', 'code', 'codeBlock', 'blockquote', 'clearFormatting', 'horizontalRule',
                    'details',
                    'emoji', 'characters',
                ],
                'tools_menu' => ['find', 'accessibility', 'statistics', 'preview', 'sourceCode', 'help'],
                'text_toolbar_buttons' => ['styles', 'bold', 'italic', 'underline', 'link'],
                'file_attachments' => true,
            ],

            /*
             * The bar this package ships, under a name. Worth having as a preset even
             * though it is also the fallback: it is the one arrangement that shows every
             * kind of tool the editor has, and naming it lets a field ask for it back after
             * a project has rebuilt the configured bar into something else.
             */
            'default' => [
                'toolbar' => [
                    ['undo', 'redo'],
                    'divider',
                    ['headings', 'styles', 'fontSize'],
                    'divider',
                    ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
                    'divider',
                    ['alignment', 'lineHeight'],
                    'divider',
                    ['lists', 'mediaBrowser', 'table', 'callouts'],
                    'divider',
                    ['more'],
                    'pin',
                    ['tools', 'fullscreen'],
                ],
                'more' => [
                    'subscript', 'superscript', 'code', 'codeBlock', 'blockquote', 'clearFormatting', 'horizontalRule',
                    'details',
                    'emoji', 'characters',
                ],
                'tools_menu' => ['find', 'accessibility', 'statistics', 'preview', 'sourceCode', 'help'],
                'text_toolbar_buttons' => [
                    'styles', 'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground',
                ],
                'file_attachments' => true,
            ],

            /*
             * Everything the package can draw: `default` plus the four tools the
             * documentation names as registered and deliberately unplaced. Three of them -
             * `fontFamily`, `textCase` and `language` - go on the bar rather than into
             * `more`, because each expands into a dropdown of its own and `more` holds
             * button names; `strike` is a plain mark and goes with the other marks.
             *
             * Not here, and on purpose: `mergeTags` and `customBlocks` do nothing until a
             * project configures them, `attachFiles` is the picture button under another
             * name, and the table editing tools appear with a selected table rather than on
             * the bar.
             */
            'full' => [
                'toolbar' => [
                    ['undo', 'redo'],
                    'divider',
                    ['headings', 'styles', 'fontFamily', 'fontSize'],
                    'divider',
                    ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground', 'textCase', 'language'],
                    'divider',
                    ['alignment', 'lineHeight'],
                    'divider',
                    ['lists', 'mediaBrowser', 'table', 'callouts'],
                    'divider',
                    ['more'],
                    'pin',
                    ['tools', 'fullscreen'],
                ],
                'more' => [
                    'subscript', 'superscript', 'strike', 'code', 'codeBlock', 'blockquote', 'clearFormatting',
                    'horizontalRule',
                    'details',
                    'emoji', 'characters',
                ],
                'tools_menu' => ['find', 'accessibility', 'statistics', 'preview', 'sourceCode', 'help'],
                'text_toolbar_buttons' => [
                    'styles', 'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground',
                ],
                'file_attachments' => true,
            ],
        ];
    }

    /**
     * One preset by name.
     *
     * A name nobody registered is a typo rather than a request for the shipped bar, so it
     * raises - with the known names in the message, which is the answer to the mistake
     * that produced it.
     *
     * @return array<string, mixed>
     */
    public static function get(string $name): array
    {
        $presets = static::all();

        return $presets[$name] ?? throw new LogicException(
            "Toolbar preset [{$name}] cannot be found. Known presets: ".implode(', ', array_keys($presets)).'.'
        );
    }
}
