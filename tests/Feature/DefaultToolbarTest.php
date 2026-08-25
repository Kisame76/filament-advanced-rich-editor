<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('resolves the shipped toolbar into groups, dropdowns and dividers', function (): void {
    expect(toolbarShape(editor()))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:paragraph,h1,h2,h3,h4', 'fontSize'],
        ['divider'],
        ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2'],
        ['divider'],
        ['dropdown:bulletList,orderedList,taskList', 'image', 'embed', 'table', 'blockquote'],
        ['divider'],
        [moreShape()],
        [toolsShape(), 'fullscreen'],
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
        ['dropdown:paragraph,h1,h2,h3,h4', 'fontSize'],
        ['divider'],
        ['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2'],
        ['divider'],
        ['dropdown:bulletList,orderedList,taskList', 'image', 'embed', 'table', 'blockquote'],
        ['divider'],
        [moreShape()],
        [toolsShape(), 'fullscreen'],
    ]);
});

it('builds a separate divider instance for every occurrence', function (): void {
    $toolbar = editor()->getToolbarButtons();

    $dividers = array_values(array_filter(
        array_map(fn (array $group): mixed => $group[0], editor()->getToolbarButtons()),
        fn (mixed $item): bool => $item instanceof ToolbarDivider,
    ));

    expect($dividers)->toHaveCount(5)
        // Each occurrence is its own object: Filament clones toolbar items while
        // filtering, and the view renders every one of them.
        ->and($dividers[0])->not->toBe($dividers[1]);
});

it('configures the headings and lists dropdowns from the field', function (): void {
    $toolbar = editor()->getToolbarButtons();

    $headings = toolbarItem(editor(), 'dropdown:paragraph,h1,h2,h3,h4');
    $lists = toolbarItem(editor(), 'dropdown:bulletList,orderedList,taskList');

    expect($headings)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($headings->getName())->toBe('Headings')
        ->and($headings->getIcon())->toBe('fi-o-heading')
        ->and($headings->hasTextualButtons())->toBeTrue()
        ->and($lists)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($lists->getName())->toBe('Lists')
        ->and($lists->getIcon())->toBe('heroicon-o-list-bullet')
        ->and($lists->hasTextualButtons())->toBeTrue();
});

it('resolves dropdown options against the registered editor tools', function (): void {
    $toolbar = editor()->getToolbarButtons();

    expect(resolvedButtonNames(toolbarItem(editor(), 'dropdown:paragraph,h1,h2,h3,h4')))->toBe(['paragraph', 'h1', 'h2', 'h3', 'h4'])
        ->and(resolvedButtonNames(toolbarItem(editor(), 'dropdown:bulletList,orderedList,taskList')))->toBe(['bulletList', 'orderedList', 'taskList']);
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

it('makes room for the styles picker beside the heading levels', function (): void {
    // The token is in the shipped layout and removes itself where a project named no
    // styles. Without it in the default nobody who has not read the manual would ever see
    // the feature at all - and it is now the sanctioned way to reach a theme's typography,
    // since the typeface picker is no longer on the bar.
    expect(toolbarGroup(editor(), 'fontSize'))->not->toContain('styles');

    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    expect(toolbarGroup(editor(), 'fontSize'))
        ->toBe(['dropdown:paragraph,h1,h2,h3,h4', 'styles', 'fontSize']);
});
