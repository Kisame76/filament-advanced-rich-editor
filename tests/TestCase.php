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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kisame76\FilamentAdvancedRichEditor\FilamentAdvancedRichEditorServiceProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Fonts;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    // Migrations are registered rather than run per test, so a driver whose database
    // survives between tests - anything that is not SQLite in memory - would try to create
    // the same tables again. Refreshing once and wrapping each test in a transaction is
    // what makes the suite portable across drivers.
    use RefreshDatabase;

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
     * SQLite in memory, unless the environment names another driver.
     *
     * The default is what anyone running `composer test` gets, and it is right for that: no
     * server to install, and a fresh database per test. But a driver is not an implementation
     * detail a query layer can ignore - `LIKE` escaping, collation and case sensitivity all
     * differ - and a suite that has only ever seen SQLite cannot tell you what production
     * does. Point it at a real server to find out:
     *
     *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
     *   DB_DATABASE=arte DB_USERNAME=root DB_PASSWORD=secret vendor/bin/pest
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Covers are off unless a test asks for them. Generating one means an ffmpeg
        // process or a file read per listed row, and almost every test in this suite lists
        // rows without caring what their tiles show - so leaving the shipped default on
        // would make the suite slow, and its speed dependent on whether the machine running
        // it happens to have ffmpeg. `MediaCoversTest` switches them on, and pins that the
        // shipped default is the other way round.
        $app['config']->set('filament-advanced-rich-editor.media_library.covers.enabled', false);

        $driver = env('DB_CONNECTION', 'sqlite');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $driver === 'sqlite'
            ? [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
            ]
            : [
                'driver' => $driver,
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
                'prefix' => '',
            ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
