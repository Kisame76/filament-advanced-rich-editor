<?php

declare(strict_types=1);

/**
 * The mode that takes the toolbar away.
 *
 * Everything it switches on is on by default already, so the mode is not a shortcut for
 * five calls - it is the decision that this field is a document rather than a form control,
 * and it holds that decision against a project that switched those things off globally.
 */
it('draws no toolbar at all', function (): void {
    expect(editor()->notion()->getToolbarButtons())->toBe([]);
});

it('keeps what is left to work with, even where the config switched it off', function (): void {
    // The whole of the mode: with no bar, the slash menu, the grip and the bar over a
    // selection are the only ways left to reach anything. A project that turned one of them
    // off for its ordinary fields did not mean it for this one.
    config()->set('filament-advanced-rich-editor.slash.enabled', false);
    config()->set('filament-advanced-rich-editor.drag_handle.enabled', false);
    config()->set('filament-advanced-rich-editor.drag_handle.insert', false);
    config()->set('filament-advanced-rich-editor.text_toolbar', false);

    $editor = editor()->notion();

    expect($editor->hasSlashMenu())->toBeTrue()
        ->and($editor->hasDragHandle())->toBeTrue()
        ->and($editor->hasDragHandleInsert())->toBeTrue()
        ->and($editor->hasTextToolbar())->toBeTrue();
});

it('leaves an ordinary field alone', function (): void {
    config()->set('filament-advanced-rich-editor.drag_handle.enabled', false);

    expect(editor()->hasDragHandle())->toBeFalse()
        ->and(toolbarShape(editor()))->not->toBe([])
        // And switching the mode off is the same as never asking for it.
        ->and(toolbarShape(editor()->notion(false)))->not->toBe([])
        ->and(editor()->notion(false)->hasDragHandle())->toBeFalse();
});

it('loses to what the field says itself', function (): void {
    expect(editor()->notion()->dragHandle(false)->hasDragHandle())->toBeFalse()
        ->and(editor()->notion()->slashMenu(false)->hasSlashMenu())->toBeFalse()
        ->and(editor()->notion()->textToolbar(false)->hasTextToolbar())->toBeFalse()
        ->and(toolbarShape(editor()->notion()->toolbarButtons([['bold']])))->toBe([['bold']]);
});

it('lets a preset put a bar back', function (): void {
    // `->preset()` and the mode are the same idea at two ranges, and a field that names both
    // has said something more specific about the bar than the mode did.
    expect(toolbarShape(editor()->notion()->preset('minimal'))[0])
        ->toBe(['bold', 'italic', 'underline'])
        // The mode still holds everything else it stands for.
        ->and(editor()->notion()->preset('minimal')->hasDragHandle())->toBeTrue();
});

it('takes a closure, so a record can decide', function (): void {
    expect(editor()->notion(fn (): bool => true)->getToolbarButtons())->toBe([])
        ->and(toolbarShape(editor()->notion(fn (): bool => false)))->not->toBe([]);
});

it('renders without a toolbar rather than raising', function (): void {
    // The empty bar is not a special case in the view: `@if ((! $isDisabled) &&
    // filled($toolbarButtons))` simply draws nothing, and `getTools()` never came from the
    // bar in the first place - so every tool the slash menu offers is still registered.
    $editor = editor()->notion();

    expect($editor->getTools())->not->toBeEmpty()
        ->and($editor->getSlashMenuForJs())->not->toBeNull();
});

it('still takes a file, because the slash menu offers one', function (): void {
    // The bar is where the upload answer is otherwise read from, and this mode has no bar -
    // so without an answer of its own it would switch the upload off on the one field whose
    // whole premise is that `/` reaches everything. The slash menu's insert group ships
    // `image` and `attachFiles`.
    $editor = editor()->notion();

    expect($editor->hasFileAttachments())->toBeTrue()
        ->and($editor->getSlashMenuForJs()['groups'] ?? [])->not->toBeEmpty();
});

it('lets a preset and the field overrule the upload answer', function (): void {
    expect(editor()->notion()->preset('comment')->hasFileAttachments())->toBeFalse()
        ->and(editor()->notion()->fileAttachments(false)->hasFileAttachments())->toBeFalse();
});

it('has no opinion about a switch it never named', function (): void {
    // The mode holds five things on, written out as a list. A switch outside it has to fall
    // through to the config exactly as on any other field, or the mode would be forcing
    // decisions nobody wrote down.
    config()->set('filament-advanced-rich-editor.typography.enabled', false);
    config()->set('filament-advanced-rich-editor.text_case', false);

    expect(editor()->notion()->hasTypography())->toBeFalse()
        ->and(editor()->notion()->hasTextCase())->toBeFalse()
        // While the five it did name are held against the same config.
        ->and(editor()->notion()->hasSlashMenu())->toBeTrue();
});
