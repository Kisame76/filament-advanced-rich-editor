<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasLabel;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;

/**
 * The two panels of the image toolbar: the alt text, and the width and height.
 *
 * Both are popovers anchored to their button rather than a second level replacing the bar.
 * The bar is composed of independent items - Filament renders each toolbar entry on its
 * own - so a level swap would need state shared between siblings that have no common
 * wrapper, and the bubble menu destroys and re-initialises that markup on every hide.
 * A popover keeps each control self contained and keeps the bar configurable.
 *
 * Typing inside the bar is only possible at all because the resize extension widens the
 * toolbar's visibility rule: Filament shows it while the EDITOR has focus, and an input
 * takes that focus away.
 */
class ToolbarImagePanel extends ViewComponent implements HasEmbeddedView
{
    use OpensAwayFromTheEdge;

    public const MODE_ALT = 'alt';

    public const MODE_SIZE = 'size';

    use HasExtraAttributes;
    use HasLabel;
    use HasName;

    protected string $mode = self::MODE_ALT;

    protected string $evaluationIdentifier = 'toolbarImagePanel';

    protected string $viewIdentifier = 'toolbarImagePanel';

    final public function __construct(string $name, string $mode)
    {
        $this->name($name);
        $this->mode = $mode;
    }

    public static function make(string $name = 'imageAlt', string $mode = self::MODE_ALT): static
    {
        $static = app(static::class, ['name' => $name, 'mode' => $mode]);
        $static->configure();

        return $static;
    }

    public static function alt(): static
    {
        return static::make('imageAlt', static::MODE_ALT)
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.label'));
    }

    public static function size(): static
    {
        return static::make('imageSize', static::MODE_SIZE)
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_size.label'));
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isSize(): bool
    {
        return $this->mode === static::MODE_SIZE;
    }

    public function toEmbeddedHtml(): string
    {
        return $this->isSize() ? $this->renderSizePanel() : $this->renderAltPanel();
    }

    /**
     * The shared shell: a toggle button and the popover it opens.
     *
     * What the panel does is `resources/js/image-panel.js`, an Alpine component loaded with
     * `x-load-src` the way the media browser is. It used to be a string inside this `x-data`,
     * five kilobytes per copy, checked with `toContain()`; now it is one file, tested under
     * `tests/js`, and the markup only says which panel it is.
     */
    protected function renderShell(string $icon, string $label, string $body): string
    {
        $config = implode(', ', [
            'mode: '.Js::from($this->mode)->toHtml(),
            'menuPosition: '.Js::from($this->menuPositionScript())->toHtml(),
            'menuUpClass: '.Js::from(static::MENU_UP_CLASS)->toHtml(),
        ]);

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                // An empty value is the default strategy, which loads straight away. The
                // panel is no use until it is loaded, and it is a few kilobytes.
                'x-load' => '',
                'x-load-src' => FilamentAsset::getAlpineComponentSrc('image-panel', 'kisame76/filament-advanced-rich-editor'),
                'x-data' => "arteImagePanel({ {$config} })",
                // Re-read only while the panel is closed. The tick fires on every editor
                // transaction, and a panel that keeps re-reading would overwrite what is
                // being typed into it. Opening it reads once, deliberately.
                'x-effect' => 'editorUpdatedAt && ! open && (anchor(), read())',
                'x-on:click.outside' => 'open = false',
                'x-on:arte-image-lock.window' => "'locked' in this ? locked = ! \$event.detail.unlocked : null",
                'x-on:keydown.escape.prevent.stop' => 'open = false',
            ], escape: false)
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-arte-image-panel']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <button
                type="button"
                tabindex="-1"
                aria-haspopup="true"
                x-bind:aria-expanded="open"
                aria-label="<?= e($label) ?>"
                x-tooltip="{ content: <?= Js::from($label)->toHtml() ?>, theme: $store.theme }"
                x-ref="trigger"
                x-on:click="open = ! open; open && (read(), positionMenu(), $nextTick(() => $refs.first?.focus()))"
                class="fi-fo-rich-editor-tool"
            >
                <?= $icon ?>
            </button>

            <div
                x-show="open"
                x-cloak
                x-ref="menu"
                x-bind:class="{ [menuUpClass]: dropUp }"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-image-panel-menu"
            >
                <?= $body ?>
            </div>
        </div>

