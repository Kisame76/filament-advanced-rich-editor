<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('lets a field replace the configured toolbar entirely', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar', [['bold'], 'divider', ['italic']]);

    $editor = editor()->toolbarButtons([
        ['italic', 'bold'],
        'divider',
        ['headings'],
    ]);

    expect(toolbarShape($editor))->toBe([
        ['italic', 'bold'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4'],
    ]);
});

it('groups consecutive loose buttons into one group', function (): void {
    expect(toolbarShape(editor()->toolbarButtons(['bold', 'italic', 'divider', 'link'])))->toBe([
        ['bold', 'italic', 'divider', 'link'],
    ]);
});

it('passes unknown tokens through untouched', function (): void {
    // Filament raises its own descriptive error for a button name it cannot resolve, so
    // the layout must not swallow or rewrite names it does not recognise.
    expect(toolbarShape(editor()->toolbarButtons([['bold', 'notAToken', 'divider', 'link']])))->toBe([
        ['bold', 'notAToken', 'divider', 'link'],
    ]);
});

it('drops a divider that ends up last inside its own group', function (): void {
    expect(toolbarShape(editor()->toolbarButtons([['bold', 'divider']])))->toBe([
        ['bold'],
    ]);
});

it('accepts component instances anywhere in the toolbar', function (): void {
    $editor = editor()->toolbarButtons([
        ['bold', ToolbarDropdown::make('Alignment', ['alignStart', 'alignEnd'])],
        'divider',
        ['link'],
    ]);

    $toolbar = $editor->getToolbarButtons();

    expect(toolbarShape($editor))->toBe([
        ['bold', 'dropdown:alignStart,alignEnd'],
        ['divider'],
        ['link'],
    ])
        // A hand built dropdown is resolved against the tools just like a token is.
        ->and(resolvedButtonNames($toolbar[0][1]))->toBe(['alignStart', 'alignEnd']);
});

it('removes a disabled button but keeps the dividers and dropdowns around it', function (): void {
    expect(toolbarShape(editor()->disableToolbarButtons(['italic'])))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4', 'fontSize', 'blockquote', 'codeBlock'],
        ['divider'],
        ['bold', 'strike', 'underline', 'link'],
        ['divider'],
        ['superscript', 'subscript'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:bulletList,orderedList,taskList'],
        ['divider'],
        ['image', 'table'],
    ]);
});

it('drops a dropdown when its token is disabled', function (): void {
    expect(toolbarShape(editor()->disableToolbarButtons(['headings'])))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['fontSize', 'blockquote', 'codeBlock'],
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

it('does not reach inside a dropdown token', function (): void {
    // Documented behaviour: disabling works on the toolbar array, which still holds the
    // unexpanded token at that point. `headingLevels()` is the way to narrow a dropdown.
    expect(toolbarShape(editor()->disableToolbarButtons(['h1', 'taskList']))[2])->toBe([
        'dropdown:h1,h2,h3,h4',
        'fontSize',
        'blockquote',
        'codeBlock',
    ]);
});

it('collapses the dividers that an emptied group left behind', function (): void {
    expect(toolbarShape(editor()->disableToolbarButtons(['superscript', 'subscript'])))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4', 'fontSize', 'blockquote', 'codeBlock'],
        ['divider'],
        ['bold', 'italic', 'strike', 'underline', 'link'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:bulletList,orderedList,taskList'],
        ['divider'],
        ['image', 'table'],
    ]);
});

it('drops a trailing divider', function (): void {
    expect(toolbarShape(editor()->disableToolbarButtons(['image', 'table'])))->toBe([
        ['undo', 'redo'],
        ['divider'],
        ['dropdown:h1,h2,h3,h4', 'fontSize', 'blockquote', 'codeBlock'],
        ['divider'],
        ['bold', 'italic', 'strike', 'underline', 'link'],
        ['divider'],
        ['superscript', 'subscript'],
        ['divider'],
        ['dropdown:alignStart,alignCenter,alignEnd,alignJustify', 'dropdown:bulletList,orderedList,taskList'],
    ]);
});

it('drops a leading divider', function (): void {
    expect(toolbarShape(editor()->toolbarButtons(['divider', ['bold'], 'divider', ['italic']])))->toBe([
        ['bold'],
        ['divider'],
        ['italic'],
    ]);
});

it('returns an empty toolbar when every button is disabled', function (): void {
    expect(toolbarShape(editor()->disableAllToolbarButtons()))->toBe([]);
});

it('appends enabled buttons to the end of the toolbar', function (): void {
    expect(toolbarShape(editor()->enableToolbarButtons([['taskList']])))->toBe([
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
        ['taskList'],
    ]);
});

it('does not enable a button that is already in the toolbar', function (): void {
    $editor = editor()
        ->toolbarButtons([['bold', 'italic']])
        ->enableToolbarButtons([['bold', 'strike']]);

    expect(toolbarShape($editor))->toBe([
        ['bold', 'italic'],
        ['strike'],
    ]);
});

it('applies disabling and enabling in the order they were called', function (): void {
    $editor = editor()
        ->toolbarButtons([['bold', 'italic']])
        ->disableToolbarButtons(['italic'])
        ->enableToolbarButtons([['italic', 'link']]);

    expect(toolbarShape($editor))->toBe([
        ['bold'],
        ['italic', 'link'],
    ]);
});
