<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The browser's pool when the field stores its attachments as media library media.
 *
 * Two shapes, and the difference is one closure:
 *
 *   - without a pool query, the pool is this record's own media in the configured
 *     collection. That is what the editor already shows in the content, so the browser adds
 *     reuse inside one document and changes nothing about who may see what.
 *   - with a pool query, the closure IS the pool: every row it returns can be browsed and,
 *     because the provider authorises through this same object, referenced. That is the
 *     shared library, and it is opt-in for exactly that reason.
 *
 * Ids are media UUIDs, which is what the image node already carries in `attrs.id`. Picking an
 * existing item therefore stores nothing the upload path would not have stored.
 */
class SpatieMediaSource implements MediaSource
{
    /**
     * @param  Closure(Builder<Media>): mixed|null  $poolQuery  defined pool, overriding the scope
     * @param  Closure(): mixed|null  $getRecordUsing
     * @param  array<int, string>|null  $acceptedMimeTypes  null accepts every image
     * @param  string|null  $thumbnailConversion  the conversion the grid draws, if the model has one
     * @param  string  $scope  'collection', 'model' or 'record' - each narrower than the last
     * @param  Closure(): mixed|null  $getModelUsing  the model class, for a form with no record yet
     */
    final public function __construct(
        protected string $collection = 'default',
        protected ?string $conversion = null,
        protected ?string $visibility = null,
        protected ?Closure $poolQuery = null,
        protected ?Closure $getRecordUsing = null,
        protected ?array $acceptedMimeTypes = null,
        protected ?string $thumbnailConversion = null,
        protected string $scope = 'collection',
        protected ?Closure $getModelUsing = null,
    ) {}

    /**
     * @param  Closure(Builder<Media>): mixed|null  $poolQuery
     * @param  Closure(): mixed|null  $getRecordUsing
     * @param  array<int, string>|null  $acceptedMimeTypes
     */
    public static function make(
        string $collection = 'default',
        ?string $conversion = null,
        ?string $visibility = null,
        ?Closure $poolQuery = null,
        ?Closure $getRecordUsing = null,
        ?array $acceptedMimeTypes = null,
        ?string $thumbnailConversion = null,
        string $scope = 'collection',
        ?Closure $getModelUsing = null,
    ): static {
        return app(static::class, [
            'collection' => $collection,
            'conversion' => $conversion,
            'visibility' => $visibility,
            'poolQuery' => $poolQuery,
            'getRecordUsing' => $getRecordUsing,
            'acceptedMimeTypes' => $acceptedMimeTypes,
            'thumbnailConversion' => $thumbnailConversion,
            'scope' => $scope,
            'getModelUsing' => $getModelUsing,
        ]);
    }

    public function isRecordScoped(): bool
    {
        return ! ($this->poolQuery instanceof Closure) && $this->scope === 'record';
    }

    public function hasFolders(): bool
    {
        // A media collection is flat. Collections are the closest thing it has to folders,
        // and which ones are browsable is the pool query's business, not the grid's.
        return false;
    }

    public function page(string $search = '', ?string $folder = null, int $page = 1, int $perPage = 40, array $filters = []): array
    {
        $query = $this->query();

        if ($query === null) {
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => []];
        }

        // The term is used as a pattern rather than escaped into a literal, which is what
        // Filament's own table search does too. Escaping `%` and `_` only works alongside an
        // `ESCAPE` clause, and that clause cannot be written portably - the string literal
        // meaning a single backslash differs between MySQL and Postgres, and SQLite supplies no
        // default escape character at all, so an escaped term silently matched nothing there.
        // Camera file names are full of underscores, which made this the common case rather
        // than the exotic one. A pattern over-matches at worst; escaping under-matched, and a
        // picture you cannot find is worse than one neighbour too many in the grid.
        if (filled($search)) {
            // `ilike` on Postgres, whose `LIKE` is case-sensitive - unlike MySQL's default
            // collation and unlike SQLite, where the suite runs. Searching a library for
            // "hafen" and being told there is no "Hamburger Hafen" is not a search.
            // Through the model rather than `$query->getConnection()`, which is typed as the
            // interface and does not declare the driver.
            $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $query->where(static function (Builder $query) use ($search, $operator): void {
                $query->where('name', $operator, "%{$search}%")
                    ->orWhere('file_name', $operator, "%{$search}%");
            });
        }

