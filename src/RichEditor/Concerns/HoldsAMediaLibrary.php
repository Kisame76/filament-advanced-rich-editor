<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The pool a picture is chosen from, and the rules for what may be chosen.
 *
 * A field either browses a Spatie media collection, a disk folder, or nothing at all, and
 * which one it is decides everything downstream: what the browser lists, what a stored id
 * may point at, and whether the tamper guard has anything to answer with.
 */
trait HoldsAMediaLibrary
{
    protected bool|Closure|null $hasMediaLibrary = null;

    protected ?Closure $mediaLibraryQuery = null;

    protected string|Closure|null $mediaLibraryDirectory = null;

    protected int|Closure|null $mediaLibraryPageSize = null;

    protected string|Closure|null $mediaLibraryThumbnail = null;

    protected bool|Closure|null $mediaLibraryListView = null;

    protected string|Closure|null $mediaLibraryScope = null;

    protected mixed $mediaLibraryUploadsTo = null;

    /**
     * Whether the image button opens the media browser instead of Filament's upload dialog.
     *
     * On by default, because a picture that is already on the server is the common case in a
     * long document and re-uploading it is the one thing the stock dialog forces. Turning it
     * off restores Filament's own dialog exactly.
     */
    public function mediaLibrary(bool|Closure $condition = true): static
    {
        $this->hasMediaLibrary = $condition;

        return $this;
    }

    public function hasMediaLibrary(): bool
    {
        if (! $this->hasFileAttachments()) {
            return false;
        }

        return (bool) ($this->evaluate($this->hasMediaLibrary)
            ?? config('filament-advanced-rich-editor.media_library.enabled')
            ?? true);
    }

    /**
     * Opens the browser onto a library shared across records, rather than onto this record's
     * own attachments.
     *
     * The closure receives the media query and returns the pool. It is the whole definition:
     * whatever it returns is what the grid lists, and - because the file attachment provider
     * authorises through the same object - what a saved `data-id` is allowed to resolve to.
     * Widening the browser and widening the lookup is deliberately one act, so the two cannot
     * drift into a gap.
     *
     * Media library fields only; a plain disk field takes `mediaLibraryDirectory()` instead.
     *
     * @param  Closure(Builder<Media>): mixed|null  $callback
     */
    public function mediaLibraryQuery(?Closure $callback): static
    {
        $this->mediaLibraryQuery = $callback;

        return $this;
    }

    /**
     * The directory the browser lists, for a field that stores attachments as plain files.
     *
     * Null keeps the pool at the field's own `fileAttachmentsDirectory()`, which is where its
     * uploads already land. Naming a directory makes it a shared library: everything under it
     * can be browsed, reused across records, and referenced from saved content.
     */
    public function mediaLibraryDirectory(string|Closure|null $directory): static
    {
        $this->mediaLibraryDirectory = $directory;

        return $this;
    }

    public function getMediaLibraryDirectory(): ?string
    {
        $directory = $this->evaluate($this->mediaLibraryDirectory)
            ?? config('filament-advanced-rich-editor.media_library.directory');

        return filled($directory) ? trim((string) $directory, '/') : null;
    }

    /**
     * The model new uploads belong to, instead of the record being edited.
     *
     * A media row belongs to a model and its file lives at a path built from the row's id, so a
     * picture uploaded while editing an article is that article's - and deleting the article
     * takes the file with it. Harmless while only that article shows it; not harmless once a
     * second one reuses it.
     *
     * Give the pictures a model of their own and the coupling is gone: nothing an editor
     * deletes owns them.
     *
     *     ->mediaLibraryUploadsTo(fn () => MediaLibrary::firstOrCreate(['key' => 'editor']))
     *
     * Naming one also points the browser at it, so uploading and browsing agree without a
     * second call. `mediaLibraryQuery()` still overrides the pool outright.
     */
    public function mediaLibraryUploadsTo(mixed $target): static
    {
        $this->mediaLibraryUploadsTo = $target;

        return $this;
    }

    public function getMediaLibraryUploadTarget(): ?Model
    {
        $target = $this->evaluate($this->mediaLibraryUploadsTo);

        return ($target instanceof Model) ? $target : null;
    }

    /**
     * How far the browser looks. Three settings, each narrower than the last:
     *
     *   'collection'  every picture in the collection this field uploads to, whichever record
     *                 or model it belongs to. The default, because the collection *is* the
     *                 library: a picture put in `rich-editor` is a picture for rich editors,
     *                 and an article and a post that both upload there draw from one pool
     *                 rather than each fetching the same picture again. Separate libraries are
     *                 separate collections, which is what collections are for.
     *   'model'       only the records of the model being edited.
     *   'record'      only the record in front of you.
     *
     * Whichever it is, it is also what a stored `data-id` may resolve to - the browser and the
     * lookup are one object. `mediaLibraryQuery()` overrides it entirely.
     */
    public function mediaLibraryScope(string|Closure|null $scope): static
    {
        $this->mediaLibraryScope = $scope;

        return $this;
    }

    public function getMediaLibraryScope(): string
    {
        $scope = $this->evaluate($this->mediaLibraryScope)
            ?? config('filament-advanced-rich-editor.media_library.scope')
            ?? 'collection';

        return in_array($scope, ['collection', 'model', 'record'], strict: true) ? $scope : 'collection';
    }

