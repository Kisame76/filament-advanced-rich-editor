<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast as BaseRichEditorStateCast;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Contracts\Support\Htmlable;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts\RichEditorStateCast;

/**
 * How the field's value crosses between the editor and the database.
 *
 * The editor speaks TipTap JSON and the column may want HTML or JSON; an empty document is
 * not an empty value until something says so; and `required` has to mean "has text" rather
 * than "is not an empty string", because an empty document is a populated JSON structure.
 */
trait CastsItsContent
{
    protected bool|Closure|null $isNullWhenEmpty = null;

    /**
     * Filament's own cast, swapped for the one that survives an empty string. Swapped rather
     * than appended, because the two directions apply the casts in opposite orders and a
     * guard that has to run first in both cannot be a second entry in the list.
     *
     * @return array<StateCast>
     */
    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn (StateCast $stateCast): StateCast => ($stateCast instanceof BaseRichEditorStateCast)
                ? app(RichEditorStateCast::class, ['richEditor' => $this])
                : $stateCast,
            parent::getDefaultStateCasts(),
        );
    }

    /**
     * Whether an empty document is stored as nothing rather than as `<p></p>`.
     *
     * Off by default, and that is a decision about somebody else's database rather than a
     * preference: a column that is `NOT NULL` without a default takes Filament's `<p></p>`
     * and refuses a null, so turning this on for everyone would break a save that works
     * today. Turned on, a field that renders nothing on the page is also nothing in the
     * record, which is what `@if($post->content)` and a `whereNull` both expect.
     */
    public function nullWhenEmpty(bool|Closure $condition = true): static
    {
        $this->isNullWhenEmpty = $condition;

        return $this;
    }

    public function shouldBeNullWhenEmpty(): bool
    {
        return (bool) ($this->evaluate($this->isNullWhenEmpty)
            ?? config('filament-advanced-rich-editor.null_when_empty', false));
    }

    public function mutateDehydratedState(mixed $state): mixed
    {
        if ($this->shouldBeNullWhenEmpty() && ! $this->hasContent($state)) {
            return null;
        }

        return parent::mutateDehydratedState($state);
    }

    public function mutatesDehydratedState(): bool
    {
        return parent::mutatesDehydratedState() || $this->shouldBeNullWhenEmpty();
    }

    /**
     * Whether a piece of state holds anything a reader would see.
     *
     * Accepts state in every shape one arrives in - the document Livewire carries, the
     * markup a record was hydrated from, or nothing at all - because this is asked on the
     * way into the validator, which is before the casts have finished agreeing on one.
     */
    public function hasContent(mixed $state): bool
    {
        if ($state instanceof Htmlable) {
            $state = $state->toHtml();
        }

        // Also the guard that keeps an empty string away from the parser: `setContent('')`
        // walks a document body that was never built and dies on the null.
        if (blank($state)) {
            return false;
        }

        if (is_string($state)) {
            $state = $this->getTipTapEditor()->setContent($state)->getDocument();
        }

        if (! is_array($state)) {
            return true;
        }

        return ! DocumentContent::isBlank($state);
    }

    /**
     * Filament rejects a document holding exactly one empty paragraph, which is the shape an
     * untouched editor produces and nothing else. A second empty paragraph, a space, a line
     * break, the same document as markup and a field holding nothing at all all get through
     * a `required()` that was meant to stop them. `hasContent()` answers the question once,
     * for every shape.
     */
    public function getRequiredValidationRule(): string|Closure
    {
        if (! $this->isRequired()) {
            return 'nullable';
        }

        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->hasContent($value)) {
                return;
            }

            $fail('validation.required')->translate();
        };
    }
}
