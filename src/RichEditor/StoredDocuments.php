<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * The documents a project keeps, read one column of one row at a time.
 *
 * A package cannot know which tables of an application hold rich content, so it is told - a
 * model and its columns, or a model alone, which then answers with the columns it registered
 * as rich content itself. What asks is anything that has to look at every document rather
 * than the one on the screen: the media library finding where a file is used, and
 * `arte:diagnose` finding what is wrong in them.
 */
final class StoredDocuments
{
    /**
     * @param  array<class-string<Model>, array<int, string>>  $documents  model => columns; no columns reads the model's own
     */
    public function __construct(private readonly array $documents) {}

    /**
     * What a configuration or an option names, as model => columns: a model with its columns,
     * or a model alone under a numeric key. Anything that is not a model is passed over, and
     * a model named twice is read once with every column it was named with.
     *
     * @param  array<mixed>  $named
     * @return array<class-string<Model>, array<int, string>>
     */
    public static function from(array $named): array
    {
        $documents = [];

        foreach ($named as $key => $value) {
            [$model, $columns] = is_int($key) ? [$value, []] : [$key, (array) $value];

            if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
                continue;
            }

            $documents[$model] = array_values(array_unique([
                ...($documents[$model] ?? []),
                ...self::columnsOf($model, array_values(array_filter($columns, 'is_string'))),
            ]));
        }

        return array_filter($documents);
    }

    /**
     * The places the configuration names: `documents`, and `media_library.documents`, where
     * the list was kept before it served more than the library. A published config file
     * still has it there, and it is read as long as it does.
     *
     * @return array<class-string<Model>, array<int, string>>
     */
    public static function configured(): array
    {
        return self::merge(
            self::from((array) (config('filament-advanced-rich-editor.documents') ?? [])),
            self::from((array) (config('filament-advanced-rich-editor.media_library.documents') ?? [])),
        );
    }

    /**
     * Several lists of places as one, each model once with every column any of them named.
     *
     * @param  array<class-string<Model>, array<int, string>>  ...$lists
     * @return array<class-string<Model>, array<int, string>>
     */
    public static function merge(array ...$lists): array
    {
        $documents = [];

        foreach ($lists as $list) {
            foreach ($list as $model => $columns) {
                $documents[$model] = array_values(array_unique([...($documents[$model] ?? []), ...$columns]));
            }
        }

        return $documents;
    }

    /**
     * Every column of every row, with the document in it.
     *
     * `$narrow` hands back the rows worth reading for a model and its columns, where a caller
     * can ask the database for candidates more cheaply than reading everything; without it,
     * every row is read.
     *
     * @param  (Closure(class-string<Model>, array<int, string>): Builder<Model>)|null  $narrow
     * @return Generator<int, array{0: Model, 1: string, 2: string|array<string, mixed>, 3: bool}>
     */
    public function each(?Closure $narrow = null): Generator
    {
        foreach ($this->documents as $model => $columns) {
            $columns = self::columnsOf($model, $columns);

            if ($columns === []) {
                continue;
            }

            $query = ($narrow === null) ? $model::query() : $narrow($model, $columns);

            // Reported and passed over: a place named in the configuration that cannot be read
            // any more - a table that was dropped, a column that was renamed - must not stop
            // the places after it from being read.
            //
            // Asked first inside a transaction of its own, which is a savepoint where the caller
            // already holds one. Postgres gives up on a whole transaction at its first failed
            // statement, so without it one place that cannot be read took every place after it
            // down as well - they failed too, and were passed over just the same.
            try {
                $found = $query->getModel()->getConnection()->transaction(static fn (): bool => $query->clone()->exists());
            } catch (QueryException $exception) {
                report($exception);

                continue;
            }

            if (! $found) {
                continue;
            }

            try {
                foreach ($query->lazyById(100) as $record) {
                    foreach ($columns as $column) {
                        [$content, $encoded] = self::read($record, $column);

                        if ($content !== null) {
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
     * The document in a column, as a tree or as markup, and whether it was a tree written into
     * a string - which is how it has to go back.
     *
     * @return array{0: string|array<string, mixed>|null, 1: bool}
     */
    public static function read(Model $record, string $column): array
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
    public static function entry(Model $record): string
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
