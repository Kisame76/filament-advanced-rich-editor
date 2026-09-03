<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor;

use BladeUI\Icons\Factory as BladeIconsFactory;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedHostSanitizer;
use Kisame76\FilamentAdvancedRichEditor\View\Components\Content;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class FilamentAdvancedRichEditorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-advanced-rich-editor';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            // `<x-arte-content :content="$post->body" />`. The prefix is the one the CSS
            // classes already use, so a project only ever learns the one abbreviation.
            ->hasViewComponents('arte', Content::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            [
                Css::make('filament-advanced-rich-editor', __DIR__.'/../resources/dist/filament-advanced-rich-editor.css'),

                // The media browser, as an Alpine component rather than a script: it is one
                // `x-data` object and it is loaded the way Filament loads its own fields,
                // by `x-load-src` on the element that uses it. Keeping it out of the Blade
                // file is what lets it be tested at all.
                AlpineComponent::make('media-picker', __DIR__.'/../resources/dist/js/media-picker.js'),

                // The TipTap extensions are only pulled in once an editor actually renders a
                // task list, so they stay out of the panel bundle for every other page.
                Js::make('advanced-rich-editor/task-list', __DIR__.'/../resources/dist/js/task-list.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/task-item', __DIR__.'/../resources/dist/js/task-item.js')
                    ->loadedOnRequest(),

                // Same deal for the font size mark: only fields that render the stepper
                // ask for it.
                // The brush that carries formatting. Loaded only where the button is,
                // since a field without it has no way to arm one.
                Js::make('advanced-rich-editor/format-brush', __DIR__.'/../resources/dist/js/format-brush.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/font-size', __DIR__.'/../resources/dist/js/font-size.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/font-family', __DIR__.'/../resources/dist/js/font-family.js')
                    ->loadedOnRequest(),

                // Loaded only by editors that allow images to be dragged.
                Js::make('advanced-rich-editor/image-resize', __DIR__.'/../resources/dist/js/image-resize.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/image-rotate', __DIR__.'/../resources/dist/js/image-rotate.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/image-caption', __DIR__.'/../resources/dist/js/image-caption.js')
                    ->loadedOnRequest(),

                // The side a picture sits on. Schema and one command, so a field that does
                // not offer it loads nothing.
                Js::make('advanced-rich-editor/image-float', __DIR__.'/../resources/dist/js/image-float.js')
                    ->loadedOnRequest(),

                // Whether a picture carries anything worth describing. Same shape as the
                // one above it, and loaded on the same terms.
                Js::make('advanced-rich-editor/image-decorative', __DIR__.'/../resources/dist/js/image-decorative.js')
                    ->loadedOnRequest(),

                // Where a picture points. Schema only - the anchor is built when the page
                // is rendered, so there is no command to ship with it.
                Js::make('advanced-rich-editor/image-link', __DIR__.'/../resources/dist/js/image-link.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/text-background', __DIR__.'/../resources/dist/js/text-background.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/text-direction', __DIR__.'/../resources/dist/js/text-direction.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/text-toolbar', __DIR__.'/../resources/dist/js/text-toolbar.js')
                    ->loadedOnRequest(),

                // Two keyboard shortcuts and nothing else. It is asked for by every field
                // rather than by a configured few, because the keys it repairs are bound by
                // Filament's own build on every field.
                Js::make('advanced-rich-editor/alignment', __DIR__.'/../resources/dist/js/alignment.js')
                    ->loadedOnRequest(),

                // The language a passage is written in, and what a list is told about
                // itself. Both are schema only - a mark and a set of global attributes -
                // so a field that offers neither loads neither.
                Js::make('advanced-rich-editor/language', __DIR__.'/../resources/dist/js/language.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/list-properties', __DIR__.'/../resources/dist/js/list-properties.js')
                    ->loadedOnRequest(),

                // The project's own named styles. Both halves only ever carry the key: the
                // classes belong to the front end's design system and are added in PHP.
                Js::make('advanced-rich-editor/block-style', __DIR__.'/../resources/dist/js/block-style.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/style-class', __DIR__.'/../resources/dist/js/style-class.js')
                    ->loadedOnRequest(),

                // The attributes a link and a heading carry beyond what Filament declares.
                Js::make('advanced-rich-editor/link-attributes', __DIR__.'/../resources/dist/js/link-attributes.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/anchor', __DIR__.'/../resources/dist/js/anchor.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/slash-menu', __DIR__.'/../resources/dist/js/slash-menu.js')
                    ->loadedOnRequest(),

                // The grip reads the slash menu's own settings to find the character that
                // opens it, so the two are related at runtime and registered apart: a field
                // may well have one and not the other.
                Js::make('advanced-rich-editor/drag-handle', __DIR__.'/../resources/dist/js/drag-handle.js')
                    ->loadedOnRequest(),

                // Replaces Filament's own mention extension rather than joining it - the two
                // carry the same name, and Filament keeps the last one it is handed.
                Js::make('advanced-rich-editor/mention', __DIR__.'/../resources/dist/js/mention.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/embed', __DIR__.'/../resources/dist/js/embed.js')
                    ->loadedOnRequest(),

                // The note, tip, warning and danger boxes. Loaded only by fields that offer
                // at least one of them.
                Js::make('advanced-rich-editor/callout', __DIR__.'/../resources/dist/js/callout.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/code-block', __DIR__.'/../resources/dist/js/code-block.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/line-height', __DIR__.'/../resources/dist/js/line-height.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/indent', __DIR__.'/../resources/dist/js/indent.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/media', __DIR__.'/../resources/dist/js/media.js')
                    ->loadedOnRequest(),

                Js::make('advanced-rich-editor/character-count', __DIR__.'/../resources/dist/js/character-count.js')
                    ->loadedOnRequest(),

                // Nothing it does reaches the application: the draft it keeps lives in the
                // browser's own storage and is offered back on the next opening.
                Js::make('advanced-rich-editor/autosave', __DIR__.'/../resources/dist/js/autosave.js')
                    ->loadedOnRequest(),

                // The bar imports the panel itself, warmed as soon as the extension loads.
                // Registered so it is published and served next to the extension - the
                // import resolves against the extension's own URL, which is what keeps the
                // two together wherever the assets ended up.
                Js::make('advanced-rich-editor/find-replace', __DIR__.'/../resources/dist/js/find-replace.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/floating-panel', __DIR__.'/../resources/dist/js/floating-panel.js')
                    ->loadedOnRequest(),

                // The report is the third window built on that panel, and it imports it the
                // same way - warmed as soon as the extension loads, so the first press of
                // the button opens rather than waits.
                Js::make('advanced-rich-editor/accessibility', __DIR__.'/../resources/dist/js/accessibility.js')
                    ->loadedOnRequest(),

                // Nothing is drawn and nothing is stored: the file decides what a paste is
                // made of, before ProseMirror parses it.
                Js::make('advanced-rich-editor/paste-cleanup', __DIR__.'/../resources/dist/js/paste-cleanup.js')
                    ->loadedOnRequest(),

                // The picker imports the list itself, the first time it opens. The file is
                // registered so it is published and served next to the extension - the
                // import resolves against the extension's own URL, which is what keeps the
                // two together wherever the assets ended up.
                Js::make('advanced-rich-editor/emoji', __DIR__.'/../resources/dist/js/emoji.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/emoji-data', __DIR__.'/../resources/dist/js/emoji-data.js')
                    ->loadedOnRequest(),

                // The special characters picker, the same way, and its list too. The popup
                // both pickers are drawn in is a third file, imported by whichever of them
                // loads - registered here so it is published and served beside them.
                Js::make('advanced-rich-editor/characters', __DIR__.'/../resources/dist/js/characters.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/character-data', __DIR__.'/../resources/dist/js/character-data.js')
                    ->loadedOnRequest(),
                Js::make('advanced-rich-editor/glyph-picker', __DIR__.'/../resources/dist/js/glyph-picker.js')
                    ->loadedOnRequest(),

                // Typing straight quotes and getting the ones the language uses. Same deal:
                // characters, so no PHP half.
                Js::make('advanced-rich-editor/typography', __DIR__.'/../resources/dist/js/typography.js')
                    ->loadedOnRequest(),

                // Changing the case of a selection. Nothing of it is stored, so it has no
                // PHP half either.
                Js::make('advanced-rich-editor/text-case', __DIR__.'/../resources/dist/js/text-case.js')
                    ->loadedOnRequest(),
            ],
            'kisame76/filament-advanced-rich-editor',
        );

        $this->registerIconSet();
        $this->allowEmbedIframes();
    }

    /**
     * Widens Filament's sanitiser so a video embed survives being rendered.
     *
     * Symfony's safe element list has no `<iframe>` in it, which is the right default and
     * also removes every embed this package can insert. The element is allowed back only
     * where a project switched embeds on, and never on its own terms: `EmbedHostSanitizer`
     * narrows `src` to the video hosts, so what is allowed back is an embed rather than an
     * iframe.
     *
     * This changes a configuration the whole application shares, which is why it is a
     * config key rather than something installing the package does.
     */
    protected function allowEmbedIframes(): void
    {
        if (! config('filament-advanced-rich-editor.embed.sanitizer', true)) {
            return;
        }

        $this->app->extend(
            HtmlSanitizerConfig::class,
            static fn (HtmlSanitizerConfig $config): HtmlSanitizerConfig => $config
                // `allowElement()` replaces the attribute list for this element, so the
                // application-wide `allowAttribute('style', '*')` no longer reaches it -
                // and without `style` the frame loses the shape it carries.
                ->allowElement('iframe', [
                    'src', 'title', 'loading', 'allow', 'allowfullscreen', 'referrerpolicy',
                    'style', 'width', 'height',
                ])
                ->withAttributeSanitizer(new EmbedHostSanitizer(
                    (array) config('filament-advanced-rich-editor.embed.allowed_hosts', []),
                )),
        );
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
