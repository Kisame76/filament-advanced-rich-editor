<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarColorPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarImagePanel;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarStylePicker;

/**
 * Every menu this package hangs off a trigger. Named rather than discovered, so that a
 * sixth one added later fails this list instead of quietly shipping without the behaviour.
 *
 * @return array<string, string>
 */
function droppingMenus(): array
{
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    return [
        'textColor' => ToolbarColorPicker::text([['value' => '#000', 'label' => 'Black', 'color' => '#000', 'darkColor' => '#000']], false)->toEmbeddedHtml(),
        'fontFamily' => ToolbarFontPicker::make()->fonts([['label' => 'Inter', 'stack' => 'Inter, sans-serif', 'verify' => false]])->toEmbeddedHtml(),
        'fontSize' => ToolbarFontSize::make()->toEmbeddedHtml(),
        'styles' => ToolbarStylePicker::make()->styles(Styles::all())->toEmbeddedHtml(),
        'imagePanel' => ToolbarImagePanel::alt()->toEmbeddedHtml(),
    ];
}

it('turns every dropdown upwards when there is no room below it', function (): void {
    // The bar over a selection hangs below the text, so its menus start lower than any
    // menu on the toolbar ever does - and the editor's content box scrolls its own
    // overflow, so the window is not the only edge that cuts one off.
    $missing = [];

    foreach (droppingMenus() as $name => $html) {
        foreach ([
            'x-ref="trigger"' => 'nothing to measure from',
            'x-ref="menu"' => 'nothing to measure',
            'positionMenu()' => 'never measures the room it has',
            'fi-arte-menu-up' => 'cannot be turned',
        ] as $needle => $complaint) {
            if (! str_contains($html, $needle)) {
                $missing[] = "{$name}: {$complaint}";
            }
        }
    }

    // Collected rather than asserted one at a time, so a run names every menu that is not
    // wired up instead of stopping at the first.
    expect($missing)->toBe([]);
});

it('measures against the nearest clipping ancestor, not only the window', function (): void {
    // Raising `z-index` would not help: a menu reaching past the bottom of a scrolling
    // ancestor is cut off by geometry, and paint order has no say in it.
    expect(droppingMenus()['textColor'])
        ->toContain('clippingRect')
        ->toContain('overflowY');
});
