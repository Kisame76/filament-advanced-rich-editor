<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Forms\Components\RichEditor\MentionProvider as BaseMentionProvider;

/**
 * A mention provider whose rows carry a picture and a second line.
 *
 * Extends Filament's rather than replacing it, and keeps everything it declares: the label
 * map is still built and still answered with, because Filament reads it in two places this
 * package does not own - filling in a label a stored document is missing, and drawing its
 * own menu wherever this package's one is switched off. A field configured with these rows
 * therefore degrades to Filament's plain menu instead of breaking in it.
 *
 * The rows travel beside the map rather than instead of it. `AdvancedRichEditor` puts them
 * in front of the menu it hands to the script; everything else keeps reading labels.
 */
class MentionProvider extends BaseMentionProvider
{
    /**
     * @var array<int, MentionRow|array<string, mixed>>|Closure|null
     */
    protected array|Closure|null $rows = null;

    /**
     * The rows this trigger offers, as a list or as a closure that builds one.
     *
     * A closure is what a query belongs in: it is evaluated when the field is rendered
     * rather than when it is configured, so a list that depends on who is logged in is
     * right for whoever is looking at it.
     *
     * @param  array<int, MentionRow|array<string, mixed>>|Closure  $rows
     */
    public function rows(array|Closure $rows): static
    {
        $this->rows = $rows;

        // Filament's own contract, kept honest: `getLabels()` falls back to this map when no
        // `getLabelsUsing()` was given, and its menu draws from it wherever this package's
        // menu is switched off.
        $this->items(fn (): array => array_reduce(
            $this->getRows(),
            static function (array $items, array $row): array {
                $items[$row['id']] = $row['label'];

                return $items;
            },
            [],
        ));

        return $this;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getRows(): array
    {
        return static::normalizeRows($this->rows instanceof Closure ? ($this->rows)() : $this->rows);
    }

    public function hasRows(): bool
    {
        return $this->rows !== null;
    }

    /**
     * What a search answers with.
     *
     * A closure is asked and its answer is passed on as it stands - rows if it built rows,
     * labels if it built labels, which is what lets a provider written against Filament be
     * swapped to this class without being rewritten. Without a closure the rows are
     * searched here, the name and the second line both: somebody typing what a person does
     * does not always know how the name is spelled.
     *
     * @return array<mixed>
     */
    public function getSearchResults(string $search): array
    {
        if ($this->hasSearchResultsUsing()) {
            // Asked once. `parent` would ask again and cast every value to a string on the
            // way back, which turns a row into the word "Array".
            $results = $this->askSearchClosure($search);

            return static::looksLikeRows($results)
                ? static::normalizeRows($results)
                : static::normalizeLabels($results);
        }

        if (! $this->hasRows()) {
            return parent::getSearchResults($search);
        }

        $rows = $this->getRows();

        if (blank($search)) {
            return $rows;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => str_contains(mb_strtolower($row['label']), $needle)
                || str_contains(mb_strtolower($row['hint'] ?? ''), $needle),
        ));
    }

    /**
     * @return array<mixed>
     */
    protected function askSearchClosure(string $search): array
    {
        $callback = (fn (): ?Closure => $this->getSearchResultsUsing)->call($this);

        return $callback === null ? [] : (($callback)($search) ?? []);
    }

    /**
     * The `id => label` map Filament answers with, cast exactly as it casts it.
     *
     * @param  array<mixed>  $results
     * @return array<string, string>
     */
    protected static function normalizeLabels(array $results): array
    {
        $labels = [];

        foreach ($results as $id => $label) {
            $labels[(string) $id] = (string) $label;
        }

        return $labels;
    }

    /**
     * Whether an answer is rows rather than the `id => label` map Filament expects.
     *
     * @param  array<mixed>  $results
     */
    protected static function looksLikeRows(array $results): bool
    {
        foreach ($results as $result) {
            return $result instanceof MentionRow || is_array($result);
        }

        return false;
    }

    /**
     * @param  array<int, MentionRow|array<string, mixed>>|null  $rows
     * @return array<int, array<string, string>>
     */
    protected static function normalizeRows(?array $rows): array
    {
        return array_values(array_map(
            static function (MentionRow|array $row): array {
                if ($row instanceof MentionRow) {
                    return $row->toArray();
                }

                return MentionRow::make($row['id'] ?? '', (string) ($row['label'] ?? $row['name'] ?? ''))
                    ->avatar($row['avatar'] ?? null)
                    ->hint($row['hint'] ?? null)
                    ->toArray();
            },
            $rows ?? [],
        ));
    }
}
