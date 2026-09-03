<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Throwable;

/**
 * The browser's pool when the field stores its attachments as plain files on a disk.
 *
 * Ids are storage paths, which is what Filament already writes into `attrs.id` when no file
 * attachment provider is configured. Picking an existing file therefore stores nothing new,
 * exactly as on the media library side.
 *
 * A disk has real folders, so this source has them too - and they are the reason `has()` is
 * more than an existence check. A path is inside the pool only when it is inside the root
 * directory: without that, a saved `data-id` of `../../.env` would be a file read, and the
 * grid would be the thing that taught someone the shape of the disk.
 */
class DiskMediaSource implements MediaSource
{
    /**
     * @param  array<int, string>|null  $acceptedMimeTypes  null accepts every family this package draws
     */
    final public function __construct(
        protected ?string $disk = null,
        protected string $directory = '',
        protected ?string $visibility = null,
        protected ?array $acceptedMimeTypes = null,
        protected bool $isRecordScoped = false,
    ) {}

    /**
     * @param  array<int, string>|null  $acceptedMimeTypes
     */
    public static function make(
        ?string $disk = null,
        string $directory = '',
        ?string $visibility = null,
        ?array $acceptedMimeTypes = null,
        bool $isRecordScoped = false,
    ): static {
        return app(static::class, [
            'disk' => $disk,
            'directory' => $directory,
            'visibility' => $visibility,
            'acceptedMimeTypes' => $acceptedMimeTypes,
            'isRecordScoped' => $isRecordScoped,
        ]);
    }

    public function hasFolders(): bool
    {
        return true;
    }

    public function isRecordScoped(): bool
    {
        return $this->isRecordScoped;
    }

    public function getRoot(): string
    {
        return trim($this->directory, '/');
    }

    public function page(string $search = '', ?string $folder = null, int $page = 1, int $perPage = 40, array $filters = []): array
    {
        $root = $this->getRoot();
        $current = $this->resolveFolder($folder);

        if ($current === null) {
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'kinds' => []];
        }

        // A search looks through the whole pool rather than through the folder that happens
        // to be open. Someone typing a file name is looking for a file, not for a file in
        // this directory - and a library where search only finds what is already on screen
        // is a library nobody trusts.
        $deep = filled($search);

        [$files, $folders] = $this->read($current, $deep);

        if (filled($search)) {
            $needle = Str::lower($search);
            $files = array_values(array_filter(
                $files,
                static fn (array $item): bool => str_contains(Str::lower($item['name']), $needle),
            ));
            $folders = [];
        }

        // Read before the filter narrows anything: the kinds the filter offers have to be the
        // kinds this folder holds, not the one kind that is currently chosen.
        $types = array_values(array_unique(array_map(
            fn (array $file): string => $this->mimeOf((string) $file['path']),
            $files,
        )));

        sort($types);

        // The families present, which is what the tabs are drawn from. A tab over an empty
        // family is a door onto a wall, so a folder holding only pictures shows only the
        // picture tab.
        $kinds = array_values(array_intersect(
            MediaKinds::all(),
            array_map(
                fn (array $file): string => (string) MediaKinds::ofPath((string) $file['path']),
                $files,
            ),
        ));

        $kind = $filters['kind'] ?? null;

        if (is_string($kind) && filled($kind)) {
            $files = array_values(array_filter(
                $files,
                fn (array $file): bool => MediaKinds::ofPath((string) $file['path']) === $kind,
            ));
        }

        $type = $filters['type'] ?? null;

        if (is_string($type) && filled($type)) {
            $files = array_values(array_filter(
                $files,
                fn (array $file): bool => $this->mimeOf((string) $file['path']) === $type,
            ));
        }

