<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\ReplacesMedia;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\LibraryTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\FileNotPreviewableException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * What the media browser asks the field for while it is open.
 *
 * These are the methods the Alpine component reaches over `callSchemaComponentMethod`: one
 * page of the grid, the details of one item, and the pending uploads that exist only between
 * the drop and the save. They are the field's public surface towards its own JavaScript,
 * and nothing else in the package calls them.
 */
trait ServesTheMediaBrowser
{
    /** The dialog these uploads belong to, as Filament files a mounted action under. */
    protected const MEDIA_BROWSER_ACTION = 'mediaBrowser';

    /**
     * One page of the browser, fetched by the grid as it scrolls.
     *
     * Exposed to the front end, so it re-reads the pool from the field on every call rather
     * than trusting anything the browser sends beyond a search term and a page number.
     *
     * @return array{items: array<int, array<string, mixed>>, folders: array<int, array{name: string, path: string}>, parent: string|null, hasMore: bool, rejected: array<int, string>}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaLibraryPageForJs(string $search = '', ?string $folder = null, int $page = 1, ?string $type = null, ?string $sort = null, ?string $kind = null): array
    {
        $source = $this->getMediaSource();

        if (! $source) {
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'kinds' => [], 'perPage' => $this->getMediaLibraryPageSize(), 'rejected' => []];
        }

        // Taken over here rather than as each file lands. Uploading is a request per file, and
        // writing to the component in the middle of one forces a render between the first file
        // and the second - which is enough for Filament to rebuild its schema cache and refuse
        // the next upload, because the guard on `_startUpload` only accepts a path that a
        // cached schema still knows about.
        //
        // Doing it when the browser asks for a page keeps every upload request untouched, and
        // the browser asks as soon as an upload finishes.
        $rejected = $this->adoptMountedUploads();

        $search = trim($search);
        $page = max(1, $page);

        $perPage = $this->getMediaLibraryPageSize();

        $result = $source->page(
            search: $search,
            folder: $folder,
            page: $page,
            perPage: $perPage,
            // Checked against the families this package knows rather than passed through: what
            // arrives is whatever a request carried, and an unknown family narrowing a query
            // to nothing would read as an empty library.
            filters: [
                'type' => $type,
                'sort' => $sort,
                'kind' => in_array($kind, MediaKinds::all(), strict: true) ? $kind : null,
            ],
        );

        // Sent rather than left for the browser to infer. A grid that guesses the page size
        // from how many tiles came back reads a short last page as a tiny page size, and then
        // divides the whole library by it - which is how a two-page library grew a footer
        // saying "2 / 41" with a Next button leading to nothing.
        $result['perPage'] = $perPage;

        // Said once, by name. An upload that is refused here has already travelled - the
        // widget only asks what the browser calls a file, not what its bytes are - and a file
        // that simply never turned up in the grid reads as the dialog having lost it.
        $result['rejected'] = $rejected;

        // A picture uploaded a moment ago is not in the library yet - Filament holds it as a
        // pending attachment and only writes it on save - so a browser that listed the library
        // alone would answer "no such picture" about the file somebody just chose. It goes at
        // the front of the first page, where the newest things already are.
        //
        // Only on the first page, and only at the top of the tree: a pending upload has no
        // folder to sit in, and repeating it under every folder would be worse than not
        // showing it at all.
        if ($page === 1 && blank($folder)) {
            $pending = $this->getPendingMediaItems($search);

            if (in_array($kind, MediaKinds::all(), strict: true)) {
                $pending = array_values(array_filter(
                    $pending,
                    static fn (array $item): bool => ($item['kind'] ?? null) === $kind,
                ));
            }

            if ($type !== null && filled($type)) {
                $pending = array_values(array_filter(
                    $pending,
                    static fn (array $item): bool => $item['mime'] === $type,
                ));
            }

            $result['items'] = [...$pending, ...$result['items']];
            $result['total'] = ((int) $result['total']) + count($pending);

            // The tabs are drawn from the families on the page, and a file uploaded a
            // moment ago is on the page: without this a library holding nothing but two
            // fresh uploads answered `kinds: []`, and the tab row stayed hidden with a
            // video and a sound sitting right under it.
            $result['kinds'] = array_values(array_intersect(
                MediaKinds::all(),
                array_unique([
                    ...$result['kinds'],
                    ...array_map(static fn (array $item): string => (string) ($item['kind'] ?? ''), $pending),
                ]),
            ));
        }

        return $result;
    }

    /**
     * Everything the details panel shows about one picture.
     *
     * A second call rather than part of the page, because the expensive field is the size in
     * pixels: a picture that was never stamped with it has to be opened to be measured, and
     * doing that for a grid would be a file read per tile. The panel shows one at a time.
     *
     * @return array<string, mixed>|null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaDetailsForJs(?string $id = null): ?array
    {
        if (blank($id) || ! $this->hasMediaLibrary()) {
            return null;
        }

        // A pending upload is not in the pool, and it is already described in full by the
        // listing - the file is local, so its size in pixels was read there rather than left
        // for this call.
        $pending = $this->pendingMediaItem($id, $this->getUploadedFileAttachment($id));

        $item = $pending ?? $this->getMediaSource()?->details($id);

        // Merged here rather than in either source, so the pending path and the two stored
        // paths cannot answer this differently - and so a listing, which does not need the
        // description, does not pay a sidecar read per tile to get one.
        //
        // `replace` is what the panel's Replace button offers the file picker, or null where
        // there is no button: an upload that is not in the library yet has nothing to replace.
        return $item === null ? null : [
            ...$item,
            ...$this->getMediaMetadata($id),
            'replace' => ($pending === null) ? $this->getMediaReplacementTypes($id) : null,
        ];
    }

    /**
     * What may take the place of a file in the library, or null where nothing may.
     *
     * @return array<int, string>|null
     */
    public function getMediaReplacementTypes(mixed $id): ?array
    {
        if (FileAttachments::pending($id) || ! $this->canReplaceInMediaLibrary()) {
            return null;
        }

        $source = $this->getMediaSource();

        return ($source instanceof ReplacesMedia) ? $source->replacementTypes($id) : null;
    }

