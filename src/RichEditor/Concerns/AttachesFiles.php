<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Where an uploaded file goes, and what happens to it when the form is saved.
 *
 * Filament resolves an attachment through a provider; this package either hands it the
 * Spatie one, wraps the one a project brought so the media source stays reachable, or leaves
 * it alone. The two save methods are the halves of one act: a record has to exist before a
 * file can belong to it.
 */
trait AttachesFiles
{
    protected ?SpatieMediaLibraryPlugin $spatieMediaLibraryPlugin = null;

    public function spatieMediaLibrary(?string $collection = null, ?string $conversion = null, ?string $disk = null, ?string $visibility = null): static
    {
        $isFirstCall = $this->spatieMediaLibraryPlugin === null;

        $this->spatieMediaLibraryPlugin = SpatieMediaLibraryPlugin::make(
            $collection ?? config('filament-advanced-rich-editor.spatie.collection'),
            $conversion ?? config('filament-advanced-rich-editor.spatie.conversion'),
            $disk ?? config('filament-advanced-rich-editor.spatie.disk'),
            $visibility ?? config('filament-advanced-rich-editor.spatie.visibility'),
        );

        // The plugin is registered through a closure so the record resolver is
        // always bound to the component the plugins are resolved for. Fields are
        // cloned (repeaters, custom block modals), and a resolver captured here
        // would keep answering with the record of the original instance.
        // `plugins()` appends, so the closure is registered once and simply reads
        // whatever the latest call to this method configured.
        if ($isFirstCall) {
            $this->plugins(static function (AdvancedRichEditor $component): array {
                $plugin = $component->getSpatieMediaLibraryPlugin();

                return $plugin ? [$plugin] : [];
            });
        }

        return $this;
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        // Field level configuration wins over plugins, which in turn win over
        // the provider declared on the model's rich content attribute.
        if ($provider = $this->getSpatieMediaLibraryPlugin()?->getFileAttachmentProvider()) {
            return $this->withMediaSource($provider);
        }

        foreach ($this->getPlugins() as $plugin) {
            if (! ($plugin instanceof HasFileAttachmentProvider)) {
                continue;
            }

            if ($provider = $plugin->getFileAttachmentProvider()) {
                return $this->withMediaSource($provider);
            }
        }

        return $this->withMediaSource(parent::getFileAttachmentProvider());
    }

    /**
     * Hands the provider the pool the media browser lists from.
     *
     * Every lookup Filament performs - hydrating a document, saving one, rendering one back -
     * goes through the provider, so this is the single place where "what the browser offers"
     * becomes "what a stored id may resolve to". Attaching it anywhere else would leave some
     * of those paths reading against the record scope alone, and a picture picked from the
     * library would resolve in the editor and vanish on the page.
     */
    protected function withMediaSource(?FileAttachmentProvider $provider): ?FileAttachmentProvider
    {
        if ($provider instanceof SpatieMediaLibraryFileAttachmentProvider) {
            $provider->source(
                $this->hasMediaLibrary() ? $this->buildSpatieMediaSource($provider) : null,
            );
        }

        return $provider;
    }

    /**
     * Returns the field level Spatie plugin with a record resolver bound to
     * this very instance. Creating the closure here - and not in
     * `spatieMediaLibrary()` - is what keeps it pointing at the live component.
     */
    protected function getSpatieMediaLibraryPlugin(): ?SpatieMediaLibraryPlugin
    {
        return $this->spatieMediaLibraryPlugin
            ?->recordUsing(fn (): mixed => $this->getRecord())
            ->ownerUsing(fn (): string => $this->getName())
            ->uploadsToUsing(fn (): mixed => $this->getMediaLibraryUploadTarget());
    }

    /**
     * One held upload, checked against the list that governs where it came from.
     *
     * Filament measures every upload against `fileAttachmentsAcceptedFileTypes()`, which is
     * four image mime types and has to stay that way: the same list governs the compiled
     * drop-and-paste handler, and that handler inserts an `image` node for anything it
     * accepts - so widening it would turn a dropped film into an `<img>` pointing at an mp4.
     *
     * A file that came through the media browser is a different question with a different
     * answer, and it is answerable because the browser stamps its own ids. Those are
     * measured against what the browser accepts; everything else is handed to Filament
     * untouched.
     *
     * This is the one gate both halves of the browser pass: the grid asks it before drawing
     * a pending tile, and `resolveFileAttachmentIds()` asks it again before writing the file.
     */
    public function getUploadedFileAttachment(TemporaryUploadedFile|string|null $attachment = null): ?TemporaryUploadedFile
    {
        if (! FileAttachments::pending($attachment)) {
            return parent::getUploadedFileAttachment($attachment);
        }

        $file = data_get($this->getLivewire(), "componentFileAttachments.{$this->getStatePath()}.{$attachment}");

        if (! ($file instanceof TemporaryUploadedFile)) {
            return null;
        }

        $maxSize = $this->getFileAttachmentsMaxSize();
        $acceptedFileTypes = $this->getMediaLibraryAcceptedFileTypes();

        try {
            Validator::validate(
                ['file' => $file],
                rules: [
                    'file' => [
                        'file',
                        ...($maxSize ? ["max:{$maxSize}"] : []),
                        ...($acceptedFileTypes ? ['mimetypes:'.implode(',', $acceptedFileTypes)] : []),
                    ],
                ],
            );
        } catch (ValidationException $exception) {
            return null;
        }

        return $file;
    }

