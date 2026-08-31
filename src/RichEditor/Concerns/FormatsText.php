<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Languages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Typography;

/**
 * The formats that apply to a run of text inside a block: direction, language,
 * special characters, letter case, typography and the named styles a theme brings.
 *
 * What these have in common is that they mark a passage rather than a block, and that the
 * set on offer is a per-field answer read from the config unless the field overrules it.
 */
trait FormatsText
{
    protected bool|Closure|null $hasTextDirection = null;

    protected bool|Closure|null $hasLanguages = null;

    /**
     * @var array<mixed>|Closure|null
     */
    protected array|Closure|null $languageOptions = null;

    protected bool|Closure|null $hasCharacters = null;

    protected bool|Closure|null $hasTextCase = null;

    protected bool|Closure|null $hasTypography = null;

    protected string|Closure|null $typographyLanguage = null;

    /**
     * @var array<string, mixed>|Closure|null
     */
    protected array|Closure|null $styles = null;

    protected bool|Closure|null $hasStylePreview = null;

    protected bool|Closure|null $hasLinkAttributes = null;

    /**
     * Whether the link tool offers `rel`, `referrerpolicy`, `hreflang` and an anchor, and
     * whether the schema keeps them.
     *
     * Both halves move together on purpose. A dialog that writes an attribute the schema
     * drops is a dialog that lies, and a schema that keeps one nothing can write is dead
     * weight.
     */
    public function linkAttributes(bool|Closure $condition = true): static
    {
        $this->hasLinkAttributes = $condition;

        return $this;
    }

    public function hasLinkAttributes(): bool
    {
        return (bool) ($this->evaluate($this->hasLinkAttributes) ?? config('filament-advanced-rich-editor.link.attributes') ?? true);
    }

    /**
     * The two direction buttons. Switching them off keeps the `dir` attribute out of the
     * editor's schema, which means a document that already carries one loses it on the next
     * save - the parser only keeps what something declares.
     */
    public function textDirection(bool|Closure $condition = true): static
    {
        $this->hasTextDirection = $condition;

        return $this;
    }

    public function hasTextDirection(): bool
    {
        return (bool) ($this->evaluate($this->hasTextDirection) ?? config('filament-advanced-rich-editor.text_direction') ?? true);
    }

    /**
     * The named styles this field offers, or null to take the project's.
     *
     * An empty array is a field saying it wants none, which is why null rather than `[]` is
     * what means "not answered" - the same distinction `moreTools([])` draws.
     *
     * @param  array<string, mixed>|Closure|null  $styles
     */
    public function styles(array|Closure|null $styles): static
    {
        $this->styles = $styles;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStyles(): ?array
    {
        return $this->evaluate($this->styles);
    }

    /**
     * Whether a passage can be marked as being written in another language.
     */
    public function languages(bool|Closure $condition = true): static
    {
        $this->hasLanguages = $condition;

        return $this;
    }

    public function hasLanguages(): bool
    {
        return (bool) ($this->evaluate($this->hasLanguages) ?? config('filament-advanced-rich-editor.languages.enabled') ?? true);
    }

    /**
     * Which languages the dropdown offers, in order.
     *
     * Either `['fr' => 'Français']` or `['fr']`: a code is its own worst label but is still
     * better than nothing, and a project adding one language should not have to look up how
     * that language spells its own name.
     *
     * @param  array<mixed>|Closure|null  $languages
     */
    public function languageOptions(array|Closure|null $languages): static
    {
        $this->languageOptions = $languages;

        return $this;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function getLanguageOptions(): array
    {
        return Languages::normalize(
            $this->evaluate($this->languageOptions)
                ?? config('filament-advanced-rich-editor.languages.values')
                ?? Languages::VALUES,
        );
    }

    /**
     * The special characters picker. Nothing about it is stored as markup - a dash is a
     * character - so switching it off later leaves every one already written where it is.
     */
    public function characters(bool|Closure $condition = true): static
    {
        $this->hasCharacters = $condition;

        return $this;
    }

    public function hasCharacters(): bool
    {
        return (bool) ($this->evaluate($this->hasCharacters) ?? config('filament-advanced-rich-editor.characters') ?? true);
    }

    /**
     * Changing the case of the selection. Nothing about it is stored as markup - a raised
     * letter is a letter - so switching it off later leaves every word already changed
     * exactly as it is.
     */
    public function textCase(bool|Closure $condition = true): static
    {
        $this->hasTextCase = $condition;

        return $this;
    }

    public function hasTextCase(): bool
    {
        return (bool) ($this->evaluate($this->hasTextCase) ?? config('filament-advanced-rich-editor.text_case') ?? true);
    }

    /**
     * Straight quotes becoming the ones the language uses while they are typed, plus the
     * ellipsis and the dash. Nothing about it is stored as markup - a typographic quote is a
     * character - so switching it off later leaves every quotation already written as it is,
     * which is also the reason it ships off: what it writes is in the document from then on,
     * and turning it off afterwards does not take it back out.
     */
    public function typography(bool|Closure $condition = true): static
    {
        $this->hasTypography = $condition;

        return $this;
    }

    public function hasTypography(): bool
    {
        return (bool) ($this->evaluate($this->hasTypography) ?? config('filament-advanced-rich-editor.typography.enabled') ?? false);
    }

    /**
     * Which language's typography this field writes. The application's locale unless the
     * field says otherwise, which it has to be able to: a site in German may well have one
     * field holding English copy, and the quotation marks belong to the text rather than to
     * the panel around it.
     */
    public function typographyLanguage(string|Closure|null $language): static
    {
        $this->typographyLanguage = $language;

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getTypographySettingsForJs(): ?array
    {
        if (! $this->hasTypography()) {
            return null;
        }

        return Typography::for($this->evaluate($this->typographyLanguage) ?? app()->getLocale());
    }

    /**
     * Whether the editor marks the text a style sits on.
     *
     * Off, and that is the same reasoning the empty styles list follows rather than an
     * oversight. The classes belong to the project, so the look does too, and none of them
     * resolve in an admin panel that has never loaded the front end's stylesheet - a package
     * that invented an appearance here would be putting a design on content it knows nothing
     * about, and getting it wrong.
     *
     * Turned on, styled text gets a neutral marking: a rule down the side of a block, a
     * dotted line under a run of text. It says that something is set without claiming to
     * know what it looks like, and a project's own `[data-style]` rules overrule it.
     */
    public function stylePreview(bool|Closure $condition = true): static
    {
        $this->hasStylePreview = $condition;

        return $this;
    }

    public function hasStylePreview(): bool
    {
        return (bool) ($this->evaluate($this->hasStylePreview)
            ?? config('filament-advanced-rich-editor.style_preview', false));
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraInputAttributes(): array
    {
        $attributes = parent::getExtraInputAttributes();

        if (! $this->hasStylePreview()) {
            return $attributes;
        }

        // On the field's own wrapper rather than on the styled node: the marking is a
        // decision one field makes, and the nodes are shared with every other one.
        $attributes['class'] = trim(($attributes['class'] ?? '').' fi-arte-style-preview');

        return $attributes;
    }
}
