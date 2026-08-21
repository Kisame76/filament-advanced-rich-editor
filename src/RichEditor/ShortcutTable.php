<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;

/**
 * The shortcut list, drawn as keycaps.
 *
 * A description list rather than a table: every entry is one name and the keys that mean
 * it, which is what a `dl` is for - and it is what lets the list run in two columns on a
 * wide dialog without the reading order coming apart, where a table would have had to be
 * split into two tables.
 *
 * The keys arrive as tokens and are named in the browser, because `Mod` is ⌘ on a Mac and
 * Ctrl everywhere else, and the server is not the one being typed on. The server still
 * renders a name for every key, so the list is readable before - or without - JavaScript.
 */
class ShortcutTable extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;

    /**
     * What each token is called on a machine that is not an Apple one. The other names live
     * in the browser, next to the check that decides which set to use.
     *
     * @var array<string, string>
     */
    protected const NAMES = [
        'Mod' => 'Ctrl',
        'Alt' => 'Alt',
        'Shift' => 'Shift',
        'Enter' => 'Enter',
        'Tab' => 'Tab',
    ];

    /**
     * @var array<int, array{label: string, keys: array<int, string>}>
     */
    protected array $rows = [];

    protected string $evaluationIdentifier = 'shortcutTable';

    protected string $viewIdentifier = 'shortcutTable';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, array{label: string, keys: array<int, string>}>  $rows
     */
    public function rows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @return array<int, array{label: string, keys: array<int, string>}>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function toEmbeddedHtml(): string
    {
        $xData = <<<'JS'
            {
                // `navigator.platform` is deprecated but still the only thing that answers
                // this question everywhere; the modern one is checked first.
                isApple: /mac|iphone|ipad|ipod/i.test(
                    navigator.userAgentData?.platform || navigator.platform || navigator.userAgent,
                ),
                glyph(token) {
                    // Only the three glyphs that carry no emoji presentation. `↩` and `⇥`
                    // are emoji on most systems and turn up as coloured stickers between
                    // the keycaps, so those two keys keep their names on every platform.
                    const apple = { Mod: '⌘', Alt: '⌥', Shift: '⇧' }

                    return (this.isApple ? apple[token] : null) ?? this.name(token)
                },
                name(token) {
                    return { Mod: 'Ctrl', Alt: 'Alt', Shift: 'Shift', Enter: 'Enter', Tab: 'Tab' }[token] ?? token
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge(['x-data' => $xData], escape: false)
            ->class(['fi-arte-shortcuts']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <dl class="fi-arte-shortcuts-list">
                <?php foreach ($this->getRows() as $row) { ?>
                    <div class="fi-arte-shortcut">
                        <dt class="fi-arte-shortcut-label"><?= e($row['label']) ?></dt>

                        <dd class="fi-arte-shortcut-keys">
                            <?php foreach ($row['keys'] as $key) { ?>
                                <kbd x-text="glyph(<?= Js::from($key)->toHtml() ?>)"><?= e(static::NAMES[$key] ?? $key) ?></kbd>
                            <?php } ?>
                        </dd>
                    </div>
                <?php } ?>
            </dl>
        </div>

        <?php return ob_get_clean();
    }
}
