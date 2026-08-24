<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * One row of the mention menu.
 *
 * Filament describes a mentionable thing as `id => label`, which is everything its own menu
 * draws and nothing more. A row is the same thing with room for the two fields that make a
 * list of people usable: the picture somebody is recognised by, and the line under the name
 * that tells two people called the same thing apart.
 *
 * Deliberately not a model, a resource or anything that knows how to fetch itself. What a
 * mention menu offers is a question only the project can answer - a query, a directory, a
 * cache - and this is the shape the answer is given in.
 */
class MentionRow
{
    protected ?string $avatar = null;

    protected ?string $hint = null;

    final public function __construct(
        protected string $id,
        protected string $label,
    ) {}

    public static function make(string|int $id, string $label): static
    {
        return new static((string) $id, $label);
    }

    /**
     * The picture beside the name. Any URL the page can load - a disk, a media library
     * conversion, an avatar service.
     */
    public function avatar(?string $url): static
    {
        $this->avatar = $url;

        return $this;
    }

    /**
     * The line under the name: a role, a team, an email address. What it is for is telling
     * two rows apart, so the more two people have in common the more it is worth setting.
     */
    public function hint(?string $text): static
    {
        $this->hint = $text;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * What crosses into the browser.
     *
     * A field that was not set is left out rather than sent as null: the menu draws
     * initials where there is no picture and one line where there is no second one, so an
     * empty key would say the same thing at the cost of carrying it.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'label' => $this->label,
            'avatar' => $this->avatar,
            'hint' => $this->hint,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
