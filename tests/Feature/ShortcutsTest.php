<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Livewire\Livewire;

/**
 * The keys a project moves. Which key runs what is worked out here, in PHP, and handed to
 * the script as a finished list; the script that binds it is tested in
 * `tests/js/shortcuts.test.js`.
 */
function shortcutRows(array $shortcuts): array
{
    return array_column(Shortcuts::for(editor()->shortcuts($shortcuts)), 'keys', 'label');
}

it('reads a key however it was written', function (): void {
    foreach (['Mod-Shift-K', 'mod+shift+k', 'Shift-Mod-K', ' MOD-SHIFT-k '] as $written) {
        expect(Shortcuts::parse($written, 'link'))->toBe(['Mod', 'Shift', 'K']);
    }

    expect(Shortcuts::parse('Mod--', 'link'))->toBe(['Mod', '-'])
        ->and(Shortcuts::parse('Mod++', 'link'))->toBe(['Mod', '+'])
        ->and(Shortcuts::parse('Ctrl-Alt-arrowup', 'link'))->toBe(['Ctrl', 'Alt', 'ArrowUp'])
        // A function key types nothing, so it needs no modifier.
        ->and(Shortcuts::parse('f9', 'link'))->toBe(['F9']);
});

it('refuses a key it cannot bind, and says for which tool', function (mixed $written): void {
    expect(fn () => Shortcuts::parse($written, 'link'))
        ->toThrow(InvalidArgumentException::class, 'The shortcut for [link] cannot be bound');
})->with([
    // These type a character, and binding them would take it away from the field.
    'a letter' => 'K',
    'a capital' => 'Shift-K',
    // Written for a Mac, and nothing anywhere else.
    'Cmd' => 'Cmd-K',
    'no key' => 'Mod-',
    'a key that does not exist' => 'Mod-Shift-Foo',
    'nothing' => '',
    'not a string' => 42,
]);

it('answers to the keys it ships with until something is moved', function (): void {
    expect(editor()->getShortcuts())->toBe([])
        ->and(Shortcuts::keys(editor()))->toMatchArray(['bold' => ['Mod', 'B'], 'h2' => ['Mod', 'Alt', '2']])
        ->and(Shortcuts::bindings(editor()))->toBe([])
        // Nothing to bind, so nothing is loaded.
        ->and(editor()->getTipTapJsExtensions())->each->not->toContain('shortcuts')
        ->and(editor()->getShortcutSettingsForJs())->toBeNull();
});

it('gives a tool a first key, another one, or none', function (): void {
    $keys = Shortcuts::keys(editor()->shortcuts([
        'link' => 'Mod-K',
        'highlight' => 'Mod-Shift-Y',
        'underline' => null,
        'strike' => false,
    ]));

    expect($keys['link'])->toBe(['Mod', 'K'])
        ->and($keys['highlight'])->toBe(['Mod', 'Shift', 'Y'])
        ->and($keys)->not->toHaveKey('underline')
        ->and($keys)->not->toHaveKey('strike');
});

it('takes a key from whichever tool had it', function (): void {
    $keys = Shortcuts::keys(editor()->shortcuts(['bold' => 'Mod-Shift-B']));

    expect($keys['bold'])->toBe(['Mod', 'Shift', 'B'])
        ->and($keys)->not->toHaveKey('blockquote');
});

it('lays the field over the config, so the later claim wins', function (): void {
    config()->set('filament-advanced-rich-editor.shortcuts', ['link' => 'Mod-K', 'italic' => 'Mod-J']);

    $keys = Shortcuts::keys(editor()->shortcuts(['italic' => 'Mod-K']));

    // The field gave Mod+K to italic, so the link the config gave it to has none.
    expect($keys['italic'])->toBe(['Mod', 'K'])
        ->and($keys)->not->toHaveKey('link')
        ->and(Shortcuts::keys(editor())['link'])->toBe(['Mod', 'K']);
});

it('skips a tool the field does not have, but not a key it cannot bind', function (): void {
    config()->set('filament-advanced-rich-editor.shortcuts', ['nonsense' => 'Mod-K', 'taskList' => 'Mod-Shift-T']);

    expect(Shortcuts::keys(editor()))->not->toHaveKey('nonsense')
        ->and(Shortcuts::keys(editor()->taskList(false)))->not->toHaveKey('taskList')
        ->and(Shortcuts::keys(editor())['taskList'])->toBe(['Mod', 'Shift', 'T'])
        ->and(fn () => Shortcuts::keys(editor()->shortcuts(['bold' => 'B'])))->toThrow(InvalidArgumentException::class);
});

it('binds a key to the handler its button runs', function (): void {
    $field = editor()->shortcuts(['link' => 'Mod-K']);

    expect(Shortcuts::bindings($field))->toBe([
        ['keys' => 'Mod-k', 'tool' => 'link', 'handler' => $field->getTools()['link']->getJsHandler()],
    ]);
});

it('stops a key a tool lost from reaching the extension that shipped it', function (): void {
    $field = editor()->shortcuts(['bold' => 'Mod-Shift-B', 'redo' => null]);
    $bindings = collect(Shortcuts::bindings($field))->keyBy('keys');

    // Bold now answers blockquote's key, and its own does nothing - in lower case, which is
    // how ProseMirror's keymap tells Mod+B from Mod+Shift+B.
    expect($bindings['Mod-Shift-b'])->toMatchArray(['tool' => 'bold', 'handler' => $field->getTools()['bold']->getJsHandler()])
        ->and($bindings['Mod-b'])->toMatchArray(['tool' => 'bold', 'handler' => null])
        // Redo goes with both of its keys, the one the list shows and the one it does not.
        ->and($bindings['Mod-Shift-z'])->toMatchArray(['tool' => 'redo', 'handler' => null])
        ->and($bindings['Mod-y'])->toMatchArray(['tool' => 'redo', 'handler' => null])
        ->and($bindings)->toHaveCount(4);
});

it('lists the keys the field answers to, not the ones it shipped with', function (): void {
    $rows = shortcutRows(['bold' => 'Mod-Shift-B', 'underline' => null, 'link' => 'Mod-K', 'find' => 'Mod-Shift-F']);

    expect($rows['Bold'])->toBe(['Mod', 'Shift', 'B'])
        ->and($rows)->not->toHaveKey('Underline')
        ->and($rows)->not->toHaveKey('Quote')
        // A key somebody chose is listed even where the shipped list would not name the tool.
        ->and($rows['Link'])->toBe(['Mod', 'K'])
        ->and($rows['Find'])->toBe(['Mod', 'Shift', 'F']);
});

it('stops listing a key of the editor once a tool has it', function (): void {
    expect(shortcutRows([]))->toHaveKey('Paste as plain text')
        ->and(shortcutRows(['link' => 'Mod-Shift-V']))->not->toHaveKey('Paste as plain text');
});

it('loads its script only where a key was moved, and hands it the bindings', function (): void {
    expect(editor()->shortcuts(['link' => 'Mod-K'])->getTipTapJsExtensions())
        ->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/shortcuts', 'kisame76/filament-advanced-rich-editor'));

    config()->set('filament-advanced-rich-editor.shortcuts', ['link' => 'Mod-K']);

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.Livewire::test(EditorFormComponent::class)->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $element = (new DOMXPath($document))->query('//*[@data-arte-shortcuts]')->item(0);

    expect($element)->toBeInstanceOf(DOMElement::class)
        ->and($element->getAttribute('x-ref'))->toBe('editor')
        ->and(json_decode($element->getAttribute('data-arte-shortcuts'), true)[0])->toMatchArray(['keys' => 'Mod-k', 'tool' => 'link']);
});
