<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use BackedEnum;
use Closure;
use DateInterval;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AnchorPosition;

/**
 * What an entry and a column need in common: a renderer, built the way the caller asked.
 *
 * The methods below are the renderer's own, one for one, and they are kept as a list of
 * calls rather than as a field each. Two reasons. A field each would be twelve properties
 * whose only job is to be handed straight back, and it would silently lose the order they
 * were written in - `->styles()` after a plugin that declared its own means something.
 * `configureRenderer()` is the same mechanism with nothing in front of it, which is what a
 * project reaches for when it wants something this list does not have.
 *
 * Where the record declares the attribute - Filament's `HasRichContent` - the renderer
 * starts from what the model already says about it: its plugins, its merge tags, its
 * mentions, the disk its pictures are on. Repeating that on the entry would be a second
 * place for it to be wrong.
 */
trait RendersRichContent
{
    /**
     * @var array<int, Closure(AdvancedRichContentRenderer): mixed>
     */
    protected array $rendererConfiguration = [];

    /**
     * @param  Closure(AdvancedRichContentRenderer): mixed  $callback
     */
    public function configureRenderer(Closure $callback): static
    {
        $this->rendererConfiguration[] = $callback;

        return $this;
    }

    /**
     * @param  array<int, int>|null  $levels
     */
    public function anchorHeadings(
        ?array $levels = null,
        AnchorPosition|string|null $position = null,
        ?string $symbol = null,
        ?string $class = null,
    ): static {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->anchorHeadings($levels, $position, $symbol, $class),
        );
    }

    /**
     * @param  array{light: string|BackedEnum, dark: string|BackedEnum}|null  $themes
     */
    public function highlightCode(string|BackedEnum|null $theme = null, ?array $themes = null): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->highlightCode($theme, $themes),
        );
    }

    /**
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>|null  $styles
     */
    public function styles(?array $styles): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->styles($styles),
        );
    }

    public function linkAttributes(bool $condition = true): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->linkAttributes($condition),
        );
    }

    /**
     * @param  array<string, mixed>|null  $tags
     */
    public function mergeTags(?array $tags): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->mergeTags($tags),
        );
    }

    /**
     * @param  array<int, mixed>|null  $providers
     */
    public function mentions(?array $providers): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->mentions($providers),
        );
    }

    /**
     * @param  array<mixed>|null  $blocks
     */
    public function customBlocks(?array $blocks): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->customBlocks($blocks),
        );
    }

    /**
     * @param  array<int, RichContentPlugin>  $plugins
     */
    public function plugins(array $plugins): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->plugins($plugins),
        );
    }

    /**
     * @param  array<string, mixed>|null  $colors
     */
    public function textColors(?array $colors): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->textColors($colors),
        );
    }

    /**
     * @param  array<int, string>|null  $protocols
     */
    public function linkProtocols(?array $protocols): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->linkProtocols($protocols),
        );
    }

    public function fileAttachmentsDisk(?string $name): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->fileAttachmentsDisk($name),
        );
    }

    public function fileAttachmentsVisibility(?string $visibility): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->fileAttachmentsVisibility($visibility),
        );
    }

    /**
     * What resolves an uploaded picture back into a URL.
     *
     * The disk and the visibility above only answer for attachments that are a path. An
     * upload that went through a media library is an id, and only its provider knows what
     * that id points at - so without this, a view page could be told everything about where
     * the pictures live except the one thing that finds them.
     *
     * Usually it comes from the model, which is the better place: `setUpRichContent()` is
     * read by every path that renders the attribute, this one only by the entry it is
     * written on. This is for where that is not possible, or where one place shows the same
     * attribute differently.
     */
    public function fileAttachmentProvider(?FileAttachmentProvider $provider): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->fileAttachmentProvider($provider),
        );
    }

    /**
     * Pictures a media library field saved, found again by the id they were saved under.
     *
     * The field's own `spatieMediaLibrary()`, with the same arguments and the same defaults
     * from the `spatie` section of the config - name what the field names. A field that
     * stores privately saves a picture without its address, since the signed one it was
     * shown with runs out within the hour, so the page showing the document has the id and
     * nothing else. Without this, that page draws no pictures at all.
     *
     * The id is looked up as a Spatie UUID across the media table, the way any renderer
     * handed the provider directly looks it up: there is no field here to scope against, and
     * the document came out of the database. That is also what finds a picture the field took
     * from a shared library, which the record's own collection does not hold.
     */
    public function spatieMediaLibrary(?string $collection = null, ?string $conversion = null, ?string $disk = null, ?string $visibility = null): static
    {
        return $this->configureRenderer(function (AdvancedRichContentRenderer $renderer) use ($collection, $conversion, $disk, $visibility): void {
            $provider = SpatieMediaLibraryFileAttachmentProvider::make(
                $collection ?? config('filament-advanced-rich-editor.spatie.collection'),
                $conversion ?? config('filament-advanced-rich-editor.spatie.conversion'),
                $disk ?? config('filament-advanced-rich-editor.spatie.disk'),
                $visibility ?? config('filament-advanced-rich-editor.spatie.visibility'),
            );

            $renderer
                ->fileAttachmentProvider($provider)
                ->fileAttachmentsVisibility($provider->getDefaultFileAttachmentVisibility());
        });
    }

    /**
     * Keeps the rendered markup, which is what a table of twenty-five rows is for. The
     * conditions are the renderer's; see `AdvancedRichContentRenderer::cached()`.
     */
    public function cached(bool|int|DateInterval|null $ttl = null, ?string $store = null): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->cached($ttl, $store),
        );
    }

    public function cacheKey(string|Closure|null $key): static
    {
        return $this->configureRenderer(
            fn (AdvancedRichContentRenderer $renderer) => $renderer->cacheKey($key),
        );
    }

    /**
     * The document behind whatever the schema handed over.
     *
     * A record that declares its rich content answers `getState()` with a
     * `RichContentAttribute` rather than with the column, so anything reading the state as
     * a document has to come through here. Everything that did not was quietly reading
     * nothing - and reading nothing looks exactly like an empty document.
     *
     * @return string|array<string, mixed>|null
     */
    public function resolveRichContent(mixed $state): string|array|null
    {
        if ($state instanceof RichContentAttribute) {
            $state = $state->getModel()->getAttribute($state->getName());
        }

        return (is_string($state) || is_array($state)) ? $state : null;
    }

    public function getRichContentRenderer(mixed $state = null): AdvancedRichContentRenderer
    {
        $attribute = $this->findRichContentAttribute($state);

        $renderer = AdvancedRichContentRenderer::make($this->resolveRichContent($state));

        if ($attribute !== null) {
            $renderer->attribute($attribute);
        }

        // Last, and that is the point: whatever the entry was told wins over whatever the
        // model declared. The model describes the field; this describes one place it is
        // shown.
        foreach ($this->rendererConfiguration as $configure) {
            $configure($renderer);
        }

        return $renderer;
    }

    /**
     * What the model says about this attribute, where it says anything.
     */
    protected function findRichContentAttribute(mixed $state): ?RichContentAttribute
    {
        if ($state instanceof RichContentAttribute) {
            return $state;
        }

        $record = $this->getRecord();

        if (! ($record instanceof HasRichContent)) {
            return null;
        }

        $name = $this->getName();

        // A column may be addressed through a relationship - `author.bio` - and the
        // attribute registry of the record in hand knows nothing about the far end of one.
        return str_contains($name, '.') ? null : $record->getRichContentAttribute($name);
    }
}
