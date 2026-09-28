<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\MediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdderFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
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
     * @param  LibraryTypes|null  $types  what the pool holds; null is every family at its default
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
        protected ?LibraryTypes $types = null,
        protected ?string $thumbnailConversion = null,
        protected string $scope = 'collection',
        protected ?Closure $getModelUsing = null,
    ) {}

    /**
     * @param  Closure(Builder<Media>): mixed|null  $poolQuery
     * @param  Closure(): mixed|null  $getRecordUsing
     */
    public static function make(
        string $collection = 'default',
        ?string $conversion = null,
        ?string $visibility = null,
        ?Closure $poolQuery = null,
        ?Closure $getRecordUsing = null,
        ?LibraryTypes $types = null,
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
            'types' => $types,
            'thumbnailConversion' => $thumbnailConversion,
            'scope' => $scope,
            'getModelUsing' => $getModelUsing,
        ]);
    }

    protected function library(): LibraryTypes
    {
        return $this->types ??= LibraryTypes::make();
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
            return ['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'kinds' => []];
        }

        // What the field offers, applied to the listing rather than to the pool - see
        // `query()` for why the two are no longer the same statement.
        $this->offered($query);

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

        // The families present, which is what the tabs are drawn from - asked of the pool one
        // family at a time, since a document is told apart by its name and not by a mime type
        // a distinct list could be read off. An embed is asked the same way, having neither.
        $kinds = array_values(array_filter(
            [...$this->library()->kinds(), MediaKinds::EMBED],
            fn (string $kind): bool => (bool) $this->query()
                ?->where(fn (Builder $query) => $this->constrainToKind($query, $kind))
                ->exists(),
        ));

        $kind = $filters['kind'] ?? null;

        if (is_string($kind) && filled($kind)) {
            $query->where(fn (Builder $query) => $this->constrainToKind($query, $kind));
        }

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

        // One budget for this listing, for the reason the disk source gives.
        $covers = CoverGenerator::make();

        $items = [];

        foreach ($rows as $media) {
            $items[] = $this->item($media, $covers);
        }

        return [
            'items' => $items,
            'folders' => [],
            'parent' => null,
            'hasMore' => ($page * $perPage) < $total,
            'total' => $total,
            'types' => $types,
            'kinds' => $kinds,
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

        $this->offered($query);

        // An embed row's file is JSON, and `application/json` in the type filter would be a
        // filter that shows the embeds and calls them documents. Left out by what the row is
        // rather than by its type, now that a document may be JSON too.
        $this->notAnEmbed($query);

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
     * Narrows a listing to the families this field offers, embeds beside them.
     *
     * An embed is neither a family nor an ending - it has no mime type, and a list of types
     * is a statement about files. Narrowing one away with `image/png` would hide the Embeds
     * tab on every field that names its picture formats.
     *
     * @param  Builder<Media>  $query
     */
    protected function offered(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                foreach ($this->library()->kinds() as $kind) {
                    $query->orWhere(fn (Builder $query) => $this->constrainToKind($query, $kind));
                }
            })->orWhere('custom_properties->'.static::EMBED_PROPERTY, true);
        });
    }

    /**
     * Narrows a query to one family: a tab, or the question whether the pool holds any.
     *
     * The embed tab is a property test rather than a mime test, and every other tab has to
     * say so too - an embed row's `application/json` would not match `image/%`, but it would
     * match a document list that names JSON.
     *
     * @param  Builder<Media>  $query
     */
    protected function constrainToKind(Builder $query, string $kind): void
    {
        if ($kind === MediaKinds::EMBED) {
            $query->where('custom_properties->'.static::EMBED_PROPERTY, true);

            return;
        }

        $this->notAnEmbed($query);

        if ($kind !== MediaKinds::FILE) {
            $this->takesAsDrawn($query, $kind);

            return;
        }

        $this->takesAsFile($query);

        // A document is what no drawn family took. The same row is never under two tabs,
        // which is what `kindOf()` says about it too.
        $query->whereNot(function (Builder $query): void {
            foreach (MediaKinds::families() as $family) {
                if ($this->library()->offers($family)) {
                    $query->orWhere(fn (Builder $query) => $this->takesAsDrawn($query, $family));
                }
            }
        });
    }

    /**
     * A picture, a film or a sound, by the family its type is in and then by what the field
     * named: a mime type, a pattern or an ending.
     *
     * @param  Builder<Media>  $query
     */
    protected function takesAsDrawn(Builder $query, string $family): void
    {
        // Everything the family named is refused, so there is nothing left to match. Said
        // out loud, because a group holding no conditions is dropped from the query - and
        // the family would then widen to every row its prefix covers.
        if (($this->library()->patternsOf($family) === []) && ($this->library()->endingsOf($family) === [])) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('mime_type', 'like', $family.'/%')
            ->where(function (Builder $query) use ($family): void {
                // Matched as patterns rather than exactly, because `image/*` is a value
                // Filament accepts on `fileAttachmentsAcceptedFileTypes()` and Laravel
                // validates against - so a field configured that way is configured
                // correctly, and an exact match turned its browser silently empty.
                foreach ($this->library()->patternsOf($family) as $pattern) {
                    str_ends_with($pattern, '/*')
                        ? $query->orWhere('mime_type', 'like', substr($pattern, 0, -1).'%')
                        : $query->orWhere('mime_type', $pattern);
                }

                foreach ($this->library()->endingsOf($family) as $ending) {
                    $query->orWhereRaw('lower(file_name) like ?', ['%.'.$ending]);
                }
            });
    }

    /**
     * A document, by its name: an ending the field named, whatever the type says - or for a
     * star, any ending that is neither drawn nor refused. The same rule `LibraryTypes::kindOf()`
     * applies to a single row, asked of the table.
     *
     * Endings are letters and digits only, so they go into a pattern as they are - there is
     * no `%` or `_` in one to escape.
     *
     * @param  Builder<Media>  $query
     */
    protected function takesAsFile(Builder $query): void
    {
        $library = $this->library();

        if (! $library->offers(MediaKinds::FILE)) {
            // An empty group would be dropped from the query and match every row.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($library): void {
            foreach ($library->fileTypes() as $ending) {
                $query->orWhereRaw('lower(file_name) like ?', ['%.'.$ending]);
            }

            if (! $library->takesAnyFile()) {
                return;
            }

            $query->orWhere(function (Builder $query): void {
                // An ending at all, which is what a star is a statement about.
                $query->where('file_name', 'like', '%.%');

                $query->whereNot(function (Builder $query): void {
                    $endings = [
                        ...array_merge(...array_map(array_keys(...), array_values(MediaKinds::TYPES))),
                        ...LibraryTypes::DENIED,
                    ];

                    foreach ($endings as $ending) {
                        $query->orWhereRaw('lower(file_name) like ?', ['%.'.$ending]);
                    }
                });
            });
        });
    }

    /**
     * The family a row is listed under: an embed by the flag it carries, everything else by
     * its type and its name together.
     *
     * Public because an address depends on it - a conversion belongs to a picture, and the
     * row is the only thing that knows whether this is one.
     */
    public function kindOf(Media $media): ?string
    {
        if (Embeds::describes((array) ($media->getCustomProperty(static::EMBED_DATA_PROPERTY) ?? [])) !== null) {
            return MediaKinds::EMBED;
        }

        return $this->library()->kindOf(
            (string) $media->getAttributeValue('mime_type'),
            (string) $media->getAttributeValue('file_name'),
        );
    }

    /**
     * @param  Builder<Media>  $query
     */
    protected function notAnEmbed(Builder $query): void
    {
        $query->where(static function (Builder $query): void {
            $query->whereNull('custom_properties->'.static::EMBED_PROPERTY)
                ->orWhere('custom_properties->'.static::EMBED_PROPERTY, '!=', true);
        });
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
     * The custom properties this package writes. Prefixed, because a collection is shared
     * with whatever else a project keeps in it and `alt` is a word anybody might use.
     */
    public const ALT_PROPERTY = 'arte_alt';

    public const TITLE_PROPERTY = 'arte_title';

    /** The flag the query tests. A JSON column comparison, so it has to be a scalar. */
    public const EMBED_PROPERTY = 'arte_embed';

    /**
     * The payload, kept beside the flag rather than read out of the file.
     *
     * Spatie needs a file for every row, so the entry IS a file and the file holds the same
     * five fields - but reading it while listing would be a disk read per tile, on a grid
     * that is already doing one query. The row is the fast copy; the file is the durable one.
     */
    public const EMBED_DATA_PROPERTY = 'arte_embed_data';

    public function delete(mixed $id): bool
    {
        $media = $this->media($id);

        if (! $media || ! $this->isRecordScoped()) {
            return false;
        }

        try {
            // Spatie takes the file, the conversions and the row together, `arte-cover`
            // included - it lives where Spatie's own namer would have put it.
            return (bool) $media->delete();
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

        $record = ($this->getRecordUsing instanceof Closure) ? ($this->getRecordUsing)() : null;

        // A media row belongs to a model, so an embed cannot be added on a create form
        // before the record exists.
        if (! ($record instanceof HasMedia) || ! ($record instanceof Model) || ! $record->exists) {
            return null;
        }

        $fileName = Embeds::fileName($described['provider'], $described['id']);

        // The same video twice is one entry. Spatie has no natural key for that, so the file
        // name is the key and the old row goes.
        foreach ($record->getMedia($this->collection) as $existing) {
            if ((string) $existing->getAttributeValue('file_name') === $fileName) {
                $existing->delete();
            }
        }

        // Through a temporary file, because the adder factory takes a path or an
        // `UploadedFile` and nothing else - there is no `createFromString()` on it. The
        // adder moves the file when it succeeds; the `finally` is for when it does not.
        $temporary = (string) tempnam(sys_get_temp_dir(), 'arte-embed');

        file_put_contents($temporary, Embeds::encode($described));

        try {
            $media = FileAdderFactory::create($record, $temporary)
                ->usingFileName($fileName)
                ->usingName(Embeds::name($described))
                ->withCustomProperties([
                    static::EMBED_PROPERTY => true,
                    static::EMBED_DATA_PROPERTY => $described,
                ])
                ->toMediaCollection($this->collection);
        } catch (Throwable $exception) {
            return null;
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return (string) $media->getAttributeValue('uuid');
    }

    /**
     * @return array{alt: ?string, title: ?string}
     */
    public function metadata(mixed $id): array
    {
        $media = $this->media($id);

        if (! $media) {
            return ['alt' => null, 'title' => null];
        }

        return [
            'alt' => static::text($media->getCustomProperty(static::ALT_PROPERTY)),
            'title' => static::text($media->getCustomProperty(static::TITLE_PROPERTY)),
        ];
    }

    /**
     * @param  array{alt?: ?string, title?: ?string}  $data
     */
    public function saveMetadata(mixed $id, array $data): bool
    {
        $media = $this->media($id);

        if (! $media) {
            return false;
        }

        $properties = ['alt' => static::ALT_PROPERTY, 'title' => static::TITLE_PROPERTY];

        foreach ($properties as $key => $property) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = is_string($data[$key]) ? trim($data[$key]) : null;

            filled($value)
                ? $media->setCustomProperty($property, $value)
                : $media->forgetCustomProperty($property);
        }

        try {
            return (bool) $media->save();
        } catch (Throwable $exception) {
            // A read-only replica, or a row deleted between the lookup and the write.
            return false;
        }
    }

    protected static function text(mixed $value): ?string
    {
        return is_string($value) && filled(trim($value)) ? trim($value) : null;
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
     * The one method the provider authorises through. It goes through `query()`, so the
     * scope - the collection, the model, the record, or the pool closure where there is one
     * - is what decides whether a saved `data-id` may resolve. Not the type list: that says
     * what the browser offers today, and a document written last year must not lose its film
     * because somebody has since taken `mkv` off the list.
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
     * The scope alone: the collection, the model, the record, or the closure a shared
     * library was defined with. That is the boundary a stored id is measured against, and
     * it is the one this object authorises through.
     *
     * What the field offers is a second, narrower statement, applied by `offered()` to the
     * listing only. Fusing the two read well until a list changed: a film uploaded while
     * `video/*` was the rule stopped resolving the day the families became the formats a
     * browser plays, and the `<video>` in a published article lost its address with nothing
     * raised. What may be shown is a question about today; what may be resolved is a
     * question about what this field already wrote.
     *
     * @return Builder<Media>|null
     */
    protected function query(): ?Builder
    {
        if (! class_exists(Media::class)) {
            return null;
        }

        $query = Media::query();

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
     * The picture this tile draws.
     *
     * A picture answers with its own conversion, exactly as before. A film or a sound
     * answers with the `arte-cover` conversion - written by this package, because a package
     * cannot register a conversion on somebody else's model, and put where Spatie's own
     * namer would have put it so that the URL builds.
     */
    protected function thumbnail(Media $media, ?string $kind, ?CoverGenerator $covers): ?string
    {
        if ($kind === MediaKinds::IMAGE) {
            return MediaUrl::forWithFallback(
                $media,
                $this->thumbnailConversion ?? $this->conversion,
                $this->visibility,
            );
        }

        // A document has no picture inside it that this package could find. Where the model
        // makes the grid's conversion for documents too - the first page of a pdf, with
        // Imagick installed - that is a better tile than letters; nothing is made for it here.
        if ($kind === MediaKinds::FILE) {
            return (filled($this->thumbnailConversion) && $media->hasGeneratedConversion($this->thumbnailConversion))
                ? MediaUrl::picture($media, $this->thumbnailConversion, $this->visibility)
                : null;
        }

        // Only a film and a sound have a picture inside them to find. Anything else would be
        // copied somewhere local and marked as tried on its first listing, for nothing.
        if (! in_array($kind, [MediaKinds::VIDEO, MediaKinds::AUDIO], strict: true)) {
            return null;
        }

        if ($media->hasGeneratedConversion(CoverGenerator::CONVERSION)) {
            return $this->coverUrl($media);
        }

        if (! $covers?->mayGenerate() || $media->getCustomProperty(CoverGenerator::ATTEMPTED_PROPERTY)) {
            return null;
        }

        $bytes = $this->locally($media, static fn (string $local): ?string => $covers->bytes($kind, $local));

        return $this->keepCover($media, $bytes);
    }

    /**
     * The still for an embed row, which is a media row like any other - so its cover is the
     * same `arte-cover` conversion, only fetched from the service rather than made here.
     *
     * The embed is passed in because `item()` already has it; reading it back out of the
     * custom properties would be deriving the same answer twice.
     *
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    protected function embedThumbnail(Media $media, array $embed, ?CoverGenerator $covers): ?string
    {
        if ($media->hasGeneratedConversion(CoverGenerator::CONVERSION)) {
            return $this->coverUrl($media);
        }

        if (! $covers?->mayGenerate() || $media->getCustomProperty(CoverGenerator::ATTEMPTED_PROPERTY)) {
            return null;
        }

        return $this->keepCover($media, $covers->embed($embed));
    }

    /**
     * Writes a cover that was just made, or remembers that none could be.
     */
    protected function keepCover(Media $media, ?string $bytes): ?string
    {
        if (! is_string($bytes) || $bytes === '') {
            try {
                $media->setCustomProperty(CoverGenerator::ATTEMPTED_PROPERTY, true);
                $media->save();
            } catch (Throwable $exception) {
                // A read-only replica. It will simply be attempted again next time.
            }

            return null;
        }

        return $this->writeCover($media, $bytes) ? $this->coverUrl($media) : null;
    }

    /**
     * Puts the cover exactly where Spatie's default namer would have, and says so on the row.
     *
     * `{id}/conversions/{basename}-arte-cover.jpg`, which is what
     * `BaseUrlGenerator::getPathRelativeToRoot()` builds for a conversion - and
     * `markAsConversionGenerated()` is what makes `hasGeneratedConversion()` true, which is
     * what stops a fallback handing back the original film instead.
     */
    protected function writeCover(Media $media, string $bytes): bool
    {
        try {
            Storage::disk($this->conversionsDisk($media))->put($this->coverPath($media), $bytes);

            $media->markAsConversionGenerated(CoverGenerator::CONVERSION);

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    protected function coverPath(Media $media): string
    {
        return PathGeneratorFactory::create($media)->getPathForConversions($media)
            .pathinfo((string) $media->getAttributeValue('file_name'), PATHINFO_FILENAME)
            .'-'.CoverGenerator::CONVERSION.'.jpg';
    }

    protected function conversionsDisk(Media $media): string
    {
        return (string) ($media->getAttributeValue('conversions_disk') ?: $media->getAttributeValue('disk'));
    }

    protected function coverUrl(Media $media): ?string
    {
        // Not through `getUrl('arte-cover')`: Spatie resolves a conversion by name against
        // the ones the MODEL registered, and this one is registered nowhere - it would throw
        // `InvalidConversion`. The path is built the same way instead.
        try {
            $disk = Storage::disk($this->conversionsDisk($media));

            if ($this->visibility === 'private') {
                return $disk->temporaryUrl(
                    $this->coverPath($media),
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                );
            }

            return $disk->url($this->coverPath($media));
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * Runs something against a real file on this machine, bringing it down from a remote
     * disk first where there is no such file.
     *
     * @param  callable(string): (string|null)  $callback
     */
    protected function locally(Media $media, callable $callback): ?string
    {
        try {
            $path = $media->getPath();
        } catch (Throwable $exception) {
            $path = null;
        }

        if (is_string($path) && is_file($path)) {
            return $callback($path);
        }

        $stream = $this->readStream($media);

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

    /**
     * @return array<string, mixed>
     */
    protected function item(Media $media, ?CoverGenerator $covers = null): array
    {
        $fileName = (string) $media->getAttributeValue('file_name');

        $embed = Embeds::describes((array) ($media->getCustomProperty(static::EMBED_DATA_PROPERTY) ?? []));

        if ($embed !== null) {
            return [
                'id' => (string) $media->getAttributeValue('uuid'),
                // The link for a person; the frame address for the panel's player. See the
                // disk source, which says the same thing at more length.
                'url' => Embeds::link($embed),
                'frame' => EmbedUrl::src($embed['provider'], $embed['id'], $embed['start']),
                'thumbnail' => $this->embedThumbnail($media, $embed, $covers),
                'name' => Embeds::name($embed),
                'fileName' => $fileName,
                'mime' => '',
                'kind' => MediaKinds::EMBED,
                'embed' => $embed,
                'size' => 0,
                'folder' => null,
                'createdAt' => $media->getAttributeValue('created_at')?->toDateTimeString(),
                'modifiedAt' => ($media->getAttributeValue('updated_at') ?? $media->getAttributeValue('created_at'))?->toDateTimeString(),
                'width' => null,
                'height' => null,
            ];
        }

        $name = (string) $media->getAttributeValue('name');
        $kind = $this->kindOf($media);

        return [
            'id' => (string) $media->getAttributeValue('uuid'),
            // The URL the content will be saved with, so what is clicked and what is inserted
            // cannot come apart.
            'url' => MediaUrl::for($media, $this->conversion, $this->visibility, $kind),
            // A separate, smaller conversion where the model has one: a grid of two hundred
            // full-size photographs is a dialog that takes seconds to open and megabytes to
            // fill, for pictures drawn at 120 pixels wide.
            //
            // Falls back rather than fails. A conversion that has not been generated yet -
            // a fresh upload with a queued job behind it, or a model that never declared the
            // conversion at all - would otherwise be a URL to a file that is not there, and
            // in a grid that reads as a broken library rather than as work in progress.
            //
            // Pictures only. The fallback hands back the original where a conversion is
            // missing, and for a video that original is the film itself - which the grid
            // would put in an `<img>` and draw as nothing. No thumbnail is what tells it to
            // draw a sign instead.
            'thumbnail' => $this->thumbnail($media, $kind, $covers),
            'name' => filled($name) ? $name : $fileName,
            'fileName' => $fileName,
            'mime' => (string) $media->getAttributeValue('mime_type'),
            'kind' => $kind,
            // The tile its card will wear, read off the name the file was uploaded under.
            ...($kind === MediaKinds::FILE ? FileTypes::tile($fileName) : []),
            'size' => (int) $media->getAttributeValue('size'),
            'folder' => null,
            'createdAt' => $media->getAttributeValue('created_at')?->toDateTimeString(),
            'modifiedAt' => ($media->getAttributeValue('updated_at') ?? $media->getAttributeValue('created_at'))?->toDateTimeString(),
            // Read where it was stamped at upload time, measured off the file where it was
            // not - which is every picture something other than this editor put there. There
            // is nowhere else the numbers can come from, and the answer is remembered per file
            // so a listing pays for a picture once rather than once per listing.
            //
            // Pictures only, for the reason the disk source gives: reading a video's
            // dimensions means decoding a container this package has no business opening.
            ...($kind === MediaKinds::IMAGE ? $this->measure($media) : ['width' => null, 'height' => null]),
        ];
    }
}
