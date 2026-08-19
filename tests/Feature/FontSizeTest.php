<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FontSizePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontSize;

it('puts the stepper next to the headings dropdown', function (): void {
    expect(toolbarShape(editor())[2][1])->toBe('fontSize');
});

it('registers the font size plugin by default', function (): void {
    $plugins = array_filter(editor()->getPlugins(), fn (object $plugin): bool => $plugin instanceof FontSizePlugin);

    expect(editor()->hasFontSize())->toBeTrue()
        ->and($plugins)->toHaveCount(1);
});

it('drops the stepper and the plugin when the font size is turned off', function (): void {
    $editor = editor()->fontSize(false);

    expect($editor->hasFontSize())->toBeFalse()
        ->and(toolbarShape($editor)[2])->toBe([
            'dropdown:h1,h2,h3,h4',
            'blockquote',
            'codeBlock',
        ])
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof FontSizePlugin))->toBe([]);
});

it('reads its bounds from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.font_size', [
        'enabled' => true,
        'min' => 10,
        'max' => 40,
        'step' => 2,
        'default' => 18,
    ]);

    expect(editor()->getFontSizeOptions())->toBe(['min' => 10, 'max' => 40, 'step' => 2, 'default' => 18]);
});

it('lets a field override the bounds', function (): void {
    expect(editor()->fontSizeOptions(min: 12, max: 24, step: 4, default: 14)->getFontSizeOptions())
        ->toBe(['min' => 12, 'max' => 24, 'step' => 4, 'default' => 14]);
});

it('keeps the bounds sane', function (): void {
    // A default outside the range would leave the stepper showing a size it refuses to set.
    expect(editor()->fontSizeOptions(min: 20, max: 10, step: 0, default: 4)->getFontSizeOptions())
        ->toBe(['min' => 20, 'max' => 20, 'step' => 1, 'default' => 20]);
});

it('renders a stepper carrying its bounds', function (): void {
    $html = ToolbarFontSize::make()->min(10)->max(40)->step(2)->defaultSize(18)->toEmbeddedHtml();

    // The bounds travel as a JSON payload inside the Alpine component, so they are read
    // back out rather than matched as a substring of an escaped string.
    preg_match("/JSON\.parse\('(.*?)'\)/", $html, $matches);

    $options = json_decode(str_replace('\\u0022', '"', $matches[1] ?? ''), associative: true);

    expect($html)->toContain('fi-arte-font-size')
        // The tick is what keeps the displayed size in sync with the caret.
        ->toContain('editorUpdatedAt && sync()')
        ->toContain('setFontSize')
        ->and($options)->toBe(['min' => 10, 'max' => 40, 'step' => 2, 'fallback' => 18, 'unit' => 'px']);
});

it('round trips a font size through the php renderer', function (): void {
    $html = '<p><span style="font-size: 24px">Big</span> small</p>';

    $rendered = RichContentRenderer::make($html)->plugins([FontSizePlugin::make()])->toHtml();

    expect($rendered)->toContain('font-size: 24px')
        ->and($rendered)->toContain('Big');
});

it('loses the size without the plugin, which is why it exists', function (): void {
    $html = '<p><span style="font-size: 24px">Big</span> small</p>';

    expect(RichContentRenderer::make($html)->toHtml())->not->toContain('font-size: 24px');
});
