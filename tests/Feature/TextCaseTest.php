<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextCasePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;

it('offers one tool per mode', function (): void {
    $tools = editor()->getTools();

    expect($tools)->toHaveKeys(['textCaseSentence', 'textCaseLower', 'textCaseUpper'])
        ->and($tools['textCaseUpper']->getJsHandler())
        ->toBe("\$getEditor()?.chain().focus().setTextCase('upper').run()");
});

it('labels each mode in the case it produces', function (): void {
    $tools = editor()->getTools();

    // Label and icon say the same thing twice: the label is set in the case it produces,
    // and Lucide's Aa/AB/ab draw it. Neither has to carry the meaning on its own.
    expect($tools['textCaseUpper']->getLabel())->toBe('UPPER CASE')
        ->and($tools['textCaseLower']->getLabel())->toBe('lower case')
        ->and($tools['textCaseSentence']->getLabel())->toBe('Sentence case')
        ->and($tools['textCaseUpper']->getIcon())->toBe(Icons::get('text_case_upper'));
});

it('refuses a mode it does not have', function (): void {
    expect(TextCasePlugin::toolName('upper'))->toBe('textCaseUpper')
        ->and(TextCasePlugin::toolName('title'))->toBeNull();
});

it('stores nothing, so it declares no PHP extension', function (): void {
    // A raised letter is a letter. Nothing to parse, nothing for the sanitiser to allow,
    // nothing for the renderer to be taught - the same deal the characters picker has.
    expect(TextCasePlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(TextCasePlugin::make()->getEditorActions())->toBe([]);
});

it('ships with no button anywhere, and the shortcut as the way in', function (): void {
    // Most documents never change the case of anything, and `more` is finite. The tools are
    // registered - so they can be configured in, and so the key works - but nothing spends a
    // slot on them.
    expect(editor()->getMoreTools())->not->toContain('textCaseSentence')
        ->and(array_merge(...toolbarShape(editor())))->not->toContain('textCaseUpper');
});

it('names the shortcut in the list, because nothing else says the field can do this', function (): void {
    $rows = collect(Shortcuts::for(editor()));

    expect($rows->firstWhere('keys', ['Shift', 'F3']))
        ->not->toBeNull()
        ->and($rows->firstWhere('keys', ['Shift', 'F3'])['label'])
        ->toBe('Cycle the case of the selection');
});

it('drops the shortcut row with the feature', function (): void {
    $rows = collect(Shortcuts::for(editor()->textCase(false)));

    expect($rows->firstWhere('keys', ['Shift', 'F3']))->toBeNull();
});

it('takes the tools away where the field says so', function (): void {
    expect(editor()->textCase(false)->getTools())->not->toHaveKey('textCaseUpper');
});

it('takes the trigger away with them', function (): void {
    // The token has to be put on the bar for this to mean anything. Asserting it against the
    // shipped toolbar would pass whatever the token does, because the token is not in it -
    // which is what this assertion did until a deliberate break failed to turn it red.
    $editor = editor()->toolbarButtons([['bold', 'textCase']])->textCase(false);

    expect(toolbarShape($editor))->toBe([['bold']]);
});

it('reads whether it is offered from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.text_case', false);

    expect(editor()->hasTextCase())->toBeFalse()
        ->and(editor()->textCase()->hasTextCase())->toBeTrue();
});

it('puts the three behind one trigger where a project asks for the token', function (): void {
    $editor = editor()->toolbarButtons([['textCase']]);

    expect(toolbarShape($editor))->toBe([
        ['dropdown:textCaseSentence,textCaseLower,textCaseUpper'],
    ]);
});

it('keeps the modes and their order the same on both sides', function (): void {
    // The JavaScript holds the same list, and `Shift+F3` walks it in that order. Two copies
    // of one list is what this asserts away: a mode added on one side only is a menu entry
    // whose command does not exist, or a command nothing can reach.
    $js = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/text-case.js');

    preg_match("/export const CASE_MODES = \[([^\]]+)\]/", $js, $matches);

    expect($matches)->toHaveCount(2);

    $modes = array_map(
        static fn (string $mode): string => trim($mode, " '"),
        explode(',', $matches[1]),
    );

    expect($modes)->toBe(TextCasePlugin::MODES);
});
