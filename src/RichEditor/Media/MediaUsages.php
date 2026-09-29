<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentAttachments;

/**
 * Where a file of the library is used, across the documents a project keeps.
 *
 * A package cannot know which tables of an application hold rich content, so it is told - a
 * model and its columns, or a model alone, which then answers with the columns it registered
 * as rich content itself. The field adds its own column, so the common case of one model and
 * one column needs no configuration at all.
 *
 * The database is asked for a pattern, which finds candidates cheaply and over-matches on
 * purpose: `%`, `_` and a shorter id inside a longer one all get through, since escaping a
 * `LIKE` portably is not possible (see `SpatieMediaSource::page()`). Each candidate is then
 * read, and only a node that points at the file itself counts - `DocumentAttachments` decides.
 */
class MediaUsages
{
    /**
     * @param  array<class-string<Model>, array<int, string>>  $documents  model => columns; no columns reads the model's own
     */
    final public function __construct(protected array $documents = []) {}

    /**
     * @param  array<class-string<Model>, array<int, string>>  $documents
     */
    public static function make(array $documents): static
    {
        return app(static::class, ['documents' => $documents]);
    }

    /**
     * How many entries use the file, and what a few of them are called - which is what the
     * question before deleting or replacing it tells a person.
     *
     * @return array{count: int, entries: array<int, string>}
     */
    public function describe(mixed $id, int $named = 3): array
    {
        $seen = [];
        $entries = [];

        foreach ($this->using($id) as [$record]) {
            $key = $record::class.'#'.$record->getKey();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (count($entries) < $named) {
                $entries[] = static::entry($record);
            }
        }

        return ['count' => count($seen), 'entries' => $entries];
    }

    /**
     * Takes the file out of every entry using it, and answers how many were saved.
     */
    public function remove(mixed $id): int
    {
        return $this->rewrite($id, static fn (string|array $content): string|array|null => DocumentAttachments::remove($content, $id));
    }

    /**
     * Points every entry using the file at its replacement, and answers how many were saved.
     *
     * @param  array{src?: string|null, name?: string|null, size?: string|null, width?: int|null, height?: int|null}  $changes
     */
    public function update(mixed $id, array $changes): int
    {
        return $this->rewrite($id, static fn (string|array $content): string|array|null => DocumentAttachments::update($content, $id, $changes));
    }

    /**
     * @param  Closure(string|array<string, mixed>): (string|array<string, mixed>|null)  $change
     */
    protected function rewrite(mixed $id, Closure $change): int
    {
        $pending = [];

        foreach ($this->using($id) as [$record, $column, $content, $encoded]) {
            $changed = $change($content);

            if ($changed === null) {
                continue;
            }

            // A document tree stored as a string goes back as one, and one the model casts
            // goes back as the array it hands out.
            $record->setAttribute($column, ($encoded && is_array($changed)) ? json_encode($changed) : $changed);

            $pending[$record::class.'#'.$record->getKey()] = $record;
        }

        // Saved through the model, so whatever listens to it - a search index, a cache, the
        // `RichContentSaved` event - hears that the document changed. Once per entry, however
        // many of its columns held the file.
        foreach ($pending as $record) {
            $record->save();
        }

        return count($pending);
    }

    /**
     * Every column of every entry that points at the file.
     *
     * @return Generator<int, array{0: Model, 1: string, 2: string|array<string, mixed>, 3: bool}>
     */
    protected function using(mixed $id): Generator
    {
        if (! is_scalar($id) || blank((string) $id)) {
            return;
        }

        $id = (string) $id;

        foreach ($this->documents as $model => $columns) {
            $columns = static::columnsOf($model, $columns);

            if ($columns === []) {
                continue;
            }

            $query = static::candidates($model, $columns, $id);

            // Reported and passed over: a place named in the configuration that cannot be read
            // any more - a table that was dropped, a column that was renamed - must not stop a
            // file from being deleted. The entries that can still be read are cleaned.
            try {
                foreach ($query->lazyById(100) as $record) {
                    foreach ($columns as $column) {
                        [$content, $encoded] = static::read($record, $column);

                        if (($content !== null) && DocumentAttachments::contains($content, $id)) {
                            yield [$record, $column, $content, $encoded];
                        }
                    }
                }
            } catch (QueryException $exception) {
                report($exception);
            }
        }
    }

    /**
     * The rows that may point at the file: the id as it is, as `json_encode()` writes its
     * slashes, and as markup writes its `&` - a path is an id on a disk, and both spellings
     * turn up in stored documents.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $columns
     * @return Builder<Model>
     */
    protected static function candidates(string $model, array $columns, string $id): Builder
    {
        $needles = array_values(array_unique([
            $id,
            str_replace('/', '\/', $id),
            htmlspecialchars($id, ENT_QUOTES),
        ]));

        return $model::query()->where(static function (Builder $query) use ($columns, $needles): void {
            foreach ($columns as $column) {
                foreach ($needles as $needle) {
                    $query->orWhere($column, 'like', "%{$needle}%");
                }
            }
        });
    }

    /**
     * The document in a column, as a tree or as markup, and whether it was a tree written into
     * a string - which is how it has to go back.
     *
     * @return array{0: string|array<string, mixed>|null, 1: bool}
     */
    protected static function read(Model $record, string $column): array
    {
        $value = $record->getAttribute($column);

        if (is_array($value)) {
            return [$value, false];
        }

        if (! is_string($value) || blank($value)) {
            return [null, false];
        }

        if (str_starts_with(ltrim($value), '{')) {
            $decoded = json_decode($value, associative: true);

            if (is_array($decoded) && (($decoded['type'] ?? null) === 'doc')) {
                return [$decoded, true];
            }
        }

        return [$value, false];
    }

    /**
     * The columns to look in: the ones named, or the ones the model registered as rich content
     * where none were.
     *
     * Public because the field asks it as well, to merge what it was told into one list.
     *
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    public static function columnsOf(string $model, array $columns = []): array
    {
        if (! is_subclass_of($model, Model::class)) {
            return [];
        }

        if ($columns === []) {
            $instance = new $model;

            $columns = match (true) {
                method_exists($instance, 'richContentEventAttributes') => $instance->richContentEventAttributes(),
                method_exists($instance, 'getRichContentAttributes') => array_keys($instance->getRichContentAttributes()),
                default => [],
            };
        }

        return array_values(array_unique(array_filter($columns, static fn (mixed $column): bool => is_string($column) && filled($column))));
    }

    /**
     * What a person calls an entry: its model, its key, and its title where it has one.
     */
    protected static function entry(Model $record): string
    {
        $title = null;

        // Read only where the row has the column: a model in strict mode throws for an
        // attribute it was never given.
        foreach (['title', 'name', 'label', 'subject'] as $attribute) {
            $value = array_key_exists($attribute, $record->getAttributes()) ? $record->getAttribute($attribute) : null;

            if (is_string($value) && filled($value)) {
                $title = $value;

                break;
            }
        }

        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.';

        return (string) __($key.(($title === null) ? 'usage_entry_untitled' : 'usage_entry'), [
            'model' => class_basename($record),
            'key' => (string) $record->getKey(),
            'title' => Str::limit((string) $title, 40),
        ]);
    }
}
