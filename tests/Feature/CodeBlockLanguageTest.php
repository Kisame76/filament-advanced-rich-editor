<?php

declare(strict_types=1);

it('offers the languages from the config file', function (): void {
    expect(editor()->getCodeBlockLanguages())
        ->toHaveKey('php')
        ->toHaveKey('javascript')
        ->and(editor()->getCodeBlockLanguages()['php'])->toBe('PHP');
});

it('lets a field choose its own languages', function (): void {
    // A field for release notes has no business offering twelve languages, and one for API
    // docs may want a thirteenth.
    expect(editor()->codeBlockLanguages(['php' => 'PHP'])->getCodeBlockLanguages())
        ->toBe(['php' => 'PHP']);
});

it('accepts a closure for the languages', function (): void {
    expect(editor()->codeBlockLanguages(fn (): array => ['sql' => 'SQL'])->getCodeBlockLanguages())
        ->toBe(['sql' => 'SQL']);
});

it('takes the picker away when the list is emptied', function (): void {
    // One switch rather than two: a project that curated the languages down to nothing has
    // already said it does not want a picker.
    expect(editor()->codeBlockLanguages([])->getCodeBlockSettingsForJs())->toBeNull();
});

it('tells the script the languages and what to call a block without one', function (): void {
    $settings = editor()->codeBlockLanguages(['php' => 'PHP'])->getCodeBlockSettingsForJs();

    expect($settings['languages'])->toBe(['php' => 'PHP'])
        ->and($settings['plain'])->toBe('Plain text');
});
