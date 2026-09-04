<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
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
        // Emptied of blanks: an embed has no mime type, and offering one in the filter
        // beside the tabs would be a filter that calls a video a JSON document.
        $types = array_values(array_unique(array_filter(array_map(
            fn (array $file): string => $this->mimeOf((string) $file['path']),
            $files,
        ))));

        sort($types);

        // The families present, which is what the tabs are drawn from. A tab over an empty
        // family is a door onto a wall, so a folder holding only pictures shows only the
        // picture tab.
        $kinds = array_values(array_intersect(
            MediaKinds::all(),
            array_map(static fn (array $file): string => (string) ($file['kind'] ?? ''), $files),
        ));

        $kind = $filters['kind'] ?? null;

        if (is_string($kind) && filled($kind)) {
            $files = array_values(array_filter(
                $files,
                static fn (array $file): bool => ($file['kind'] ?? null) === $kind,
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

        // One budget for this listing. Made here rather than held on the source, because a
        // source is built fresh per request anyway and a budget that outlived the request
        // would be a budget that is already spent.
        $covers = CoverGenerator::make();

        return [
            'items' => array_map(
                fn (array $file): array => $this->item($file, $covers),
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

    public function delete(mixed $id): bool
    {
        $path = $this->normalise($id);

        if ($path === null || ! $this->isRecordScoped) {
            return false;
        }

        try {
            // The companions first: a file deleted with its sidecar left behind is an orphan
            // that `arte:media-covers --prune` then has to find.
            foreach ([Sidecar::pathFor($path), Sidecar::pathFor($path, 'cover.jpg')] as $companion) {
                if ($this->disk()->exists($companion)) {
                    $this->disk()->delete($companion);
                }
            }

            return $this->disk()->delete($path);
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public function saveEmbed(array $embed): mixed
    {
        $described = Embeds::describes($embed);

        if ($described === null) {
            return null;
        }

        $root = $this->getRoot();
        $path = ltrim($root.'/'.Embeds::fileName($described['provider'], $described['id']), '/');

        try {
            return $this->disk()->put($path, Embeds::encode($described)) ? $path : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * @return array{alt: ?string, title: ?string}
     */
    public function metadata(mixed $id): array
    {
        $path = $this->normalise($id);

        if ($path === null) {
            return ['alt' => null, 'title' => null];
        }

        return static::describedBy(Sidecar::read($this->disk(), $path));
    }

    /**
     * @param  array{alt?: ?string, title?: ?string}  $data
     */
    public function saveMetadata(mixed $id, array $data): bool
    {
        $path = $this->normalise($id);

        if ($path === null) {
            return false;
        }

        return Sidecar::write($this->disk(), $path, static::describes($data));
    }

    /**
     * The two fields out of whatever the sidecar holds - which is also where the cover
     * marker lives, and that is none of the panel's business.
     *
     * @param  array<string, mixed>  $sidecar
     * @return array{alt: ?string, title: ?string}
     */
    protected static function describedBy(array $sidecar): array
    {
        return [
            'alt' => is_string($sidecar['alt'] ?? null) && filled($sidecar['alt']) ? $sidecar['alt'] : null,
            'title' => is_string($sidecar['title'] ?? null) && filled($sidecar['title']) ? $sidecar['title'] : null,
        ];
    }

    /**
     * What a save writes: the keys it was given, with an emptied one spelled as null so the
     * merge removes it.
     *
     * @param  array{alt?: ?string, title?: ?string}  $data
     * @return array<string, string|null>
     */
    protected static function describes(array $data): array
    {
        $written = [];

        foreach (['alt', 'title'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = is_string($data[$key]) ? trim($data[$key]) : null;

            $written[$key] = filled($value) ? $value : null;
        }

        return $written;
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

    protected function contentsOf(string $path): ?string
    {
        try {
            $contents = $this->disk()->get($path);
        } catch (Throwable $exception) {
            return null;
        }

        return is_string($contents) ? $contents : null;
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

        if (str_ends_with($path, '.'.Embeds::SUFFIX)) {
            $embed = Embeds::read($this->contentsOf($path));

            if ($embed === null) {
                return null;
            }

            return $this->item([
                'path' => $path,
                'name' => Embeds::name($embed),
                'kind' => MediaKinds::EMBED,
                'embed' => $embed,
                'size' => 0,
                'timestamp' => $timestamp,
            ]);
        }

        return $this->item([
            'path' => $path,
            'name' => basename($path),
            'kind' => MediaKinds::ofPath($path),
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
        // The companions this package writes beside a medium. A `.json` is refused by the
        // mime check below anyway, but a cover is a JPEG and would list as a picture in its
        // own right - the same film appearing twice, once as itself and once as its first
        // frame. Refused here rather than filtered in `page()`, because `accepts()` is also
        // what `normalise()` asks: something that cannot be listed must not be resolvable
        // through a hand-written id either.
        // The entry an embed is stored as, which is the one companion that IS a library
        // entry rather than a description of one. An accepted-types list is a statement
        // about files and has nothing to say about it.
        if (str_ends_with($path, '.'.Embeds::SUFFIX)) {
            return true;
        }

        if (str_contains(basename($path), '.cover.')) {
            return false;
        }

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

                // An embed is a JSON document, which nothing draws - so it is recognised
                // here, before `accepts()` sees it, and turned into a row of its own. The
                // file IS the entry: its whole content is what the embed is.
                if (str_ends_with($path, '.'.Embeds::SUFFIX)) {
                    $embed = Embeds::read($this->contentsOf($path));

                    if ($embed !== null) {
                        $files[] = [
                            'path' => $path,
                            'name' => Embeds::name($embed),
                            'kind' => MediaKinds::EMBED,
                            'embed' => $embed,
                            'size' => 0,
                            'timestamp' => (int) ($entry->lastModified() ?? 0),
                        ];
                    }

                    continue;
                }

                if (! $this->accepts($path)) {
                    continue;
                }

                $files[] = [
                    'path' => $path,
                    'name' => basename($path),
                    // Read once here rather than off the name again in three places below.
                    'kind' => MediaKinds::ofPath($path),
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
    protected function item(array $file, ?CoverGenerator $covers = null): array
    {
        $path = (string) $file['path'];
        $timestamp = (int) ($file['timestamp'] ?? 0);

        if (($file['kind'] ?? null) === MediaKinds::EMBED) {
            return [
                'id' => $path,
                // The link a person recognises, which is what Copy link hands over - and
                // what pasting it back into this dialog would produce again. What gets
                // *inserted* is built from the provider and the id instead, the same way the
                // embed dialog builds it, so nothing here decides that.
                'url' => Embeds::link($file['embed']),
                // The address a browser will frame, for the panel's own player. Kept apart
                // from `url` because the two are different things: one is for a person, one
                // is for an iframe.
                'frame' => EmbedUrl::src($file['embed']['provider'], $file['embed']['id'], $file['embed']['start']),
                'thumbnail' => $this->embedThumbnail($path, $covers),
                'name' => (string) $file['name'],
                'fileName' => basename($path),
                'mime' => '',
                'kind' => MediaKinds::EMBED,
                'embed' => $file['embed'],
                'size' => 0,
                'folder' => $this->parentOf($path),
                'createdAt' => $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null,
                'modifiedAt' => $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null,
                'width' => null,
                'height' => null,
            ];
        }

        $url = $this->url($path);
        $kind = MediaKinds::ofPath($path);

        return [
            'id' => $path,
            'url' => $url,
            // A picture is its own thumbnail on a disk, which has no conversions to ask for.
            // A film and a sound get a cover made for them the first time they are listed,
            // and a badge until then - see `thumbnail()`.
            'thumbnail' => $this->thumbnail($path, $kind, $covers),
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

    /**
     * The picture this tile draws: the file itself for a picture, a cover beside it for a
     * film or a sound, and null where there is none - which is what tells the grid to draw a
     * badge instead of a broken image.
     */
    protected function thumbnail(string $path, ?string $kind, ?CoverGenerator $covers): ?string
    {
        if ($kind === MediaKinds::IMAGE) {
            return $this->url($path);
        }

        if ($kind === null) {
            return null;
        }

        $cover = Sidecar::pathFor($path, 'cover.jpg');

        try {
            if ($this->disk()->exists($cover)) {
                return $this->url($cover);
            }
        } catch (Throwable $exception) {
            return null;
        }

        // Only while listing, and only while there is budget. A single lookup - the panel
        // asking about one file - must not start a process.
        if (! $covers?->mayGenerate()) {
            return null;
        }

        if (Sidecar::read($this->disk(), $path)[CoverGenerator::ATTEMPTED_KEY] ?? false) {
            return null;
        }

        $bytes = $this->locally($path, static fn (string $local): ?string => $covers->bytes($kind, $local));

        if (! is_string($bytes) || $bytes === '') {
            // Remembered rather than retried. A file with no picture in it, or a binary that
            // is not installed, would otherwise cost the same work on every listing for ever.
            Sidecar::write($this->disk(), $path, [CoverGenerator::ATTEMPTED_KEY => true]);

            return null;
        }

        try {
            $this->disk()->put($cover, $bytes);
        } catch (Throwable $exception) {
            return null;
        }

        return $this->url($cover);
    }

    /**
     * The still for an embed: the same three steps as any other cover - already there,
     * budget, marker - with the bytes coming from the service instead of from the file.
     */
    protected function embedThumbnail(string $path, ?CoverGenerator $covers): ?string
    {
        $cover = Sidecar::pathFor($path, 'cover.jpg');

        try {
            if ($this->disk()->exists($cover)) {
                return $this->url($cover);
            }
        } catch (Throwable $exception) {
            return null;
        }

        if (! $covers?->mayGenerate()) {
            return null;
        }

        if (Sidecar::read($this->disk(), $path)[CoverGenerator::ATTEMPTED_KEY] ?? false) {
            return null;
        }

        $embed = Embeds::read($this->contentsOf($path));

        $bytes = ($embed === null) ? null : $covers->embed($embed);

        if (! is_string($bytes) || $bytes === '') {
            Sidecar::write($this->disk(), $path, [CoverGenerator::ATTEMPTED_KEY => true]);

            return null;
        }

        try {
            $this->disk()->put($cover, $bytes);
        } catch (Throwable $exception) {
            return null;
        }

        return $this->url($cover);
    }

    /**
     * Runs something against a real file on this machine.
     *
     * Both readers need a path rather than a stream - ffmpeg is a process and takes a file
     * name - and on a remote disk there is no such path, so the bytes are brought down to a
     * temporary one and taken away again afterwards.
     *
     * @param  callable(string): (string|null)  $callback
     */
    protected function locally(string $path, callable $callback): ?string
    {
        $local = $this->localPath($path);

        if ($local !== null) {
            return $callback($local);
        }

        $stream = $this->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $temporary = (string) tempnam(sys_get_temp_dir(), 'arte-media');

        try {
            file_put_contents($temporary, $stream);

            return $callback($temporary);
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }

            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
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
