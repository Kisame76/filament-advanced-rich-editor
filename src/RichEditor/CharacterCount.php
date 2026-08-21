<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;
use Illuminate\Support\Number;

/**
 * The line under the editor that says how long the text is.
 *
 * It is rendered through Filament's own `belowContent()` rather than by replacing the
 * editor's view: a copy of that view would have to be kept in step with every Filament
 * release, and this needs nothing from inside it.
 *
 * The first number comes from PHP, measured the way the validation measures, so the line
 * is right before anything is typed. After that the editor announces its own counts on
 * every change and this listens - no polling, and no second opinion about what a character
 * is.
 */
class CharacterCount extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;

    /**
     * The share of the limit at which the line starts warning. Late enough to stay quiet
     * while there is room, early enough that a long sentence does not blow past it.
     */
    public const WARNING_THRESHOLD = 0.9;

    protected int $characters = 0;

    protected ?int $words = null;

    protected ?int $limit = null;

    protected string $evaluationIdentifier = 'characterCount';

    protected string $viewIdentifier = 'characterCount';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    public function characters(int $characters): static
    {
        $this->characters = $characters;

        return $this;
    }

    public function getCharacters(): int
    {
        return $this->characters;
    }

    public function words(?int $words): static
    {
        $this->words = $words;

        return $this;
    }

    public function getWords(): ?int
    {
        return $this->words;
    }

    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Singular and plural of every phrase the line can show, with `:count` and `:limit`
     * left in. The browser fills them in, because the numbers change without PHP ever
     * hearing about it - so the wording has to travel to where the counting happens.
     *
     * @return array<string, array<string, string>>
     */
    protected function getTemplates(): array
    {
        $read = fn (string $key): array => [
            'one' => __("filament-advanced-rich-editor::advanced-rich-editor.character_count.{$key}.one"),
            'other' => __("filament-advanced-rich-editor::advanced-rich-editor.character_count.{$key}.other"),
        ];

        return [
            'characters' => $read($this->limit === null ? 'characters' : 'characters_of_limit'),
            'words' => $read('words'),
        ];
    }

    public function toEmbeddedHtml(): string
    {
        $templates = $this->getTemplates();
        $limit = $this->getLimit();
        $words = $this->getWords();

        $xData = <<<JS
            {
                characters: {$this->characters},
                words: {$this->js($words)},
                limit: {$this->js($limit)},
                templates: {$this->js($templates)},
                formatter: new Intl.NumberFormat({$this->js(str_replace('_', '-', app()->getLocale()))}),
                // The editor is the only thing that knows what it holds, so it says so, and
                // the field it belongs to is the one whose numbers these are. Two editors on
                // a page each hear their own.
                update(event) {
                    if (! \$el.closest('.fi-fo-field-content-col')?.contains(event.target)) {
                        return
                    }

                    this.characters = event.detail.characters

                    if (this.words !== null) {
                        this.words = event.detail.words
                    }
                },
                phrase(kind, count) {
                    const template = this.templates[kind][count === 1 ? 'one' : 'other']

                    return template
                        .replace(':count', this.formatter.format(count))
                        .replace(':limit', this.formatter.format(this.limit ?? 0))
                },
                get state() {
                    if (this.limit === null) {
                        return null
                    }

                    if (this.characters > this.limit) {
                        return 'danger'
                    }

                    return this.characters >= this.limit * {$this->js(static::WARNING_THRESHOLD)} ? 'warning' : null
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-data' => $xData,
                'x-on:arte-character-count.window' => 'update($event)',
                'x-bind:class' => "state ? 'fi-arte-character-count-' + state : ''",
            ], escape: false)
            ->class(['fi-arte-character-count', $this->getInitialStateClass()]);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?> aria-live="polite">
            <?php if ($words !== null) { ?>
                <span x-text="phrase('words', words)"><?= e($this->phrase('words', $words)) ?></span>

                <span aria-hidden="true">&middot;</span>
            <?php } ?>

            <span x-text="phrase('characters', characters)"><?= e($this->phrase('characters', $this->characters)) ?></span>
        </div>

        <?php return ob_get_clean();
    }

    /**
     * The same phrasing the browser builds, for the render that happens before any
     * JavaScript has run.
     */
    protected function phrase(string $kind, int $count): string
    {
        $templates = $this->getTemplates();

        return str_replace(
            [':count', ':limit'],
            [Number::format($count), Number::format($this->limit ?? 0)],
            $templates[$kind][$count === 1 ? 'one' : 'other'],
        );
    }

    protected function getInitialStateClass(): string
    {
        if ($this->limit === null) {
            return '';
        }

        if ($this->characters > $this->limit) {
            return 'fi-arte-character-count-danger';
        }

        return $this->characters >= $this->limit * static::WARNING_THRESHOLD
            ? 'fi-arte-character-count-warning'
            : '';
    }

    protected function js(mixed $value): string
    {
        return Js::from($value)->toHtml();
    }
}
