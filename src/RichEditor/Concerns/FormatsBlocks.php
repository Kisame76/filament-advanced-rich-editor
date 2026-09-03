<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Callouts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\IndentPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Indent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\LineHeight;
use LogicException;

/**
 * The formats that apply to a whole block: headings, lists, alignment, line spacing,
 * callouts, code blocks and embeds.
 *
 * Each is a switch, a list, or both - a project says which heading levels a field offers and
 * which list kinds it knows, and the toolbar is built from what is left. Everything here is
 * resolved rather than stored: two fields on one page may offer different sets.
 */
trait FormatsBlocks
{
    /**
     * @var array<int, int> | Closure | null
     */
    protected array|Closure|null $headingLevels = null;

    protected bool|Closure|null $hasHeadingParagraph = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $listTypes = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $alignments = null;

    protected bool|Closure|null $hasLineHeight = null;

    /**
     * @var array<int, mixed> | Closure | null
     */
    protected array|Closure|null $lineHeights = null;

    protected bool|Closure|null $hasIndent = null;

    protected string|int|float|Closure|null $indentStep = null;

    protected int|string|Closure|null $indentMax = null;

    protected bool|Closure|null $hasTaskList = null;

    protected bool|Closure|null $hasCallouts = null;

    protected bool|Closure|null $hasListProperties = null;

    /**
     * @var array<int, mixed>|Closure|null
     */
    protected array|Closure|null $calloutVariants = null;

    protected bool|Closure|null $hasEmbeds = null;

    protected bool|Closure|null $hasMedia = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $codeBlockLanguages = null;

