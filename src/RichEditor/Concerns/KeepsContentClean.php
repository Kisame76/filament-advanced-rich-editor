<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\PasteCleanupPlugin;

/**
 * The two guards over what enters the document: the paste cleaner and the
 * accessibility check.
 *
 * One works on the way in and stores nothing of its own; the other works on what is already
 * there and marks nothing. Both are pure inspection, so a field that switches either off
 * keeps every document it ever wrote.
 */
trait KeepsContentClean
{
    protected bool|Closure|null $hasAccessibility = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $accessibilityRules = null;

    protected bool|Closure|null $hasPasteCleanup = null;

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $pasteKeepStyles = null;

    /**
     * The check that reads the document and says what is wrong with it.
     *
     * Six questions, and they are the six a person writing an article can answer and nobody
     * downstream can: a picture nobody described, a link that says "click here", a heading
     * level jumped over, a table with no header row, a link with nothing in it, and a colour
     * that cannot be read on the page it is going to.
     *
     * Shipped off, and switched on per field or in the config. Two reasons, and the second
     * is the real one: it is a review tool rather than a way of writing, so it belongs on
     * the bar of the fields a project decided it belongs on - and the contrast rule is
     * measured against a page this package has to be told the colour of. Shipped on, every
     * project whose pages are not white would be handed findings that are wrong, which is
     * the surest way to teach somebody to stop reading a panel.
     *
     * Nothing about it is stored - a check marks no document - so switching it on and off
     * changes nothing that was written either way.
     */
    public function accessibility(bool|Closure $condition = true): static
    {
        $this->hasAccessibility = $condition;

        return $this;
    }

    public function hasAccessibility(): bool
    {
        return (bool) ($this->evaluate($this->hasAccessibility) ?? config('filament-advanced-rich-editor.accessibility.enabled') ?? false);
    }

    /**
     * Which of the six are asked. A rule left out is not reported and not counted.
     *
     * @param  array<int, string> | Closure  $rules
     */
    public function accessibilityRules(array|Closure $rules): static
    {
        $this->accessibilityRules = $rules;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAccessibilityRules(): array
    {
        $rules = $this->evaluate($this->accessibilityRules)
            ?? config('filament-advanced-rich-editor.accessibility.rules')
            ?? AccessibilityPlugin::RULES;

        return array_values(array_filter(
            (array) $rules,
            static fn (mixed $rule): bool => is_string($rule) && in_array($rule, AccessibilityPlugin::RULES, strict: true),
        ));
    }

    /**
     * The palette as three channels rather than as names.
     *
     * Filament stores `data-color="ink"` and not the colour, which is the right way round
     * and leaves the browser with a name it cannot turn into a contrast ratio. Only the
     * light half crosses over: a document rendered in both themes is two questions, and
     * answering one of them twice would be a panel listing everything twice.
     *
     * @return array<string, string>
     */
    public function getAccessibilityPalette(): array
    {
        $palette = [];

        foreach ($this->getTextColorsForPicker() as $color) {
            if (filled($color['color'])) {
                $palette[$color['value']] = $color['color'];
            }
        }

        return $palette;
    }

    /**
     * What the extension reads off the editor element. Null while the check is switched off,
     * which is also when the extension that would read it was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getAccessibilitySettingsForJs(): ?array
    {
        if (! $this->hasAccessibility()) {
            return null;
        }

        return AccessibilityPlugin::getSettings([
            'rules' => $this->getAccessibilityRules(),
            'weakPhrases' => AccessibilityPlugin::getWeakPhrases(),
            'threshold' => (float) (config('filament-advanced-rich-editor.accessibility.threshold') ?? 4.5),
            'largeThreshold' => (float) (config('filament-advanced-rich-editor.accessibility.large_threshold') ?? 3.0),
            // What the editor cannot know, because both belong to the front end: the colour
            // a page is, and the colour it writes on it where nobody chose one.
            'background' => (string) (config('filament-advanced-rich-editor.accessibility.background') ?? '#ffffff'),
            'text' => (string) (config('filament-advanced-rich-editor.accessibility.text') ?? '#18181b'),
            'palette' => $this->getAccessibilityPalette(),
        ]);
    }

    /**
     * Cleaning what arrives from the clipboard.
     *
     * Word puts a stylesheet, a handful of tags no browser has heard of and a list that is
     * not a list onto the clipboard alongside the paragraph; Google Docs puts every run of
     * text in a span carrying eleven declarations, one of which is the only place its bold
     * lives. Both are turned back into a document on the way in - structure kept, typography
     * dropped - and a copy from another editor is left exactly as it is.
     *
     * Nothing about it is stored, so switching it off changes the next paste and no document
     * that was ever written with it.
     */
    public function pasteCleanup(bool|Closure $condition = true): static
    {
        $this->hasPasteCleanup = $condition;

        return $this;
    }

    public function hasPasteCleanup(): bool
    {
        return (bool) ($this->evaluate($this->hasPasteCleanup) ?? config('filament-advanced-rich-editor.paste.cleanup') ?? true);
    }

    /**
     * The style properties a cleaned paste keeps.
     *
     * Shipped as the alignment and nothing else, which is the one thing in Word's `style`
     * whose absence a reader would notice and the one this package has no other way to
     * carry. Everything else there - the font, the size, the colour, the line height - is
     * parsed into a mark of this package's own, so a property left standing is not noise the
     * next save drops: it is Calibri 11pt in black, in the document, for good.
     *
     * A project that wants a paste to arrive wearing its colours names them here. Naming a
     * property also takes it out of the promotion to tags, because `font-weight` kept is a
     * style somebody wants rather than a `<strong>` and a style.
     *
     * @param  array<int, string> | Closure  $properties
     */
    public function pasteKeepStyles(array|Closure $properties): static
    {
        $this->pasteKeepStyles = $properties;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getPasteKeepStyles(): array
    {
        $properties = $this->evaluate($this->pasteKeepStyles)
            ?? config('filament-advanced-rich-editor.paste.keep_styles')
            ?? PasteCleanupPlugin::DEFAULT_KEEP_STYLES;

        $kept = [];

        foreach ((array) $properties as $property) {
            // A published config file is hand-written, and a stray null or number in this
            // list is a typo rather than a property: dropped here, because the alternative
            // is a TypeError out of a form that was only being rendered.
            if (! is_string($property)) {
                continue;
            }

            $property = strtolower(trim($property));

            if ($property !== '') {
                $kept[] = $property;
            }
        }

        return $kept;
    }

    /**
     * What the extension reads off the editor element. Null while the cleaning is switched
     * off, which is also when the extension that would read it was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getPasteSettingsForJs(): ?array
    {
        return $this->hasPasteCleanup() ? PasteCleanupPlugin::getSettings($this->getPasteKeepStyles()) : null;
    }
}
