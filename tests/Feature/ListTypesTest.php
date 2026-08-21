<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;

it('reads the list types from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.lists', ['orderedList', 'bulletList']);

    expect(editor()->getListTypes())->toBe(['orderedList', 'bulletList'])
        ->and(toolbarDropdownName(editor(), 'bulletList'))->toBe('dropdown:orderedList,bulletList');
});

it('falls back to all three list types when the config was never merged', function (): void {
    config()->set('filament-advanced-rich-editor.lists', null);

    expect(editor()->getListTypes())->toBe(['bulletList', 'orderedList', 'taskList']);
});

it('lets a field choose its own list types', function (): void {
    $editor = editor()->listTypes(['taskList', 'bulletList']);

    expect(toolbarDropdownName($editor, 'bulletList'))->toBe('dropdown:taskList,bulletList')
        ->and(resolvedButtonNames(toolbarDropdown($editor, 'bulletList')))->toBe(['taskList', 'bulletList']);
});

it('accepts a closure for the list types', function (): void {
    expect(editor()->listTypes(fn (): array => ['bulletList'])->getListTypes())->toBe(['bulletList']);
});

it('registers the task list plugin by default', function (): void {
    $editor = editor();

    expect($editor->hasTaskList())->toBeTrue()
        // Filtered by type: the field registers other plugins (the font size mark) too.
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof TaskListPlugin))->toHaveCount(1)
        ->and($editor->getTools())->toHaveKey('taskList')
        ->and($editor->getTools()['taskList']->getLabel())->toBe('Task list');
});

it('unregisters the task list plugin when the task list is turned off', function (): void {
    $editor = editor()->taskList(false);

    expect($editor->hasTaskList())->toBeFalse()
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof TaskListPlugin))->toBe([])
        ->and($editor->getTools())->not->toHaveKey('taskList');
});

it('reads the task list default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.task_list', false);

    expect(editor()->hasTaskList())->toBeFalse()
        // An explicit call still wins over the configured default.
        ->and(editor()->taskList()->hasTaskList())->toBeTrue();
});

it('evaluates the task list condition late enough to be a closure', function (): void {
    // The plugin is registered as a closure in `setUp()`, so a condition that is only
    // resolvable once the field is bound to a schema still switches the plugin off.
    $editor = editor()->taskList(fn (): bool => false);

    expect(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof TaskListPlugin))->toBe([]);
});

it('drops the task list option from the dropdown instead of rendering a dead entry', function (): void {
    $editor = editor()->taskList(false);
    $lists = toolbarDropdown($editor, 'bulletList');

    expect($lists->getButtons())->toBe(['bulletList', 'orderedList', 'taskList'])
        ->and(resolvedButtonNames($lists))->toBe(['bulletList', 'orderedList']);
});
