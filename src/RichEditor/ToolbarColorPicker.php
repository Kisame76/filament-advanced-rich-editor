<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Closure;
use Filament\Schemas\Components\Concerns\HasLabel;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Filament\Support\Concerns\HasIcon;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;

/**
 * A dropdown of colour swatches for the selection.
 *
 * One class serves both colours because only three things differ between them: which mark
 * is written, which command writes it, and where the colour is painted in the swatch. The
 * text colour rides on Filament's own `textColor` mark - it ships the mark, the commands
 * and a configurable palette - while the background uses this package's `textBackground`
 * mark, since Filament registers TipTap's highlight without colour support.
 */
class ToolbarColorPicker extends ViewComponent implements HasEmbeddedView
{
    use OpensAwayFromTheEdge;

    public const MODE_TEXT = 'text';

    public const MODE_BACKGROUND = 'background';

    use HasExtraAttributes;
    use HasIcon;
    use HasLabel;
    use HasName;

    /**
     * @var array<int, array{value: string, label: string, color: string, darkColor: string}> | Closure
     */
    protected array|Closure $colors = [];

    protected bool|Closure $hasCustomColor = true;

    protected string $mode = self::MODE_TEXT;

    protected string $evaluationIdentifier = 'toolbarColorPicker';

    protected string $viewIdentifier = 'toolbarColorPicker';

    final public function __construct(string $name, string $mode)
    {
        $this->name($name);
        $this->mode = $mode;
    }

