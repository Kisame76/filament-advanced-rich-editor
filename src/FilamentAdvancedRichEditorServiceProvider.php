<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor;

use BladeUI\Icons\Factory as BladeIconsFactory;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentAdvancedRichEditorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-advanced-rich-editor';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            [
                Css::make('filament-advanced-rich-editor', __DIR__.'/../resources/dist/filament-advanced-rich-editor.css'),

                // The TipTap extensions are only pulled in once an editor actually renders a
                // task list, so they stay out of the panel bundle for every other page.
                Js::make('advanced-rich-editor/task-list', __DIR__.'/../resources/dist/js/task-list.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/task-item', __DIR__.'/../resources/dist/js/task-item.js')
                    ->loadedOnRequest(),

                // Same deal for the font size mark: only fields that render the stepper
                // ask for it.
                Js::make('advanced-rich-editor/font-size', __DIR__.'/../resources/dist/js/font-size.js')
                    ->loadedOnRequest(),

                // Loaded only by editors that allow images to be dragged.
                Js::make('advanced-rich-editor/image-resize', __DIR__.'/../resources/dist/js/image-resize.js')
                    ->loadedOnRequest(),
            ],
            'kisame76/filament-advanced-rich-editor',
        );

        $this->registerIconSet();
    }

    /**
     * Register the package's own Blade Icons set so toolbar buttons can reference icons
     * that Filament does not ship (currently `arte-task-list`). Blade Icons is a hard
     * dependency of filament/support, but the set is registered defensively: a consumer
     * may replace the icon factory, and a missing icon set must never break the editor.
     */
    protected function registerIconSet(): void
    {
        if (! class_exists(BladeIconsFactory::class)) {
            return;
        }

        $this->callAfterResolving(BladeIconsFactory::class, function (BladeIconsFactory $factory): void {
            $factory->add('filament-advanced-rich-editor', [
                'path' => __DIR__.'/../resources/svg',
                'prefix' => 'arte',
            ]);
        });
    }
}
