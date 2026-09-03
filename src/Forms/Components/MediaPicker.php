<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;

/**
 * The grid of pictures inside the image dialog.
 *
 * A form field rather than a bare view, for one reason: what was clicked has to arrive in
 * the action's `$data` alongside the alt text and the upload, and a field is how anything
 * gets there. Its state is the id of the picked item - a media UUID or a storage path,
 * whichever the field behind it stores - which is exactly what the image node will carry.
 *
 * The field holds no list of its own. Pages are fetched from the editor as the grid scrolls,
 * so the pool is read from the component on every request rather than shipped into the
 * browser once and trusted afterwards.
 */
class MediaPicker extends Field
{
    protected string $view = 'filament-advanced-rich-editor::media-picker';

    protected string|Closure|null $editorKey = null;

    protected bool|Closure $hasFolders = false;

    protected bool|Closure $isRecordScoped = true;

    protected bool|Closure|null $isListView = null;

    protected int|Closure|null $pageSize = null;

    protected string|Closure|null $kind = null;

    /**
     * The schema key of the editor this grid asks for its pages.
     *
     * The dialog belongs to the editor, but it is rendered by the Livewire component the form
     * lives in, so the grid needs the key to address the right field - the same channel the
     * mention menu uses.
     */
    public function editorKey(string|Closure|null $key): static
    {
        $this->editorKey = $key;

        return $this;
    }

    public function getEditorKey(): ?string
    {
        $key = $this->evaluate($this->editorKey);

        return filled($key) ? (string) $key : null;
    }

    public function folders(bool|Closure $condition = true): static
    {
        $this->hasFolders = $condition;

        return $this;
    }

    public function hasFolders(): bool
    {
        return (bool) $this->evaluate($this->hasFolders);
    }

    public function recordScoped(bool|Closure $condition = true): static
    {
        $this->isRecordScoped = $condition;

        return $this;
    }

    public function isRecordScoped(): bool
    {
        return (bool) $this->evaluate($this->isRecordScoped);
    }

    /**
     * Everything the grid draws. It is built in the browser, so the strings have to cross
     * over from here - this is the one place that knows the locale.
     *
     * @return array<string, mixed>
     */
    public function getLabels(): array
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.';

        return [
            'search' => (string) __($key.'search'),
            'empty' => (string) __($key.($this->isRecordScoped() ? 'empty_record' : 'empty_library')),
            'emptySearch' => (string) __($key.'empty_search'),
            'up' => (string) __($key.'up'),
            'pending' => (string) __($key.'pending'),
            'upload' => (string) __($key.'upload'),
            'grid' => (string) __($key.'view_grid'),
            'list' => (string) __($key.'view_list'),
            'filter' => (string) __($key.'filter'),
            'allTypes' => (string) __($key.'all_types'),
            'allKinds' => (string) __($key.'all_kinds'),
            // Keyed by family so the tabs read as words rather than as `image` and `video`.
            'kinds' => array_combine(
                MediaKinds::all(),
                array_map(static fn (string $kind): string => (string) __($key.'kinds.'.$kind), MediaKinds::all()),
            ),
            'sort' => (string) __($key.'sort'),
            'previous' => (string) __($key.'previous'),
            'next' => (string) __($key.'next'),
            'nothingSelected' => (string) __($key.'nothing_selected'),
            'copy' => (string) __($key.'copy_url'),
            'copied' => (string) __($key.'copied'),
            'drop' => (string) __($key.'drop'),
            'name' => (string) __($key.'details.name'),
            'size' => (string) __($key.'details.size'),
            'dimensions' => (string) __($key.'details.dimensions'),
            'type' => (string) __($key.'details.type'),
            'modified' => (string) __($key.'details.modified'),
            'items' => (string) __($key.'items'),
            'sorts' => [
                'newest' => (string) __($key.'sorts.newest'),
                'oldest' => (string) __($key.'sorts.oldest'),
                'name' => (string) __($key.'sorts.name'),
                'largest' => (string) __($key.'sorts.largest'),
                'smallest' => (string) __($key.'sorts.smallest'),
            ],
        ];
    }

    /**
     * Which of the two layouts the browser opens in.
     *
     * The grid is the default because picking a picture is done by looking at pictures. The
     * list is what the grid cannot do: names, sizes and dates lined up in columns, which is
     * how you find one file among four hundred rather than recognise one among twelve.
     */
    public function listView(bool|Closure $condition = true): static
    {
        $this->isListView = $condition;

        return $this;
    }

    public function isListView(): bool
    {
        return (bool) ($this->evaluate($this->isListView)
            ?? config('filament-advanced-rich-editor.media_library.list_view')
            ?? false);
    }

    /**
     * How many pictures one request fetches.
     *
     * Passed in rather than inferred, because the grid builds its page numbers out of it. A
     * browser left to guess the size from how many tiles came back reads a short last page as
     * a tiny page size and then divides the whole library by that.
     */
    public function pageSize(int|Closure $size): static
    {
        $this->pageSize = $size;

        return $this;
    }

    /**
     * The tab the browser opens on.
     *
     * Which button was pressed, in other words: the picture button opens on pictures and the
     * sound button on sounds, so nobody arrives at a grid of everything having already said
     * what they were looking for. Blank opens on all of them.
     */
    public function kind(string|Closure|null $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getKind(): string
    {
        $kind = $this->evaluate($this->kind);

        return in_array($kind, MediaKinds::all(), strict: true) ? $kind : '';
    }

    public function getPageSize(): int
    {
        $size = (int) ($this->evaluate($this->pageSize)
            ?? config('filament-advanced-rich-editor.media_library.page_size')
            ?? 40);

        return max(1, $size);
    }
}
