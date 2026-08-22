<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders;

use Closure;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdderFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    protected ?MediaSource $source = null;

    protected ?Closure $getUploadTargetUsing = null;

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
     * The model new uploads are attached to, when it is not the record being edited.
     *
     * A media row belongs to a model, and the file's path is derived from the row's id - so a
     * picture uploaded while editing an article belongs to that article, and deleting the
     * article takes the file with it. Fine while the picture is only used there; not fine once
     * a second article reuses it.
     *
     * Pointing uploads at a model that exists for the purpose - a library nobody edits - breaks
     * that coupling: no article owns the file, so no article can take it away.
     *
     * Reading and cleaning up are deliberately left alone. The sweep still looks only at the
     * record's own collection, which is why library pictures are never swept: they are not the
     * record's to delete.
     */
    public function uploadsToUsing(?Closure $callback): static
    {
        $this->getUploadTargetUsing = $callback;

        return $this;
    }

    public function getUploadTarget(): ?Model
    {
        if (! ($this->getUploadTargetUsing instanceof Closure)) {
            return null;
        }

        $target = ($this->getUploadTargetUsing)();

        return ($target instanceof Model) ? $target : null;
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
     *
     * Unless uploads go somewhere else. A library model is already there before the form is
     * opened, so there is nothing to wait for and the attachment can be written at once.
     */
    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return ! $this->getUploadTarget()?->exists;
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
        $record = $this->getUploadRecord();

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

            $properties = ($owner !== null) ? [static::OWNER_PROPERTY => $owner] : [];

            // Measured here because here is the one moment it is free: the file is a local
            // copy that has already been read. The media browser shows the dimensions of a
            // picture, and reading them back off a remote disk later is a request per image.
            $dimensions = MediaDimensions::fromPath($temporaryPath);

            if ($dimensions !== null) {
                $properties += $dimensions;
            }

            if ($properties !== []) {
                $adder = $adder->withCustomProperties($properties);
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
     * And nothing at all is deleted once the browser has offered these pictures to other
     * records; see `sweepsSafely()`.
     *
     * @param  array<mixed>  $exceptIds
     */
    public function cleanUpFileAttachments(array $exceptIds): void
    {
        if (! $this->sweepsSafely()) {
            return;
        }

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
     * Whether removing a picture from this record's content may delete the file behind it.
     *
     * Only when this record is the only place the picture could be. The sweep can read one
     * record's content; it cannot read every other record's, and it has no way to learn which
     * models and columns even hold rich content. So the moment the browser lists a pool wider
     * than the record - the shipped default, because sharing one library across records is what
     * the browser is for - the uuid being removed here may equally be sitting in a document this
     * code will never see, and deleting the file would take that document's picture away with
     * it, silently and for good.
     *
     * The two outcomes are not comparable. A file kept too long costs disk space, is visible in
     * the library, and can be deleted deliberately at any time; a file deleted too early costs
     * somebody else's content and cannot be undone. So a shared library is swept by hand, or by
     * `spatie/laravel-medialibrary`'s own cleanup commands, which can see the whole picture.
     *
     * `->mediaLibraryScope('record')` narrows the pool back to this record and turns automatic
     * sweeping on again, as does switching the browser off: with nothing wider than the record
     * on offer, nothing else can be holding the uuid.
     */
    protected function sweepsSafely(): bool
    {
        // No source at all means nothing ever widened the pool - a renderer, or a field whose
        // browser is off. That is the behaviour the sweep was written for.
        return ! $this->source instanceof MediaSource
            || $this->source->isRecordScoped();
    }

    /**
     * Resolves a media UUID (or an already hydrated media instance) against the record's own
     * collection, and against the browsable pool where the field has one. Scoping the lookup
     * is what stops a tampered `data-id` from another record being resolved into a URL here.
     *
     * The two scopes are a union, and each is there for its own reason. The record's own
     * collection is what this field uploaded and has always been able to resolve. The pool is
     * whatever the media browser was configured to list - and because it is the same object
     * the grid lists from, opening the browser wider and allowing a wider `data-id` are one
     * act rather than two that can drift apart.
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

            return $this->resolveFromSource($uuid);
        }

        if ($file instanceof Media) {
            return $file;
        }

        // A field with no record is a create page: the record has not been inserted yet, but the
        // document is being typed right now, so its ids are as client-supplied as they ever get.
        // The pool is the scope there, exactly as it is once the record exists - which is what
        // keeps the browser working on a create page without opening the lookup to every media
        // row in the application.
        //
        // The field is recognised by the owner resolver it injects; nothing else sets one.
        if ($this->getOwnerUsing instanceof Closure) {
            return $this->resolveFromSource($uuid);
        }

        // Genuinely no field — a `RichContentRenderer` handed this provider directly. There is
        // nothing to scope against and nothing to scope for: the content being rendered came out
        // of the database rather than off a keyboard.

        // `findByUuid()` is declared on a trait shared with other models and returns the
        // framework's base model type.
        $media = Media::findByUuid($uuid);

        return ($media instanceof Media) ? $media : null;
    }

    /**
     * The pool the media browser lists from, when the field opened one.
     *
     * Set by the field rather than passed to the constructor, because the source needs the
     * live component to resolve its record and the provider is built before there is one.
     */
    public function source(?MediaSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSource(): ?MediaSource
    {
        return $this->source;
    }

    /**
     * The pool's answer for a UUID the record's own collection does not hold.
     *
     * Only a `SpatieMediaSource` can answer with media; a disk source belongs to a field that
     * does not use this provider at all.
     */
    protected function resolveFromSource(string $uuid): ?Media
    {
        return ($this->source instanceof SpatieMediaSource)
            ? $this->source->media($uuid)
            : null;
    }

    protected function getMediaUrl(Media $media): ?string
    {
        return MediaUrl::for(
            $media,
            $this->getConversion(),
            $this->getDefaultFileAttachmentVisibility(),
        );
    }

    /**
     * The model an upload is attached to: the library where one was named, the record otherwise.
     *
     * Only the write path asks. Reading and cleaning up keep asking `getMediaRecord()`, because
     * what a field may resolve and what it may delete are questions about the record.
     */
    protected function getUploadRecord(): HasMedia&Model
    {
        $target = $this->getUploadTarget();

        if ($target instanceof HasMedia && $target->exists) {
            return $target;
        }

        if ($target !== null) {
            throw new RuntimeException(sprintf(
                'The model [%s] that rich editor uploads are directed to must exist and implement [%s].',
                $target::class,
                HasMedia::class,
            ));
        }

        return $this->getMediaRecord(required: true);
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
