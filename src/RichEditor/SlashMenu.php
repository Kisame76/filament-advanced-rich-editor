<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Forms\Components\RichEditor\RichEditorTool;

use function Filament\Support\generate_icon_html;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * What the slash menu offers, built from the field it opens in.
 *
 * The list is derived rather than declared. Every entry is a tool the field actually
 * registered, carrying that tool's own label, icon and handler - so a command in the menu
 * and the button for it are the same thing, and a feature switched off disappears from
 * both without anything being told twice. A hand-written list would agree with the toolbar
 * on the day it was written.
 *
 * Only blocks and things you insert. The menu opens where the caret sits in text with
 * nothing selected, and `/bold` there would mark nothing at all.
 */
class SlashMenu
{
    /**
     * The groups and their contents, when the config file has nothing to say.
     *
     * `'headings'` and `'callouts'` expand to what the field offers - its heading levels
     * and its kinds of callout - which are the same tokens the toolbar uses.
     *
     * @var array<string, array<int, string>>
     */
    public const GROUPS = [
        // What the block the caret sits in *is*. Every one of these changes something that
        // is already there.
        'style' => [
            'paragraph', 'headings',
            'bulletList', 'orderedList', 'taskList',
            'blockquote', 'codeBlock', 'callouts',
        ],
        // What arrives. Uploading is not a group of its own, the way it is in some editors:
        // one entry does not need a heading over it.
        'insert' => [
            'image', 'attachFiles', 'embed', 'table', 'horizontalRule', 'details', 'emoji', 'characters',
            'customBlocks', 'mergeTags',
        ],
    ];

    /**
     * @return array{char: string, empty: string, groups: array<int, array{key: string, label: string, items: array<int, array<string, mixed>>}>}
     */
    public static function for(AdvancedRichEditor $editor): array
    {
        $tools = $editor->getTools();
        $groups = [];

        foreach ($editor->getSlashGroups() as $key => $names) {
            $items = [];

            foreach (static::expand($names, $editor) as $name) {
                $tool = $tools[$name] ?? null;

                // An unregistered name is dropped rather than raised, exactly as it is
                // inside a toolbar dropdown: the config file lists what a project would
                // like to offer, and what a field offers is the field's own business.
                if (! $tool instanceof RichEditorTool) {
                    continue;
                }

                // Both of these are registered whether or not anything was configured for
                // them, so without this the menu would offer a picker with nothing in it.
                if (! static::isUseful($name, $editor)) {
                    continue;
                }

                $items[] = static::item($name, $tool);
            }

            // A group with nothing in it is a heading with nothing under it.
            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => (string) __("filament-advanced-rich-editor::advanced-rich-editor.slash.groups.{$key}"),
                'items' => $items,
            ];
        }

        return [
            'char' => $editor->getSlashChar(),
            'empty' => (string) __('filament-advanced-rich-editor::advanced-rich-editor.slash.empty'),
            'groups' => $groups,
        ];
    }

    /**
     * Whether a tool has anything behind it in this field.
     *
     * Filament registers the merge tag and custom block tools unconditionally; what they
     * open is a picker over a list the field may never have been given. On a toolbar that
     * is a button nobody put there, but the menu lists everything, so it has to ask.
     */
    protected static function isUseful(string $name, AdvancedRichEditor $editor): bool
    {
        return match ($name) {
            'mergeTags' => filled($editor->getMergeTags()),
            'customBlocks' => filled($editor->getCustomBlocks()),
            default => true,
        };
    }

    /**
     * Expands the tokens a group may hold, each into what this field offers rather than
     * into a fixed list: the heading levels it was given, and the kinds of callout it was
     * given. Both are the same tokens the toolbar uses, so a group written once agrees
     * with the bar.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    protected static function expand(array $names, AdvancedRichEditor $editor): array
    {
        $expanded = [];

        foreach ($names as $name) {
            if ($name === 'headings') {
                foreach ($editor->getHeadingLevels() as $level) {
                    $expanded[] = "h{$level}";
                }

                continue;
            }

            if ($name === 'callouts') {
                foreach ($editor->getCalloutVariants() as $variant) {
                    $expanded[] = Callouts::toolName($variant);
                }

                continue;
            }

            $expanded[] = $name;
        }

        return $expanded;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(string $name, RichEditorTool $tool): array
    {
        return [
            'name' => $name,
            'label' => (string) $tool->getLabel(),
            // The icon the toolbar draws, rendered here because the menu is built in
            // JavaScript and has no way to resolve a Blade Icons name.
            'icon' => generate_icon_html($tool->getIcon(), alias: $tool->getIconAlias())?->toHtml() ?? '',
            'aliases' => static::aliases($name),
            // The tool's own handler, evaluated in the editor's Alpine scope. Nothing is
            // reimplemented here, which is what keeps the menu and the button honest.
            'handler' => (string) $tool->getJsHandler(),
        ];
    }

    /**
     * The words someone types instead of the label.
     *
     * Nobody types `/bullet list`. The aliases are translated, so a German panel answers to
     * `/liste` and every panel answers to `/ul`.
     *
     * @return array<int, string>
     */
    protected static function aliases(string $name): array
    {
        $key = "filament-advanced-rich-editor::advanced-rich-editor.slash.aliases.{$name}";
        $aliases = __($key);

        if (! is_string($aliases) || $aliases === $key) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $aliases))));
    }
}
