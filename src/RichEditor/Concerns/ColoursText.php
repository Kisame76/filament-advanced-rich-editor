<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Filament\Forms\Components\RichEditor\TextColor;

/**
 * The two colour marks - text and background - and the palettes behind them.
 *
 * Filament ships the text colour and its mark; the background is this package's own. Both
 * read the same palette shape, and both let a project decide whether the picker also offers
 * a free colour or only the named ones.
 */
trait ColoursText
{
    protected bool|Closure|null $hasTextColor = null;

    protected bool|Closure|null $hasTextBackground = null;

    protected bool|Closure|null $hasCustomColors = null;

    /**
     * @var array<string, string> | Closure | null
     */
    protected array|Closure|null $backgroundColors = null;

    /**
     * The swatch dropdown that paints the letters.
     *
     * The palette is Filament's own `textColors()`, so a project configures it once and
     * both the swatches and the stored `data-color` values follow - which is also what
     * keeps a colour dark-mode aware, since a palette entry carries a light and a dark
     * value while a hand-picked one cannot.
     */
    public function textColor(bool|Closure $condition = true): static
    {
        $this->hasTextColor = $condition;

        return $this;
    }

    public function hasTextColor(): bool
    {
        return (bool) ($this->evaluate($this->hasTextColor)
            ?? config('filament-advanced-rich-editor.colors.text', true));
    }

    /**
     * The swatch dropdown that paints behind the letters.
     */
    public function textBackground(bool|Closure $condition = true): static
    {
        $this->hasTextBackground = $condition;

        return $this;
    }

    public function hasTextBackground(): bool
    {
        return (bool) ($this->evaluate($this->hasTextBackground)
            ?? config('filament-advanced-rich-editor.colors.background', true));
    }

    /**
     * @param  array<string, string> | Closure  $colors  label keyed by CSS colour
     */
    public function backgroundColors(array|Closure $colors): static
    {
        $this->backgroundColors = $colors;

        return $this;
    }

    /**
     * The background palette, in the shape the swatch dropdown consumes.
     *
     * @return array<int, array{value: string, label: string, color: string, darkColor: string}>
     */
    public function getBackgroundColors(): array
    {
        $colors = $this->evaluate($this->backgroundColors)
            ?? config('filament-advanced-rich-editor.colors.background_palette')
            ?? [];

        $resolved = [];

        foreach ($colors as $color => $label) {
            // A list rather than a map is accepted too, in which case the colour is its
            // own label - handy for a quick palette of hex values.
            if (is_int($color)) {
                $color = $label;
            }

            $resolved[] = [
                'value' => (string) $color,
                'label' => (string) $label,
                'color' => (string) $color,
                'darkColor' => (string) $color,
            ];
        }

        return $resolved;
    }

    /**
     * The palette both the swatch grid and Filament's own machinery read.
     *
     * Only the default is replaced: a field that configures `textColors()`, or a model
     * that registers them on its rich content attribute, still wins. Filament's default
     * lists every Tailwind hue including nine neutrals that are hard to tell apart in a
     * grid of swatches, which is a fine palette for a labelled select and a poor one here.
     *
     * @return array<string, TextColor>
     */
    public function getTextColors(): array
    {
        if (filled($this->evaluate($this->textColors)) || filled($this->getContentAttribute()?->getTextColors())) {
            return parent::getTextColors();
        }

        $palette = config('filament-advanced-rich-editor.colors.text_palette');

        if (blank($palette)) {
            return parent::getTextColors();
        }

        $colors = [];

        foreach ($palette as $name => $color) {
            // A plain `name => colour` map is accepted too, in which case the colour is
            // used for both themes.
            $colors[$name] = is_array($color)
                ? TextColor::make($color['label'] ?? $name, $color['color'] ?? null, $color['dark'] ?? null)
                : TextColor::make($name, $color);
        }

        return $colors;
    }

    /**
     * The text palette, translated from Filament's own colour objects.
     *
     * @return array<int, array{value: string, label: string, color: string, darkColor: string}>
     */
    public function getTextColorsForPicker(): array
    {
        $resolved = [];

        foreach ($this->getTextColors() as $name => $color) {
            $resolved[] = [
                'value' => (string) $name,
                'label' => (string) ($color->getLabel() ?? $name),
                'color' => (string) $color->getColor(),
                'darkColor' => (string) $color->getDarkColor(),
            ];
        }

        return $resolved;
    }

    public function customColors(bool|Closure $condition = true): static
    {
        $this->hasCustomColors = $condition;

        return $this;
    }

    public function hasCustomColors(): bool
    {
        return (bool) ($this->evaluate($this->hasCustomColors)
            ?? config('filament-advanced-rich-editor.colors.custom', true));
    }
}
