<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FocusModePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * Writing with the rest of the document out of the way. What is dimmed and where the line goes
 * is worked out in the browser and tested in `tests/js/focus-mode.test.js`; what is tested here
 * is the wiring - the switch, the place in the tools menu, and the pressed state.
 */
it('ships off', function (): void {
    // Some people write like this every day and most never reach for it - and in a form, a
    // page scrolling to keep a line in the middle surprises anybody who did not ask.
    expect(editor()->hasFocusMode())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('focusMode');
});

it('reads the switch from the config file, and lets a field overrule it', function (): void {
    expect(editor()->focusMode()->getTools())->toHaveKey('focusMode');

    config()->set('filament-advanced-rich-editor.focus_mode', true);

    expect(editor()->hasFocusMode())->toBeTrue()
        ->and(editor()->focusMode(false)->hasFocusMode())->toBeFalse();
});

it('waits in the shipped tools menu, beside the other ways of looking at the document', function (): void {
    // Named there whether or not it is on, so switching it on is all a project does.
    expect(config('filament-advanced-rich-editor.tools_menu'))->toContain('focusMode')
        ->and(ToolbarLayout::tokens()['focusMode'](editor()))->toBe([])
        ->and(ToolbarLayout::tokens()['focusMode'](editor()->focusMode()))->toBe('focusMode');
});

it('is a toggle that says when it is on, and stores nothing', function (): void {
    $plugin = FocusModePlugin::make();
    $tool = collect($plugin->getEditorTools())->firstWhere(fn (RichEditorTool $tool): bool => $tool->getName() === 'focusMode');

    expect($tool->getJsHandler())->toBe('$getEditor()?.chain().focus().toggleFocusMode().run()')
        ->and($tool->getActiveJsExpression())->toBe('$getEditor()?.storage?.arteFocusMode?.on === true')
        ->and($tool->getExtraAttributes())->toHaveKey('x-bind:aria-pressed')
        // A view and nothing else: no node, no mark, nothing in the stored document.
        ->and($plugin->getTipTapPhpExtensions())->toBe([]);
});
