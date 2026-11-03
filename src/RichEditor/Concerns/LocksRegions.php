<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\LockedRegions;

/**
 * Parts of the document the people writing in it cannot change - the fixed paragraphs of a
 * template, a contract's clauses - while the rest is theirs.
 *
 * Two questions, asked separately. Whether a field has locked parts at all is a fact about the
 * field. Who may lock, unlock and change them is a fact about the person in front of it, so it
 * takes a closure, and nobody may until a project says who.
 */
trait LocksRegions
{
    protected bool|Closure|null $hasLockedRegions = null;

    protected bool|Closure $lockedRegionsEditable = false;

    /**
     * Off unless asked for. Most fields hold something one person writes from top to bottom,
     * and a padlock in their menus is a question nobody asked - and a field without it unwraps
     * any locked part it is handed, which is what switching it off ought to mean.
     */
    public function lockedRegions(bool|Closure $condition = true): static
    {
        $this->hasLockedRegions = $condition;

        return $this;
    }

    public function hasLockedRegions(): bool
    {
        return (bool) ($this->evaluate($this->hasLockedRegions)
            ?? config('filament-advanced-rich-editor.locked_regions') ?? false);
    }

    /**
     * Whether the person in front of the field may lock, unlock and change locked parts - the
     * one who writes the template rather than the ones who fill it in:
     *
     *     ->lockedRegionsEditable(fn (): bool => auth()->user()->can('edit-templates'))
     */
    public function lockedRegionsEditable(bool|Closure $condition = true): static
    {
        $this->lockedRegionsEditable = $condition;

        return $this;
    }

    public function areLockedRegionsEditable(): bool
    {
        return (bool) $this->evaluate($this->lockedRegionsEditable);
    }

    /**
     * What the script reads off the editor element, or null where the feature is off.
     *
     * @return array{editable: bool, label: string}|null
     */
    public function getLockedRegionSettingsForJs(): ?array
    {
        return $this->hasLockedRegions()
            ? [
                'editable' => $this->areLockedRegionsEditable(),
                'label' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.lock.badge'),
            ]
            : null;
    }

    /**
     * The check on the server that the locked parts came through a save untouched, for
     * everybody who may not change them.
     *
     * A rule object rather than a closure, because Laravel skips a closure on an empty value -
     * and emptying the field is the plainest way there is to get rid of a locked part.
     *
     * @return array<int, ValidationRule>
     */
    public function getLockedRegionRules(): array
    {
        if ((! $this->hasLockedRegions()) || $this->areLockedRegionsEditable()) {
            return [];
        }

        $field = $this;

        return [new class($field) implements ValidationRule
        {
            public bool $implicit = true;

            public function __construct(protected mixed $field) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if ($this->field->keepsLockedRegions($value)) {
                    return;
                }

                $fail('filament-advanced-rich-editor::advanced-rich-editor.validation.locked')->translate();
            }
        }];
    }

    /**
     * Whether a state holds exactly the locked parts the field started from.
     */
    public function keepsLockedRegions(mixed $state): bool
    {
        $editor = $this->getTipTapEditor();

        return LockedRegions::kept(
            $this->toDocument($this->getLockedRegionsOrigin()),
            $this->toDocument($state),
            static fn (array $part): string => $editor->setContent(['type' => 'doc', 'content' => [$part]])->getHTML(),
        );
    }

    /**
     * What the document held before anybody touched it here: the record's stored value, as
     * it was read rather than as this request may have changed it - or, on a form that creates
     * one, the field's default, which is where a template comes in.
     */
    protected function getLockedRegionsOrigin(): mixed
    {
        $record = $this->getRecord();

        return $record instanceof Model
            ? data_get($record->getOriginal(), $this->getName())
            : $this->getDefaultState();
    }
}
