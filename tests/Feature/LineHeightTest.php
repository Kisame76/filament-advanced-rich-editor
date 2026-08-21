<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LineHeightPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

it('sits next to the alignment, in the group that lays the block out', function (): void {
    expect(toolbarGroup(editor(), 'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2'))->toBe([
        'dropdown:alignStart,alignCenter,alignEnd,alignJustify',
        'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2',
    ]);
});

it('keeps its own icon on the trigger', function (): void {
    $dropdown = toolbarItem(editor(), 'dropdown:lineHeight1,lineHeight1_15,lineHeight1_5,lineHeight2');

    // The options are numbers, so there is nothing to swap the icon for - unlike the
    // alignment dropdown, whose trigger shows the alignment the caret is in.
    expect($dropdown)->toBeInstanceOf(ToolbarDropdown::class)
        ->and($dropdown->getName())->toBe('Line spacing')
        ->and($dropdown->getIcon())->toBe('arte-line-spacing')
        ->and($dropdown->hasStaticIcon())->toBeTrue()
        ->and($dropdown->hasTextualButtons())->toBeTrue();
});

it('names the two spacings people do not measure', function (): void {
    $tools = editor()->getTools();

    expect($tools['lineHeight1']->getLabel())->toBe('Single (1.0)')
        ->and($tools['lineHeight2']->getLabel())->toBe('Double (2.0)')
        ->and($tools['lineHeight1_15']->getLabel())->toBe('1.15')
        ->and($tools['lineHeight1_5']->getLabel())->toBe('1.5');
});

it('toggles the spacing rather than only setting it', function (): void {
    $tools = editor()->getTools();

    // Picking the spacing a block already has takes it back off, which is the only way
    // back to whatever the theme sets.
    expect($tools['lineHeight1_5']->getJsHandler())->toContain("toggleLineHeight('1.5')")
        ->and($tools['lineHeight1_5']->getActiveJsExpression())->toContain("lineHeight: '1.5'");
});

it('reads the spacings from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.line_height.values', [1, 3]);

    expect(editor()->getLineHeights())->toBe(['1', '3'])
        ->and(toolbarDropdownName(editor(), 'lineHeight3'))->toBe('dropdown:lineHeight1,lineHeight3');
});

it('lets a field choose its own spacings', function (): void {
    expect(toolbarDropdownName(editor()->lineHeights([1.5, 2]), 'lineHeight2'))
        ->toBe('dropdown:lineHeight1_5,lineHeight2');
});

it('accepts a closure for the spacings', function (): void {
    expect(editor()->lineHeights(fn (): array => [1.25])->getLineHeights())->toBe(['1.25']);
});

it('spells one spacing one way', function (): void {
    // `1.50` and `1.5` are the same number, and the toolbar compares the stored value
    // against the one its button carries - so only one spelling may reach the schema.
    expect(editor()->lineHeights([1.5, '1.50', '1.500'])->getLineHeights())->toBe(['1.5'])
        ->and(LineHeight::toolName('1.50'))->toBe('lineHeight1_5')
        ->and(LineHeight::normalise(2.0))->toBe('2');
});

it('drops a value that is not a line height', function (): void {
    expect(editor()->lineHeights([1.5, '150%', '24px', 'inherit', 0.1, 99, ''])->getLineHeights())
        ->toBe(['1.5'])
        ->and(LineHeight::toolName('150%'))->toBeNull();
});

it('drops the dropdown and the plugin when the spacing is turned off', function (): void {
    $editor = editor()->lineHeight(false);

    expect($editor->hasLineHeight())->toBeFalse()
        ->and($editor->getTools())->not->toHaveKey('lineHeight1_5')
        ->and(toolbarGroup($editor, 'dropdown:alignStart,alignCenter,alignEnd,alignJustify'))
        ->toBe(['dropdown:alignStart,alignCenter,alignEnd,alignJustify'])
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof LineHeightPlugin))
        ->toBe([]);

    config()->set('filament-advanced-rich-editor.line_height.enabled', false);

    expect(editor()->hasLineHeight())->toBeFalse();
});

it('drops the trigger when there is nothing left to pick', function (): void {
    expect(toolbarShape(editor()->lineHeights([])))
        ->toContain(['dropdown:alignStart,alignCenter,alignEnd,alignJustify']);
});

it('keeps the spacing across the php round trip', function (): void {
    $html = '<p style="line-height: 1.5">Text</p>';

    $rendered = RichContentRenderer::make($html)->plugins([LineHeightPlugin::make([1.5])])->toHtml();

    expect($rendered)->toContain('line-height: 1.5');
});

it('loses the spacing without the plugin, which is why it exists', function (): void {
    // Content is re-parsed on every hydration and the parser only keeps attributes that
    // something declared, so this is also what a field with the dropdown switched off does.
    expect(RichContentRenderer::make('<p style="line-height: 1.5">Text</p>')->toHtml())
        ->not->toContain('line-height');
});

it('refuses a spacing that carries css of its own', function (): void {
    // The sanitiser allows `style` on every element but does not look inside CSS, so a
    // value with anything but digits in it would be a way to write further declarations.
    $rendered = RichContentRenderer::make('<p style="line-height: 1; position: fixed">x</p>')
        ->plugins([LineHeightPlugin::make([1])])
        ->toHtml();

    expect($rendered)->not->toContain('position')
        // The first declaration is a number and survives; only what followed it is gone.
        ->and($rendered)->toContain('line-height: 1');

    $unitful = RichContentRenderer::make('<p style="line-height: 150%">x</p>')
        ->plugins([LineHeightPlugin::make([1.5])])
        ->toHtml();

    expect($unitful)->not->toContain('line-height');
});

it('carries the spacing on the blocks that can hold one', function (): void {
    $html = '<h2 style="line-height: 2">Title</h2><blockquote style="line-height: 2"><p style="line-height: 2">Quote</p></blockquote>';

    $rendered = RichContentRenderer::make($html)->plugins([LineHeightPlugin::make([2])])->toHtml();

    expect(substr_count($rendered, 'line-height: 2'))->toBe(3);
});

it('sits beside the alignment in one style attribute', function (): void {
    // Both are global attributes writing `style`, and the renderer merges them - without
    // that one of the two would overwrite the other on its way into the document.
    $rendered = RichContentRenderer::make('<p style="text-align: center; line-height: 1.5">x</p>')
        ->plugins([LineHeightPlugin::make([1.5])])
        ->toHtml();

    expect($rendered)->toContain('text-align: center')
        ->and($rendered)->toContain('line-height: 1.5');
});