    /**
     * Puts the upload the dialog is holding in the place of a file in the library.
     *
     * The id stays, so every document using the file shows the new one from its next render
     * - and this document too, once the browser tells the editor what changed. That is what
     * `item` and `card` are for: the address the nodes point at now, and the name and size a
     * card writes into itself.
     *
     * The upload is let go of whatever the answer, and before anything else can go wrong. An
     * upload left in the dialog's form is validated on Submit, and a file nobody can see then
     * stops the dialog from inserting anything, without a word.
     *
     * @return array{replaced: bool, item?: array<string, mixed>|null, documents?: int, card?: array{name: string|null, size: string|null}, accept?: array<int, string>|null}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function replaceMediaForJs(string $id): array
    {
        $file = $this->takeReplacementUpload();

        try {
            if (blank($id) || ! $this->hasMediaLibrary() || FileAttachments::pending($id)) {
                return ['replaced' => false];
            }

            $accept = $this->getMediaReplacementTypes($id);
            $source = $this->getMediaSource();

            if (($accept === null) || ! ($source instanceof ReplacesMedia) || ! ($file instanceof TemporaryUploadedFile)) {
                return ['replaced' => false];
            }

            // What any upload into this browser is asked - the size and the content - before
            // the source asks what only a replacement is: the family, and the ending.
            if (! $this->acceptsMediaLibraryUpload($file) || ! $source->replace($id, $file)) {
                return ['replaced' => false, 'accept' => $accept];
            }

            $item = $this->getMediaDetailsForJs($id);

            $card = (($item['kind'] ?? null) === MediaKinds::FILE)
                ? ['name' => MediaLibraryAction::cardName($item), 'size' => ByteSize::format($item['size'] ?? null)]
                : null;

            // Every entry using the file is pointed at it, stored and all: the address, what a
            // card says about it, and the shape a sized picture is drawn in.
            $documents = rescue(fn (): int => $this->getMediaUsages()->update($id, [
                'src' => $item['url'] ?? null,
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                ...($card ?? []),
            ]), 0);

            return [
                'replaced' => true,
                'item' => $item,
                'documents' => $documents,
                ...(($card !== null) ? ['card' => $card] : []),
            ];
        } finally {
            // Handed over or refused, it is finished with - and Livewire's own sweep is a day
            // away.
            rescue(static fn () => $file?->delete(), report: false);
        }
    }

    /**
     * Takes the replacement out of the browser's dialog, and leaves the dialog holding none.
     *
     * Read out of the mounted actions for the reason the uploads to insert are: the dialog's
     * form belongs to a modal, and this is the one place its state can be reached from. Only
     * the browser's own frame - another dialog's `replacement` field, if one ever had such a
     * thing, is none of this field's business.
     */
    protected function takeReplacementUpload(): ?TemporaryUploadedFile
    {
        $livewire = $this->getLivewire();
        $mounted = data_get($livewire, 'mountedActions');

        if (! is_array($mounted)) {
            return null;
        }

        foreach ($mounted as $index => $action) {
            $name = data_get($action, 'name');

            if (is_string($name) && ($name !== static::MEDIA_BROWSER_ACTION)) {
                continue;
            }

            $files = data_get($action, 'data.replacement');

            if (! is_array($files) || ($files === [])) {
                continue;
            }

            data_set($livewire, "mountedActions.{$index}.data.replacement", []);

            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Where a description waits while the file it describes is still a temporary upload.
     *
     * On the Livewire component rather than on the field, and for the same reason
     * `componentFileAttachments` is: the dialog is a modal, its form is thrown away when it
     * closes, and a description typed there has to outlive that.
     */
    public const PENDING_METADATA_KEY = 'arteMediaMetadata';

    /**
     * What this medium is called, wherever the answer happens to live.
     *
     * @return array{alt: ?string, title: ?string}
     */
    public function getMediaMetadata(mixed $id): array
    {
        if (FileAttachments::pending($id)) {
            $held = data_get($this->getLivewire(), static::PENDING_METADATA_KEY.'.'.$id);

            return [
                'alt' => is_string($held['alt'] ?? null) ? $held['alt'] : null,
                'title' => is_string($held['title'] ?? null) ? $held['title'] : null,
            ];
        }

        return $this->getMediaSource()?->metadata($id) ?? ['alt' => null, 'title' => null];
    }

    /**
     * The panel's one field, saved as it is left.
     *
     * Reachable from the browser, so what it accepts is the whole of what it trusts: two
     * keys, strings, and a length that is a description rather than a document. Anything
     * else is `false` and nothing is written - the panel then shows the value it last knew,
     * which is the honest thing to show when a write did not happen.
     *
     * @param  array<string, mixed>  $data
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function saveMediaMetadataForJs(string $id, array $data): bool
    {
        if (blank($id) || ! $this->hasMediaLibrary()) {
            return false;
        }

        $written = [];

        foreach ($data as $key => $value) {
            // An unknown key is refused rather than dropped. Dropping it would write the rest
            // and answer `true`, which tells the panel a save happened that partly did not.
            if (! in_array($key, ['alt', 'title'], strict: true)) {
                return false;
            }

            if ($value !== null && ! is_string($value)) {
                return false;
            }

            $value = is_string($value) ? trim($value) : '';

            if (mb_strlen($value) > 1000) {
                return false;
            }

            $written[$key] = $value;
        }

        if ($written === []) {
            return false;
        }

        if (FileAttachments::pending($id)) {
            // Only for an upload this field is actually holding. An id that is merely spelled
            // with the prefix is not one of ours, and holding a description against it would
            // be a place for anything to write anything.
            if (! ($this->getUploadedFileAttachment($id) instanceof TemporaryUploadedFile)) {
                return false;
            }

            $livewire = $this->getLivewire();
            $key = static::PENDING_METADATA_KEY.'.'.$id;

            data_set($livewire, $key, [...(array) (data_get($livewire, $key) ?? []), ...$written]);

            return true;
        }

        return (bool) $this->getMediaSource()?->saveMetadata($id, $written);
    }

    /**
     * Throws away what is selected.
     *
     * Three refusals before anything happens: no library, an upload that is not saved yet -
     * which is a discard rather than a delete - and a field that may not delete, which by
     * default is every pool wider than this record's own. The source refuses the last one
     * again, because a rule that only lives on the exposed method is a rule the next caller
     * does not have.
     *
     * Then takes the file out of every entry using it - see `mediaLibraryDocuments()` - and
     * answers how many that was.
     *
     * @return array{deleted: bool, documents: int}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function deleteMediaForJs(string $id): array
    {
        if (blank($id) || ! $this->hasMediaLibrary() || FileAttachments::pending($id)) {
            return ['deleted' => false, 'documents' => 0];
        }

        if (! $this->canDeleteFromMediaLibrary() || ! $this->getMediaSource()?->delete($id)) {
            return ['deleted' => false, 'documents' => 0];
        }

        // After the file, never before: a file that could not be deleted must not have been
        // taken out of anybody's text. And reported rather than raised - the file is gone
        // either way, and saying so is still the true answer.
        $documents = rescue(fn (): int => $this->getMediaUsages()->remove($id), 0);

        return ['deleted' => true, 'documents' => $documents];
    }

    /**
     * How many entries use a file, and what a few of them are called - asked before deleting
     * or replacing it, so the question can say what else it touches.
     *
     * Only for a file in the pool, and only on a field that may delete or replace at all: the
     * answer names other records, and nothing else here needs to.
     *
     * @return array{count: int, entries: array<int, string>}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaUsageForJs(string $id): array
    {
        $none = ['count' => 0, 'entries' => []];

        if (blank($id) || ! $this->hasMediaLibrary() || FileAttachments::pending($id)) {
            return $none;
        }

        if (! ($this->canDeleteFromMediaLibrary() || $this->canReplaceInMediaLibrary()) || ! $this->getMediaSource()?->has($id)) {
            return $none;
        }

        return $this->getMediaUsages()->describe($id);
    }

    /**
     * Moves a description from the upload it was typed against onto the file that upload
     * became.
     *
     * Called from `resolveFileAttachmentIds()`, at the one point where a pending id turns
     * into a real one. Let go of afterwards: a second save must not write it back over a
     * description somebody has since corrected in the library.
     */
    public function applyPendingMediaMetadata(mixed $pendingId, mixed $savedId): void
    {
        if (! FileAttachments::pending($pendingId) || blank($savedId)) {
            return;
        }

        $livewire = $this->getLivewire();
        $key = static::PENDING_METADATA_KEY.'.'.$pendingId;

        $held = data_get($livewire, $key);

        if (! is_array($held) || $held === []) {
            return;
        }

        $this->getMediaSource()?->saveMetadata($savedId, [
            'alt' => is_string($held['alt'] ?? null) ? $held['alt'] : null,
            'title' => is_string($held['title'] ?? null) ? $held['title'] : null,
        ]);

        data_set($livewire, $key, null);
    }

    /**
     * Takes over the uploads the image dialog is holding.
     *
     * The dialog is a modal, and a modal's form is thrown away when it closes - so an upload
     * that lived there disappeared the moment somebody pressed apply, taking every picture
     * they had queued up but not used yet with it.
     *
     * Moved here instead, where Filament already keeps pending attachments: they belong to the
     * field, outlive any number of trips through the dialog, and are turned into real files
     * only when the form is saved - and then only the ones the content actually references.
     * Everything else is simply never written, so nothing has to be cleaned up.
     *
     * @param  array<mixed>  $files
     */
    public function registerPendingUploads(array $files): void
    {
        // Held in a variable because `data_set()` takes its target by reference, which a method
        // call cannot be.
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        foreach ($files as $file) {
            if (! ($file instanceof TemporaryUploadedFile)) {
                continue;
            }

            // Named after the file rather than after the key it arrived under. The dialog
            // reports its whole set every time one more picture is added, and the keys of that
            // set are Filament's business - a plain list one moment, keyed by id the next - so
            // trusting them would queue the same upload twice under two different names.
            //
            // Hashed, because the temporary file name carries dots and `data_set()` reads a dot
            // as a level of nesting: one attachment would quietly become a tree that nothing
            // can find again.
            $id = FileAttachments::PENDING_PREFIX.md5($file->getFilename());

            data_set($livewire, "componentFileAttachments.{$statePath}.{$id}", $file);
        }
    }

    /**
     * Takes over whatever the image dialog is currently holding.
     *
     * The dialog's own form belongs to a modal and is thrown away when the modal closes, so an
     * upload left there disappears the moment somebody presses apply - taking every picture
     * they had queued up but not used yet with it. Moved to the field instead, where Filament
     * already keeps pending attachments.
     *
     * Read out of the mounted actions rather than pushed in by the dialog, so that nothing is
     * written to the component while an upload request is in flight.
     *
     * An upload the browser does not take is let go of on the way, out of the dialog's own
     * field as well - left there, the dialog's Submit would validate a file nobody can see and
     * refuse to insert anything. Answers with the names of the ones it let go of.
     *
     * @return array<int, string>
     */
    public function adoptMountedUploads(): array
    {
        $livewire = $this->getLivewire();
        $mounted = data_get($livewire, 'mountedActions');

        if (! is_array($mounted)) {
            return [];
        }

        $rejected = [];

        foreach ($mounted as $index => $action) {
            // Somebody else's dialog, left alone. Every mounted action keeps its form state
            // under the same key, so a `file` field further down the stack belongs to
            // whatever opened it - and measuring those uploads against this field's list
            // took them out of the form they were attached to, named as refused in a dialog
            // about something else. A frame that does not say what it is is read as this
            // one: Filament names every action it mounts, so an unnamed frame is a hand-made
            // one, and refusing to read it would take the dialog's own uploads away.
            $name = data_get($action, 'name');

            if (is_string($name) && ($name !== static::MEDIA_BROWSER_ACTION)) {
                continue;
            }

            $files = data_get($action, 'data.file');

            if (! is_array($files)) {
                continue;
            }

            $kept = array_filter(
                $files,
                fn (mixed $file): bool => ! ($file instanceof TemporaryUploadedFile) || $this->acceptsMediaLibraryUpload($file),
            );

            foreach (array_diff_key($files, $kept) as $file) {
                $rejected[] = (string) $file->getClientOriginalName();
            }

            if (count($kept) !== count($files)) {
                data_set($livewire, "mountedActions.{$index}.data.file", $kept);
            }

            $this->registerPendingUploads($kept);
        }

        return $rejected;
    }

    /**
     * Lets go of every upload this field was holding.
     *
     * Called once the form has been saved, which is the moment they have all been decided: the
     * ones the content references have been written to disk and carry real ids now, and the
     * rest are pictures somebody fetched and did not use.
     *
     * Both are finished with. Keeping the first would show the same picture twice in the
     * browser - once as the file it became, once as the upload it used to be - and keeping the
     * second would offer a temporary file that is about to be swept away as though it were a
     * library item.
     *
     * The temporary files go too. Livewire prunes its own directory eventually, but "eventually"
     * is a lot of abandoned uploads on a busy editor, and these are known to be finished with.
     */
    public function discardPendingUploads(): void
    {
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        // The descriptions typed against those uploads go with them. The ones that became
        // files have already been written through `applyPendingMediaMetadata()`; the rest
        // describe pictures nobody used. Above the guard below, or a field holding a
        // description and no attachment would keep it for ever.
        data_set($livewire, static::PENDING_METADATA_KEY, []);

        $attachments = data_get($livewire, "componentFileAttachments.{$statePath}");

        if (! is_array($attachments) || $attachments === []) {
            return;
        }

        foreach ($attachments as $file) {
            if (! ($file instanceof TemporaryUploadedFile)) {
                continue;
            }

            // A file Livewire has already swept, or one on a disk that will not have it
            // deleted: not being able to tidy up is not a reason to fail a save.
            rescue(static fn () => $file->delete(), report: false);
        }

        data_set($livewire, "componentFileAttachments.{$statePath}", []);
    }

    /**
     * The uploads this field is holding that have not been saved yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPendingMediaItems(string $search = ''): array
    {
        $attachments = data_get($this->getLivewire(), "componentFileAttachments.{$this->getStatePath()}");

        if (! is_array($attachments)) {
            return [];
        }

        $items = [];

        foreach (array_reverse($attachments, preserve_keys: true) as $id => $attachment) {
            if (! is_string($id)) {
                continue;
            }

            $item = $this->pendingMediaItem($id, $attachment);

            if ($item === null) {
                continue;
            }

            if (filled($search) && ! str_contains(Str::lower($item['name']), Str::lower($search))) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * One pending upload as the grid draws an item, or null where it is not one this browser
     * should offer - a file that failed the validation it came in under, or one Filament's own
     * dialog is holding, which takes pictures only.
     *
     * @return array<string, mixed>|null
     */
    protected function pendingMediaItem(string $id, mixed $attachment): ?array
    {
        if (! ($attachment instanceof TemporaryUploadedFile)) {
            return null;
        }

        // Through the field rather than off the file: this is where the size and the types are
        // checked again, and a rejected upload must not become a tile.
        $file = $this->getUploadedFileAttachment($id);

        if (! $file) {
            return null;
        }

        $name = (string) $file->getClientOriginalName();

        // The type the ending names, the way the pool reads one off a file on a disk. What
        // the bytes say is the upload check's business and often another answer entirely -
        // a spreadsheet written as text is `text/plain` - and filing a pending row under
        // that hid it from a filter set to its own type until the form was saved.
        $sniffed = (string) $file->getMimeType();
        $mime = $this->getMediaLibraryTypes()->mimeOf($name) ?: $sniffed;

        // Any family the field offers - a document too, which becomes a card rather than an
        // element. Filed the way the pool files it, so a tile cannot change tabs on save.
        $kind = $this->getMediaLibraryTypes()->kindOf($sniffed, $name);

        if ($kind === null) {
            return null;
        }

        // Off the file, not through Filament's `getUploadedFileAttachmentTemporaryUrl()`.
        // That wrapper takes the OBJECT back through `getUploadedFileAttachment()`, and with
        // an object rather than an id there is no prefix to say the file came through the
        // browser - so it was measured against Filament's picture-only list and every
        // video and sound came back null. The file was validated against the browser's
        // list a few lines up; this is the one place it is asked for its address.
        $url = static::temporaryUrlOf($file, document: $kind === MediaKinds::FILE);

        if (blank($url)) {
            return null;
        }

        return [
            'id' => $id,
            'url' => $url,
            // A picture stands in for itself; a video and a sound have nothing to draw, and
            // saying so is what lets the grid put a sign there instead of a broken picture.
            'thumbnail' => $kind === MediaKinds::IMAGE ? $url : null,
            'name' => $name,
            'fileName' => $name,
            'mime' => $mime,
            'kind' => $kind,
            ...($kind === MediaKinds::FILE ? FileTypes::tile($name) : []),
            'size' => (int) $file->getSize(),
            'folder' => null,
            'createdAt' => null,
            'modifiedAt' => null,
            // Measured here rather than left for the panel: a pending upload is a file Livewire
            // is holding on local disk, so reading its header costs nothing, and there are a
            // handful of them rather than a library's worth.
            ...($kind === MediaKinds::IMAGE
                ? ($this->measurePending($file) ?? ['width' => null, 'height' => null])
                : ['width' => null, 'height' => null]),
            // Drawn differently, and said out loud: this one is not in the library until the
            // form is saved, and somebody who navigates away now will not find it again.
            'pending' => true,
        ];
    }

    /**
     * Where a held upload can be looked at before it is saved, or null where Livewire
     * refuses to say.
     *
     * Livewire hands out a preview address only for the extensions in its
     * `temporary_file_upload.preview_mimes` list, and answers anything else with an
     * exception rather than a null. The shipped list covers `mp4`, `mov`, `mp3`, `wav`
     * and `m4a` but not `webm`, `ogg`, `flac` or `aac` - so a project that uploads those
     * extends that list, and until it does the upload is held but cannot be shown. Held
     * rather than crashed: an exception here would take the whole page request down for
     * one file that merely cannot be previewed.
     *
     * A document is let onto that list for this request, by the ending its content has - which
     * is what Livewire reads, and which for a spreadsheet written as text is `txt`. It was
     * checked against the ending it came under already, and Livewire serves a preview as a
     * download rather than as a page. Asked for here rather than at boot, because the endings
     * a field takes are the field's, and a single field may name one no configuration does.
     */
    protected static function temporaryUrlOf(TemporaryUploadedFile $file, bool $document = false): ?string
    {
        $key = 'livewire.temporary_file_upload.preview_mimes';
        $previewable = config($key);
        $widened = false;

        if ($document) {
            $ending = Str::lower((string) $file->guessExtension());

            if (($ending !== '') && ! in_array($ending, LibraryTypes::DENIED, strict: true)) {
                config()->set($key, array_values(array_unique([
                    ...(is_array($previewable) ? $previewable : []),
                    $ending,
                ])));

                $widened = true;
            }
        }

        try {
            return $file->temporaryUrl();
        } catch (FileNotPreviewableException $exception) {
            return null;
        } finally {
            // Put back, because that list governs every temporary upload in the request and
            // not only this tile: widening it for one document and leaving it wide hands out
            // preview addresses for files no field here has anything to do with.
            if ($widened) {
                config()->set($key, $previewable);
            }
        }
    }

    /**
     * @return array{width: int, height: int}|null
     */
    protected function measurePending(TemporaryUploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (is_file($path)) {
            return MediaDimensions::fromPath($path);
        }

        // Livewire keeps temporary uploads on a remote disk in some setups, where there is no
        // local file to point `getimagesize()` at.
        return MediaDimensions::fromString((string) $file->get());
    }

    /**
     * The item behind an id the dialog sent back, pending uploads included.
     *
     * Never trusts the id: a pending one has to be an upload this field is actually holding,
     * and a stored one has to be something the pool would have listed.
     *
     * @return array<string, mixed>|null
     */
    public function findMediaItem(mixed $id): ?array
    {
        // The raw held value rather than a validated one: `pendingMediaItem()` re-checks it
        // through the field anyway, and asking twice runs the size and accepted-type
        // validator - which reads the file - once per lookup for nothing.
        $held = is_string($id)
            ? data_get($this->getLivewire(), "componentFileAttachments.{$this->getStatePath()}.{$id}")
            : null;

        if (is_string($id) && ($pending = $this->pendingMediaItem($id, $held)) !== null) {
            return $pending;
        }

        return $this->getMediaSource()?->find($id);
    }
}
