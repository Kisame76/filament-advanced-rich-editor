<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('resolves the shipped toolbar into groups, dropdowns and dividers', function (): void {
    expect(toolbarShape(editor()))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4', 'fontSize', 'blockquote', 'codeBlock'],
        ['divider'],
        ['bold', 'italic', 'strike', 'underline', 'link'],
        ['divider'],
        ['superscript', 'subscript'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:bulletList,orderedList,taskList'],
        ['divider'],
        ['image', 'table'],
    ]);
});

it('reads the default toolbar from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar', [['bold'], 'divider', ['image']]);

    expect(editor()->getDefaultToolbarButtons())->toBe([['bold'], 'divider', ['image']])
        ->and(toolbarShape(editor()))->toBe([
            ['bold'],
            ['divider'],
            ['image'],
        ]);
});

it('falls back to the same toolbar when the config was never merged', function (): void {
    // The hard coded fallback in `getDefaultToolbarButtons()` and the shipped config file
    // are two copies of one layout; this is what keeps them from drifting apart.
    config()->set('filament-advanced-rich-editor.toolbar', null);

    expect(toolbarShape(editor()))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4', 'fontSize', 'blockquote', 'codeBlock'],
        ['divider'],
        ['bold', 'italic', 'strike', 'underline', 'link'],
        ['divider'],
        ['superscript', 'subscript'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:bulletList,orderedList,taskList'],
        ['divider'],
        ['image', 'table'],
    ]);
});

it('builds a separate divider instance for every occurrence', function (): void {
    $toolbar = editor()->getToolbarButtons();

    expect($toolbar[3][0])->toBeInstanceOf(ToolbarDivider::class)
        ->and($toolbar[5][0])->toBeInstanceOf(ToolbarDivider::class)
        ->and($toolbar[3][0])->not->toBe($toolbar[5][0]);
});

it('configures the headings and lists dropdowns from the field', function (): void {
    $toolbar = editor()->getToolbarButtons();

    [$headings] = $toolbar[2];
    [, $lists] = $toolbar[8];

    expect($headings)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($headings->getName())->toBe('Headings')
        ->and($headings->getIcon())->toBe('fi-o-heading')
        ->and($headings->hasTextualButtons())->toBeTrue()
        ->and($lists)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($lists->getName())->toBe('Lists')
        ->and($lists->getIcon())->toBe(Heroicon::ListBullet)
        ->and($lists->hasTextualButtons())->toBeTrue();
});

it('resolves dropdown options against the registered editor tools', function (): void {
    $toolbar = editor()->getToolbarButtons();

    expect(resolvedButtonNames($toolbar[2][0]))->toBe(['h1', 'h2', 'h3', 'h4'])
        ->and(resolvedButtonNames($toolbar[8][1]))->toBe(['bulletList', 'orderedList', 'taskList']);
});

it('registers an image tool alongside Filament\'s own tools', function (): void {
    $tools = editor()->getTools();

    expect($tools)->toHaveKey('image')
        ->and($tools['image']->getLabel())->toBe('Image')
        ->and($tools['image']->getActiveKey())->toBe('image')
        // Filament's stock tools must survive the extra registration.
        ->and($tools)->toHaveKeys(['bold', 'italic', 'h1', 'bulletList', 'undo']);
});

it('treats the image tool as the toolbar\'s file attachment button', function (): void {
    // Without this the parent would reject every upload made through the default toolbar,
    // because it only ever looks for the `attachFiles` button.
    expect(editor()->hasFileAttachmentsByDefault())->toBeTrue()
        ->and(editor()->toolbarButtons([['bold', 'italic']])->hasFileAttachmentsByDefault())->toBeFalse();
});

it('never answers a button lookup with a divider', function (): void {
    $editor = editor()->toolbarButtons([
        ['bold'],
        ToolbarDivider::make(),
        ['italic'],
    ]);

    expect($editor->hasToolbarButton('divider'))->toBeFalse()
        ->and($editor->hasToolbarButton('bold'))->toBeTrue()
        ->and($editor->hasFileAttachmentsByDefault())->toBeFalse()
        ->and(toolbarShape($editor))->toBe([
            ['bold'],
            ['divider'],
            ['italic'],
        ]);
});