    /**
     * Every attachment this document still uses, and the uploads among them written to disk.
     *
     * Copied from Filament for one line. The parent walks the document asking
     * `$node->type !== 'image'`, which was the whole truth while a picture was the only
     * thing an editor could upload. A video carries an id in exactly the same way and would
     * be invisible to that question - and the list this method returns is the list
     * `cleanUpFileAttachments()` spares, so an invisible attachment is a deleted file. The
     * document would keep pointing at it, and nothing would raise.
     *
     * `FileAttachments::carriedBy()` is that one line, so the next kind of file joins the
     * lifecycle by being named there.
     *
     * @return array<mixed>
     */
    public function resolveFileAttachmentIds(): array
    {
        $fileAttachmentIds = [];

        $this->rawState(
            $this->getTipTapEditor()
                ->setContent($this->getRawState() ?? [
                    'type' => 'doc',
                    'content' => [],
                ])
                ->descendants(function (object &$node) use (&$fileAttachmentIds): void {
                    if (! FileAttachments::carriedBy($node->type ?? null)) {
                        return;
                    }

                    if (blank($node->attrs->id ?? null)) {
                        return;
                    }

                    $attachment = $this->getUploadedFileAttachment($node->attrs->id);

                    if ($attachment) {
                        $node->attrs->id = $this->saveUploadedFileAttachment($attachment);
                        $node->attrs->src = $this->getFileAttachmentUrl($node->attrs->id);

                        $fileAttachmentIds[] = $node->attrs->id;

                        return;
                    }

                    if (filled($this->getFileAttachmentUrl($node->attrs->id))) {
                        $fileAttachmentIds[] = $node->attrs->id;
                    }
                })
                ->{$this->isJson() ? 'getDocument' : 'getHtml'}(),
        );

        return $fileAttachmentIds;
    }

    /**
     * The attachment ids the record already held, which is what the tamper check measures a
     * save against.
     *
     * Copied from Filament for the same one line `resolveFileAttachmentIds()` is: a media id
     * that was legitimately in the document before this edit has to be recognised as such, or
     * the first save of an untouched record would be refused as tampering.
     *
     * @return array<string>
     */
    public function getOriginalFileAttachmentPaths(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return [];
        }

        $attribute = $this->getName();

        $originalContent = $record->getOriginal($attribute, $record->getAttribute($attribute));

        if (blank($originalContent)) {
            return [];
        }

        $ids = [];

        $this->getTipTapEditor()
            ->setContent($originalContent)
            ->descendants(function (object $node) use (&$ids): void {
                if (! FileAttachments::carriedBy($node->type ?? null)) {
                    return;
                }

                if (blank($node->attrs->id ?? null)) {
                    return;
                }

                $ids[] = $node->attrs->id;
            });

        return $ids;
    }

    public function saveFileAttachments(): void
    {
        parent::saveFileAttachments();

        // Not on a create page, where the parent bails out and leaves the work to
        // `saveFileAttachmentsToRecord()`: discarding here would throw the uploads away before
        // a single one of them had been written.
        if ($this->getFileAttachmentProvider()?->isExistingRecordRequiredToSaveNewFileAttachments() && (! $this->getRecord())) {
            return;
        }

        $this->discardPendingUploads();
    }

    /**
     * Writes the editor's state to a record that has just been created.
     *
     * Copied from the parent so one dereference can be made safe: Filament resolves the
     * attribute name with `$this->getContentAttribute()->getName()`, and
     * `getContentAttribute()` is null unless the *model* registers rich content through
     * `HasRichContent`. Any file attachment provider that needs a persisted record - the
     * Spatie one does, because media is attached to a saved model - reaches this method
     * on every create page, so a plain model would fatal with "Call to a member function
     * getName() on null" the first time an image is uploaded. The field's own name is the
     * correct fallback, since that is the attribute the state came from.
     *
     * `$record` is guarded as well: the parent assumes `saveRelationships()` never runs
     * without one.
     */
    public function saveFileAttachmentsToRecord(): void
    {
        $fileAttachmentProvider = $this->getFileAttachmentProvider();

        if (! $fileAttachmentProvider?->isExistingRecordRequiredToSaveNewFileAttachments()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record?->wasRecentlyCreated) {
            return;
        }

        $fileAttachmentIds = $this->resolveFileAttachmentIds();

        $record->setAttribute(
            $this->getContentAttribute()?->getName() ?? $this->getName(),
            $this->getState(),
        );
        $record->save();

        $fileAttachmentProvider->cleanUpFileAttachments(exceptIds: $fileAttachmentIds);

        $this->discardPendingUploads();
    }
}