    public static function make(string $name = 'textColor', string $mode = self::MODE_TEXT): static
    {
        $static = app(static::class, ['name' => $name, 'mode' => $mode]);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, array{value: string, label: string, color: string, darkColor?: string}>  $colors
     */
    public static function text(array $colors, bool $withCustomColor = true): static
    {
        return static::make('textColor', static::MODE_TEXT)
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.text_color'))
            ->icon(Icons::get('text_color'))
            ->colors($colors)
            ->customColor($withCustomColor);
    }

    /**
     * @param  array<int, array{value: string, label: string, color: string, darkColor?: string}>  $colors
     */
    public static function background(array $colors, bool $withCustomColor = true): static
    {
        return static::make('textBackground', static::MODE_BACKGROUND)
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.text_background'))
            ->icon(Icons::get('text_background'))
            ->colors($colors)
            ->customColor($withCustomColor);
    }

    /**
     * @param  array<int, array{value: string, label: string, color: string, darkColor?: string}> | Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string, darkColor: string}>
     */
    public function getColors(): array
    {
        return array_values(array_map(
            static fn (array $color): array => [
                'value' => (string) $color['value'],
                'label' => (string) ($color['label'] ?? $color['value']),
                'color' => (string) ($color['color'] ?? $color['value']),
                'darkColor' => (string) ($color['darkColor'] ?? $color['color'] ?? $color['value']),
            ],
            $this->evaluate($this->colors),
        ));
    }

    public function customColor(bool|Closure $condition = true): static
    {
        $this->hasCustomColor = $condition;

        return $this;
    }

    public function hasCustomColor(): bool
    {
        return (bool) $this->evaluate($this->hasCustomColor);
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function isBackground(): bool
    {
        return $this->mode === static::MODE_BACKGROUND;
    }

    /**
     * The mark the dropdown reads its current value from, and the two commands it writes
     * with. Filament's text colour mark keeps the value in a `data-color` attribute; this
     * package's background mark uses a plain `color` attribute.
     *
     * @return array{mark: string, attribute: string, set: string, unset: string}
     */
    protected function getMarkConfiguration(): array
    {
        return $this->isBackground()
            ? ['mark' => 'textBackground', 'attribute' => 'color', 'set' => 'setTextBackground', 'unset' => 'unsetTextBackground']
            : ['mark' => 'textColor', 'attribute' => 'data-color', 'set' => 'setTextColor', 'unset' => 'unsetTextColor'];
    }

    public function toEmbeddedHtml(): string
    {
        $configuration = $this->getMarkConfiguration();
        $colors = $this->getColors();

        $label = $this->getLabel();
        $clearLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.color_clear');
        $customLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.color_custom');

        // The set command takes an object for the text colour and a bare string for the
        // background, which is the only asymmetry the markup has to carry.
        $setArgument = $this->isBackground() ? 'color' : '{ color: color }';

        // Byte for byte the chevron `ToolbarButtonGroup` draws, rather than an icon of our
        // own: it is the same piece of furniture, and Filament already styles that class
        // small, thin and grey, so the two triggers cannot drift apart.
        $chevron = '<svg class="fi-fo-rich-editor-dropdown-tool-chevron" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M3 4.5 6 7.5l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        $xData = <<<JS
            {
                {$this->menuPositioning()}
                open: false,
                current: null,
                colors: {$this->encodeColors($colors)},
                sync() {
                    const value = \$getEditor()?.getAttributes({$this->js($configuration['mark'])})?.[{$this->js($configuration['attribute'])}] ?? null

                    this.current = value ?? null
                },
                swatch(value) {
                    const entry = this.colors.find((color) => color.value === value)

                    return entry ? entry.color : value
                },
                apply(color) {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    let chain = editor.chain().focus()

                    // Without a selection the whole run under the caret is meant, which is
                    // what a reader expects when recolouring a word they clicked into.
                    if (editor.state.selection.empty) {
                        chain = chain.extendMarkRange({$this->js($configuration['mark'])})
                    }

                    chain.{$configuration['set']}({$setArgument}).run()

                    this.current = color
                    this.open = false
                },
                clear() {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    let chain = editor.chain().focus()

                    if (editor.state.selection.empty) {
                        chain = chain.extendMarkRange({$this->js($configuration['mark'])})
                    }

                    chain.{$configuration['unset']}().run()

                    this.current = null
                    this.open = false
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-data' => $xData,
                'x-effect' => 'editorUpdatedAt && sync()',
                'x-on:click.outside' => 'open = false',
                'x-on:keydown.escape.prevent' => 'open = false',
            ], escape: false)
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-arte-color-picker']);

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
                x-on:click="open = ! open; open && positionMenu()"
                class="fi-fo-rich-editor-tool fi-fo-rich-editor-dropdown-tool-trigger"
            >
                <span class="fi-arte-color-preview">
                    <?= generate_icon_html($this->getIcon())->toHtml() ?>

                    <span
                        class="fi-arte-color-preview-bar"
                        x-bind:style="current ? { backgroundColor: swatch(current) } : {}"
                        x-bind:class="{ 'fi-arte-color-preview-bar-empty': ! current }"
                    ></span>
                </span>

                <?= $chevron ?>
            </button>

            <div
                x-show="open"
                x-cloak
                role="menu"
                x-ref="menu"
                x-bind:class="{ [menuUpClass]: dropUp }"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-color-menu"
            >
                <div class="fi-arte-color-grid">
                    <?php foreach ($colors as $color) { ?>
                        <button
                            type="button"
                            tabindex="-1"
                            role="menuitem"
                            aria-label="<?= e($color['label']) ?>"
                            x-tooltip="{ content: <?= Js::from($color['label'])->toHtml() ?>, theme: $store.theme }"
                            x-bind:class="{ 'fi-active': current === <?= Js::from($color['value'])->toHtml() ?> }"
                            x-on:click="apply(<?= Js::from($color['value'])->toHtml() ?>)"
                            style="--fi-arte-color: <?= e($color['color']) ?>; --fi-arte-dark-color: <?= e($color['darkColor']) ?>"
                            class="fi-arte-color-swatch"
                        ></button>
                    <?php } ?>
                </div>

                <div class="fi-arte-color-actions">
                    <button
                        type="button"
                        tabindex="-1"
                        role="menuitem"
                        x-on:click="clear()"
                        class="fi-arte-color-clear"
                    >
                        <span class="fi-arte-color-clear-swatch"></span>
                        <?= e($clearLabel) ?>
                    </button>

                    <?php if ($this->hasCustomColor()) { ?>
                        <label class="fi-arte-color-custom" x-tooltip="{ content: <?= Js::from($customLabel)->toHtml() ?>, theme: $store.theme }">
                            <?= generate_icon_html(Icons::get('color_custom'))->toHtml() ?>

                            <input
                                type="color"
                                aria-label="<?= e($customLabel) ?>"
                                x-on:change="apply($event.target.value)"
                                class="fi-arte-color-custom-input"
                            />
                        </label>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php return ob_get_clean();
    }

    /**
     * @param  array<int, array<string, string>>  $colors
     */
    protected function encodeColors(array $colors): string
    {
        return Js::from($colors)->toHtml();
    }

    protected function js(string|BackedEnum|null $value): string
    {
        return Js::from($value)->toHtml();
    }
}
