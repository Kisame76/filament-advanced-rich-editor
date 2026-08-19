<?php

declare(strict_types=1);

use Filament\Support\Enums\Alignment;

it('centres the toolbar by default', function (): void {
    expect(editor()->getToolbarAlignment())->toBe('center');
});

it('reads the default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar_alignment', 'end');

    expect(editor()->getToolbarAlignment())->toBe('end');
});

it('lets the field override the configured default', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar_alignment', 'end');

    expect(editor()->toolbarAlignment('start')->getToolbarAlignment())->toBe('start');
});

it('accepts the alignment enum', function (): void {
    expect(editor()->toolbarAlignment(Alignment::End)->getToolbarAlignment())->toBe('end');
});

it('maps the physical aliases onto the logical ones', function (): void {
    expect(editor()->toolbarAlignment(Alignment::Left)->getToolbarAlignment())->toBe('start')
        ->and(editor()->toolbarAlignment(Alignment::Right)->getToolbarAlignment())->toBe('end')
        ->and(editor()->toolbarAlignment(Alignment::Justify)->getToolbarAlignment())->toBe('between');
});

it('accepts a closure', function (): void {
    expect(editor()->toolbarAlignment(fn (): string => 'between')->getToolbarAlignment())->toBe('between');
});

it('rejects an alignment the toolbar cannot use', function (): void {
    editor()->toolbarAlignment('top')->getToolbarAlignment();
})->throws(LogicException::class, 'cannot be aligned to [top]');
