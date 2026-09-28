<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Illuminate\Support\Str;

/**
 * Somewhere the link dialog can offer records instead of a typed URL.
 *
 * A source answers with URLs rather than with ids to be resolved later, and that is the one
 * decision the whole thing turns on. It is not a preference: `tiptap-php`'s link mark matches
 * `a[href]` and returns `false` for an empty one, so a link carrying a reference and no `href`
 * is not a link the next hydration recognises. The markup survives, the linking does not, and
 * nothing says so. A resolved URL is therefore always what is written, which means the value
 * of a picked option is simply the URL.
 *
 * The query belongs to the project. What a source needs from this package is a name, a
 * heading and somewhere to be asked - the rest is a closure, because a list of linkable
 * records depends on the models, the routes and who is logged in, and none of those are this
 * package's business. Same reasoning the mention providers are configured on the field rather
 * than in the config file: a closure is not a cacheable config value.
 */
class LinkSource
{
    protected ?string $label = null;

    protected ?Closure $using = null;

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return app(static::class, ['name' => $name]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The heading this source's records are listed under, where a field has more than one.
     */
    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        // The name read as a title, the way Filament names a field nobody labelled. A source
        // called `articles` is headed "Articles" without anybody saying so twice.
        return $this->label ?? (string) Str::of($this->name)->headline();
    }

    /**
     * What this source offers, as `url => label`, given whatever was typed into the search.
     *
     * The search term arrives as it was typed, empty string included - the dialog asks once
     * before anything is typed so the list is not blank on opening.
     */
    public function using(Closure $callback): static
    {
        $this->using = $callback;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(string $search): array
    {
        if ($this->using === null) {
            return [];
        }

        $options = ($this->using)($search);

        return static::clean(is_array($options) ? $options : []);
    }

    /**
     * The answer, reduced to what can actually become a link.
     *
     * A blank URL is dropped rather than passed on, and that is the guard the whole class
     * exists around: it is the one value that reaches the mark and is thrown away by the
     * parser, taking the link with it. A row with no label is dropped too - an option nobody
     * can read is an option nobody can pick.
     *
     * @param  array<mixed>  $options
     * @return array<string, string>
     */
    protected static function clean(array $options): array
    {
        $cleaned = [];

        foreach ($options as $url => $label) {
            $url = trim((string) $url);
            $label = trim(is_scalar($label) ? (string) $label : '');

            if ($url === '' || $label === '') {
                continue;
            }

            $cleaned[$url] = $label;
        }

        return $cleaned;
    }
}
