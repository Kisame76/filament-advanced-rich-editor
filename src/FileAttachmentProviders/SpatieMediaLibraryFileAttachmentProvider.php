<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders;

use Closure;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdderFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Stores rich editor image attachments as `spatie/laravel-medialibrary` media instead of
 * plain files on a filesystem disk.
 *
 * The image node's `attrs.id` holds the media UUID rather than a storage path, which is what
 * makes the lookups below record-scoped: a UUID is only resolved when it is part of the
 * record's own media collection.
 *
 * `spatie/laravel-medialibrary` is an optional dependency. Every entry point is guarded, so a
 * project that never uses this provider is unaffected by its absence; only actually using the
 * feature raises an exception telling the developer to install the package.
 */
class SpatieMediaLibraryFileAttachmentProvider implements FileAttachmentProvider
{
    /**
     * The custom property every attachment this package uploads is stamped with.
     *
     * A media collection belongs to the record, not to the field, so without a mark saying
     * which field put a file there, cleaning up one editor's removed images would take the
     * other editor's images with it - two rich editors on one model share a collection.
     */
    public const OWNER_PROPERTY = 'arte_field';

    protected ?RichContentAttribute $attribute = null;

    protected ?Closure $getRecordUsing = null;

    protected ?Closure $getOwnerUsing = null;

    final public function __construct(
        protected ?string $collection = null,
        protected ?string $conversion = null,
        protected ?string $disk = null,
        protected ?string $visibility = null,
    ) {}

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
     * Called by `RichContentAttribute` when the provider is discovered through a rich content
     * attribute registered on the model, which is where the record comes from on that path.
     */
    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * The field injects `fn () => $component->getRecord()` here, so that the provider follows the
     * record of the form instead of the model the rich content attribute was built from.
     */
    public function recordUsing(?Closure $callback): static
    {
        $this->getRecordUsing = $callback;

        return $this;
    }

    /**
     * The field injects `fn () => $component->getName()` here: the name of the attribute the
     * editor writes to, which is what makes an attachment belong to one editor rather than to
     * the record as a whole. It is the name and not the state path on purpose - the same
     * content edited through a second form is still the same content.
     */
    public function ownerUsing(?Closure $callback): static
    {
        $this->getOwnerUsing = $callback;

        return $this;
    }

    /**
     * Which field owns the attachments this provider uploads, or null where there is no field
     * to ask - a `RichContentRenderer` handed the provider directly, for instance, which only
     * ever reads.
     */
    public function getOwner(): ?string
    {
        if ($this->getOwnerUsing) {
            $owner = ($this->getOwnerUsing)();

            if (is_string($owner) && filled($owner)) {
                return $owner;
            }
        }

        $owner = $this->attribute?->getName();

        return (is_string($owner) && filled($owner)) ? $owner : null;
    }

    public function getRecord(): ?Model
    {
        if ($this->getRecordUsing) {
            $record = ($this->getRecordUsing)();

            if ($record instanceof Model) {
                return $record;
            }
        }

        $record = $this->attribute?->getModel();

        return ($record instanceof Model) ? $record : null;
    }

    public function getCollection(): string
    {
        $collection = $this->collection ?? config('filament-advanced-rich-editor.spatie.collection', 'default');

        return is_string($collection) && filled($collection) ? $collection : 'default';
    }

    public function getConversion(): ?string
    {
        $conversion = $this->conversion ?? config('filament-advanced-rich-editor.spatie.conversion');

        return is_string($conversion) && filled($conversion) ? $conversion : null;
    }

    public function getDisk(): ?string
    {
        $disk = $this->disk ?? config('filament-advanced-rich-editor.spatie.disk');

        return is_string($disk) && filled($disk) ? $disk : null;
    }

    /**
     * On a create form there is no record yet, so Filament skips the attachment save during
     * dehydration and replays it from `saveRelationshipsUsing()` once the record has been
     * inserted — media rows always need a persisted model to belong to.
     */
    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return true;
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        $visibility = $this->visibility ?? config('filament-advanced-rich-editor.spatie.visibility', 'public');