    /**
     * The languages the code block's picker offers, as `value => label`.
     *
     * An empty list takes the picker away: a project that curated the languages down to
     * nothing has said it does not want one. A language a block already carries is offered
     * even when it is not listed - it is still what the block is written in.
     *
     * @param  array<string, string> | Closure  $languages
     */
    public function codeBlockLanguages(array|Closure $languages): static
    {
        $this->codeBlockLanguages = $languages;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getCodeBlockLanguages(): array
    {
        $languages = $this->evaluate($this->codeBlockLanguages)
            ?? config('filament-advanced-rich-editor.code_block.languages')
            ?? [];

        return is_array($languages) ? $languages : [];
    }

    /**
     * What the code block script needs from PHP: the languages and the wording for a block
     * that declares none.
     *
     * @return array<string, mixed>|null
     */
    public function getCodeBlockSettingsForJs(): ?array
    {
        $languages = $this->getCodeBlockLanguages();

        if ($languages === []) {
            return null;
        }

        return [
            'languages' => $languages,
            'plain' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.code_block.plain'),
        ];
    }

    /**
     * Whether the field can embed a video from YouTube or Vimeo.
     *
     * On by default but not on the shipped bar, since the media browser arrived: that
     * button covers video from your own server, and two video-shaped buttons beside each
     * other is one door too many for a bar with a finite number of places. The slash menu
     * still finds this one, and a bar that names it still gets it.
     *
     * Off means the button and the script are gone; stored embeds are still rendered,
     * because a field that stops offering something has no business deleting what was
     * written before it stopped.
     */
    public function embeds(bool|Closure $condition = true): static
    {
        $this->hasEmbeds = $condition;

        return $this;
    }

    public function hasEmbeds(): bool
    {
        return (bool) ($this->evaluate($this->hasEmbeds) ?? config('filament-advanced-rich-editor.embed.enabled') ?? true);
    }

    /**
     * Whether the field can place a video or a sound that lives on this server.
     *
     * The neighbour of `embeds()` and the opposite half of the same question: that one
     * frames a file somebody else hosts, this one points at one of your own. Named `media`
     * rather than `video` because one node draws both elements - and it is not the media
     * *library*, which is `mediaLibrary()` and browses pictures.
     *
     * Off means the buttons, the dialog and the script are gone; stored players are still
     * rendered, because a field that stops offering something has no business deleting what
     * was written before it stopped.
     */
    public function media(bool|Closure $condition = true): static
    {
        $this->hasMedia = $condition;

        return $this;
    }

    public function hasMedia(): bool
    {
        return (bool) ($this->evaluate($this->hasMedia) ?? config('filament-advanced-rich-editor.media.enabled') ?? true);
    }

    /**
     * What the embed script needs from PHP: the provider names, in the panel's language,
     * and whether YouTube is embedded through its cookie-free host.
     *
     * @return array<string, mixed>|null
     */
    public function getEmbedSettingsForJs(): ?array
    {
        if (! $this->hasEmbeds()) {
            return null;
        }

        return [
            'nocookie' => (bool) config('filament-advanced-rich-editor.embed.youtube_nocookie', true),
            'labels' => [
                'youtube' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.youtube'),
                'vimeo' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.providers.vimeo'),
            ],
        ];
    }

    /**
     * @param  array<int, int> | Closure  $levels
     */
    public function headingLevels(array|Closure $levels): static
    {
        $this->headingLevels = $levels;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getHeadingLevels(): array
    {
        $levels = array_values(array_map(
            intval(...),
            $this->evaluate($this->headingLevels) ?? config('filament-advanced-rich-editor.heading_levels') ?? [1, 2, 3, 4],
        ));

        foreach ($levels as $level) {
            if (($level >= 1) && ($level <= 6)) {
                continue;
            }

            throw new LogicException("The heading level [{$level}] used by the rich editor [{$this->getName()}] does not exist, because HTML only defines the headings [h1] to [h6].");
        }

        return $levels;
    }

    /**
     * @param  array<int, string> | Closure  $types
     */
    /**
     * Whether the headings dropdown also offers the plain paragraph.
     *
     * With it, the dropdown covers every block the caret can be in, so it reads as a
     * choice rather than a set of toggles. Filament's heading tools already turn a heading
     * back into a paragraph when the active level is picked again, so a block never ends
     * up unset either way.
     */
    public function headingParagraph(bool|Closure $condition = true): static
    {
        $this->hasHeadingParagraph = $condition;

        return $this;
    }

    public function hasHeadingParagraph(): bool
    {
        return (bool) ($this->evaluate($this->hasHeadingParagraph)
            ?? config('filament-advanced-rich-editor.heading_paragraph', true));
    }

    /**
     * Which list types the 'lists' dropdown offers, in the listed order.
     *
     * @param  array<int, string> | Closure  $types
     */
    public function listTypes(array|Closure $types): static
    {
        $this->listTypes = $types;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getListTypes(): array
    {
        // `taskList` is left in place even when the task list is disabled:
        // `ToolbarButtonGroup::resolve()` silently drops button names without a
        // matching tool, so the dropdown simply renders one option less.
        return array_values($this->evaluate($this->listTypes) ?? config('filament-advanced-rich-editor.lists') ?? ['bulletList', 'orderedList', 'taskList']);
    }

    /**
     * Which alignments the 'alignment' dropdown offers, in the listed order. The first
     * one doubles as the trigger's resting icon.
     *
     * @param  array<int, string> | Closure  $alignments
     */
    public function alignments(array|Closure $alignments): static
    {
        $this->alignments = $alignments;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAlignments(): array
    {
        return array_values($this->evaluate($this->alignments)
            ?? config('filament-advanced-rich-editor.alignments')
            ?? ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify']);
    }

    /**
     * The line spacing dropdown. Switching it off also drops the extension, so a field that
     * has none stops declaring the attribute - and content that already carries a spacing
     * loses it on the next save, the same way the direction does.
     */
    public function lineHeight(bool|Closure $condition = true): static
    {
        $this->hasLineHeight = $condition;

        return $this;
    }

    public function hasLineHeight(): bool
    {
        return (bool) ($this->evaluate($this->hasLineHeight) ?? config('filament-advanced-rich-editor.line_height.enabled') ?? true);
    }

    /**
     * Which spacings the 'lineHeight' dropdown offers, in the listed order.
     *
     * @param  array<int, mixed> | Closure  $values
     */
    public function lineHeights(array|Closure $values): static
    {
        $this->lineHeights = $values;

        return $this;
    }

    /**
     * Canonicalised and de-duplicated, so `1.50` and `1.5` name one option and not two, and
     * a value that is not a line height at all is dropped rather than rendered.
     *
     * @return array<int, string>
     */
    public function getLineHeights(): array
    {
        return LineHeight::values(array_values($this->evaluate($this->lineHeights)
            ?? config('filament-advanced-rich-editor.line_height.values')
            ?? [1, 1.15, 1.5, 2]));
    }

    /**
     * The indent buttons, the two keys, and the attribute behind them.
     *
     * Ships off. Most documents indent nothing, and the ones that do are a kind rather than
     * a majority - a contract, a report, minutes - so a bar that carried the pair everywhere
     * would be spending two places on a question most fields never ask. Switching it on is
     * one line, and the keys work from that moment whether or not the buttons are placed.
     *
     * Switching it off drops the extension with it, so a field that has none stops declaring
     * the attribute - and content that already carries an indent loses it on the next save,
     * the same way the spacing does.
     */
    public function indent(bool|Closure $condition = true): static
    {
        $this->hasIndent = $condition;

        return $this;
    }

    public function hasIndent(): bool
    {
        return (bool) ($this->evaluate($this->hasIndent) ?? config('filament-advanced-rich-editor.indent.enabled') ?? false);
    }

    /**
     * How far one step in moves a block. A CSS length - `'2.5rem'`, `'1.27cm'`, `'40px'` -
     * or a bare number, which is read as `rem`.
     */
    public function indentStep(string|int|float|Closure $step): static
    {
        $this->indentStep = $step;

        return $this;
    }

    /**
     * Canonicalised, so a length written by the toolbar and one parsed back out of a
     * document are the same string. A length this side cannot multiply - a percentage, a
     * unit nobody named, a zero - is the shipped step rather than nothing at all, because a
     * field whose step is nothing has two buttons that do nothing.
     */
    public function getIndentStep(): string
    {
        return Indent::step($this->evaluate($this->indentStep)
            ?? config('filament-advanced-rich-editor.indent.step'));
    }

    /**
     * How many steps in a block may go.
     */
    public function indentMax(int|string|Closure $max): static
    {
        $this->indentMax = $max;

        return $this;
    }

    public function getIndentMax(): int
    {
        return Indent::max($this->evaluate($this->indentMax)
            ?? config('filament-advanced-rich-editor.indent.max'));
    }

    /**
     * The step and the depth, for the view to hand to the script. Null while indenting is
     * switched off, which is also when the extension that would read them was never
     * registered.
     *
     * @return array<string, mixed>|null
     */
    public function getIndentSettingsForJs(): ?array
    {
        return $this->hasIndent()
            ? IndentPlugin::getSettings($this->getIndentStep(), $this->getIndentMax())
            : null;
    }

    public function taskList(bool|Closure $condition = true): static
    {
        $this->hasTaskList = $condition;

        return $this;
    }

    public function hasTaskList(): bool
    {
        return (bool) ($this->evaluate($this->hasTaskList) ?? config('filament-advanced-rich-editor.task_list') ?? true);
    }

    public function callouts(bool|Closure $condition = true): static
    {
        $this->hasCallouts = $condition;

        return $this;
    }

    public function hasCallouts(): bool
    {
        return (bool) ($this->evaluate($this->hasCallouts) ?? config('filament-advanced-rich-editor.callouts.enabled') ?? true);
    }

    /**
     * Which kinds of callout this field offers, in the order the dropdown and the slash
     * menu read them in.
     *
     * @param  array<int, mixed>|Closure|null  $variants
     */
    public function calloutVariants(array|Closure|null $variants): static
    {
        $this->calloutVariants = $variants;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCalloutVariants(): array
    {
        return Callouts::normalize(
            $this->evaluate($this->calloutVariants)
                ?? config('filament-advanced-rich-editor.callouts.variants')
                ?? Callouts::VARIANTS,
        );
    }

    /**
     * Whether a list can be told which marker to draw, where to start counting and whether
     * to count backwards.
     *
     * Off means the schema is not declared on either side, so a stored list keeps its
     * markup in the database and loses it on the next save - the same bargain every other
     * switch in here makes.
     */
    public function listProperties(bool|Closure $condition = true): static
    {
        $this->hasListProperties = $condition;

        return $this;
    }

    public function hasListProperties(): bool
    {
        return (bool) ($this->evaluate($this->hasListProperties) ?? config('filament-advanced-rich-editor.list_properties') ?? true);
    }
}
