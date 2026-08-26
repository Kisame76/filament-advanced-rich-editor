<?php

declare(strict_types=1);

it('gives selected text a floating toolbar of its own', function (): void {
    // Filament's JavaScript treats `'paragraph'` as a special case and shows that toolbar
    // on a non-empty selection inside a paragraph, which is the bubble people expect.
    expect(editor()->getFloatingToolbars())->toHaveKey('paragraph');
});

it('offers what a selection is actually for', function (): void {
    $buttons = editor()->getFloatingToolbars()['paragraph'];

    expect(toolbarGroupsShape([$buttons])[0])
        ->toBe(['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground']);
});

it('puts the styles picker in the bubble once a project has styles', function (): void {
    // The reason the bubble is worth having at all: bold and italic are on every editor,
    // the project's own design system is not.
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    expect(toolbarGroupsShape([editor()->getFloatingToolbars()['paragraph']])[0])
        ->toBe(['styles', 'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground']);
});

it('leaves out what the field switched off', function (): void {
    expect(toolbarGroupsShape([editor()->textColor(false)->getFloatingToolbars()['paragraph']])[0])
        ->toBe(['bold', 'italic', 'underline', 'link', 'textBackground']);
});

it('falls back to the same bubble when the config was never merged', function (): void {
    // The hard coded fallback and the shipped config file are two copies of one list, and
    // this is what keeps them from drifting apart - the same guard the main toolbar has.
    $configured = toolbarGroupsShape([editor()->getFloatingToolbars()['paragraph']])[0];

    config()->set('filament-advanced-rich-editor.text_toolbar_buttons', null);

    expect(toolbarGroupsShape([editor()->getFloatingToolbars()['paragraph']])[0])->toBe($configured);
});

it('drops the bubble when a field or the config says so', function (): void {
    expect(editor()->textToolbar(false)->getFloatingToolbars())->not->toHaveKey('paragraph');

    config()->set('filament-advanced-rich-editor.text_toolbar', false);

    expect(editor()->hasTextToolbar())->toBeFalse()
        ->and(editor()->getFloatingToolbars())->not->toHaveKey('paragraph')
        ->and(editor()->textToolbar()->getFloatingToolbars())->toHaveKey('paragraph');
});

it('lets a field name its own contents', function (): void {
    $field = editor()->textToolbarButtons(['bold', 'link']);

    expect(toolbarGroupsShape([$field->getFloatingToolbars()['paragraph']])[0])->toBe(['bold', 'link'])
        // An empty list is a field saying it wants no bubble, not a field saying nothing.
        ->and(editor()->textToolbarButtons([])->getFloatingToolbars())->not->toHaveKey('paragraph');
});

it('leaves the image and table bubbles where they were', function (): void {
    expect(editor()->getFloatingToolbars())->toHaveKeys(['image', 'table']);
});

it('reads the same left to right as the bar at the top', function (): void {
    // A link is an annotation on selected text, the same as bold, so it sits with the marks
    // in both places. Two bars that offer the same buttons in a different order are two
    // things to learn instead of one.
    //
    // Nothing in the bubble that is not on the bar: what a selection is for is what the
    // marks group at the top offers, in the same order.
    $bubble = toolbarGroupsShape([editor()->getFloatingToolbars()['paragraph']])[0];

    expect($bubble)->toBe(['bold', 'italic', 'underline', 'link', 'textColor', 'textBackground'])
        ->and(array_values(array_diff($bubble, toolbarGroup(editor(), 'bold'))))->toBe([])
        // And what they share is in the same order in both.
        ->and(array_values(array_intersect($bubble, toolbarGroup(editor(), 'bold'))))
        ->toBe(toolbarGroup(editor(), 'bold'));
});
