<?php

declare(strict_types=1);

use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;

it('draws every icon through one registry', function (): void {
    // Config values are strings, so the file stays cacheable, but a bare Heroicon name is
    // handed back as the enum: Filament then picks the variant that matches the size, and
    // the buttons keep looking like the ones it draws beside them.
    // The defaults are outline throughout, which is the style the package's own buttons
    // and the bundled rotations share. A bare name would give Filament's filled variant.
    expect(Icons::get('image_rotate_left'))->toBe('arte-rotate-ccw')
        ->and(Icons::get('image_delete'))->toBe('heroicon-o-trash')
        ->and(Icons::get('image_delete'))->not->toBeInstanceOf(Heroicon::class);

    config()->set('filament-advanced-rich-editor.icons.image_delete', 'trash');

    // A bare Heroicon name still resolves to the enum, so Filament sizes it itself.
    expect(Icons::get('image_delete'))->toBe(Heroicon::Trash);

    config()->set('filament-advanced-rich-editor.icons.image_rotate_left', 'lucide-rotate-ccw');

    expect(Icons::get('image_rotate_left'))
        ->toBe('lucide-rotate-ccw')
        ->and(editor()->getTools()['imageRotateLeft']->getIcon())->toBe('lucide-rotate-ccw');
});

it('refuses an icon it does not know', function (): void {
    Icons::get('nope');
})->throws(InvalidArgumentException::class, 'no icon named [nope]');

it('lists every icon it draws in the config file', function (): void {
    // The registry and the shipped config file are two copies of one list. An icon added
    // to `Icons::defaults()` and forgotten here would be swappable in name only: nothing
    // in the published config would say it exists.
    $config = require __DIR__.'/../../config/filament-advanced-rich-editor.php';

    expect($config['icons'])->toBe(Icons::defaults());
});
