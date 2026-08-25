<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TextBackgroundPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarColorPicker;

it('puts both pickers next to the other character controls', function (): void {
    expect(toolbarGroup(editor(), 'textColor'))->toBe([
        'bold', 'italic', 'underline', 'link', 'textColor', 'textBackground',
    ]);
});

it('builds the text palette from filaments own colours', function (): void {
    $picker = toolbarItem(editor(), 'textColor');

    expect($picker)->toBeInstanceOf(ToolbarColorPicker::class)
        ->and($picker->getMode())->toBe(ToolbarColorPicker::MODE_TEXT)
        // A palette entry keeps a separate dark value, which is what a hand-picked
        // colour cannot have.
        ->and($picker->getColors()[0])->toHaveKeys(['value', 'label', 'color', 'darkColor'])
        ->and(collect($picker->getColors())->pluck('value'))->toContain('red');
});

it('builds the background palette from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.colors.background_palette', ['#123456' => 'Deep']);

    $picker = toolbarItem(editor(), 'textBackground');

    expect($picker->getMode())->toBe(ToolbarColorPicker::MODE_BACKGROUND)
        ->and($picker->getColors())->toBe([
            ['value' => '#123456', 'label' => 'Deep', 'color' => '#123456', 'darkColor' => '#123456'],
        ]);
});

it('accepts a plain list of colours as a palette', function (): void {
    $colors = editor()->backgroundColors(['#aabbcc'])->getBackgroundColors();

    expect($colors)->toBe([
        ['value' => '#aabbcc', 'label' => '#aabbcc', 'color' => '#aabbcc', 'darkColor' => '#aabbcc'],
    ]);
});

it('drops a picker the field turned off', function (): void {
    expect(toolbarGroup(editor()->textColor(false), 'textBackground'))->toBe([
        'bold', 'italic', 'underline', 'link', 'textBackground',
    ])
        ->and(toolbarGroup(editor()->textBackground(false), 'textColor'))->toBe([
            'bold', 'italic', 'underline', 'link', 'textColor',
        ]);
});

it('registers the background mark only while the picker is there', function (): void {
    $registered = fn (object $editor): array => array_filter(
        $editor->getPlugins(),
        fn (object $plugin): bool => $plugin instanceof TextBackgroundPlugin,
    );

    expect($registered(editor()))->toHaveCount(1)
        ->and($registered(editor()->textBackground(false)))->toBe([]);
});

it('writes each colour through its own command', function (): void {
    $text = ToolbarColorPicker::text([['value' => 'red', 'label' => 'Red', 'color' => '#f00']])->toEmbeddedHtml();
    $background = ToolbarColorPicker::background([['value' => '#fef08a', 'label' => 'Yellow']])->toEmbeddedHtml();

    expect($text)->toContain('setTextColor({ color: color })')
        ->toContain('unsetTextColor()')
        // Filament keeps the text colour in a data attribute, not in a plain one.
        ->toContain('data-color')
        ->and($background)->toContain('setTextBackground(color)')
        ->toContain('unsetTextBackground()');
});

it('offers a way to clear the colour and to pick a free one', function (): void {
    $html = ToolbarColorPicker::text([['value' => 'red', 'label' => 'Red', 'color' => '#f00']])->toEmbeddedHtml();

    expect($html)->toContain('fi-arte-color-clear')
        ->toContain('type="color"')
        ->and(ToolbarColorPicker::text([], withCustomColor: false)->toEmbeddedHtml())
        ->not->toContain('type="color"');
});

it('draws the colour tools with the bundled lucide icons', function (): void {
    // A letter, a highlighter and a palette say what the tools paint; Heroicons' swatch and
    // eye dropper name the instrument instead. Each one has to exist in the shipped set, or
    // Blade Icons throws the moment a toolbar renders.
    $icons = ['text_color' => 'arte-letter-a', 'text_background' => 'arte-highlighter', 'color_custom' => 'arte-palette'];

    foreach ($icons as $key => $name) {
        expect(Icons::get($key))->toBe($name)
            ->and(__DIR__.'/../../resources/svg/'.substr($name, 5).'.svg')->toBeFile();
    }

    expect(toolbarItem(editor(), 'textColor')->getIcon())->toBe('arte-letter-a')
        ->and(toolbarItem(editor(), 'textBackground')->getIcon())->toBe('arte-highlighter');

    // Rendered, not just named: the trigger carries the letter itself and the menu the
    // palette, which is what proves the set is registered under the `arte` prefix.
    expect(ToolbarColorPicker::text([])->toEmbeddedHtml())
        ->toContain('m6 18 6-12 6 12')
        ->toContain('cx="13.5"')
        // No baseline stroke under the letter: the colour bar already sits there.
        ->not->toContain('M4 20h16');
});

it('wears the same chevron as filaments own dropdowns', function (): void {
    $html = ToolbarColorPicker::text([])->toEmbeddedHtml();

    // Filament styles that class small, thin and grey; an icon of our own would sit next
    // to the heading dropdown at a different size and weight.
    expect($html)->toContain('class="fi-fo-rich-editor-dropdown-tool-chevron"')
        ->not->toContain('chevron-down');
});

it('keeps a background colour across the php round trip', function (): void {
    $html = '<p><mark data-color="#fef08a" style="background-color: #fef08a">marked</mark></p>';

    $rendered = RichContentRenderer::make($html)->plugins([TextBackgroundPlugin::make()])->toHtml();

    expect($rendered)->toContain('data-color="#fef08a"')
        ->toContain('background-color: #fef08a')
        // Exactly one mark: a second one of the same name would nest on every save.
        ->and(substr_count($rendered, '<mark'))->toBe(1);
});

it('loses the colour without the plugin, which is why it exists', function (): void {
    $html = '<p><mark data-color="#fef08a" style="background-color: #fef08a">marked</mark></p>';

    expect(RichContentRenderer::make($html)->toHtml())->not->toContain('#fef08a');
});

it('refuses a colour that would break out of the style attribute', function (): void {
    $html = '<p><mark data-color="red;position:fixed;inset:0">x</mark></p>';

    $rendered = RichContentRenderer::make($html)->plugins([TextBackgroundPlugin::make()])->toHtml();

    expect($rendered)->not->toContain('position:fixed');
});

it('ships a text palette that is legible as a grid', function (): void {
    $colors = editor()->getTextColorsForPicker();

    expect($colors)->toHaveCount(12)
        ->and(collect($colors)->pluck('value')->all())
        ->toBe(['ink', 'grey', 'slate', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'indigo', 'purple', 'pink'])
        // Every entry carries a distinct dark value, which is the point of a named palette.
        ->and(collect($colors)->filter(fn (array $color): bool => $color['color'] === $color['darkColor']))->toBeEmpty();
});

it('lets a field keep filaments palette', function (): void {
    $colors = editor()->textColors(['brand' => 'Brand'])->getTextColorsForPicker();

    expect($colors)->toHaveCount(1)
        ->and($colors[0]['value'])->toBe('brand');
});

it('falls back to filaments palette when the config was emptied', function (): void {
    config()->set('filament-advanced-rich-editor.colors.text_palette', null);

    expect(collect(editor()->getTextColorsForPicker())->pluck('value'))->toContain('zinc');
});
