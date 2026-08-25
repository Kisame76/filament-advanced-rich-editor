<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('parks the rarely used tools in a dropdown at the end of the toolbar', function (): void {
    // The end of the aligned groups, not of the whole bar: what the menu holds are tools
    // for the text, so it travels with them rather than with the pinned corner.
    expect(array_slice(toolbarGroupsShape(editor()->getFlowToolbarButtons()), -1))->toBe([
        ['dropdown:strike,subscript,superscript,code,codeBlock,clearFormatting,horizontalRule,details,emoji'],
    ]);
});

it('fills the dropdown with the tools most documents never need', function (): void {
    // Every one of them is a stock Filament tool, so they resolve without the package
    // registering anything of its own.
    expect(resolvedButtonNames(toolbarDropdown(editor(), 'subscript')))->toBe([
        'strike', 'subscript', 'superscript', 'code', 'codeBlock', 'clearFormatting', 'horizontalRule', 'details',
        // The package's own addition, and the only one shipped in the list. The two
        // direction tools are registered but left out - see TextDirectionTest.
        'emoji',
    ]);
});

it('lets a field and the config file say what goes in there', function (): void {
    expect(resolvedButtonNames(toolbarDropdown(editor()->moreTools(['code', 'highlight']), 'code')))
        ->toBe(['code', 'highlight']);

    config()->set('filament-advanced-rich-editor.more', ['horizontalRule']);

    expect(resolvedButtonNames(toolbarDropdown(editor(), 'horizontalRule')))->toBe(['horizontalRule']);
});

it('leaves the button out entirely when there is nothing to put in it', function (): void {
    // An empty dropdown would be a trigger that opens onto nothing.
    expect(toolbarGroup(editor()->moreTools([]), 'fullscreen'))->toBe([toolsShape(), 'fullscreen']);
});

it('keeps the three dots on the trigger whatever the caret sits in', function (): void {
    $html = ToolbarDropdown::more(['subscript', 'superscript'])->resolve(editor()->getTools())->toEmbeddedHtml();

    // Filament's own dropdowns swap the trigger for the active option - which is what makes
    // the alignment trigger follow the caret - and an overflow menu must not: the dots are
    // the way back to it. The trigger still lights up while one of its tools is on.
    expect($html)->toContain('fi-active')
        ->and($html)->not->toContain('(() =>');
});

it('draws the trigger with the ellipsis', function (): void {
    expect(Icons::get('more'))->toBe('heroicon-o-ellipsis-horizontal')
        ->and(toolbarDropdown(editor(), 'subscript')->getIcon())->toBe('heroicon-o-ellipsis-horizontal');
});

it('names the dropdown after what it holds', function (): void {
    expect(toolbarDropdown(editor(), 'subscript')->getName())->toBe('More');
});
