<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\IndentPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Indent;

/**
 * One step in, one step out, and the margin the document keeps between them.
 */
$render = function (string $html, mixed $step = null, mixed $max = null): string {
    return RichContentRenderer::make($html)
        ->plugins([IndentPlugin::make($step, $max)])
        ->toHtml();
};

it('ships off, and nothing ships on a bar either', function (): void {
    // Most documents indent nothing, so the pair is a decision rather than a default -
    // the same call the brush and the date buttons make.
    $editor = editor();

    expect($editor->hasIndent())->toBeFalse()
        ->and($editor->getTools())->not->toHaveKey('indent')
        ->and($editor->getTools())->not->toHaveKey('outdent')
        ->and($editor->getIndentSettingsForJs())->toBeNull()
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof IndentPlugin))
        ->toBe([])
        ->and(resolvedButtonNames(toolbarDropdown($editor, 'subscript')))
        ->not->toContain('indent');
});

it('registers the pair and nothing else once it is on', function (): void {
    $tools = editor()->indent()->getTools();

    expect($tools)->toHaveKey('indent')
        ->and($tools)->toHaveKey('outdent')
        ->and($tools['indent']->getLabel())->toBe('Increase indent')
        ->and($tools['outdent']->getLabel())->toBe('Decrease indent')
        ->and($tools['indent']->getIcon())->toBe('arte-indent-increase')
        ->and($tools['outdent']->getIcon())->toBe('arte-indent-decrease');

    config()->set('filament-advanced-rich-editor.indent.enabled', true);

    expect(editor()->hasIndent())->toBeTrue();
});

it('moves rather than marks, so neither button has an active state', function (): void {
    $tools = editor()->indent()->getTools();

    expect($tools['indent']->getJsHandler())->toContain('indentBlock()')
        ->and($tools['outdent']->getJsHandler())->toContain('outdentBlock()')
        ->and($tools['indent']->getActiveJsExpression())->toBeNull()
        ->and($tools['outdent']->getActiveJsExpression())->toBeNull();
});

it('gives the names buttons on a bar once it is on, and takes them back off', function (): void {
    // A token rather than a bare name, so a bar naming the pair on a field that has them
    // switched off drops them instead of raising on a tool nobody registered.
    $bar = [['bold', 'indent', 'outdent']];

    expect(toolbarShape(editor()->indent()->toolbarButtons($bar)))->toBe([['bold', 'indent', 'outdent']])
        ->and(toolbarShape(editor()->indent(false)->toolbarButtons($bar)))->toBe([['bold']]);
});

it('hands the step and the depth to the script', function (): void {
    expect(editor()->indent()->getIndentSettingsForJs())->toBe(['step' => '2.5rem', 'max' => 8])
        ->and(editor()->indent()->indentStep('1.27cm')->indentMax(4)->getIndentSettingsForJs())
        ->toBe(['step' => '1.27cm', 'max' => 4]);
});

it('reads a bare number as rem and canonicalises the spelling', function (): void {
    expect(editor()->indentStep(2)->getIndentStep())->toBe('2rem')
        ->and(editor()->indentStep('2.50rem')->getIndentStep())->toBe('2.5rem')
        ->and(editor()->indentStep('40PX')->getIndentStep())->toBe('40px')
        ->and(editor()->indentStep(fn (): string => '1in')->getIndentStep())->toBe('1in');
});

it('falls back to the shipped step rather than to nothing', function (): void {
    // A field whose step is nothing has two buttons that do nothing, which is worse than a
    // field that quietly steps by the shipped amount.
    foreach (['50%', '0rem', '-2rem', 'inherit', '2', ''] as $nonsense) {
        expect(editor()->indentStep($nonsense)->getIndentStep())->toBe('2.5rem');
    }

    config()->set('filament-advanced-rich-editor.indent.step', '3rem');

    expect(editor()->getIndentStep())->toBe('3rem');
});

it('answers an out-of-range depth with the shipped one', function (): void {
    // A configured `0` is the feature being switched off in the wrong place; clamping it to
    // `1` would answer a different question.
    expect(editor()->indentMax(4)->getIndentMax())->toBe(4)
        ->and(editor()->indentMax(0)->getIndentMax())->toBe(8)
        ->and(editor()->indentMax(41)->getIndentMax())->toBe(8)
        ->and(editor()->indentMax('6')->getIndentMax())->toBe(6);

    config()->set('filament-advanced-rich-editor.indent.max', 3);

    expect(editor()->getIndentMax())->toBe(3);
});

it('keeps the indent across the php round trip', function () use ($render): void {
    expect($render('<p style="margin-inline-start: 5rem">Text</p>'))
        ->toContain('margin-inline-start: 5rem');
});

