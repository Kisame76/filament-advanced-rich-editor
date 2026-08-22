<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Tiptap\Core\Extension;

/**
 * Routes the rich editor's image uploads into a `spatie/laravel-medialibrary` collection.
 *
 * The plugin adds nothing to the editor itself — no Tiptap extension, no tool, no action. It
 * exists so that the media library provider travels with the content: registering the plugin on
 * a rich content attribute lets `RichContentAttribute` and `RichContentRenderer` discover the
 * provider through `HasFileAttachmentProvider`, so image URLs also resolve when the content is
 * rendered outside of a form.
 */
class SpatieMediaLibraryPlugin implements HasFileAttachmentProvider, RichContentPlugin
{
    protected SpatieMediaLibraryFileAttachmentProvider $provider;

    final public function __construct(
        ?string $collection = null,
        ?string $conversion = null,
        ?string $disk = null,
        ?string $visibility = null,
    ) {
        $this->provider = SpatieMediaLibraryFileAttachmentProvider::make($collection, $conversion, $disk, $visibility);
    }

    public static function make(?string $collection = null, ?string $conversion = null, ?string $disk = null, ?string $visibility = null): static
    {
        return app(static::class, [
            'collection' => $collection,
            'conversion' => $conversion,
            'disk' => $disk,
            'visibility' => $visibility,
        ]);
    }

    /**
     * Forwarded to the provider. The field injects `fn () => $component->getRecord()` here, so
     * that uploads follow the form's record instead of the model the rich content attribute was
     * built from. On a create form that closure resolves to `null` until the record has been
     * inserted, which is why the provider requires an existing record and lets Filament defer the
     * save to `saveRelationshipsUsing()`.
     */
    public function recordUsing(?Closure $callback): static
    {
        $this->provider->recordUsing($callback);

        return $this;
    }

    /**
     * Forwarded to the provider. The field injects `fn () => $component->getName()` here, so
     * that an attachment is remembered as belonging to the editor that uploaded it - a media
     * collection hangs off the record, and two editors on one model would otherwise clean up
     * each other's images.
     */
    public function ownerUsing(?Closure $callback): static
    {
        $this->provider->ownerUsing($callback);

        return $this;
    }

    /**
     * Forwarded to the provider. The field injects the model new uploads should belong to, so a
     * shared library can own its own pictures instead of them hanging off whichever record
     * happened to be open when they were fetched.
     */
    public function uploadsToUsing(?Closure $callback): static
    {
        $this->provider->uploadsToUsing($callback);

        return $this;
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        return $this->provider;
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
