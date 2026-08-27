<?php

declare(strict_types=1);

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;

it('puts the button left of the fullscreen one', function (): void {
    // Switched on for this: the shipped default leaves the source dialog off the bar.
    expect(toolbarGroup(editor()->sourceCode(), 'fullscreen'))->toBe([toolsShape(), 'fullscreen']);
});

it('hands the editor its own html to open with', function (): void {
    // Read out of the browser rather than off the server's copy of the state: the last
    // keystrokes may not have been synced yet, and they belong in the source view too.
    expect(editor()->sourceCode()->getTools()['sourceCode']->getJsHandler())
        ->toContain("mountAction('sourceCode'")
        ->toContain('$getEditor().getHTML()');
});

it('edits the html in a code editor rather than a text box', function (): void {
    $action = editor()->sourceCode()->getAction('sourceCode');

    $field = $action->getSchema(testSchema())->getComponents()[0];

    expect($field)->toBeInstanceOf(CodeEditor::class)
        ->and($field->getLanguage())->toBe(Language::Html);
});

it('shows the html the way it will be stored, not the way the browser wrote it', function (): void {
    $field = editor();

    // The browser's serialisation and the stored one are produced by two different halves
    // of the same schema. What the source view shows has to be the half that is saved, or
    // it is a view of something nobody keeps.
    $normalised = $field->normaliseSourceHtml('<p>text <em>emphasis</em></p><script>alert(1)</script>');

    expect($normalised)->toContain('<em>emphasis</em>')
        ->not->toContain('<script>');
});

it('keeps what the editor can hold and drops the rest', function (): void {
    $field = editor();

    // A `dir` survives because this package registered it, a `data-nonsense` does not,
    // exactly as it would when the same markup was pasted into the editor.
    expect($field->normaliseSourceHtml('<p dir="rtl" data-nonsense="1">x</p>'))
        ->toContain('dir="rtl"')
        ->not->toContain('data-nonsense');
});

it('drops the button when a field or the config says so', function (): void {
    config()->set('filament-advanced-rich-editor.source_code', true);

    expect(editor()->sourceCode(false)->getTools())->not->toHaveKey('sourceCode')
        ->and(toolbarGroup(editor()->sourceCode(false), 'fullscreen'))->toBe([toolsShape(), 'fullscreen']);

    config()->set('filament-advanced-rich-editor.source_code', false);

    expect(editor()->hasSourceCode())->toBeFalse()
        ->and(toolbarShape(editor()))->not->toContain(['sourceCode']);
});

it('keeps the source dialog off the bar until a project asks for it', function (): void {
    // Raw HTML editing bypasses every control the toolbar represents: an editor typing into
    // that box writes whatever the schema will accept. Worth having, not worth handing to
    // everybody who installs the package without being asked.
    expect(editor()->hasSourceCode())->toBeFalse()
        // Named in the shipped tools menu and dropped out of it while it is off, exactly
        // the way the styles picker resolves to nothing - so switching it on needs no
        // toolbar surgery, and the corner keeps its shape either way.
        ->and(resolvedButtonNames(toolbarItem(editor(), toolsShape())))->not->toContain('sourceCode')
        ->and(resolvedButtonNames(toolbarItem(editor()->sourceCode(), toolsShape())))->toContain('sourceCode')
        ->and(toolbarGroup(editor(), toolsShape()))->toBe([toolsShape(), 'fullscreen']);
});

it('turns the source dialog on from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.source_code', true);

    expect(editor()->hasSourceCode())->toBeTrue()
        ->and(editor()->sourceCode(false)->hasSourceCode())->toBeFalse();
});
