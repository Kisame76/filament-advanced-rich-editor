<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPin;

it('pins the group that is about the editor rather than about the text', function (): void {
    $split = editor()->getSplitToolbarButtons();

    expect(toolbarGroupsShape($split['pinned']))->toBe([
        ['find', 'accessibility', 'fullscreen', 'help'],
    ])
        // The overflow menu is not one of them: what it holds are tools for the text, so
        // it stays with the aligned groups and ends them.
        ->and(array_slice(toolbarGroupsShape($split['flow']), -1))->toBe([
            [moreShape()],
        ]);
});

it('still answers a button lookup for a pinned button', function (): void {
    // The two halves are one toolbar; a button that is pinned is still on the bar, and
    // Filament's own checks must not stop finding it.
    expect(editor()->sourceCode()->hasToolbarButton('sourceCode'))->toBeTrue()
        ->and(editor()->hasToolbarButton('help'))->toBeTrue()
        // The marker itself carries no button name at all.
        ->and(editor()->hasToolbarButton('pin'))->toBeFalse();
});

it('pins to the end of the bar', function (): void {
    expect(editor()->getToolbarAlignment())->toBe('center')
        ->and(editor()->getToolbarPinSide())->toBe('end')
        ->and(editor()->toolbarAlignment('start')->getToolbarPinSide())->toBe('end')
        ->and(editor()->toolbarAlignment('between')->getToolbarPinSide())->toBe('end');
});

it('takes the start of a bar that is aligned to the end', function (): void {
    // That bar has pushed its groups against the end, so the end is the one edge the
    // pinned buttons cannot have - they would land back among the groups they were
    // taken out of.
    expect(editor()->toolbarAlignment('end')->getToolbarPinSide())->toBe('start')
        ->and(editor()->toolbarAlignment('right')->getToolbarPinSide())->toBe('start');
});

it('splits a group the marker sits inside', function (): void {
    $split = editor()->toolbarButtons([['bold', 'pin', 'italic']])->getSplitToolbarButtons();

    // What was on either side of the marker was never one cluster, so it stays two.
    expect(toolbarGroupsShape($split['flow']))->toBe([['bold']])
        ->and(toolbarGroupsShape($split['pinned']))->toBe([['italic']]);
});

it('drops a marker that has nothing left to split', function (): void {
    $split = editor()->toolbarButtons([['bold'], 'pin', ['italic'], 'pin', ['strike']])->getSplitToolbarButtons();

    expect(toolbarGroupsShape($split['flow']))->toBe([['bold']])
        ->and(toolbarGroupsShape($split['pinned']))->toBe([['italic'], ['strike']])
        // Neither marker survives into the toolbar the view renders.
        ->and(toolbarShape(editor()->toolbarButtons([['bold'], 'pin', ['italic']])))
        ->toBe([['bold'], ['italic']]);
});

it('leaves the bar unsplit when nothing follows the marker', function (): void {
    $split = editor()->toolbarButtons([['bold', 'italic'], 'pin'])->getSplitToolbarButtons();

    expect(toolbarGroupsShape($split['flow']))->toBe([['bold', 'italic']])
        ->and($split['pinned'])->toBe([]);
});

it('collapses the dividers of each half on its own', function (): void {
    $split = editor()
        ->toolbarButtons([['bold'], 'divider', 'pin', 'divider', ['italic'], 'divider'])
        ->getSplitToolbarButtons();

    // Trailing in the flow, leading and trailing in the pinned half: all three border on
    // nothing once the bar is two containers, so all three go.
    expect(toolbarGroupsShape($split['flow']))->toBe([['bold']])
        ->and(toolbarGroupsShape($split['pinned']))->toBe([['italic']]);
});

it('puts everything back on the bar when the marker is disabled', function (): void {
    $editor = editor()->disableToolbarButtons(['pin']);

    expect($editor->getPinnedToolbarButtons())->toBe([])
        ->and(array_slice(toolbarGroupsShape($editor->getFlowToolbarButtons()), -1))->toBe([
            ['find', 'accessibility', 'fullscreen', 'help'],
        ]);
});

it('expands the marker into nothing anyone can draw', function (): void {
    $resolved = ToolbarLayout::resolve(['pin'], editor());

    expect($resolved[0])->toBeInstanceOf(ToolbarPin::class);
});
