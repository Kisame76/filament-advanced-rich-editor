<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts;

/**
 * Where the media browser gets its pictures, and — the same thing — what a picture in the
 * content is allowed to point at.
 *
 * One object answers both questions on purpose. The grid lists from `page()` and the file
 * attachment provider authorises through `has()`, so a hand-edited `data-id` can reach
 * exactly what the browser would have offered: no wider, and no narrower. Two lists would
 * drift, and the drift would be a hole.
 *
 * There are two implementations because there are two ways a Filament rich editor stores an
 * image: as `spatie/laravel-medialibrary` media, keyed by UUID, or as a plain file on a disk,
 * keyed by its path. Both keys are what the image node already carries in `attrs.id`, so
 * picking an existing item stores nothing new.
 */
interface MediaSource
{
    /**
     * One page of the browser.
     *
     * `total` is what the footer counts and what the page numbers are built from, so it is the
     * size of the whole pool rather than of this page.
     *
     * @param  string  $search  matches the file name and the media name; blank lists everything
     * @param  string|null  $folder  a folder path for sources that have folders, ignored by the rest
     * @param  array{type?: string|null, sort?: string|null, kind?: string|null}  $filters
     * @return array{items: array<int, array<string, mixed>>, folders: array<int, array{name: string, path: string}>, parent: string|null, hasMore: bool, total: int, types: array<int, string>, kinds: array<int, string>}
     */
    public function page(string $search = '', ?string $folder = null, int $page = 1, int $perPage = 40, array $filters = []): array;

    /**
     * Whether this id is inside the pool.
     *
     * The authorisation half of the interface. Called for every image the editor saves, so it
     * has to answer without loading the pool.
     */
    public function has(mixed $id): bool;

    /**
     * One item as `page()` would have listed it, or null when it is not in the pool.
     *
     * @return array<string, mixed>|null
     */
    public function find(mixed $id): ?array;

    /**
     * One item with everything the details panel shows, dimensions included.
     *
     * Kept apart from `find()` because the expensive part is the dimensions: a picture that was
     * never stamped with them has to be opened to be measured, and doing that for every tile in
     * a grid would be a file read per thumbnail. The panel shows one picture at a time.
     *
     * @return array<string, mixed>|null
     */
    public function details(mixed $id): ?array;

    /**
     * Whether the browser should draw a folder column at all. Media collections have no
     * folders; a disk directory does.
     */
    public function hasFolders(): bool;

    /**
     * Whether the pool is this record's own attachments, rather than a library shared across
     * records. The browser says so in its empty state, because "no images yet" means something
     * different in each case.
     */
    public function isRecordScoped(): bool;
}
