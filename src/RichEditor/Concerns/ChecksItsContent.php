<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;

/**
 * What a field may say about its content beyond how long it is.
 *
 * Filament measures `maxLength()` and `minLength()` on the serialised text, which covers
 * characters and nothing else. Two kinds of statement are left over, and both are ordinary
 * things to ask of a document: how many words it holds, and what it has to have in it - a
 * picture, a heading, a link.
 *
 * The measuring was already here. Words are counted by `measureCharacterCount()`, the same
 * call the line under the field reports, so the number that refuses a save is the number the
 * author was watching while they wrote. The tree is walked by `DocumentContent`, which
 * already answers "is there anything in here". What was missing is the rules and their
 * messages, and that is all this trait is.
 *
 * Every rule stands down on an empty field. A minimum of fifty words is a statement about
 * content that is there; making an optional field mandatory as a side effect of one is
 * `required()`'s job and not this one's.
 *
 * Empty is `hasContent()` and deliberately not `blank()`, which is what Filament's own length
 * rules ask and what these asked first. The two differ on exactly the state that matters: an
 * untouched editor hands over a document holding one empty paragraph, which is present, not
 * blank, and holds no words at all. Asked with `blank()`, a `minWords(50)` on an optional
 * field refuses every save nobody typed into - and a required one complains twice about the
 * same empty field. Laravel skips a closure rule on a null or an empty string by itself, so
 * that half was never the question; this is.
 */
trait ChecksItsContent
{
    protected int|Closure|null $minWords = null;

    protected int|Closure|null $maxWords = null;

    /**
     * @var array<int, string>|string|Closure|null
     */
    protected array|string|Closure|null $requiredContent = null;

    /**
     * The fewest words a save is accepted with.
     *
     * No config key, deliberately: a word count is a statement about this field the way
     * `maxLength()` is, and a project-wide "every editor needs fifty words" is not a thing
     * anybody means.
     */
    public function minWords(int|Closure|null $words): static
    {
        $this->minWords = $words;

        return $this;
    }

    public function getMinWords(): ?int
    {
        return $this->asWordCount($this->evaluate($this->minWords));
    }

    /**
     * The most words a save is accepted with.
     */
    public function maxWords(int|Closure|null $words): static
    {
        $this->maxWords = $words;

        return $this;
    }

    public function getMaxWords(): ?int
    {
        return $this->asWordCount($this->evaluate($this->maxWords));
    }

    /**
     * Whether either half of the word count was named. What the counter reads to decide
     * whether it shows words - see `CountsCharacters::hasCharacterCountWords()`.
     */
    public function hasWordRule(): bool
    {
        return $this->getMinWords() !== null || $this->getMaxWords() !== null;
    }

    /**
     * What the document has to hold for a save to be accepted, by node or mark name.
     *
     * Names rather than a method per kind, because the list of kinds is not this package's
     * to close: it ships a callout, an embed and a task item of its own, and a project's own
     * node has to be askable for without an entry anywhere. Marks are searched alongside
     * nodes, so `'link'` works - somebody asking for a link does not care that TipTap stores
     * one as a mark on text rather than as a node.
     *
     * @param  array<int, string>|string|Closure|null  $content
     */
    public function mustContain(array|string|Closure|null $content): static
    {
        $this->requiredContent = $content;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredContent(): array
    {
        $content = $this->evaluate($this->requiredContent) ?? [];

        return array_values(array_filter(
            array_map(trim(...), is_array($content) ? $content : [$content]),
            static fn (string $type): bool => $type !== '',
        ));
    }

    /**
     * @return array<mixed>
     */
    public function getValidationRules(): array
    {
        return [
            ...parent::getValidationRules(),
            ...$this->getContentValidationRules(),
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function getContentValidationRules(): array
    {
        $rules = [];

        if (($max = $this->getMaxWords()) !== null) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($max): void {
                if ((! $this->hasContent($value)) || $this->measureCharacterCount($value)['words'] <= $max) {
                    return;
                }

                $fail('filament-advanced-rich-editor::advanced-rich-editor.validation.max_words')
                    ->translate(['max' => $max]);
            };
        }

        if (($min = $this->getMinWords()) !== null) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($min): void {
                if ((! $this->hasContent($value)) || $this->measureCharacterCount($value)['words'] >= $min) {
                    return;
                }

                $fail('filament-advanced-rich-editor::advanced-rich-editor.validation.min_words')
                    ->translate(['min' => $min]);
            };
        }

        foreach ($this->getRequiredContent() as $type) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($type): void {
                if (! $this->hasContent($value)) {
                    return;
                }

                $document = $this->toDocument($value);

                if ($document === null || DocumentContent::contains($document, $type)) {
                    return;
                }

                $fail('filament-advanced-rich-editor::advanced-rich-editor.validation.must_contain')
                    ->translate(['content' => $this->nameOfContent($type)]);
            };
        }

        return $rules;
    }

    /**
     * What a node or mark is called in a sentence somebody reads.
     *
     * The type name is the fallback rather than an error: a project's own node has no entry
     * here, and "must contain productCard" is a worse message than the translated one and a
     * far better one than none.
     */
    protected function nameOfContent(string $type): string
    {
        $key = "filament-advanced-rich-editor::advanced-rich-editor.validation.content.{$type}";
        $name = __($key);

        return is_string($name) && $name !== $key ? $name : $type;
    }

    /**
     * A number that is actually a count of words, or null.
     *
     * Zero is not a rule. A `maxWords(0)` - a computed limit that came back empty - is a
     * field asking for a document with no words in it, which nobody writes down on purpose,
     * and a `minWords(0)` is a rule every document already satisfies.
     */
    protected function asWordCount(?int $value): ?int
    {
        return ($value !== null && $value >= 1) ? $value : null;
    }
}
