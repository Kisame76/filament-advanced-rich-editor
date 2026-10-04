<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;

/**
 * The toolbar's font size: a number, and a menu of the sizes anyone actually picks.
 *
 * A stepper takes three controls' worth of toolbar to say what one dropdown says, and
 * nobody steps from 12 to 48 one press at a time. The sizes people reach for are a short
 * list, so that is the list - with a field at the top of it for the size that is not on it.
 *
 * It is not a `RichEditorTool` because a tool is a single button with one handler. The
 * markup below lives in the editor's own Alpine scope, so `$getEditor()` and the
 * `editorUpdatedAt` tick are both in reach: the first applies a size to the selection,
 * the second keeps the displayed number in sync while the cursor moves.
 */
class ToolbarFontSize extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasName;
    use OpensAwayFromTheEdge;

    protected int|Closure $min = 8;

    protected int|Closure $max = 96;

    protected int|Closure $step = 1;

    protected int|Closure $defaultSize = 16;

    /**
     * @var array<int, int> | Closure
     */
    protected array|Closure $sizes = [8, 9, 10, 11, 12, 14, 16, 18, 24, 30, 36, 48, 60, 72, 96];

    protected string|Closure $unit = 'px';

    protected string $evaluationIdentifier = 'toolbarFontSize';

    protected string $viewIdentifier = 'toolbarFontSize';

    final public function __construct(string $name = 'fontSize')
    {
        $this->name($name);
    }

    public static function make(string $name = 'fontSize'): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function min(int|Closure $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|Closure $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|Closure $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function defaultSize(int|Closure $size): static
    {
        $this->defaultSize = $size;

        return $this;
    }

    public function unit(string|Closure $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /**
     * @param  array<int, int> | Closure  $sizes
     */
    public function sizes(array|Closure $sizes): static
    {
        $this->sizes = $sizes;

        return $this;
    }

    /**
     * The offered sizes, kept inside the bounds the mark clamps to anyway - a menu entry
     * that gets silently corrected on click is a menu entry that lies.
     *
     * @return array<int, int>
     */
    public function getSizes(): array
    {
        $sizes = array_map(intval(...), $this->evaluate($this->sizes));

        return array_values(array_unique(array_filter(
            $sizes,
            fn (int $size): bool => $size >= $this->getMin() && $size <= $this->getMax(),
        )));
    }

    public function getMin(): int
    {
        return (int) $this->evaluate($this->min);
    }

    public function getMax(): int
    {
        return (int) $this->evaluate($this->max);
    }

    public function getStep(): int
    {
        return max(1, (int) $this->evaluate($this->step));
    }

    public function getDefaultSize(): int
    {
        return (int) $this->evaluate($this->defaultSize);
    }

    public function getUnit(): string
    {
        return (string) $this->evaluate($this->unit);
    }

    public function toEmbeddedHtml(): string
    {
        // What the field does - measuring the size at the caret, clamping, putting the
        // selection back and writing the mark - is `resources/js/font-size-picker.js`, an
        // Alpine component loaded with `x-load-src`. It used to be a string inside this
        // `x-data`; the markup now only carries the bounds.
        $config = Js::from([
            'min' => $this->getMin(),
            'max' => $this->getMax(),
            'step' => $this->getStep(),
            'fallback' => $this->getDefaultSize(),
            'unit' => $this->getUnit(),
            'menuPosition' => $this->menuPositionScript(),
            'menuUpClass' => static::MENU_UP_CLASS,
        ])->toHtml();

        $label = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_size.label');
        $default = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_size.default');

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                // An empty value is the default strategy, which loads straight away.
                'x-load' => '',
                'x-load-src' => FilamentAsset::getAlpineComponentSrc('font-size-picker', 'kisame76/filament-advanced-rich-editor'),
                // Escaped for the attribute it lands in: a stray quote in here would end
                // the attribute early and take everything after it with it.
                'x-data' => e("arteFontSizePicker({$config})"),
                // The tick is what makes the number follow the caret: every editor
                // transaction bumps it, and the effect re-reads the mark.
                'x-effect' => 'editorUpdatedAt && sync()',
                'x-on:click.outside' => 'open = false',
                'x-on:keydown.escape.prevent' => 'open = false',
            ], escape: false)
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-fo-rich-editor-dropdown-tool-textual', 'fi-arte-font-size']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php // The whole control is the hit box: clicking anywhere in it lands in the
                  // field, and reaching the field opens the list. A chevron a few pixels
                  // wide is a target, not a control.?>
            <div
                class="fi-fo-rich-editor-tool fi-arte-font-size-control"
                x-on:pointerdown="capture()"
                x-on:click="$refs.size.focus()"
            >
                <?php // The number is the field: typed into where it is read, rather than
                      // in a box somewhere else that repeats it.?>
                <input
                    type="text"
                    inputmode="numeric"
                    aria-label="<?= e($label) ?>"
                    x-ref="size"
                    x-model.number="size"
                    x-on:focus="enter(); $event.target.select()"
                    x-on:input="edited()"
                    x-on:keydown.enter.prevent.stop="apply(size)"
                    x-on:blur="leave()"
                    x-on:keydown.escape.prevent.stop="cancel()"
                    class="fi-arte-font-size-value"
                />

                <button
                    type="button"
                    tabindex="-1"
                    aria-haspopup="true"
                    x-bind:aria-expanded="open"
                    aria-label="<?= e($label) ?>"
                    x-tooltip="{ content: <?= Js::from($label)->toHtml() ?>, theme: $store.theme }"
                    x-on:mousedown.prevent
                    x-ref="trigger"
                    x-on:click.stop="open = ! open; open && positionMenu()"
                    class="fi-arte-font-size-toggle"
                >
                    <svg
                        class="fi-fo-rich-editor-dropdown-tool-chevron"
                        viewBox="0 0 12 12"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path
                            d="M3 4.5 6 7.5l3-3"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </button>
            </div>

            <?php // Pressing in the menu must not take the focus out of the field: the
                  // field applies on the way out, so a blur here would fire first, apply
                  // the size that is already set, spend the remembered selection and close
                  // the menu - and the click everyone was making would land on nothing.
                  // Cancelling mousedown keeps the focus put; the click still arrives.?>
            <div
                x-show="open"
                x-cloak
                role="menu"
                x-on:mousedown.prevent
                x-ref="menu"
                x-bind:class="{ [menuUpClass]: dropUp }"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-font-size-menu"
            >
                <button
                    type="button"
                    tabindex="-1"
                    role="menuitem"
                    x-on:click="clear()"
                    x-bind:class="{ 'fi-active': ! isMarked }"
                    class="fi-fo-rich-editor-dropdown-tool-option fi-arte-font-size-default"
                ><?= e($default) ?></button>

                <?php foreach ($this->getSizes() as $size) { ?>
                    <button
                        type="button"
                        tabindex="-1"
                        role="menuitem"
                        x-on:click="apply(<?= $size ?>)"
                        x-bind:class="{ 'fi-active': isMarked && size === <?= $size ?> }"
                        class="fi-fo-rich-editor-dropdown-tool-option"
                    ><?= $size ?></button>
                <?php } ?>
            </div>
        </div>

        <?php return ob_get_clean();
    }
}
