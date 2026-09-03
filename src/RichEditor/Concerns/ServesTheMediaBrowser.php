<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Livewire\Attributes\Renderless;
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
    /**
     * One page of the browser, fetched by the grid as it scrolls.
     *
     * Exposed to the front end, so it re-reads the pool from the field on every call rather
     * than trusting anything the browser sends beyond a search term and a page number.
     *
     * @return array{items: array<int, array<string, mixed>>, folders: array<int, array{name: string, path: string}>, parent: string|null, hasMore: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaLibraryPageForJs(string $search = '', ?string $folder = null, int $page = 1, ?string $type = null, ?string $sort = null, ?string $kind = null): array
    {
        $source = $this->getMediaSource();

        if (! $source) {
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'kinds' => [], 'perPage' => $this->getMediaLibraryPageSize()];
        }

        // Taken over here rather than as each file lands. Uploading is a request per file, and
        // writing to the component in the middle of one forces a render between the first file
        // and the second - which is enough for Filament to rebuild its schema cache and refuse
        // the next upload, because the guard on `_startUpload` only accepts a path that a
        // cached schema still knows about.
        //
        // Doing it when the browser asks for a page keeps every upload request untouched, and
        // the browser asks as soon as an upload finishes.
        $this->adoptMountedUploads();

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

        if ($pending !== null) {
            return $pending;
        }

        return $this->getMediaSource()?->details($id);
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
     */
    public function adoptMountedUploads(): void
    {
        $mounted = data_get($this->getLivewire(), 'mountedActions');

        if (! is_array($mounted)) {
            return;
        }

        foreach ($mounted as $action) {
            $files = data_get($action, 'data.file');

            if (is_array($files)) {
                $this->registerPendingUploads($files);
            }
        }
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
     * should offer - a file that failed Filament's own validation, or something that is not a
     * picture and would insert a broken image.
     *
     * @return array<string, mixed>|null
     */
    protected function pendingMediaItem(string $id, mixed $attachment): ?array
    {
        if (! ($attachment instanceof TemporaryUploadedFile)) {
            return null;
        }

        // Through the field rather than off the file: this is where Filament re-checks the
        // size and the accepted types, and a rejected upload must not become a tile.
        $file = $this->getUploadedFileAttachment($id);

        if (! $file) {
            return null;
        }

        $mime = (string) $file->getMimeType();
        $kind = MediaKinds::of($mime);

        // Any family the browser draws, not only pictures. What is refused is a file that
        // belongs to none of them, which reaches here when a project widened the accepted
        // types past what this package can insert.
        if ($kind === null) {
            return null;
        }

        $url = $this->getUploadedFileAttachmentTemporaryUrl($file);

        if (blank($url)) {
            return null;
        }

        $name = (string) $file->getClientOriginalName();

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
