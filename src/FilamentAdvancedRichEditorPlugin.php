<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * Optional panel plugin. Registering it is NOT required — the editor is wired per field
 * via {@see AdvancedRichEditor} and
 * the package assets auto-register in the service provider. It exists so consumers who
 * prefer the panel-plugin convention can write
 * `->plugin(FilamentAdvancedRichEditorPlugin::make())`.
 */
class FilamentAdvancedRichEditorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-advanced-rich-editor';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
