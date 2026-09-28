<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextToolbarPlugin;

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

it('registers the extension that owns when the bubble is shown', function (): void {
    // Filament hard-codes the rule for this one key: focused, non-empty selection, and
    // `isActive('paragraph')`. The last clause is what keeps the bar out of a heading, so
    // the package replaces the rule through the bubble menu's own `updateOptions` message -
    // the same route the image bar and the two list bars already take.
    expect(pluginNames(editor()))->toContain(TextToolbarPlugin::class);
});

it('takes the extension away with the bubble it belongs to', function (): void {
    // Both ways of losing the bar, because a rule for a bar that is not there is a message
    // addressed to nobody.
    expect(pluginNames(editor()->textToolbar(false)))->not->toContain(TextToolbarPlugin::class)
        ->and(pluginNames(editor()->textToolbarButtons([])))->not->toContain(TextToolbarPlugin::class);
});

it('keeps the extension in the mode that has no other way to reach a mark', function (): void {
    // The hole this closes is worst here: with no toolbar, a heading offered no link, no
    // colours and no styles at all.
    expect(pluginNames(editor()->notion()))->toContain(TextToolbarPlugin::class);
});

it('ships the rule and nothing else', function (): void {
    $plugin = TextToolbarPlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorTools())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('text-toolbar');
});

it('answers in one place whether the bar is drawn at all', function (): void {
    expect(editor()->hasTextToolbarBubble())->toBeTrue()
        ->and(editor()->textToolbar(false)->hasTextToolbarBubble())->toBeFalse()
        ->and(editor()->textToolbarButtons([])->hasTextToolbarBubble())->toBeFalse()
        // And it is the same answer the floating toolbar list gives, rather than a second
        // one that can drift from it.
        ->and(array_key_exists('paragraph', editor()->getFloatingToolbars()))->toBeTrue();
});

it('no longer hides the bar from the stylesheet', function (): void {
    // The rule used to be a `:has(.ProseMirror-selectednode)` override that stopped the bar
    // being painted over the picture bar, written there because `shouldShow` looked out of
    // reach. It is not out of reach, and two mechanisms answering one question is one too
    // many: the JavaScript rule refuses a node selection outright.
    // Named by the selector rather than by the class, because the class has a second and
    // unrelated job: it is how a selected embed card is drawn.
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/filament-advanced-rich-editor.css');

    expect($css)->not->toContain(":has(.ProseMirror-selectednode) [x-ref='floatingToolbar::paragraph']")
        ->and($css)->toContain('.fi-arte-embed-card.ProseMirror-selectednode');
});