        $this->sort($files, $filters['sort'] ?? null);

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_map(
                $this->item(...),
                array_slice($files, $offset, $perPage),
            ),
            // Folders belong to the first page: they are the navigation, and navigation that
            // appears once the reader has scrolled far enough is not navigation.
            'folders' => ($page === 1) ? $folders : [],
            'parent' => ($current === $root) ? null : $this->parentOf($current),
            'hasMore' => count($files) > ($offset + $perPage),
            'total' => count($files),
            'types' => $types,
            'kinds' => $kinds,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    protected function sort(array &$files, ?string $sort): void
    {
        $by = match ($sort) {
            'oldest' => static fn (array $a, array $b): int => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0),
            'name' => static fn (array $a, array $b): int => strnatcasecmp((string) $a['name'], (string) $b['name']),
            'largest' => static fn (array $a, array $b): int => ($b['size'] ?? 0) <=> ($a['size'] ?? 0),
            'smallest' => static fn (array $a, array $b): int => ($a['size'] ?? 0) <=> ($b['size'] ?? 0),
            default => static fn (array $a, array $b): int => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0),
        };

        usort($files, $by);
    }

    public function details(mixed $id): ?array
    {
        // Already measured by `item()`: there is one way to learn how big a picture is, and it
        // is the same one whether a row or a panel is asking.
        return $this->find($id);
    }

    /**
     * How big a picture is, measured once per file.
     *
     * @return array{width: int, height: int}|null
     */
    protected function measure(string $path, int $size, int $timestamp): ?array
    {
        return MediaDimensions::remembered(
            'arte-media-dimensions:'.md5(($this->disk ?? 'default').':'.$path.':'.$size.':'.$timestamp),
            function () use ($path): ?array {
                // The local path where there is one: `getimagesize()` reads only the header it
                // needs, which beats pulling bytes through the filesystem abstraction.
                $local = $this->localPath($path);

                if ($local !== null) {
                    return MediaDimensions::fromPath($local);
                }

                return MediaDimensions::fromStream($this->readStream($path));
            },
        );
    }

    /**
     * The file on this machine, where the disk has one.
     *
     * Every adapter answers `path()`, but only a local one answers with somewhere a file
     * actually is - S3 hands back a prefixed key. `is_file()` is what tells the two apart, and
     * it is also what catches a row whose file has been deleted.
     */
    protected function localPath(string $path): ?string
    {
        $adapter = $this->adapter();

        if ($adapter === null) {
            return null;
        }

        try {
            $local = $adapter->path($path);
        } catch (Throwable $exception) {
            return null;
        }

        return is_file($local) ? $local : null;
    }

    /**
     * @return resource|null
     */
    protected function readStream(string $path): mixed
    {
        try {
            $stream = $this->disk()->readStream($path);
        } catch (Throwable $exception) {
            return null;
        }

        return is_resource($stream) ? $stream : null;
    }

    protected function mimeOf(string $path): string
    {
        return MediaKinds::mimeOf($path);
    }

    public function has(mixed $id): bool
    {
        $path = $this->normalise($id);

        if ($path === null) {
            return false;
        }

        try {
            return $this->disk()->exists($path);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function find(mixed $id): ?array
    {
        $path = $this->normalise($id);

        if ($path === null || ! $this->has($path)) {
            return null;
        }

        $size = 0;
        $timestamp = 0;

        try {
            $size = (int) $this->disk()->size($path);
            $timestamp = (int) $this->disk()->lastModified($path);
        } catch (Throwable $exception) {
            // Metadata is decoration here; the file is known to exist.
        }

        return $this->item([
            'path' => $path,
            'name' => basename($path),
            'size' => $size,
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * A path that is inside the pool, or null.
     *
     * Everything that decides whether a stored id may be resolved happens here: the path is
     * flattened, `..` segments are refused outright rather than collapsed, the root is
     * enforced, and the extension has to be one the pool accepts. An id from a hand-edited
     * document reaches this method before anything is read.
     */
    protected function normalise(mixed $id): ?string
    {
        if (! is_string($id) || blank($id)) {
            return null;
        }

        // Refused rather than resolved: a legitimate path from this browser never contains a
        // traversal segment, so one that does is not a path that needs repairing.
        if (str_contains($id, '..') || str_contains($id, "\0")) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $id), '/');

        if (blank($path)) {
            return null;
        }

        $root = $this->getRoot();

        if (filled($root) && ! str_starts_with($path.'/', $root.'/')) {
            return null;
        }

        return $this->accepts($path) ? $path : null;
    }

    /**
     * A folder path that is inside the pool, or null. The root itself is always allowed.
     */
    protected function resolveFolder(?string $folder): ?string
    {
        $root = $this->getRoot();

        if (blank($folder)) {
            return $root;
        }

        if (str_contains($folder, '..') || str_contains($folder, "\0")) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $folder), '/');

        if (filled($root) && ! str_starts_with($path.'/', $root.'/')) {
            return null;
        }

        return $path;
    }

    protected function parentOf(string $path): ?string
    {
        $parent = trim(dirname($path), '/');

        return ($parent === '.' || $parent === '') ? '' : $parent;
    }

    protected function accepts(string $path): bool
    {
        // Only what a browser can draw or play, whatever else is lying in the directory. A
        // grid is a grid of things that can be inserted, and the ones that cannot are not
        // hidden out of tidiness - offering them would insert a player nobody can start.
        $mime = MediaKinds::mimeOf($path);

        if ($mime === '') {
            return false;
        }

        if ($this->acceptedMimeTypes === null || $this->acceptedMimeTypes === []) {
            return true;
        }

        foreach ($this->acceptedMimeTypes as $accepted) {
            // `image/*` is as valid a value on Filament's own setter as `image/png` is, so both
            // spellings have to mean what they say here.
            $matches = str_ends_with($accepted, '/*')
                ? str_starts_with($mime, substr($accepted, 0, -1))
                : $mime === $accepted;

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * One directory listing, as files and folders.
     *
     * Flysystem is asked directly because its listing carries the size and the modification
     * time of every entry in the one call, where `files()` plus `size()` per item is a
     * request per picture.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{name: string, path: string}>}
     */
    protected function read(string $directory, bool $deep): array
    {
        $files = [];
        $folders = [];

        $adapter = $this->adapter();

        if ($adapter === null) {
            return [$files, $folders];
        }

        try {
            $driver = $adapter->getDriver();
        } catch (Throwable $exception) {
            $driver = null;
        }

        if (! ($driver instanceof FilesystemOperator)) {
            return [$files, $folders];
        }

        try {
            $listing = $driver->listContents($directory, $deep);

            foreach ($listing as $entry) {
                if ($entry instanceof DirectoryAttributes) {
                    $folders[] = [
                        'name' => basename($entry->path()),
                        'path' => trim($entry->path(), '/'),
                    ];

                    continue;
                }

                if (! ($entry instanceof FileAttributes)) {
                    continue;
                }

                $path = trim($entry->path(), '/');

                if (! $this->accepts($path)) {
                    continue;
                }

                $files[] = [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => (int) ($entry->fileSize() ?? 0),
                    'timestamp' => (int) ($entry->lastModified() ?? 0),
                ];
            }
        } catch (Throwable $exception) {
            // A directory that does not exist yet is an empty library, not an error page.
            return [[], []];
        }

        usort($folders, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return [$files, $folders];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    protected function item(array $file): array
    {
        $path = (string) $file['path'];
        $timestamp = (int) ($file['timestamp'] ?? 0);
        $url = $this->url($path);
        $kind = MediaKinds::ofPath($path);

        return [
            'id' => $path,
            'url' => $url,
            // A picture is its own thumbnail on a disk, which has no conversions to ask for.
            // A video and a sound have no thumbnail at all, and saying so is what lets the
            // grid draw a sign instead of a broken picture.
            'thumbnail' => $kind === MediaKinds::IMAGE ? $url : null,
            'name' => (string) $file['name'],
            'fileName' => (string) $file['name'],
            'mime' => $this->mimeOf($path),
            'kind' => $kind,
            'size' => (int) ($file['size'] ?? 0),
            'folder' => $this->parentOf($path),
            'createdAt' => $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null,
            'modifiedAt' => $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null,
            // Measured off the file, because there is nowhere else for it to come from: a disk
            // has no row to stamp. Remembered per file, so a listing pays for a picture once
            // rather than once per listing.
            //
            // Pictures only. A video has dimensions too, and reading them means decoding a
            // container this package has no business opening - so a player is sized by the
            // browser that plays it.
            ...($kind === MediaKinds::IMAGE
                ? ($this->measure($path, (int) ($file['size'] ?? 0), $timestamp) ?? ['width' => null, 'height' => null])
                : ['width' => null, 'height' => null]),
        ];
    }

    protected function url(string $path): ?string
    {
        $adapter = $this->adapter();

        if ($adapter === null) {
            return null;
        }

        if ($this->visibility === 'private') {
            try {
                return $adapter->temporaryUrl(
                    $path,
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                );
            } catch (Throwable $exception) {
                // This driver does not support creating temporary URLs.
            }
        }

        try {
            return $adapter->url($path);
        } catch (Throwable $exception) {
            return null;
        }
    }

    protected function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * The disk as the concrete adapter, or null.
     *
     * Listing a directory and building a URL are not on the filesystem contract - a project
     * can register a driver that offers neither. Asking for the adapter rather than assuming
     * one turns "this disk cannot be browsed" into an empty grid instead of a fatal.
     */
    protected function adapter(): ?FilesystemAdapter
    {
        $disk = $this->disk();

        return ($disk instanceof FilesystemAdapter) ? $disk : null;
    }
}
