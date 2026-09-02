<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\SourceCodeAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DateTimeFormats;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DragHandlePlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FindReplacePlugin;
use Livewire\Attributes\Renderless;

/**
 * The tools that help while writing without changing what is written: emoji, find
 * and replace, the drag grip, the source view, the shortcut list and fullscreen.
 *
 * Switching any of them off is the same act every time - the extension is not loaded, so the
 * button and its keyboard shortcut go together, and nothing already written changes.
 */
trait OffersWritingAids
{
    protected bool|Closure|null $hasEmoji = null;

    protected bool|Closure|null $hasFind = null;

    protected bool|Closure|null $hasDragHandle = null;

    protected bool|Closure|null $hasDragHandleInsert = null;

    protected bool|Closure|null $hasSourceCode = null;

    protected bool|Closure|null $hasHelp = null;

    protected string|Htmlable|Closure|null $helpMore = null;

    protected string|Closure|null $helpMoreLabel = null;

    protected bool|Closure|null $hasFullscreen = null;

    protected bool|Closure|null $hasDateTime = null;

    /**
     * @var array<string, ?string>|Closure|null
     */
    protected array|Closure|null $dateTimeFormats = null;

    /**
     * The emoji picker. Nothing about it is stored as markup - an emoji is a character -
     * so switching it off later leaves every emoji already written where it is.
     */
    public function emoji(bool|Closure $condition = true): static
    {
        $this->hasEmoji = $condition;

        return $this;
    }

    public function hasEmoji(): bool
    {
        return (bool) ($this->evaluate($this->hasEmoji) ?? config('filament-advanced-rich-editor.emoji') ?? true);
    }

    /**
     * Finding and replacing inside this field. Nothing about it is stored - a search marks
     * no document and a replacement is ordinary text - so switching it off later changes
     * nothing that was ever written with it.
     */
    public function find(bool|Closure $condition = true): static
    {
        $this->hasFind = $condition;

        return $this;
    }

    public function hasFind(): bool
    {
        return (bool) ($this->evaluate($this->hasFind) ?? config('filament-advanced-rich-editor.find') ?? true);
    }

    /**
     * The strings and icons the bar draws, for the view to hand to the script. Null while
     * searching is switched off, which is also when the extension that would read them was
     * never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getFindSettingsForJs(): ?array
    {
        return $this->hasFind() ? FindReplacePlugin::getLabels() : null;
    }

    /**
     * The grip in the margin that a block can be dragged by, and the plus beside it.
     *
     * Only the top level of the document gets one, and the grip on a list therefore takes
     * the list rather than the item under the mouse: a list item is a node that may only
     * live inside a list, so a drag of one is a drag that refuses more often than it works.
     *
     * Nothing about it is stored - rearranging a document changes the order of what is in it
     * and leaves no trace of how - so switching it off later changes nothing that was
     * written with it.
     */
    public function dragHandle(bool|Closure $condition = true): static
    {
        $this->hasDragHandle = $condition;

        return $this;
    }

    public function hasDragHandle(): bool
    {
        return (bool) ($this->evaluate($this->hasDragHandle)
            ?? $this->notionDefaultFor('dragHandle')
            ?? config('filament-advanced-rich-editor.drag_handle.enabled')
            ?? true);
    }

    /**
     * The plus that starts a new block under the one being hovered.
     *
     * What it inserts is not a paragraph: the caret lands in an empty block and the slash
     * menu opens on top of it, so the button offers everything that could go there. Where
     * the slash menu is switched off it makes the empty block and stops, which is the whole
     * of what it can honestly do without a list to offer.
     */
    public function dragHandleInsert(bool|Closure $condition = true): static
    {
        $this->hasDragHandleInsert = $condition;

        return $this;
    }

    public function hasDragHandleInsert(): bool
    {
        return (bool) ($this->evaluate($this->hasDragHandleInsert)
            ?? $this->notionDefaultFor('dragHandleInsert')
            ?? config('filament-advanced-rich-editor.drag_handle.insert')
            ?? true);
    }

    /**
     * The icons and labels the handle draws, for the view to hand to the script. Null while
     * the grip is switched off, which is also when the extension that would read them was
     * never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getDragHandleSettingsForJs(): ?array
    {
        return $this->hasDragHandle() ? DragHandlePlugin::getSettings($this->hasDragHandleInsert()) : null;
    }

    /**
     * The button that opens the shortcut list, and whatever else this field has to say.
     */
    public function help(bool|Closure $condition = true): static
    {
        $this->hasHelp = $condition;

        return $this;
    }

    public function hasHelp(): bool
    {
        return (bool) ($this->evaluate($this->hasHelp) ?? config('filament-advanced-rich-editor.help') ?? true);
    }

    /**
     * Something to tell the people writing in this field - a house rule, a reminder, a link
     * to the style guide. It becomes a second tab in the help dialog, and only then: with
     * nothing to say there is nothing to tab between.
     *
     * A plain string is escaped and its line breaks kept. Pass an `Htmlable` to write
     * markup, which is trusted, so build it in code rather than out of anything a user typed.
     */
    public function helpMore(string|Htmlable|Closure|null $content, string|Closure|null $label = null): static
    {
        $this->helpMore = $content;
        $this->helpMoreLabel = $label;

        return $this;
    }

