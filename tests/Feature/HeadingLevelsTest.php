<?php

declare(strict_types=1);

it('reads the heading levels from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.heading_levels', [2, 3]);

    expect(editor()->getHeadingLevels())->toBe([2, 3])
        ->and(toolbarShape(editor())[2][0])->toBe('dropdown:h2,h3');
});

it('falls back to h1 to h4 when the config was never merged', function (): void {
    config()->set('filament-advanced-rich-editor.heading_levels', null);

    expect(editor()->getHeadingLevels())->toBe([1, 2, 3, 4]);
});

it('lets a field choose its own heading levels', function (): void {
    $editor = editor()->headingLevels([1, 2, 3, 4, 5, 6]);

    expect($editor->getHeadingLevels())->toBe([1, 2, 3, 4, 5, 6])
        ->and(toolbarShape($editor)[2][0])->toBe('dropdown:h1,h2,h3,h4,h5,h6')
        ->and(resolvedButtonNames($editor->getToolbarButtons()[2][0]))
        ->toBe(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
});

it('keeps the configured order of the heading levels', function (): void {
    expect(toolbarShape(editor()->headingLevels([3, 1, 2]))[2][0])->toBe('dropdown:h3,h1,h2');
});

it('accepts a closure for the heading levels', function (): void {
    expect(editor()->headingLevels(fn (): array => [2, 3])->getHeadingLevels())->toBe([2, 3]);
});

it('normalises the heading levels into a list of integers', function (): void {
    // A published config file is plain data and may well hold numeric strings or gaps.
    expect(editor()->headingLevels([3 => '2', 7 => '3'])->getHeadingLevels())->toBe([2, 3]);
});

it('rejects a heading level above h6', function (): void {
    editor()->headingLevels([1, 7])->getHeadingLevels();
})->throws(
    LogicException::class,
    'The heading level [7] used by the rich editor [content] does not exist',
);

it('rejects a heading level below h1', function (): void {
    editor()->headingLevels([0])->getHeadingLevels();
})->throws(LogicException::class, 'The heading level [0]');

it('rejects an invalid heading level while the toolbar is being built', function (): void {
    editor()->headingLevels([9])->getToolbarButtons();
})->throws(LogicException::class, 'The heading level [9]');
