<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tables\Columns;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\RendersRichContent;

/**
 * A stored document in a table cell, as the few words that fit there.
 *
 * A column is not a page, so the default is the excerpt and not the markup: a row is one
 * line high, and a document put in one either overflows it or is cut mid-tag. Plain text
 * also means the column keeps everything `TextColumn` does with plain text - the tooltip,
 * the line clamp, the search, the copy button.
 *
 * `->html()` renders the document instead, for the tables where that is genuinely what is
 * wanted. It is Filament's own switch, and returning markup from here is safe for the same
 * reason the entry is: what comes out of the renderer has been sanitised.
 */
class AdvancedRichColumn extends TextColumn
{
    use RendersRichContent;

    protected int|Closure|null $excerptLength = null;

    /**
     * Whether the column answered the length question itself, however it answered it -
     * "not asked" and "asked for the whole text" are two different answers and null is
     * both of them.
     */
    protected bool $hasExcerptLength = false;

    protected function setUp(): void
    {
        parent::setUp();

        // A default formatter rather than an override of `formatState()`: a project that
        // wants something else says `->formatStateUsing()` and gets it, which is the same
        // sentence it would write on any other column.
        $this->formatStateUsing(static function (AdvancedRichColumn $column, mixed $state): string|HtmlString {
            $renderer = $column->getRichContentRenderer($state);

            if ($column->isHtml()) {
                return new HtmlString($renderer->toHtml());
            }

            $characters = $column->getExcerptLength();

            return $characters === null ? $renderer->toText() : $renderer->toExcerpt($characters);
        });
    }

    /**
     * How much of the document the cell shows.
     *
     * Null is the whole of it, for a column that would rather clamp the text with CSS or
     * cut it with Filament's own `->limit()`. Never calling this takes the configured
     * length, which is the one a meta description would use.
     */
    public function excerptLength(int|Closure|null $characters): static
    {
        $this->excerptLength = $characters;
        $this->hasExcerptLength = true;

        return $this;
    }

    public function getExcerptLength(): ?int
    {
        if (! $this->hasExcerptLength) {
            return (int) config('filament-advanced-rich-editor.excerpt.characters', 160);
        }

        $characters = $this->evaluate($this->excerptLength);

        return $characters === null ? null : (int) $characters;
    }
}
