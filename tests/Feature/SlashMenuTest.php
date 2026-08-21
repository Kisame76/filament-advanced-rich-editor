<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

/**
 * The tool names the menu offers, flattened out of its groups.
 *
 * @return array<int, string>
 */
function slashNames(AdvancedRichEditor $editor): array
{
    return array_merge(...array_map(
        static fn (array $group): array => array_column($group['items'], 'name'),
        SlashMenu::for($editor)['groups'],
    ));
}

/**
 * One item, by the tool it runs.
 *
 * @return array<string, mixed>|null
 */
function slashItem(AdvancedRichEditor $editor, string $name): ?array
{
    foreach (SlashMenu::for($editor)['groups'] as $group) {
        foreach ($group['items'] as $item) {
            if ($item['name'] === $name) {
                return $item;
            }
        }
    }

    return null;
}

it('offers the blocks and the things you insert', function (): void {
    expect(slashNames(editor()))->toBe([
        'paragraph', 'h1', 'h2', 'h3', 'h4',
        'bulletList', 'orderedList', 'taskList',
        'blockquote', 'codeBlock', 'horizontalRule', 'details',
        'image', 'table', 'attachFiles', 'emoji',
    ]);
});

it('offers no inline formatting', function (): void {
    // The caret sits in an empty block with nothing selected, which is the only place the
    // menu opens. `/bold` there would mark nothing, and an entry that does nothing is
    // worse than a missing one.
    expect(slashNames(editor()))
        ->not->toContain('bold')
        ->not->toContain('italic')
        ->not->toContain('link')
        ->not->toContain('textColor');
});

it('leaves out a heading level the field does not offer', function (): void {
    expect(slashNames(editor()->headingLevels([2, 3])))
        ->toContain('h2')
        ->toContain('h3')
        ->not->toContain('h1')
        ->not->toContain('h4');
});

it('leaves out a task list that is switched off', function (): void {
    // The list is built from the tools the field actually registered, so a feature that is
    // off disappears from the menu without anything having to be told about it twice.
    expect(slashNames(editor()->taskList(false)))->not->toContain('taskList');
});

it('drops a name that is not a registered tool', function (): void {
    config()->set('filament-advanced-rich-editor.slash.groups.blocks', ['paragraph', 'nonsense']);

    expect(slashNames(editor()))->toContain('paragraph')
        ->not->toContain('nonsense');
});

it('leaves out a group that nothing is left in', function (): void {
    config()->set('filament-advanced-rich-editor.slash.groups.insert', ['nonsense']);

    expect(array_column(SlashMenu::for(editor())['groups'], 'key'))->toBe(['blocks']);
});

it('runs the button the toolbar runs', function (): void {
    // The handler is the tool's own, so a command in the menu and the button for it cannot
    // come apart - there is only one of them.
    $tool = editor()->getTools()['blockquote'];

    expect(slashItem(editor(), 'blockquote')['handler'])->toBe($tool->getJsHandler());
});

it('shows the keys, where a command has some', function (): void {
    expect(slashItem(editor(), 'blockquote')['keys'])->toBe(['Mod', 'Shift', 'B'])
        ->and(slashItem(editor(), 'h2')['keys'])->toBe(['Mod', 'Alt', '2'])
        ->and(slashItem(editor(), 'image')['keys'])->toBe([]);
});

it('carries the label and the icon the toolbar draws', function (): void {
    $item = slashItem(editor(), 'blockquote');

    expect($item['label'])->toBe((string) editor()->getTools()['blockquote']->getLabel())
        ->and($item['icon'])->toContain('<svg');
});

it('knows the names people type instead of the label', function (): void {
    // Nobody types `/bullet list`. The aliases are translated, so `/liste` finds it in a
    // German panel and `/ul` finds it in either.
    expect(slashItem(editor(), 'bulletList')['aliases'])->toContain('ul');
});

it('is off where the field says so', function (): void {
    expect(editor()->hasSlashMenu())->toBeTrue()
        ->and(editor()->slashMenu(false)->hasSlashMenu())->toBeFalse();
});

it('reads whether it is on from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.slash.enabled', false);

    expect(editor()->hasSlashMenu())->toBeFalse();
});

it('hands the view nothing while it is switched off', function (): void {
    expect(editor()->slashMenu(false)->getSlashMenuForJs())->toBeNull();
});

it('hands the view nothing when there would be nothing to choose from', function (): void {
    // A panel that can only ever say "no matching command" is one that should not open,
    // and the data attribute carrying it has no business in the markup either.
    config()->set('filament-advanced-rich-editor.slash.groups', ['blocks' => ['nonsense']]);

    expect(editor()->getSlashMenuForJs())->toBeNull();
});

it('offers the merge tags and custom blocks only once there are some', function (): void {
    // Filament registers both tools whether or not anything was configured for them, so
    // without a check the menu would offer a picker over an empty list.
    expect(slashNames(editor()))
        ->not->toContain('mergeTags')
        ->not->toContain('customBlocks')
        ->and(slashNames(editor()->mergeTags(['name'])))->toContain('mergeTags');
});
