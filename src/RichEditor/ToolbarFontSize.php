<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Closure;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;

/**
 * The toolbar's font size stepper: minus, an editable size, plus.
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

    protected int|Closure $min = 8;

    protected int|Closure $max = 96;

    protected int|Closure $step = 1;

    protected int|Closure $defaultSize = 16;

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
        $unit = $this->getUnit();

        $alpine = Js::from([
            'min' => $this->getMin(),
            'max' => $this->getMax(),
            'step' => $this->getStep(),
            'fallback' => $this->getDefaultSize(),
            'unit' => $unit,
        ])->toHtml();

        // `size` mirrors the size at the caret; `apply()` is the only writer, so a value
        // typed into the input goes through the same clamping as the buttons.
        //
        // Text without an explicit size still has one - the theme's. Showing a guess
        // instead would make the first step go the wrong way: with prose at 14px, a
        // stepper claiming 16px turns a click on minus into 15px, which is larger than
        // what the user was looking at. So the size is measured off the rendered node
        // whenever the mark itself has nothing to say, which also makes the stepper
        // report a heading's size while the caret sits inside one.
        $xData = <<<JS
            {
                size: {$this->getDefaultSize()},
                ...{$alpine},
                measure() {
                    const editor = \$getEditor()

                    if (! editor?.view) {
                        return null
                    }

                    try {
                        const { node, offset } = editor.view.domAtPos(editor.state.selection.from)
                        let element = node

                        if (element?.nodeType === Node.TEXT_NODE) {
                            element = element.parentElement
                        } else if (element?.childNodes?.length) {
                            element = element.childNodes[Math.min(offset, element.childNodes.length - 1)] ?? element
                        }

                        while (element && element.nodeType !== Node.ELEMENT_NODE) {
                            element = element.parentElement
                        }

                        const measured = element ? Number.parseFloat(window.getComputedStyle(element).fontSize) : NaN

                        return Number.isFinite(measured) ? Math.round(measured) : null
                    } catch (error) {
                        return null
                    }
                },
                sync() {
                    const marked = Number.parseFloat(\$getEditor()?.getAttributes('fontSize')?.size)

                    if (Number.isFinite(marked)) {
                        this.size = Math.round(marked)

                        return
                    }

                    this.size = this.measure() ?? this.fallback
                },
                apply(value) {
                    const parsed = Number.parseFloat(value)
                    const current = Number.isFinite(parsed) ? parsed : (this.measure() ?? this.fallback)
                    const next = Math.min(this.max, Math.max(this.min, Math.round(current)))

                    this.size = next

                    \$getEditor()?.chain().focus().setFontSize(next + this.unit).run()
                },
            }
            JS;

        $label = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_size.label');
        $decrease = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_size.decrease');
        $increase = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_size.increase');

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-data' => $xData,
                // The tick is what makes the number follow the caret: every editor
                // transaction bumps it, and the effect re-reads the mark.
                'x-effect' => 'editorUpdatedAt && sync()',
                'role' => 'group',
                'aria-label' => e($label),
            ], escape: false)
            ->class(['fi-arte-font-size']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <button
                type="button"
                tabindex="-1"
                aria-label="<?= e($decrease) ?>"
                x-tooltip="{ content: <?= Js::from($decrease)->toHtml() ?>, theme: $store.theme }"
                x-on:click="apply(size - step)"
                x-bind:disabled="size <= min"
                class="fi-arte-font-size-btn"
            >&minus;</button>

            <input
                type="text"
                inputmode="numeric"
                aria-label="<?= e($label) ?>"
                x-model.number="size"
                x-on:change="apply(size)"
                x-on:blur="apply(size)"
                x-on:keydown.enter.prevent.stop="apply(size)"
                x-on:keydown.arrow-up.prevent="apply(size + step)"
                x-on:keydown.arrow-down.prevent="apply(size - step)"
                class="fi-arte-font-size-input"
            />

            <span class="fi-arte-font-size-unit"><?= e($unit) ?></span>

            <button
                type="button"
                tabindex="-1"
                aria-label="<?= e($increase) ?>"
                x-tooltip="{ content: <?= Js::from($increase)->toHtml() ?>, theme: $store.theme }"
                x-on:click="apply(size + step)"
                x-bind:disabled="size >= max"
                class="fi-arte-font-size-btn"
            >+</button>
        </div>

        <?php return ob_get_clean();
    }
}
