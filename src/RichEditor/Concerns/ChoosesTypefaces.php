<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;

/**
 * The typeface and the size of a run of text.
 *
 * Both are marks rather than blocks, both are drawn from a list a project names, and both
 * store what the list says rather than what the browser resolved - a document that keeps
 * `font-size: 1.25rem` survives a theme that later redefines what large means.
 */
trait ChoosesTypefaces
{
    protected bool|Closure|null $hasFontPicker = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $fonts = null;

    protected bool|Closure|null $hasFontSize = null;

    /**
     * @var array<string, int|Closure|null>
     */
    protected array $fontSizeOptions = [];

    /**
     * The typeface dropdown.
     */
    public function fontPicker(bool|Closure $condition = true): static
    {
        $this->hasFontPicker = $condition;

        return $this;
    }

    public function hasFontPicker(): bool
    {
        return (bool) ($this->evaluate($this->hasFontPicker) ?? config('filament-advanced-rich-editor.fonts.enabled') ?? true);
    }

    /**
     * The typefaces this field offers, as label => CSS stack. Setting them here replaces
     * everything the package would otherwise find: no directory is read, no generic stack
     * is added, and no `@font-face` is written - the field is saying it knows better, and
     * that the fonts it names are already loaded.
     *
     * @param  array<string, string> | Closure | null  $fonts
     */
    public function fonts(array|Closure|null $fonts): static
    {
        $this->fonts = $fonts;

        return $this;
    }

    /**
     * @return array<string, string> | null
     */
    public function getFonts(): ?array
    {
        return $this->evaluate($this->fonts);
    }

    /**
     * The toolbar's font size stepper, and with it the font size mark.
     *
     * Turning it off also unregisters the TipTap extensions, so no size can be applied
     * and none is parsed out of existing content.
     */
    public function fontSize(bool|Closure $condition = true): static
    {
        $this->hasFontSize = $condition;

        return $this;
    }

    public function hasFontSize(): bool
    {
        return (bool) ($this->evaluate($this->hasFontSize)
            ?? config('filament-advanced-rich-editor.font_size.enabled', true));
    }

    /**
     * The bounds of the stepper. Anything left as null keeps the configured default.
     */
    public function fontSizeOptions(
        int|Closure|null $min = null,
        int|Closure|null $max = null,
        int|Closure|null $step = null,
        int|Closure|null $default = null,
    ): static {
        $this->fontSizeOptions = [
            'min' => $min ?? ($this->fontSizeOptions['min'] ?? null),
            'max' => $max ?? ($this->fontSizeOptions['max'] ?? null),
            'step' => $step ?? ($this->fontSizeOptions['step'] ?? null),
            'default' => $default ?? ($this->fontSizeOptions['default'] ?? null),
        ];

        return $this;
    }

    /**
     * @return array{min: int, max: int, step: int, default: int, sizes: array<int, int>}
     */
    public function getFontSizeOptions(): array
    {
        $option = fn (string $key, int $fallback): int => (int) ($this->evaluate($this->fontSizeOptions[$key] ?? null)
            ?? config("filament-advanced-rich-editor.font_size.{$key}", $fallback));

        $min = max(1, $option('min', 8));
        $max = max($min, $option('max', 96));

        $sizes = $this->evaluate($this->fontSizeOptions['sizes'] ?? null)
            ?? config('filament-advanced-rich-editor.font_size.sizes')
            ?? [8, 9, 10, 11, 12, 14, 16, 18, 24, 30, 36, 48, 60, 72, 96];

        return [
            'min' => $min,
            'max' => $max,
            'step' => max(1, $option('step', 1)),
            'default' => min($max, max($min, $option('default', 16))),
            'sizes' => array_values(array_map(intval(...), $sizes)),
        ];
    }
}