it('loses the indent without the plugin, which is why it exists', function (): void {
    expect(RichContentRenderer::make('<p style="margin-inline-start: 5rem">Text</p>')->toHtml())
        ->not->toContain('margin-inline-start');
});

it('converts margin-left into the logical property', function () use ($render): void {
    // What every other editor writes, and what a right-to-left paragraph needs on its
    // right instead.
    expect($render('<p style="margin-left: 5rem">Text</p>'))
        ->toContain('margin-inline-start: 5rem')
        ->not->toContain('margin-left');
});

it('reads a length written in another unit onto its own grid', function () use ($render): void {
    // 36pt is 48px, which is a step and a fifth at the shipped 2.5rem - near enough to one
    // that somebody meant one.
    expect($render('<p style="margin-left: 36pt">Text</p>'))
        ->toContain('margin-inline-start: 2.5rem');

    expect($render('<p style="margin-inline-start: 96px">Text</p>'))
        ->toContain('margin-inline-start: 5rem');
});

it('re-measures a document when the step changes', function () use ($render): void {
    // The document keeps the number of steps and not the length, so both buttons keep
    // landing on the grid - and a project that changes the step re-grids what it has.
    // Two and a half steps at the new one, and the half goes up - PHP rounds away from zero
    // and JavaScript rounds toward positive, which are the same rule for a length that is
    // never negative.
    expect($render('<p style="margin-inline-start: 5rem">Text</p>', '2rem'))
        ->toContain('margin-inline-start: 6rem');
});

it('reads nothing under half a step as no indent at all', function () use ($render): void {
    expect($render('<p style="margin-inline-start: 1rem">Text</p>'))->not->toContain('margin-inline-start')
        ->and($render('<p style="margin-inline-start: 0">Text</p>'))->not->toContain('margin-inline-start')
        ->and($render('<p style="margin-inline-start: auto">Text</p>'))->not->toContain('margin-inline-start')
        ->and($render('<p style="margin-inline-start: 50%">Text</p>'))->not->toContain('margin-inline-start');
});

it('holds a document inside the depth the field allows', function () use ($render): void {
    expect($render('<p style="margin-inline-start: 100rem">Text</p>'))
        ->toContain('margin-inline-start: 20rem');

    expect($render('<p style="margin-inline-start: 100rem">Text</p>', null, 2))
        ->toContain('margin-inline-start: 5rem');
});

it('refuses an indent that carries css of its own', function () use ($render): void {
    // The sanitiser allows `style` on every element but does not look inside CSS, so a
    // value with anything but a length in it would be a way to write further declarations.
    $rendered = $render('<p style="margin-inline-start: 5rem; position: fixed">x</p>');

    expect($rendered)->not->toContain('position')
        ->and($rendered)->toContain('margin-inline-start: 5rem');

    expect($render('<p style="margin-inline-start: 5rem)">x</p>'))
        ->not->toContain('margin-inline-start');
});

it('carries the indent on the blocks that can hold one', function () use ($render): void {
    $html = '<h2 style="margin-inline-start: 5rem">Title</h2>'.
        '<blockquote style="margin-inline-start: 5rem"><p style="margin-inline-start: 5rem">Quote</p></blockquote>';

    expect(substr_count($render($html), 'margin-inline-start: 5rem'))->toBe(3);
});

it('leaves a list item to its own nesting', function () use ($render): void {
    // A list indents by nesting, which is where its numbering and its bullets come from,
    // and a margin beside that would be a second indent the list knows nothing about.
    expect($render('<ul><li style="margin-inline-start: 5rem">Item</li></ul>'))
        ->not->toContain('margin-inline-start')
        ->and(Indent::TYPES)->not->toContain('listItem');
});

it('sits beside the alignment and the spacing in one style attribute', function (): void {
    $rendered = RichContentRenderer::make('<p style="text-align: center; margin-inline-start: 5rem">x</p>')
        ->plugins([IndentPlugin::make()])
        ->toHtml();

    expect($rendered)->toContain('text-align: center')
        ->and($rendered)->toContain('margin-inline-start: 5rem');
});

it('names the two keys in the help dialog, and only while they answer', function (): void {
    // The only way to reach the feature on a field that was given no buttons for it, which
    // is most of them - so the row is not a nicety.
    expect(array_column(Shortcuts::for(editor()->indent()), 'label'))
        ->toContain('Increase indent', 'Decrease indent')
        ->and(array_column(Shortcuts::for(editor()), 'label'))
        ->not->toContain('Increase indent');
});
