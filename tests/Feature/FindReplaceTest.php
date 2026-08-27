<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FindReplacePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;

it('sits with the tools that are about the editor rather than about the text', function (): void {
    // Searching belongs with fullscreen and help: none of the three changes a document, they
    // change how one is being worked on. They are a menu rather than three buttons, so the
    // corner keeps its shape as the rest of that family arrives.
    expect(editor()->getToolsMenu())->toContain('find')
        ->and(toolbarGroup(editor(), toolsShape()))->toBe([toolsShape(), 'fullscreen']);
});

it('opens the bar from the button', function (): void {
    expect(editor()->getTools()['find']->getJsHandler())->toContain('openFind()');
});

it('hands the bar every string it draws, because it is built in the browser', function (): void {
    $settings = editor()->getFindSettingsForJs();

    expect($settings)->toHaveKeys(['find', 'replace', 'previous', 'next', 'replaceOne', 'replaceAll', 'close', 'matchCase', 'wholeWord', 'noResults', 'count'])
        ->and($settings['find'])->toBe('Find')
        // The counter is a sentence with two numbers in it, and where those numbers sit
        // differs by language - so it crosses over as the sentence rather than as a format
        // this package would have to reinvent in JavaScript.
        ->and($settings['count'])->toBe(':current of :total')
        ->and($settings['icons'])->toHaveKeys(['previous', 'next', 'close', 'replace', 'grip'])
        ->and($settings['icons']['next'])->toContain('<svg')
        // The bar is a window hanging off the body, so it needs something to be dragged by
        // and something that says so - a text field cannot be a drag handle.
        ->and($settings['icons']['grip'])->toContain('<svg');
});

it('drops the tool and the bar when a field turns searching off', function (): void {
    $editor = editor()->find(false);

    expect($editor->getTools())->not->toHaveKey('find')
        // Without the settings the editor element carries no `data-arte-find`, and the
        // extension that reads it was never registered either.
        ->and($editor->getFindSettingsForJs())->toBeNull()
        ->and($editor->getPlugins())->not->toContain(FindReplacePlugin::class);
});

it('takes its name off the bar as well, not only out of the tool list', function (): void {
    // An unregistered name left standing in a toolbar group is not a missing button, it is
    // a `LogicException` out of the view - so the switch has to reach the layout too.
    expect(toolbarShape(editor()->find(false)))->not->toContain(['find'])
        ->and(array_merge(...toolbarShape(editor()->find(false))))->not->toContain('find');
});

it('turns searching off from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.find', false);

    expect(editor()->hasFind())->toBeFalse();
});

it('lists both keys, because the two of them open the same window differently', function (): void {
    $keys = array_column(Shortcuts::for(editor()), 'keys', 'label');

    expect($keys)->toHaveKeys(['Find', 'Find and replace'])
        ->and($keys['Find'])->toBe(['Mod', 'F'])
        // Mod+Alt+F rather than Ctrl+H, which is bound as well: this is the one pair that
        // works on both platforms, so the list says something true wherever it is read.
        // Ctrl+H is there for the muscle memory Word and Docs built, and on a Mac it never
        // arrives - Cmd+H hides the application before the page sees it.
        ->and($keys['Find and replace'])->toBe(['Mod', 'Alt', 'F']);
});

it('says nothing about keys the field does not answer to', function (): void {
    $labels = array_column(Shortcuts::for(editor()->find(false)), 'label');

    expect($labels)->not->toContain('Find')
        ->and($labels)->not->toContain('Find and replace');
});

it('stores nothing: a search leaves the document exactly as it was', function (): void {
    $plugin = FindReplacePlugin::make();

    // No PHP extension and no mark. A replacement is ordinary text by the time it is
    // saved, so nothing has to be taught about it on the way back out.
    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and(RichContentRenderer::make('<p>one <strong>two</strong></p>')->toHtml())
        ->toContain('<strong>two</strong>');
});

it('draws icons a project can swap', function (): void {
    // Replacing and the grip are Lucide drawings this package ships: Heroicons has neither,
    // and its circular arrow reads as reload rather than as one thing becoming another.
    expect(Icons::get('find'))->not->toBeEmpty()
        ->and(Icons::get('find_replace'))->toBe('arte-replace')
        ->and(Icons::get('find_grip'))->toBe('arte-grip-vertical');

    config()->set('filament-advanced-rich-editor.icons.find', 'heroicon-o-map');

    expect(Icons::get('find'))->toBe('heroicon-o-map');
});
