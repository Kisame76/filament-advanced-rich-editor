<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Kisame76\FilamentAdvancedRichEditor\FilamentAdvancedRichEditorServiceProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Fonts;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Filament is not auto-discovered inside the package test suite, so every provider a
     * form field touches is registered by hand. `filament/filament` itself is only needed
     * for the icon aliases and the `filament.*` config the editor reads.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentAdvancedRichEditorServiceProvider::class,
            // Registered only when it is installed. It is a dev dependency, so it is there in
            // this repository - but the package itself works without it, and a suite that
            // cannot start without it would stop being able to prove that.
            ...(class_exists(MediaLibraryServiceProvider::class) ? [MediaLibraryServiceProvider::class] : []),
        ];
    }

    /**
     * The font scan is memoised for the life of the process, which is right in a request and
     * wrong in a suite that rewrites the same directory between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Fonts::forget();
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
