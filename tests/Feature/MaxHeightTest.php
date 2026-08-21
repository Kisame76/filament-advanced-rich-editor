<?php

declare(strict_types=1);

it('lets the editor grow with its content until a height is set', function (): void {
    expect(editor()->getMaxHeight())->toBeNull();
});

it('takes a height from the field', function (): void {
    expect(editor()->maxHeight('400px')->getMaxHeight())->toBe('400px');
});

it('reads the default height from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.max_height', '20rem');

    expect(editor()->getMaxHeight())->toBe('20rem');
});

it('lets a field grow freely against a configured height', function (): void {
    config()->set('filament-advanced-rich-editor.max_height', '20rem');

    expect(editor()->maxHeight(null)->getMaxHeight())->toBeNull();
});

it('accepts a closure for the height', function (): void {
    expect(editor()->maxHeight(fn (): string => '30rem')->getMaxHeight())->toBe('30rem');
});

it('takes a plain number as pixels', function (): void {
    // `maxHeight(400)` is what anyone writes first, and `max-height: 400` is not a length
    // any browser accepts - the field would silently keep growing.
    expect(editor()->maxHeight(400)->getMaxHeight())->toBe('400px');
});

it('stops pinning the toolbar to the page once the field scrolls inside itself', function (): void {
    // A field with its own scrollbar keeps the toolbar above the text on its own: the bar
    // is not in the box that moves. Pinning it to the viewport on top of that would tear
    // it away from the field it belongs to as the page scrolls past.
    expect(editor()->isStickyToolbar())->toBeTrue()
        ->and(editor()->maxHeight('400px')->isStickyToolbar())->toBeFalse();
});
