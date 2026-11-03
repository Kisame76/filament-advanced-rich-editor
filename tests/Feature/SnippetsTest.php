<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Snippets;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * Ready-made content a field offers to put where the caret is. A snippet is a tool like any
 * other - the toolbar's dropdown and the slash menu offer the same one - and its content is
 * asked of the field when it is picked.
 */
const SNIPPETS = [
    'signature' => '<p>Kind regards,<br>The editorial team</p>',
    'legal_note' => ['label' => 'Legal note', 'content' => '<p>Prices without VAT.</p>'],
];

it('offers nothing until there is something to offer', function (): void {
    expect(editor()->hasSnippets())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('snippetSignature')
        ->and(ToolbarLayout::tokens()['snippets'](editor()))->toBe([])
        ->and(editor()->getSnippetForJs('signature'))->toBeNull();
});

it('reads a snippet as content alone, or as a label and content', function (): void {
    expect(Snippets::normalize(SNIPPETS))->toBe([
        'signature' => ['label' => 'Signature', 'content' => '<p>Kind regards,<br>The editorial team</p>'],
        'legal_note' => ['label' => 'Legal note', 'content' => '<p>Prices without VAT.</p>'],
    ])
        ->and(Snippets::toolName('legal_note'))->toBe('snippetLegalNote')
        // A document as an array is content too.
        ->and(Snippets::normalize(['doc' => ['content' => ['type' => 'doc', 'content' => []]]])['doc']['content'])
        ->toBe(['type' => 'doc', 'content' => []]);
});

it('refuses a key that cannot name a tool, and a snippet with no content, saying which', function (array $snippets, string $message): void {
    expect(fn () => Snippets::normalize($snippets))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'a label as the key' => [['Kind regards' => '<p>…</p>'], '[Kind regards] is not one'],
    'a list' => [['<p>…</p>'], '[0] is not one'],
    'no content' => [['signature' => ['label' => 'Signature']], 'The snippet [signature] has no content'],
    'two keys, one tool' => [['legal_note' => '<p>a</p>', 'legalNote' => '<p>b</p>'], 'The snippets [legal_note] and [legalNote] would be the same tool, [snippetLegalNote]'],
]);

it('registers a tool per snippet, offered in one dropdown', function (): void {
    $field = editor()->snippets(SNIPPETS);
    $dropdown = ToolbarLayout::tokens()['snippets']($field);

    expect($field->getTools()['snippetSignature']->getLabel())->toBe('Signature')
        ->and($field->getTools()['snippetLegalNote']->getLabel())->toBe('Legal note')
        ->and($dropdown)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($dropdown->getButtons())->toBe(['snippetSignature', 'snippetLegalNote'])
        // In the shipped toolbar, where it resolves to nothing on a field with none.
        ->and(config('filament-advanced-rich-editor.toolbar'))->toContain(['lists', 'mediaBrowser', 'table', 'callouts', 'snippets']);
});

it('asks the field for the content when one is picked, and only for a key on its list', function (): void {
    $field = editor()->snippets(SNIPPETS);

    expect($field->getTools()['snippetSignature']->getJsHandler())
        ->toContain("'getSnippetForJs', { key: 'signature' }")
        ->toContain('insertContent(content)')
        ->and($field->getSnippetForJs('signature'))->toBe('<p>Kind regards,<br>The editorial team</p>')
        ->and($field->getSnippetForJs('elsewhere'))->toBeNull()
        ->and($field->getSnippetForJs(['signature']))->toBeNull();
});

it('lists them in the slash menu, with what gets added', function (): void {
    $insert = collect(SlashMenu::for(editor()->snippets(SNIPPETS))['groups'])->firstWhere('key', 'insert');

    expect(collect($insert['items'])->pluck('label')->all())->toContain('Signature', 'Legal note');
});

it('takes its list from the config, and a field replaces it rather than adding to it', function (): void {
    config()->set('filament-advanced-rich-editor.snippets', ['signature' => '<p>Regards</p>']);

    expect(array_keys(editor()->getSnippets()))->toBe(['signature'])
        ->and(array_keys(editor()->snippets(fn (): array => ['footer' => '<p>Footer</p>'])->getSnippets()))->toBe(['footer']);
});

it('reads its list once, however many places ask for it', function (): void {
    // The toolbar, the slash menu, the plugin and every snippet picked ask - and a list read
    // from a table is a query each time.
    $reads = 0;
    $field = editor()->snippets(function () use (&$reads): array {
        $reads++;

        return SNIPPETS;
    });

    $field->hasSnippets();
    $field->getSnippets();
    $field->getSnippetForJs('signature');
    SlashMenu::for($field);

    expect($reads)->toBe(1)
        // And again for a list the field is handed afresh.
        ->and(array_keys($field->snippets(['footer' => '<p>Footer</p>'])->getSnippets()))->toBe(['footer']);
});
