<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;

/**
 * The counter under the field, and the measuring behind it.
 *
 * Counting means walking the document tree rather than the HTML, because the HTML carries
 * markup the reader never sees. The limit is shown rather than enforced - `maxLength()` is
 * what refuses a save.
 */
trait CountsCharacters
{
    protected bool|Closure|null $hasCharacterCount = null;

    protected bool|Closure|null $hasCharacterCountWords = null;

    protected int|Closure|null $characterCountLimit = null;

    protected bool|Closure|null $hasStatistics = null;

    protected bool|Closure|null $enforcesMaxLength = null;

    /**
     * The line under the editor that says how long the text is.
     */
    public function characterCount(bool|Closure $condition = true): static
    {
        $this->hasCharacterCount = $condition;

        return $this;
    }

    public function hasCharacterCount(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacterCount) ?? config('filament-advanced-rich-editor.character_count.enabled') ?? true);
    }

    public function characterCountWords(bool|Closure $condition = true): static
    {
        $this->hasCharacterCountWords = $condition;

        return $this;
    }

    /**
     * A field that refuses a save over three hundred words and then counts characters
     * underneath is a field reporting the wrong number, so a word rule turns the words on.
     * Between the field's own answer and the config file, on the same reasoning the Notion
     * mode sits there: it is more specific than the project's default and less specific than
     * this field saying so itself.
     */
    public function hasCharacterCountWords(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacterCountWords)
            ?? ($this->hasWordRule() ? true : null)
            ?? config('filament-advanced-rich-editor.character_count.words')
            ?? false);
    }

    /**
     * A number to count towards without a rule behind it, for the fields that have a target
     * rather than a maximum. Without one the counter shows `maxLength()`, which is the
     * number Filament already validates - two sources for one limit is how they drift.
     */
    public function characterCountLimit(int|Closure|null $limit): static
    {
        $this->characterCountLimit = $limit;

        return $this;
    }

    public function getCharacterCountLimit(): ?int
    {
        // Each half is normalised before the fallback rather than the pair afterwards, and
        // the difference shows on a field that sets both: a `characterCountLimit(0)` is a
        // field that named no target rather than one that named nought, so it falls through
        // to `maxLength()` the way a null does. Normalising after the fallback would let an
        // empty config value hide the limit the record really is validated against.
        return $this->asLimit($this->evaluate($this->characterCountLimit))
            ?? $this->asLimit($this->getMaxLength());
    }

    /**
     * A number that is actually a limit, or null.
     *
     * Below one is not a limit: a `maxLength(0)` - a config value that arrived empty, a
     * computed limit that came back zero - is a field announcing a limit of nought, and
     * where it is held, one that refuses every character. Nobody writes that down on
     * purpose, and the three answers this trait gives about the limit have to agree about
     * it, so they ask here rather than each deciding.
     */
    protected function asLimit(?int $value): ?int
    {
        return ($value !== null && $value >= 1) ? $value : null;
    }

    /**
     * Whether the editor refuses input past `maxLength()` instead of only letting the save
     * be refused over it.
     *
     * A setting rather than an assumption: a comment box wants to block, an article wants
     * to warn. Nothing about `maxLength()` changes either way - it stays the rule the
     * record is validated against, and this is the question of whether somebody finds out
     * about it while typing or when they press save.
     */
    public function enforceMaxLength(bool|Closure $condition = true): static
    {
        $this->enforcesMaxLength = $condition;

        return $this;
    }

    public function enforcesMaxLength(): bool
    {
        return (bool) ($this->evaluate($this->enforcesMaxLength)
            ?? config('filament-advanced-rich-editor.character_count.enforce')
            ?? false);
    }

    /**
     * What the browser needs to hold the limit, or nothing where there is nothing to hold.
     *
     * The limit is `getMaxLength()` and deliberately not `getCharacterCountLimit()`. The
     * second falls back to the first but may be set on its own, and its own docblock calls
     * it a number with no rule behind it - enforcing that would refuse a keystroke the
     * server would have accepted.
     *
     * @return array{limit: int, enforce: bool}|null
     */
    public function getCharacterCountSettingsForJs(): ?array
    {
        if (! $this->enforcesMaxLength()) {
            return null;
        }

        $limit = $this->asLimit($this->getMaxLength());

        return $limit === null ? null : ['limit' => $limit, 'enforce' => true];
    }

    /**
     * The statistics dialog: how long the document is, in words, characters, blocks and
     * minutes.
     *
     * It lives in the tools menu rather than on the bar, which is where the config file
     * reserved a place for it - the things a field *does* belong together, and a dropdown
     * drops an entry whose tool was switched off instead of raising on it.
     */
    public function statistics(bool|Closure $condition = true): static
    {
        $this->hasStatistics = $condition;

        return $this;
    }

    public function hasStatistics(): bool
    {
        return (bool) ($this->evaluate($this->hasStatistics)
            ?? config('filament-advanced-rich-editor.statistics.enabled')
            ?? true);
    }

    /**
     * How long a piece of content is, measured the way `getLengthValidationRules()` measures
     * it: the text the PHP serialiser produces, escaping and all. The browser mirrors these
     * rules so that the number never changes meaning between the first render and the first
     * keystroke.
     *
     * Two numbers, because the line under the field shows two. What the statistics dialog
     * adds costs a second walk of the document and a regex over the text, and this runs on
     * every render of every field that carries a counter.
     *
     * @return array{characters: int, words: int}
     */
    public function measureCharacterCount(mixed $content): array
    {
        return $this->measure($content, full: false);
    }

    /**
     * Everything the statistics dialog says, measured in one go.
     *
     * The two numbers above are among them and are measured the same way, deliberately: two
     * answers to "how long is this" on one screen that disagree are worse than one answer.
     *
     * @return array{characters: int, charactersWithoutSpaces: int, words: int, paragraphs: int, readingMinutes: int}
     */
    public function measureDocument(mixed $content): array
    {
        return $this->measure($content, full: true);
    }

    /**
     * @return array<string, int>
     */
    protected function measure(mixed $content, bool $full): array
    {
        if (blank($content)) {
            // The same names in the same order as the answer below, written out rather than
            // built from a list: a list would still leave the occupied answer naming its own
            // keys, so it moved the duplication rather than removing it. What holds the two
            // together is the test that compares their shapes, since nothing here can.
            return $full
                ? ['characters' => 0, 'words' => 0, 'charactersWithoutSpaces' => 0, 'paragraphs' => 0, 'readingMinutes' => 0]
                : ['characters' => 0, 'words' => 0];
        }

        $editor = $this->getTipTapEditor()->setContent($content);
        $text = $editor->getText();

        // Words are counted on what was written rather than on what was escaped: nobody
        // means `&amp;` when they count words.
        $words = count(preg_split('/\s+/u', trim(html_entity_decode($text)), flags: PREG_SPLIT_NO_EMPTY) ?: []);

        $counted = ['characters' => Str::length($text), 'words' => $words];

        if (! $full) {
            return $counted;
        }

        $perMinute = (int) (config('filament-advanced-rich-editor.statistics.words_per_minute') ?: 200);

        return [
            ...$counted,
            // Off the same string as the character count, escaping included. Measured on the
            // written text instead it would be a second thing called characters, smaller
            // than the first for a reason nobody reading the dialog could see.
            'charactersWithoutSpaces' => Str::length((string) preg_replace('/\s+/u', '', $text)),
            'paragraphs' => DocumentContent::countBlocks($editor->getDocument()),
            // Rounded up, because a text that takes a minute and a bit takes two. Anything
            // at all is at least a minute; saying "under a minute" is the dialog's job.
            'readingMinutes' => $words === 0 ? 0 : (int) ceil($words / max(1, $perMinute)),
        ];
    }
}
