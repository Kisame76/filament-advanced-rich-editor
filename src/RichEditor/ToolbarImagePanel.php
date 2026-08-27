<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasLabel;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

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
     * @param  array<string, string>  $state  extra Alpine members
     */
    protected function renderShell(string $icon, string $label, array $state, string $body): string
    {
        $members = implode("\n", array_map(
            static fn (string $value, string $key): string => "                {$key}: {$value},",
            $state,
            array_keys($state),
        ));

        // `read()` runs whenever the editor reports a change, so the fields follow a drag
        // as it happens, and `commit()` is the only writer.
        $xData = <<<JS
            {
                {$this->menuPositioning()}
                open: false,
            {$members}
                position() {
                    return \$getEditor()?.state?.selection?.from
                },
                node() {
                    const editor = \$getEditor()
                    const position = this.position()

                    if (! editor || position === undefined) {
                        return null
                    }

                    const node = editor.state.doc.nodeAt(position)

                    return node?.type.name === 'image' ? node : null
                },
                image() {
                    // Read off the node rather than through `getAttributes()`: that one
                    // answers for the selection, and the selection is a plain caret again
                    // as soon as anything focuses away from the image.
                    return this.node()?.attrs ?? {}
                },
                update(attributes) {
                    const editor = \$getEditor()
                    const position = this.position()

                    if (! editor || position === undefined) {
                        return
                    }

                    // Mirrors how a resize drag commits: the node selection is restored
                    // first, because focusing the editor would collapse it to a caret.
                    editor.chain().setNodeSelection(position).updateAttributes('image', attributes).run()
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-data' => $xData,
                // Re-read only while the panel is closed. The tick fires on every editor
                // transaction, and a panel that keeps re-reading would overwrite what is
                // being typed into it. Opening it reads once, deliberately.
                'x-effect' => 'editorUpdatedAt && ! open && read()',
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
                x-on:change="commit()"
                x-on:blur="commit()"
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
                x-on:change="commit()"
                x-on:blur="commit()"
                x-on:keydown.enter.prevent.stop="commit(); open = false"
                class="fi-arte-image-panel-input fi-arte-image-panel-input-text"
            />
        </label>

        <p class="fi-arte-image-panel-hint"><?= e($captionHint) ?></p>

        <?php $body = ob_get_clean();

        return $this->renderShell(
            generate_icon_html(Icons::get('image_alt'))->toHtml(),
            $label,
            [
                'alt' => "''",
                'caption' => "''",
                'read' => "function () { const image = this.image(); this.alt = image.alt ?? ''; this.caption = image.caption ?? '' }",
                // Neither can be stored empty - the renderer drops falsy attributes - so
                // clearing a field removes it rather than pretending otherwise.
                'commit' => "function () { this.update({ alt: this.alt.trim() === '' ? null : this.alt, caption: this.caption.trim() === '' ? null : this.caption }) }",
            ],
            $body,
        );
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

        return $this->renderShell(
            generate_icon_html(Icons::get('image_size'))->toHtml(),
            $label,
            [
                'width' => 'null',
                'height' => 'null',
                'ratio' => 'null',
                'locked' => 'true',
                'read' => <<<'JS'
                    function () {
                        const attributes = this.image()

                        // An image that has never been sized carries no width or height of
                        // its own, so the rendered element answers for it.
                        const element = $getEditor()?.view?.nodeDOM?.(this.position() ?? 0)
                        const image = element?.querySelector?.('img') ?? element ?? null

                        this.width = Number.parseInt(attributes.width, 10) || image?.offsetWidth || null
                        this.height = Number.parseInt(attributes.height, 10) || image?.offsetHeight || null
                        this.ratio = (this.width > 0 && this.height > 0) ? (this.width / this.height) : null
                        this.locked = ! ($getEditor()?.storage?.arteImageResize?.unlocked ?? false)
                    }
                    JS,
                'toggleLock' => <<<'JS'
                    function () {
                        const storage = $getEditor()?.storage?.arteImageResize

                        if (! storage) {
                            return
                        }

                        storage.unlocked = ! storage.unlocked
                        this.locked = ! storage.unlocked

                        // The same switch also sits in the toolbar itself, where it is
                        // visible during a drag. One state, two places to reach it.
                        window.dispatchEvent(new CustomEvent('arte-image-lock', {
                            detail: { unlocked: storage.unlocked },
                        }))
                    }
                    JS,
                // While the ratio is locked the other field follows along as you type, so
                // the pair that will be applied is visible before you commit to it.
                // The single writer for both fields. `x-model` would be one too, and it
                // updates AFTER this handler, so a bound model would keep overwriting the
                // linked value with the previous one.
                'link' => <<<'JS'
                    function (changed, raw) {
                        const value = Number.parseInt(raw, 10)
                        const valid = Number.isFinite(value) && value > 0

                        if (changed === 'height') {
                            this.height = valid ? value : null
                        } else {
                            this.width = valid ? value : null
                        }

                        if (! valid || ! this.locked || ! this.ratio) {
                            return
                        }

                        if (changed === 'height') {
                            this.width = Math.round(value * this.ratio)
                        } else {
                            this.height = Math.round(value / this.ratio)
                        }
                    }
                    JS,
                'isDirty' => <<<'JS'
                    function () {
                        const attributes = this.image()
                        const width = Number.parseInt(this.width, 10)
                        const height = Number.parseInt(this.height, 10)

                        if (! Number.isFinite(width) || ! Number.isFinite(height) || width < 1 || height < 1) {
                            return false
                        }

                        return width !== Number.parseInt(attributes.width, 10)
                            || height !== Number.parseInt(attributes.height, 10)
                    }
                    JS,
                // Applied on demand rather than on every change: with the ratio locked,
                // committing each field on its own would undo the other one before both
                // numbers had been entered.
                'apply' => <<<'JS'
                    function () {
                        if (! this.isDirty()) {
                            return
                        }

                        const width = Number.parseInt(this.width, 10)
                        const height = Number.parseInt(this.height, 10)

                        this.update({ width, height })
                        this.ratio = width / height
                        this.open = false
                    }
                    JS,
                'reset' => 'function () { this.update({ width: null, height: null }); this.$nextTick(() => this.read()) }',
            ],
            $body,
        );
    }
}
