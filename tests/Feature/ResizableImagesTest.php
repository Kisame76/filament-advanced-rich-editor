<?php

declare(strict_types=1);

it('turns resizable images on by default', function (): void {
    expect(editor()->hasResizableImages())->toBeTrue();
});

it('reads the default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.images.resizable', false);

    expect(editor()->hasResizableImages())->toBeFalse();
});

it('lets the field override the configured default', function (): void {
    config()->set('filament-advanced-rich-editor.images.resizable', false);

    expect(editor()->resizableImages()->hasResizableImages())->toBeTrue();
});

it('lets the field turn resizing off', function (): void {
    expect(editor()->resizableImages(false)->hasResizableImages())->toBeFalse();
});

it('accepts a closure', function (): void {
    expect(editor()->resizableImages(fn (): bool => false)->hasResizableImages())->toBeFalse();
});
