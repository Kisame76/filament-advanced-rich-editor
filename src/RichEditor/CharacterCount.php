<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;

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

    /**
     * Whether the field refuses input past the limit, which changes what full means:
     * the count cannot pass a limit that is held, so `over` would never be reached and
     * the line would stay on `almost` while the keyboard stopped answering.
     */
    protected bool $isEnforced = false;

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
                thresholds: {$this->js($this->getStateThresholds())},
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
                    // The two numbers are worked out in PHP and handed over, so the line
                    // cannot say one thing before the first keystroke and another after it.
                    if (this.thresholds === null) {
                        return null
                    }

                    if (this.characters >= this.thresholds.danger) {
                        return 'danger'
                    }

                    return this.characters >= this.thresholds.warning ? 'warning' : null
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
            [Numbers::format($count), Numbers::format($this->limit ?? 0)],
            $templates[$kind][$count === 1 ? 'one' : 'other'],
        );
    }

    public function enforced(bool $condition = true): static
    {
        $this->isEnforced = $condition;

        return $this;
    }

    /**
     * The two counts at which the line changes what it says, or null where there is no
     * limit to say anything about.
     *
     * Worked out once and read by both halves. The rule used to be written twice - here for
     * the first render and again in the Alpine getter for every render after - which is two
     * places to edit and one number that can disagree with itself while somebody watches.
     *
     * `danger` starts *at* the limit on a field that refuses more, because the count can
     * never pass a limit that is held: a line that only turned red above it would never turn
     * red at all, and somebody would sit on "almost full" while the keyboard stopped
     * answering.
     *
     * @return array{danger: int, warning: float}|null
     */
    public function getStateThresholds(): ?array
    {
        // Below one is not a limit: `maxLength(0)` would otherwise paint the line red on an
        // empty document, and with the limit held it would refuse every character - a field
        // that cannot be typed into at all.
        if ($this->limit === null || $this->limit < 1) {
            return null;
        }

        return [
            'danger' => $this->isEnforced ? $this->limit : $this->limit + 1,
            'warning' => $this->limit * static::WARNING_THRESHOLD,
        ];
    }

    protected function getInitialStateClass(): string
    {
        $thresholds = $this->getStateThresholds();

        if ($thresholds === null) {
            return '';
        }

        if ($this->characters >= $thresholds['danger']) {
            return 'fi-arte-character-count-danger';
        }

        return $this->characters >= $thresholds['warning']
            ? 'fi-arte-character-count-warning'
            : '';
    }

    protected function js(mixed $value): string
    {
        return Js::from($value)->toHtml();
    }
}