        <?php return ob_get_clean();
    }

    /**
     * The two pieces of text an image carries, in one panel.
     *
     * They are different things and they belong together: the alt text stands in for the
     * picture where it cannot be seen, the caption is printed under it for everyone. Anyone
     * writing one is thinking about the other, and two buttons on a bar this narrow would be
     * two places to look for the same job.
     */
    protected function renderAltPanel(): string
    {
        $label = $this->getLabel();
        $altLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.alt');
        $hint = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.hint');
        $captionLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.caption');
        $captionHint = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.caption_hint');

        ob_start(); ?>

        <label class="fi-arte-image-panel-field">
            <span class="fi-arte-image-panel-label"><?= e($altLabel) ?></span>

            <input
                type="text"
                x-ref="first"
                x-model="alt"
                x-on:blur="commitOnLeaving($event)"
                x-on:keydown.enter.prevent.stop="commit(); open = false"
                class="fi-arte-image-panel-input fi-arte-image-panel-input-text"
            />
        </label>

        <p class="fi-arte-image-panel-hint"><?= e($hint) ?></p>

        <label class="fi-arte-image-panel-field">
            <span class="fi-arte-image-panel-label"><?= e($captionLabel) ?></span>

            <input
                type="text"
                x-model="caption"
                x-on:blur="commitOnLeaving($event)"
                x-on:keydown.enter.prevent.stop="commit(); open = false"
                class="fi-arte-image-panel-input fi-arte-image-panel-input-text"
            />
        </label>

        <p class="fi-arte-image-panel-hint"><?= e($captionHint) ?></p>

        <?php $body = ob_get_clean();

        return $this->renderShell(generate_icon_html(Icons::get('image_alt'))->toHtml(), $label, $body);
    }

    protected function renderSizePanel(): string
    {
        $label = $this->getLabel();
        $widthLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_size.width');
        $heightLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_size.height');
        $resetLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_size.reset');
        $applyLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_size.apply');
        $lockedLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_lock.locked');
        $unlockedLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_lock.unlocked');

        $lockedIcon = generate_icon_html(Icons::get('image_locked'))->toHtml();
        $unlockedIcon = generate_icon_html(Icons::get('image_unlocked'))->toHtml();

        ob_start(); ?>

        <div class="fi-arte-image-panel-row">
            <label class="fi-arte-image-panel-field">
                <span class="fi-arte-image-panel-label"><?= e($widthLabel) ?></span>

                <input
                    type="number"
                    min="1"
                    inputmode="numeric"
                    x-ref="first"
                    x-bind:value="width"
                    x-on:input="link('width', $event.target.value)"
                    x-on:keydown.enter.prevent.stop="apply()"
                    class="fi-arte-image-panel-input"
                />
            </label>

            <button
                type="button"
                tabindex="-1"
                x-on:click="toggleLock()"
                x-bind:aria-pressed="locked"
                x-bind:aria-label="locked ? <?= Js::from($lockedLabel)->toHtml() ?> : <?= Js::from($unlockedLabel)->toHtml() ?>"
                x-tooltip="{ content: locked ? <?= Js::from($lockedLabel)->toHtml() ?> : <?= Js::from($unlockedLabel)->toHtml() ?>, theme: $store.theme }"
                x-bind:class="{ 'fi-active': ! locked }"
                class="fi-arte-image-panel-lock"
            >
                <span x-show="locked"><?= $lockedIcon ?></span>
                <span x-show="! locked" x-cloak><?= $unlockedIcon ?></span>
            </button>

            <label class="fi-arte-image-panel-field">
                <span class="fi-arte-image-panel-label"><?= e($heightLabel) ?></span>

                <input
                    type="number"
                    min="1"
                    inputmode="numeric"
                    x-bind:value="height"
                    x-on:input="link('height', $event.target.value)"
                    x-on:keydown.enter.prevent.stop="apply()"
                    class="fi-arte-image-panel-input"
                />
            </label>
        </div>

        <div class="fi-arte-image-panel-actions">
            <button
                type="button"
                tabindex="-1"
                x-on:click="apply()"
                x-bind:disabled="! isDirty()"
                class="fi-arte-image-panel-apply"
            ><?= e($applyLabel) ?></button>

            <button
                type="button"
                tabindex="-1"
                x-on:click="reset()"
                class="fi-arte-image-panel-reset"
            ><?= e($resetLabel) ?></button>
        </div>

        <?php $body = ob_get_clean();

        return $this->renderShell(generate_icon_html(Icons::get('image_size'))->toHtml(), $label, $body);
    }
}