        return is_string($visibility) && filled($visibility) ? $visibility : 'public';
    }

    /**
     * @return string the media UUID, which is stored as the image node's `attrs.id`
     */
    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        $record = $this->getMediaRecord(required: true);

        $fileName = $file->getClientOriginalName();

        // Reading the contents instead of handing over the real path keeps the Livewire
        // temporary file intact and works when Livewire stores temporary uploads on a remote
        // disk, where `getRealPath()` does not point at a readable local file.
        //
        // The file adder is built through the factory rather than through the model's own
        // `addMediaFromString()`, because that method lives on the `InteractsWithMedia` trait
        // and is therefore not part of the `HasMedia` contract this provider types against.
        // The copy is what gets moved into the media collection, so the Livewire upload
        // survives a second pass over the same attachment.
        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'filament-advanced-rich-editor');

        file_put_contents($temporaryPath, $file->get());

        $owner = $this->getOwner();

        try {
            $adder = FileAdderFactory::create($record, $temporaryPath)
                ->usingFileName($fileName)
                ->usingName(pathinfo($fileName, PATHINFO_FILENAME));

            if ($owner !== null) {
                $adder = $adder->withCustomProperties([static::OWNER_PROPERTY => $owner]);
            }

            $media = $adder->toMediaCollection($this->getCollection(), $this->getDisk() ?? '');
        } finally {
            // The adder moves the file when it succeeds, so there is normally nothing left
            // here. An upload that fails - a disk that is full, a rejected mime type - would
            // otherwise leave the copy behind in the system temp directory for good.
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        // Filament asks for the URL of the media it has just saved, and the media relation may
        // already have been loaded while resolving the images that were there before, so drop it
        // to make sure the new media is found again.
        $record->unsetRelation('media');

        return (string) $media->getAttributeValue('uuid');
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        $media = $this->resolveMedia($file);

        if (! $media) {
            return null;
        }

        return $this->getMediaUrl($media);
    }

    /**
     * Removes the attachments this field uploaded and no longer references.
     *
     * Scoped to this field's own uploads, and to nothing else in the collection. A media
     * collection hangs off the record, so an unscoped sweep here would delete the images of
     * every other editor on the same model - and anything else the project happens to keep in
     * that collection - every time one editor was saved.
     *
     * Media without the mark is left alone rather than assumed to be ours: it was put there by
     * something that is not this field, and the worst case for keeping a file is a file too
     * many, while the worst case for deleting one is somebody's content.
     *
     * @param  array<mixed>  $exceptIds
     */
    public function cleanUpFileAttachments(array $exceptIds): void
    {
        $record = $this->getMediaRecord();

        if (! $record?->exists) {
            return;
        }

        $owner = $this->getOwner();

        if ($owner === null) {
            return;
        }

        $exceptIds = array_map(
            fn (mixed $id): string => (string) $id,
            array_filter($exceptIds, fn (mixed $id): bool => is_string($id) || is_numeric($id)),
        );

        foreach ($record->getMedia($this->getCollection()) as $media) {
            if ($media->getCustomProperty(static::OWNER_PROPERTY) !== $owner) {
                continue;
            }

            if (in_array((string) $media->getAttributeValue('uuid'), $exceptIds, strict: true)) {
                continue;
            }

            $media->delete();
        }
    }

    /**
     * Resolves a media UUID (or an already hydrated media instance) against the record's own
     * collection. Scoping the lookup is what stops a tampered `data-id` from another record
     * being resolved into a URL here.
     */
    protected function resolveMedia(mixed $file): ?Media
    {
        if (! class_exists(Media::class)) {
            return null;
        }

        $uuid = ($file instanceof Media)
            ? $file->getAttributeValue('uuid')
            : $file;

        if (! is_string($uuid) || blank($uuid)) {
            return null;
        }

        $record = $this->getMediaRecord();

        if ($record?->exists) {
            foreach ($record->getMedia($this->getCollection()) as $media) {
                if ((string) $media->getAttributeValue('uuid') === $uuid) {
                    return $media;
                }
            }

            return null;
        }

        // Without a record — for instance a `RichContentRenderer` that was handed this provider
        // directly — there is nothing to scope against. The content being rendered comes from the
        // database rather than from the client, so an unscoped lookup is acceptable there.
        if ($file instanceof Media) {
            return $file;
        }

        // `findByUuid()` is declared on a trait shared with other models and returns the
        // framework's base model type.
        $media = Media::findByUuid($uuid);

        return ($media instanceof Media) ? $media : null;
    }

    protected function getMediaUrl(Media $media): ?string
    {
        $conversion = $this->getConversion() ?? '';

        // A private disk has no permanent public URL, so mirror Filament's own behaviour for
        // private attachments and hand out a short-lived signed URL instead.
        if ($this->getDefaultFileAttachmentVisibility() === 'private') {
            try {
                return $media->getTemporaryUrl(
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                    $conversion,
                );
            } catch (Throwable $exception) {
                // This driver does not support creating temporary URLs.
            }
        }

        try {
            return $media->getUrl($conversion);
        } catch (Throwable $exception) {
            // The media, its file or the requested conversion is gone; a missing image is far
            // better than a 500 while rendering somebody's content.
            return null;
        }
    }

    /**
     * @param  bool  $required  throw instead of returning null, for the paths that cannot degrade
     */
    protected function getMediaRecord(bool $required = false): (HasMedia&Model)|null
    {
        if (! interface_exists(HasMedia::class)) {
            if ($required) {
                throw new RuntimeException('The [spatie/laravel-medialibrary] package is required to store rich editor file attachments in a media collection. Install it with [composer require spatie/laravel-medialibrary].');
            }

            return null;
        }

        $record = $this->getRecord();

        if ($record instanceof HasMedia) {
            if ($required && (! $record->exists)) {
                throw new RuntimeException(sprintf('The record of type [%s] must be saved before rich editor file attachments can be added to its media collection.', $record::class));
            }

            return $record;
        }

        if (! $required) {
            return null;
        }

        throw new RuntimeException($record
            ? sprintf('The model [%s] must implement [%s] and use the [%s] trait to store rich editor file attachments in a media collection.', $record::class, HasMedia::class, InteractsWithMedia::class)
            : 'A record is required to store rich editor file attachments in a media collection, but none was resolved.');
    }
}
