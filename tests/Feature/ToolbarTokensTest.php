<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

it('exposes the built-in tokens', function (): void {
    expect(array_keys(ToolbarLayout::tokens()))->toBe(['divider', 'pin', 'headings', 'lists', 'alignment', 'callouts', 'language', 'characters', 'emoji', 'textCase', 'embed', 'lineHeight', 'more', 'tools', 'textColor', 'fullscreen', 'sourceCode', 'help', 'statistics', 'find', 'accessibility', 'textBackground', 'styles', 'fontFamily', 'fontSize']);
});

it('resolves tokens at any nesting depth', function (): void {
    $resolved = ToolbarLayout::resolve(
        [['bold', 'divider'], 'divider', [['headings']]],
        editor(),
    );

    expect($resolved[0][0])->toBe('bold')
        ->and($resolved[0][1])->toBeInstanceOf(ToolbarDivider::class)
        ->and($resolved[1])->toBeInstanceOf(ToolbarDivider::class)
        ->and($resolved[2][0][0])->toBeInstanceOf(ToolbarDropdown::class);
});

it('registers extra tokens from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.tokens', [
        'inline' => fn (AdvancedRichEditor $editor): ToolbarDropdown => ToolbarDropdown::make(
            $editor->getName().' inline',
            ['bold', 'italic'],
        )->icon(Heroicon::Sparkles),
    ]);

    $toolbar = editor('body')->toolbarButtons([['inline', 'link']])->getToolbarButtons();

    expect(array_keys(ToolbarLayout::tokens()))->toBe(['divider', 'pin', 'headings', 'lists', 'alignment', 'callouts', 'language', 'characters', 'emoji', 'textCase', 'embed', 'lineHeight', 'more', 'tools', 'textColor', 'fullscreen', 'sourceCode', 'help', 'statistics', 'find', 'accessibility', 'textBackground', 'styles', 'fontFamily', 'fontSize', 'inline'])
        ->and($toolbar[0][0])->toBeInstanceOf(ToolbarDropdown::class)
        // The token closure is evaluated through the field, so it can read its configuration.
        ->and($toolbar[0][0]->getName())->toBe('body inline')
        ->and(resolvedButtonNames($toolbar[0][0]))->toBe(['bold', 'italic'])
        ->and($toolbar[0][1])->toBe('link');
});

it('lets a configured token replace a built-in one', function (): void {
    config()->set('filament-advanced-rich-editor.tokens', [
        'headings' => fn (): ToolbarDropdown => ToolbarDropdown::make('Titles', ['h2', 'h3']),
    ]);

    expect(toolbarShape(editor())[2][0])->toBe('dropdown:h2,h3');
});

it('accepts a class name with a static make method as a token', function (): void {
    config()->set('filament-advanced-rich-editor.tokens', [
        'rule' => ToolbarDivider::class,
    ]);

    expect(toolbarShape(editor()->toolbarButtons([['bold'], 'rule', ['italic']])))->toBe([
        ['bold'],
        ['divider'],
        ['italic'],
    ]);
});

it('splices a token that expands into several items into its group', function (): void {
    config()->set('filament-advanced-rich-editor.tokens', [
        'inline' => fn (): array => ['bold', 'italic'],
    ]);

    // The expansion must not become a nested array, which the view would render as a group.
    expect(toolbarShape(editor()->toolbarButtons([['inline', 'link']])))->toBe([
        ['bold', 'italic', 'link'],
    ]);
});

it('does not resolve tokens produced by another token', function (): void {
    // Guards against a self-referencing token looping forever.
    config()->set('filament-advanced-rich-editor.tokens', [
        'loop' => fn (): array => ['loop', 'bold'],
    ]);

    expect(toolbarShape(editor()->toolbarButtons([['loop']])))->toBe([
        ['loop', 'bold'],
    ]);
});

it('offers a second overflow that is named rather than a second set of dots', function (): void {
    // Two menus both called "More" on one bar are two doors with the same sign and
    // different rooms behind them. This one says what is in it.
    $editor = editor()->accessibility()->sourceCode()->toolbarButtons([['tools', 'fullscreen']]);

    expect(toolbarShape($editor))->toBe([['dropdown:find,accessibility,statistics,sourceCode,help', 'fullscreen']])
        ->and(toolbarItem($editor, 'dropdown:find,accessibility,statistics,sourceCode,help')->getName())->toBe('Tools');
});

it('is the shipped corner, so it keeps its shape as that family grows', function (): void {
    // Switching the check or the source view on puts them in the menu rather than beside it,
    // and the statistics dialog arrived that way - the preview and export tools still to
    // come go the same way.
    expect(array_slice(toolbarShape(editor()), -1))->toBe([[toolsShape(), 'fullscreen']])
        ->and(resolvedButtonNames(toolbarItem(editor(), toolsShape())))->toBe(['find', 'statistics', 'help'])
        ->and(resolvedButtonNames(toolbarItem(editor()->accessibility()->sourceCode(), toolsShape())))
        ->toBe(['find', 'accessibility', 'statistics', 'sourceCode', 'help']);
});

it('goes away when every tool in it is switched off', function (): void {
    // The list naming them is as long as it ever was, so emptiness has to be counted in
    // what survived rather than in what was asked for.
    $editor = editor()->find(false)->help(false)->statistics(false);

    expect(array_merge(...toolbarShape($editor)))->not->toContain(toolsShape());
});

it('drops the menu rather than opening one onto nothing', function (): void {
    expect(toolbarShape(editor()->toolsMenu([])->toolbarButtons([['tools', 'fullscreen']])))
        ->toBe([['fullscreen']]);
});

it('reads the menu out of the config file', function (): void {
    config()->set('filament-advanced-rich-editor.tools_menu', ['help']);

    expect(editor()->getToolsMenu())->toBe(['help']);
});