    public function getHelpMore(): ?Htmlable
    {
        $content = $this->evaluate($this->helpMore) ?? config('filament-advanced-rich-editor.help_more');

        if (blank($content)) {
            return null;
        }

        return $content instanceof Htmlable ? $content : new HtmlString(nl2br(e($content)));
    }

    public function getHelpMoreLabel(): string
    {
        return $this->evaluate($this->helpMoreLabel)
            ?? __('filament-advanced-rich-editor::advanced-rich-editor.help.more');
    }

    /**
     * The button that opens the document as HTML.
     */
    public function sourceCode(bool|Closure $condition = true): static
    {
        $this->hasSourceCode = $condition;

        return $this;
    }

    public function hasSourceCode(): bool
    {
        return (bool) ($this->evaluate($this->hasSourceCode) ?? config('filament-advanced-rich-editor.source_code', false));
    }

    /**
     * The markup as this field's own schema writes it, which is the form it is stored in.
     * What the source view opens with, and what it hands back.
     */
    public function normaliseSourceHtml(?string $html): string
    {
        return SourceCodeAction::normalise($this, $html);
    }

    /**
     * The button that expands the editor to fill the window.
     */
    public function fullscreen(bool|Closure $condition = true): static
    {
        $this->hasFullscreen = $condition;

        return $this;
    }

    public function hasFullscreen(): bool
    {
        return (bool) ($this->evaluate($this->hasFullscreen)
            ?? config('filament-advanced-rich-editor.fullscreen', true));
    }

    /**
     * Writing today's date, or the time, into the document. Nothing about it is stored as
     * markup - a date is text - so switching it off later leaves every date already
     * written where it is.
     */
    public function dateTime(bool|Closure $condition = true): static
    {
        $this->hasDateTime = $condition;

        return $this;
    }

    public function hasDateTime(): bool
    {
        return (bool) ($this->evaluate($this->hasDateTime)
            ?? config('filament-advanced-rich-editor.date_time.enabled') ?? false);
    }

    /**
     * The formats this field offers, as key => format. One tool is registered per entry.
     *
     * @param  array<string, ?string>|Closure|null  $formats
     */
    public function dateTimeFormats(array|Closure|null $formats): static
    {
        $this->dateTimeFormats = $formats;

        return $this;
    }

    /**
     * The same list with every format actually settled, which is the form both halves of
     * the feature use: the tools are built from it and a click is looked up in it.
     *
     * A `null` format means "whatever this schema says a date looks like", and that is a
     * question Filament already answers three times over. Its answer is inherited rather
     * than replaced, which is the same precedence Filament's own columns and entries
     * follow - and worth knowing when the two disagree: `DateTimePicker` does not read
     * these settings, so a project that sets them reaches this field and not its pickers.
     *
     * @return array<string, string>
     */
    public function getDateTimeFormats(): array
    {
        $configured = DateTimeFormats::map(
            $this->evaluate($this->dateTimeFormats)
                ?? config('filament-advanced-rich-editor.date_time.formats')
                ?? [],
        );

        $formats = [];

        foreach ($configured as $key => $format) {
            $format ??= $this->getInheritedDateTimeFormat($key);

            if (blank($format)) {
                continue;
            }

            $formats[$key] = $format;
        }

        return $formats;
    }

    /**
     * What the surrounding schema answers for one of the three keys it knows about.
     *
     * Asked of the container only where there is one. `Component::$container` is a typed
     * property with no default, so reading it on a field that was never put in a schema is
     * a fatal error rather than a null - and every other question this field answers can be
     * asked of it standing on its own. A field with no schema simply inherits nothing, and
     * the key is then dropped like any other that names no format.
     */
    protected function getInheritedDateTimeFormat(string $key): ?string
    {
        if (! isset($this->container)) {
            return null;
        }

        $container = $this->getContainer();

        return match ($key) {
            'date' => $container->getDefaultDateDisplayFormat(),
            'time' => $container->getDefaultTimeDisplayFormat(),
            'dateTime' => $container->getDefaultDateTimeDisplayFormat(),
            default => null,
        };
    }

    /**
     * The string one date button writes, asked for at the moment it is clicked.
     *
     * What arrives is a key and never a format: the field looks it up in its own list, so a
     * request naming anything else is answered with nothing rather than with a rendering of
     * whatever it asked for. `mixed` rather than `string` for the same reason - what reaches
     * this method is whatever a request carried, and an array where a key was expected
     * belongs in the same "answered with nothing" branch as an unknown key rather than in a
     * type error and a stack trace. `Renderless` because the document does not change here -
     * the browser is given a string and puts it in itself.
     *
     * The answer is escaped, because the command that receives it parses its argument as
     * HTML on the way to the text it inserts. Escaping is what keeps a format holding an
     * ampersand or an angle bracket from arriving as markup.
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getDateTimeForJs(mixed $key): ?string
    {
        if (! is_string($key) || ! $this->hasDateTime()) {
            return null;
        }

        $format = $this->getDateTimeFormats()[$key] ?? null;

        if ($format === null) {
            return null;
        }

        return e(DateTimeFormats::render($format));
    }
}
