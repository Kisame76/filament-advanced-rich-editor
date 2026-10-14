<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\StickyToolbarPlugin;

it('is sticky with the configured offset by default', function (): void {
    expect(editor()->isStickyToolbar())->toBeTrue()
        ->and(editor()->getStickyToolbarOffset())->toBe('4rem');
});

it('reads the sticky defaults from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.sticky.enabled', false);
    config()->set('filament-advanced-rich-editor.sticky.offset', '0px');

    expect(editor()->isStickyToolbar())->toBeFalse()
        ->and(editor()->getStickyToolbarOffset())->toBe('0px');
});

it('falls back to a sticky 4rem toolbar when the config was never merged', function (): void {
    config()->set('filament-advanced-rich-editor.sticky', null);

    expect(editor()->isStickyToolbar())->toBeTrue()
        ->and(editor()->getStickyToolbarOffset())->toBe('4rem');
});

it('lets a field turn the sticky toolbar on against the config', function (): void {
    config()->set('filament-advanced-rich-editor.sticky.enabled', false);

    expect(editor()->stickyToolbar()->isStickyToolbar())->toBeTrue();
});

it('lets a field turn the sticky toolbar off against the config', function (): void {
    expect(editor()->stickyToolbar(false)->isStickyToolbar())->toBeFalse();
});

it('accepts a closure for the sticky condition', function (): void {
    expect(editor()->stickyToolbar(fn (): bool => false)->isStickyToolbar())->toBeFalse()
        ->and(editor()->stickyToolbar(fn (): bool => true)->isStickyToolbar())->toBeTrue();
});

it('lets a field override the sticky offset', function (): void {
    expect(editor()->stickyToolbarOffset('2.5rem')->getStickyToolbarOffset())->toBe('2.5rem')
        ->and(editor()->stickyToolbarOffset(fn (): string => '12px')->getStickyToolbarOffset())->toBe('12px');
});

it('returns to the configured offset when the override is cleared', function (): void {
    config()->set('filament-advanced-rich-editor.sticky.offset', '3rem');

    expect(editor()->stickyToolbarOffset('1rem')->stickyToolbarOffset(null)->getStickyToolbarOffset())
        ->toBe('3rem');
});

it('watches whether a pinned bar is stuck, which is when it squares its corners', function (): void {
    // CSS cannot ask whether a sticky box is stuck in every supported browser, so the
    // script asks an IntersectionObserver and sets a class - see `sticky-toolbar.js`.
    expect(pluginNames(editor()))->toContain(StickyToolbarPlugin::class)
        ->and(StickyToolbarPlugin::make()->getTipTapJsExtensions())->toBe([
            FilamentAsset::getScriptSrc('advanced-rich-editor/sticky-toolbar', 'kisame76/filament-advanced-rich-editor'),
        ]);
});

it('loads nothing for a bar that is not pinned', function (): void {
    expect(pluginNames(editor()->stickyToolbar(false)))->not->toContain(StickyToolbarPlugin::class)
        // A capped field is never sticky, so it has nothing to watch either.
        ->and(pluginNames(editor()->maxHeight('20rem')))->not->toContain(StickyToolbarPlugin::class);
});
