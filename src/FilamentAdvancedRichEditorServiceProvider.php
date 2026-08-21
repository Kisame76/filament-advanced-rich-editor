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
                Js::make('advanced-rich-editor/font-family', __DIR__.'/../resources/dist/js/font-family.js')
                    ->loadedOnRequest(),

                // Loaded only by editors that allow images to be dragged.
                Js::make('advanced-rich-editor/image-resize', __DIR__.'/../resources/dist/js/image-resize.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/image-rotate', __DIR__.'/../resources/dist/js/image-rotate.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/text-background', __DIR__.'/../resources/dist/js/text-background.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/text-direction', __DIR__.'/../resources/dist/js/text-direction.js')
                    ->loadedOnRequest(),

                // The attributes a link and a heading carry beyond what Filament declares.
                Js::make('advanced-rich-editor/link-attributes', __DIR__.'/../resources/dist/js/link-attributes.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/anchor', __DIR__.'/../resources/dist/js/anchor.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/slash-menu', __DIR__.'/../resources/dist/js/slash-menu.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/line-height', __DIR__.'/../resources/dist/js/line-height.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/character-count', __DIR__.'/../resources/dist/js/character-count.js')
                    ->loadedOnRequest(),

                // The picker imports the list itself, the first time it opens. The file is
                // registered so it is published and served next to the extension - the
                // import resolves against the extension's own URL, which is what keeps the
                // two together wherever the assets ended up.
                Js::make('advanced-rich-editor/emoji', __DIR__.'/../resources/dist/js/emoji.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/emoji-data', __DIR__.'/../resources/dist/js/emoji-data.js')
                    ->loadedOnRequest(),
            ],
            'kisame76/filament-advanced-rich-editor',
        );

        $this->registerIconSet();
    }

    /**
     * Register the package's own Blade Icons set so toolbar buttons can reference icons
     * that Filament does not ship (the bundled Lucide drawings and `arte-task-list`). Blade
     * Icons is a hard dependency of filament/support, but the set is registered defensively:
     * a consumer may replace the icon factory, and a missing icon set must never break the
     * editor.
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
