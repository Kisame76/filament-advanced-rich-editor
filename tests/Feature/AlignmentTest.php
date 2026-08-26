<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AlignmentPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('merges the alignments into one dropdown, next to the line spacing', function (): void {
    // Both say how the block is laid out rather than what is in it, so they share a group
    // of their own between the character controls and everything that inserts something.
    expect(toolbarGroup(editor(), 'dropdown:alignStart,alignCenter,alignEnd,alignJustify'))->toBe([
        'dropdown:alignStart,alignCenter,alignEnd,alignJustify',
        'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2',
    ]);
});

it('leaves the trigger icon to the active option', function (): void {
    $alignment = toolbarItem(editor(), 'dropdown:alignStart,alignCenter,alignEnd,alignJustify');

    // No icon of its own: `ToolbarButtonGroup` then renders the first option's icon and
    // swaps it for whichever option is active.
    expect($alignment)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($alignment->getIcon())->toBeNull()
        ->and($alignment->hasTextualButtons())->toBeTrue()
        ->and(resolvedButtonNames($alignment))->toBe(['alignStart', 'alignCenter', 'alignEnd', 'alignJustify']);
});

it('reads the alignments from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.alignments', ['alignCenter', 'alignStart']);

    expect(editor()->getAlignments())->toBe(['alignCenter', 'alignStart'])
        ->and(toolbarDropdownName(editor(), 'alignCenter'))->toBe('dropdown:alignCenter,alignStart');
});

it('lets a field choose its own alignments', function (): void {
    expect(toolbarDropdownName(editor()->alignments(['alignStart', 'alignJustify']), 'alignStart'))
        ->toBe('dropdown:alignStart,alignJustify');
});

it('accepts a closure for the alignments', function (): void {
    expect(editor()->alignments(fn (): array => ['alignEnd'])->getAlignments())->toBe(['alignEnd']);
});

it('labels the options after what they do', function (): void {
    $tools = editor()->getTools();

    expect($tools['alignStart']->getLabel())->toBe('Left')
        ->and($tools['alignCenter']->getLabel())->toBe('Center')
        ->and($tools['alignEnd']->getLabel())->toBe('Right')
        ->and($tools['alignJustify']->getLabel())->toBe('Justify')
        // The logical tool names are kept, so right-to-left content still behaves.
        ->and($tools['alignStart']->getIcon())->toBe('fi-o-align-start');
});

it('repairs the two shortcuts TipTap binds and then declines to answer', function (): void {
    // `TextAlign` binds Mod+Shift+L and Mod+Shift+R to `left` and `right`; Filament
    // configures it with `start` and `end`, and `setTextAlign` returns false for an
    // alignment it was not configured with. Both keys did nothing - and a handler that
    // returns false leaves the event to the browser, where Ctrl+Shift+R is a hard reload.
    $plugin = AlignmentPlugin::make();

    expect($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('alignment')
        // Nothing else: the four buttons and the attribute they write are Filament's own.
        ->and($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorTools())->toBe([])
        ->and($plugin->getEditorActions())->toBe([]);
});

it('registers it on every field, whatever the field shows', function (): void {
    // The keys are bound by Filament's build either way, so a field that hides the
    // dropdown has the same two broken shortcuts as one that shows it.
    expect(pluginNames(editor()))->toContain(AlignmentPlugin::class)
        ->and(pluginNames(editor()->alignments(['alignCenter'])))->toContain(AlignmentPlugin::class)
        ->and(pluginNames(editor()->toolbarButtons([['bold']])))->toContain(AlignmentPlugin::class);
});