    /**
     * The conversion the grid draws its tiles from.
     *
     * A grid of full-size photographs is a dialog that takes seconds to open, for pictures
     * shown at about 120 pixels wide - so point this at a small conversion and the browser
     * gets cheap. It is deliberately separate from the conversion used when a picture is
     * *inserted*: the tile should be small and the image in the document should not.
     *
     * The conversion has to exist on the model, because that is the only place Spatie lets
     * anyone declare one - a package cannot add a conversion to somebody else's model:
     *
     *     public function registerMediaConversions(?Media $media = null): void
     *     {
     *         $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 320, 320);
     *     }
     *
     * Anything the model has not generated falls back to the original, so naming a conversion
     * that does not exist yet costs nothing and starts working as soon as it does.
     */
    public function mediaLibraryThumbnail(string|Closure|null $conversion): static
    {
        $this->mediaLibraryThumbnail = $conversion;

        return $this;
    }

    public function getMediaLibraryThumbnail(): ?string
    {
        $conversion = $this->evaluate($this->mediaLibraryThumbnail)
            ?? config('filament-advanced-rich-editor.media_library.thumbnail');

        return filled($conversion) ? (string) $conversion : null;
    }

    /**
     * How many pictures one page of the grid holds. The grid loads the next page on demand, so
     * this is a request size rather than a limit on the library.
     */
    public function mediaLibraryPageSize(int|Closure|null $size): static
    {
        $this->mediaLibraryPageSize = $size;

        return $this;
    }

    public function getMediaLibraryPageSize(): int
    {
        $size = $this->evaluate($this->mediaLibraryPageSize)
            ?? config('filament-advanced-rich-editor.media_library.page_size')
            ?? 40;

        return max(1, min(200, (int) $size));
    }

    /**
     * Which of the two layouts the browser opens on.
     *
     * The grid is the default because picking a picture is done by looking at pictures. The
     * list is what the grid cannot do: names, sizes and dates lined up in columns, which is how
     * you find one file among four hundred rather than recognise one among twelve.
     *
     * Only the opening layout. Which one somebody browses in afterwards is a habit rather than
     * a setting, so the dialog remembers their last choice and that wins over this.
     */
    public function mediaLibraryListView(bool|Closure $condition = true): static
    {
        $this->mediaLibraryListView = $condition;

        return $this;
    }

    public function hasMediaLibraryListView(): bool
    {
        return (bool) ($this->evaluate($this->mediaLibraryListView)
            ?? config('filament-advanced-rich-editor.media_library.list_view')
            ?? false);
    }

    /**
     * The pool the browser lists from, and the pool a stored id may resolve against.
     *
     * Built fresh rather than memoised: the closures inside it read the live component, and
     * fields are cloned - by repeaters, by custom block modals - so a source cached on one
     * instance would keep answering with the record of another.
     */
    public function getMediaSource(): ?MediaSource
    {
        if (! $this->hasMediaLibrary()) {
            return null;
        }

        $provider = $this->getFileAttachmentProvider();

        if ($provider instanceof SpatieMediaLibraryFileAttachmentProvider) {
            // Already attached by `getFileAttachmentProvider()`, which is the one place that
            // has to do it: everything Filament resolves goes through the provider, and a
            // source built only here would leave those lookups unauthorised.
            return $provider->getSource();
        }

        // Any other provider defines both where an attachment lives and what its id means, and
        // neither is anything this package can enumerate. Treating it as a plain disk field
        // would open a grid on the wrong pool and, because the pool is also the authoriser,
        // refuse the very ids that provider issued. No browser is the honest answer; Filament's
        // own dialog takes the button back.
        if ($provider !== null) {
            return null;
        }

        $library = $this->getMediaLibraryDirectory();
        $directory = $library ?? $this->getFileAttachmentsDirectory();

        // Filament leaves `fileAttachmentsDirectory()` null by default, and uploads then land at
        // the root of the disk among everything else on it. A grid over that root is not a
        // library of this field's pictures, it is the disk - other features' uploads, avatars,
        // exports - and it would let a stored path resolve to any of them. A directory is what
        // turns a disk into a pool, so without one there is nothing to browse.
        if (blank($directory)) {
            return null;
        }

        return DiskMediaSource::make(
            disk: $this->getFileAttachmentsDiskName(),
            directory: (string) $directory,
            visibility: $this->getFileAttachmentsVisibility(),
            acceptedMimeTypes: $this->getFileAttachmentsAcceptedFileTypes(),
            isRecordScoped: blank($library),
        );
    }

    /**
     * The Spatie pool, for the provider to authorise through.
     *
     * Kept apart from `getMediaSource()` so the two cannot call each other: the provider is
     * where a source is attached, and `getMediaSource()` reads it back off the provider.
     */
    protected function buildSpatieMediaSource(SpatieMediaLibraryFileAttachmentProvider $provider): SpatieMediaSource
    {
        // Where uploads are sent, the browser looks. Otherwise naming a library would mean
        // pictures landing somewhere the grid cannot show and the lookup will not resolve -
        // one call that quietly needs a second one to be of any use.
        $owner = $this->getMediaLibraryUploadTarget();

        return SpatieMediaSource::make(
            collection: $provider->getCollection(),
            conversion: $provider->getConversion(),
            visibility: $provider->getDefaultFileAttachmentVisibility(),
            poolQuery: $this->mediaLibraryQuery,
            getRecordUsing: fn (): mixed => $owner ?? $this->getRecord(),
            acceptedMimeTypes: $this->getFileAttachmentsAcceptedFileTypes(),
            thumbnailConversion: $this->getMediaLibraryThumbnail(),
            scope: $this->getMediaLibraryScope(),
            // Stands in for the record on a create form, which has none yet - otherwise the
            // library would be empty at exactly the moment somebody reaches for a picture they
            // already have.
            getModelUsing: fn (): mixed => $owner ?? $this->getModel(),
        );
    }
}