        // Read before the filter narrows anything: the list of kinds the filter offers has to
        // be the kinds the pool holds, not the one kind that is currently chosen.
        $types = $this->types();

        $type = $filters['type'] ?? null;

        if (is_string($type) && filled($type)) {
            $query->where('mime_type', $type);
        }

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));

        // Counted before the page is taken, because the footer counts the library rather than
        // the tiles on screen - and the page numbers are built from it.
        $total = (clone $query)->count();

        // Applied as statements rather than chained: the ordering and paging methods reach
        // the query builder through `__call`, and a chain through them hands back the plain
        // builder - so `get()` would stop knowing it returns media.
        $this->sort($query, $filters['sort'] ?? null);

        $query->skip(($page - 1) * $perPage);
        $query->take($perPage);

        $rows = $query->get();

        $items = [];

        foreach ($rows as $media) {
            $items[] = $this->item($media);
        }

        return [
            'items' => $items,
            'folders' => [],
            'parent' => null,
            'hasMore' => ($page * $perPage) < $total,
            'total' => $total,
            'types' => $types,
        ];
    }

    /**
     * The order the library is read in.
     *
     * Newest first by default: the picture somebody is looking for is far more often the one
     * they uploaded this afternoon than the one from two years ago.
     *
     * @param  Builder<Media>  $query
     */
    protected function sort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('file_name'),
            'largest' => $query->orderByDesc('size'),
            'smallest' => $query->orderBy('size'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * The kinds of picture the pool actually holds, for the filter to offer.
     *
     * Derived rather than declared, the same way the slash menu is: a filter offering WebP in
     * a library that has none is a control that can only ever empty the grid.
     *
     * @return array<int, string>
     */
    protected function types(): array
    {
        $query = $this->query();

        if ($query === null) {
            return [];
        }

        $query->select('mime_type');
        $query->distinct();

        $types = $query->pluck('mime_type')
            ->filter(static fn (mixed $type): bool => is_string($type) && filled($type))
            ->map(static fn (mixed $type): string => (string) $type)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $types;
    }

    /**
     * Whether this reads as a media UUID at all.
     */
    protected static function isUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }

    public function has(mixed $id): bool
    {
        return $this->media($id) !== null;
    }

    public function find(mixed $id): ?array
    {
        $media = $this->media($id);

        return $media ? $this->item($media) : null;
    }

    public function details(mixed $id): ?array
    {
        $media = $this->media($id);

        if (! $media) {
            return null;
        }

        $item = $this->item($media);

        // Written down here rather than in the listing. The measurement is the same either way
        // and the cache already spares the file read; what this adds is that it survives a
        // cache flush - and doing it for the one picture being looked at is one write, where
        // doing it while listing would be one per row.
        if ($item['width'] !== null && $this->dimension($media, 'width') === null) {
            $this->remember($media, ['width' => $item['width'], 'height' => $item['height']]);
        }

        return $item;
    }

    /**
     * Keeps a measurement that has just been made.
     *
     * A picture is measured once and listed many times, so the second viewing - and every row
     * in the list after it - reads the numbers instead of opening the file. Media this package
     * uploaded is stamped at upload time and never reaches here; this fills in what was already
     * in the collection when the browser arrived.
     *
     * Failing to write is not failing to browse: a read-only replica, or a media row somebody
     * deleted between the two queries, must not turn a details panel into an error.
     *
     * @param  array{width: int, height: int}  $dimensions
     */
    protected function remember(Media $media, array $dimensions): void
    {
        try {
            $media->setCustomProperty('width', $dimensions['width']);
            $media->setCustomProperty('height', $dimensions['height']);
            $media->save();
        } catch (Throwable $exception) {
            // Measured is still measured; it will simply be measured again next time.
        }
    }

    /**
     * @return resource|null
     */
    protected function readStream(Media $media): mixed
    {
        try {
            $stream = $media->stream();
        } catch (Throwable $exception) {
            // The file behind the row is gone. A details panel without dimensions is a better
            // answer than an error where somebody just clicked a thumbnail.
            return null;
        }

        return is_resource($stream) ? $stream : null;
    }

    /**
     * The media row behind an id, or null when it is outside the pool.
     *
     * The one method the provider authorises through. It goes through `query()`, so widening
     * the pool and widening what a saved `data-id` may resolve to are the same act.
     */
    public function media(mixed $id): ?Media
    {
        if (! is_string($id) || blank($id)) {
            return null;
        }

        // Media ids are UUIDs, and on Postgres the column is a real `uuid` - so handing it
        // anything else raises a query exception rather than returning no rows. The id comes
        // out of stored content, where a stray value is exactly what has to answer "not in the
        // pool" instead of taking the page down with it.
        if (! static::isUuid($id)) {
            return null;
        }

        $query = $this->query();

        if ($query === null) {
            return null;
        }

        $media = $query->where('uuid', $id)->first();

        return ($media instanceof Media) ? $media : null;
    }

    /**
     * The pool as a query, or null where there is nothing to query - the package works
     * without `spatie/laravel-medialibrary` installed, and a create form has no record yet.
     *
     * @return Builder<Media>|null
     */
    protected function query(): ?Builder
    {
        if (! class_exists(Media::class)) {
            return null;
        }

        $query = Media::query();

        $query->where(static function (Builder $query): void {
            $query->where('mime_type', 'like', 'image/%');
        });

        if ($this->acceptedMimeTypes !== null && $this->acceptedMimeTypes !== []) {
            // Matched as patterns rather than exactly, because `image/*` is a value Filament
            // accepts on `fileAttachmentsAcceptedFileTypes()` and Laravel validates against - so
            // a field configured that way is configured correctly, and an exact match turned its
            // browser permanently and silently empty.
            $query->where(function (Builder $query): void {
                foreach ($this->acceptedMimeTypes as $type) {
                    str_ends_with($type, '/*')
                        ? $query->orWhere('mime_type', 'like', substr($type, 0, -1).'%')
                        : $query->orWhere('mime_type', $type);
                }
            });
        }

        if ($this->poolQuery instanceof Closure) {
            // The closure is the whole definition of a library pool, collection included.
            // Narrowing it here would mean a project could not put its library anywhere but
            // in the collection this field happens to upload to.
            $result = ($this->poolQuery)($query);

            // Loudly, rather than falling back to `$query`: at this point the collection filter
            // below has not been applied, so carrying on would widen the browser - and with it
            // what a stored `data-id` may resolve to - to every image in the media table. A
            // closure that forgets its `return` is a typo, and this is the one place where a
            // typo would quietly hand out other people's pictures.
            if (! $result instanceof Builder) {
                throw new RuntimeException(sprintf(
                    'The closure given to [mediaLibraryQuery()] must return the query builder it was handed; [%s] was returned instead.',
                    get_debug_type($result),
                ));
            }

            return $result;
        }

        $query->where('collection_name', $this->collection);

        // The collection is the library. A picture put in `rich-editor` is a picture for rich
        // editors, whichever record happened to be open when it arrived - so an article and a
        // post that both upload there are drawing from one pool, and neither has to fetch the
        // same picture a second time. Put them in different collections and they see different
        // pictures; that is what a collection is for.
        //
        // Whatever this lists is also what a stored `data-id` may resolve to, so the browser
        // and the lookup cannot drift apart.
        if ($this->scope === 'collection') {
            return $query;
        }

        $record = ($this->getRecordUsing instanceof Closure) ? ($this->getRecordUsing)() : null;
        $morphClass = $this->morphClass($record);

        if ($morphClass === null) {
            return null;
        }

        $query->where('model_type', $morphClass);

        if ($this->scope === 'record') {
            if (! ($record instanceof Model) || ! $record->exists) {
                return null;
            }

            $query->where('model_id', $record->getKey());
        }

        return $query;
    }

    /**
     * Which model the pool belongs to.
     *
     * The record answers where there is one. On a create form there is not, and the pool would
     * be empty at exactly the moment somebody wants to reach for a picture they already have -
     * so the field's model stands in.
     */
    protected function morphClass(mixed $record): ?string
    {
        if ($record instanceof Model) {
            return $record->getMorphClass();
        }

        $model = ($this->getModelUsing instanceof Closure) ? ($this->getModelUsing)() : null;

        if ($model instanceof Model) {
            return $model->getMorphClass();
        }

        if (! is_string($model) || ! class_exists($model)) {
            return null;
        }

        $instance = new $model;

        return ($instance instanceof Model) ? $instance->getMorphClass() : null;
    }

    protected function dimension(Media $media, string $key): ?int
    {
        $value = $media->getCustomProperty($key);

        return (is_int($value) || (is_string($value) && ctype_digit($value))) && ((int) $value) > 0
            ? (int) $value
            : null;
    }

    /**
     * How big a picture is: what it was stamped with, or what the file says.
     *
     * @return array{width: int|null, height: int|null}
     */
    protected function measure(Media $media): array
    {
        $width = $this->dimension($media, 'width');
        $height = $this->dimension($media, 'height');

        if ($width !== null && $height !== null) {
            return ['width' => $width, 'height' => $height];
        }

        $dimensions = MediaDimensions::remembered(
            'arte-media-dimensions:media:'.$media->getKey().':'.$media->getAttributeValue('size'),
            fn (): ?array => $this->fromFile($media),
        );

        return $dimensions ?? ['width' => null, 'height' => null];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    protected function fromFile(Media $media): ?array
    {
        try {
            $path = $media->getPath();
        } catch (Throwable $exception) {
            $path = null;
        }

        // The local path where there is one: `getimagesize()` reads only the header it needs,
        // which beats pulling bytes through the filesystem abstraction.
        if (is_string($path) && is_file($path)) {
            return MediaDimensions::fromPath($path);
        }

        return MediaDimensions::fromStream($this->readStream($media));
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(Media $media): array
    {
        $name = (string) $media->getAttributeValue('name');
        $fileName = (string) $media->getAttributeValue('file_name');

        return [
            'id' => (string) $media->getAttributeValue('uuid'),
            // The URL the content will be saved with, so what is clicked and what is inserted
            // cannot come apart.
            'url' => MediaUrl::for($media, $this->conversion, $this->visibility),
            // A separate, smaller conversion where the model has one: a grid of two hundred
            // full-size photographs is a dialog that takes seconds to open and megabytes to
            // fill, for pictures drawn at 120 pixels wide.
            //
            // Falls back rather than fails. A conversion that has not been generated yet -
            // a fresh upload with a queued job behind it, or a model that never declared the
            // conversion at all - would otherwise be a URL to a file that is not there, and
            // in a grid that reads as a broken library rather than as work in progress.
            'thumbnail' => MediaUrl::forWithFallback(
                $media,
                $this->thumbnailConversion ?? $this->conversion,
                $this->visibility,
            ),
            'name' => filled($name) ? $name : $fileName,
            'fileName' => $fileName,
            'mime' => (string) $media->getAttributeValue('mime_type'),
            'size' => (int) $media->getAttributeValue('size'),
            'folder' => null,
            'createdAt' => $media->getAttributeValue('created_at')?->toDateTimeString(),
            'modifiedAt' => ($media->getAttributeValue('updated_at') ?? $media->getAttributeValue('created_at'))?->toDateTimeString(),
            // Read where it was stamped at upload time, measured off the file where it was
            // not - which is every picture something other than this editor put there. There
            // is nowhere else the numbers can come from, and the answer is remembered per file
            // so a listing pays for a picture once rather than once per listing.
            ...$this->measure($media),
        ];
    }
}
