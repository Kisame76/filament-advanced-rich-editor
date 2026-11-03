<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Snippets;
use Livewire\Attributes\Renderless;

/**
 * Ready-made content to insert where the caret is - see `Snippets`.
 *
 * There is nothing to switch on: a field offers snippets once it has some, and a field with
 * none shows no button and no menu entry. Where they come from is the project's - a list in
 * the config, or a closure that reads them from wherever they are kept, per person if need be.
 */
trait InsertsSnippets
{
    /**
     * @var array<mixed>|Closure|null
     */
    protected array|Closure|null $snippets = null;

    /**
     * The list as it was read, once per field: the toolbar, the slash menu, the plugin and
     * every snippet picked ask for it, and the closure behind it is usually a query. A field
     * lives for one request, so a list changed in the table is read on the next one.
     *
     * @var array<string, array{label: string, content: string|array<mixed>}>|null
     */
    protected ?array $resolvedSnippets = null;

    /**
     * The snippets this field offers, replacing the `snippets` config rather than adding to
     * it: a field that names its own list means that list.
     *
     *     ->snippets(fn (): array => Snippet::query()->get()->mapWithKeys(
     *         fn (Snippet $snippet): array => ["s{$snippet->id}" => ['label' => $snippet->title, 'content' => $snippet->body]],
     *     )->all())
     *
     * @param  array<mixed>|Closure  $snippets
     */
    public function snippets(array|Closure $snippets): static
    {
        $this->snippets = $snippets;
        $this->resolvedSnippets = null;

        return $this;
    }

    /**
     * @return array<string, array{label: string, content: string|array<mixed>}>
     */
    public function getSnippets(): array
    {
        if ($this->resolvedSnippets !== null) {
            return $this->resolvedSnippets;
        }

        $snippets = $this->evaluate($this->snippets) ?? config('filament-advanced-rich-editor.snippets');

        return $this->resolvedSnippets = is_array($snippets) ? Snippets::normalize($snippets) : [];
    }

    public function hasSnippets(): bool
    {
        return $this->getSnippets() !== [];
    }

    /**
     * One snippet's content, asked for when it is picked rather than written into the page
     * with every button that offers it - a template can be long, and a list read from a table
     * can be longer.
     *
     * A key arrives and content goes back, and only for a key on this field's list: a request
     * naming anything else is answered with nothing. `Renderless` because nothing on the
     * server changes; the browser puts the content in itself, through the editor's own schema.
     *
     * @return string|array<mixed>|null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSnippetForJs(mixed $key): string|array|null
    {
        return is_string($key) ? ($this->getSnippets()[$key]['content'] ?? null) : null;
    }
}
