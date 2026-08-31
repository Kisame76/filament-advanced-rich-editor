<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageFloat;

/**
 * What a picture may do once it is in the document: sit left or right of the text,
 * carry a link, be marked decorative, or be dragged to a new size.
 *
 * Each is an attribute on the image node rather than a node of its own, which is why each
 * needs its own extension to survive the parser, and why the toolbar's active states are
 * spelled out instead of left to Filament's `isActive()`.
 */
trait PlacesImages
{
    protected bool|Closure|null $hasImageToolbar = null;

    protected bool|Closure|null $hasImageFloat = null;

    protected bool|Closure|null $hasImageDimensions = null;

    protected bool|Closure|null $hasImageDecorative = null;

    protected bool|Closure|null $hasImageLink = null;

    protected string|Closure|false|null $imageLoading = null;

    /**
     * The little toolbar that appears over a selected image.
     */
    public function imageToolbar(bool|Closure $condition = true): static
    {
        $this->hasImageToolbar = $condition;

        return $this;
    }

    public function hasImageToolbar(): bool
    {
        return (bool) ($this->evaluate($this->hasImageToolbar)
            ?? config('filament-advanced-rich-editor.images.toolbar', true));
    }

    /**
     * Whether the text may run past a picture instead of starting below it.
     *
     * The oldest thing anybody has ever asked an editor for, and the one piece of laying a
     * picture out that this package did not have: the size, the rotation and the caption
     * were all already there.
     */
    public function imageFloat(bool|Closure $condition = true): static
    {
        $this->hasImageFloat = $condition;

        return $this;
    }

    public function hasImageFloat(): bool
    {
        return (bool) ($this->evaluate($this->hasImageFloat)
            ?? config('filament-advanced-rich-editor.images.float', true));
    }

    /**
     * Whether a picture may be given an address to point at.
     *
     * Rendered as an `<a>` around the picture, and around the picture inside a `<figure>`
     * where there is a caption: a caption is text about the picture rather than part of what
     * is being linked.
     */
    public function imageLink(bool|Closure $condition = true): static
    {
        $this->hasImageLink = $condition;

        return $this;
    }

    public function hasImageLink(): bool
    {
        return (bool) ($this->evaluate($this->hasImageLink)
            ?? config('filament-advanced-rich-editor.images.link', true));
    }

    /**
     * Whether a picture may be marked as carrying nothing worth describing.
     *
     * A divider, a texture, a flourish beside a heading the words already say. Such a
     * picture wants an empty `alt` and `role="presentation"` together, and the pair is what
     * makes it different from a description somebody forgot - which is the thing the
     * accessibility check cannot tell apart on its own.
     *
     * Off, and the reason is that sentence: the whole of what the mark buys is a check that
     * stops reporting a deliberate empty `alt`, and that check ships off too. A field that
     * has not switched the check on gains a button whose meaning is not on its face and
     * whose effect nobody will see - on a bar that already carries thirteen. Switch this on
     * with the check, which is the only place it pays.
     */
    public function imageDecorative(bool|Closure $condition = true): static
    {
        $this->hasImageDecorative = $condition;

        return $this;
    }

    public function hasImageDecorative(): bool
    {
        return (bool) ($this->evaluate($this->hasImageDecorative)
            ?? config('filament-advanced-rich-editor.images.decorative', false));
    }

    /**
     * Whether an inserted picture is given the size it was measured at.
     *
     * On by default, because the point of it is a browser that leaves the right hole for a
     * picture it has not got yet - without which the article below jumps when it arrives.
     *
     * The catch is worth knowing before turning it off is considered: Filament renders
     * `width` as an inline `style` as well as an attribute, and this package's own resizing
     * drags the same pair, so the measured size is also the displayed size. A page with the
     * usual `img { max-width: 100%; height: auto }` handles that and gains the aspect ratio
     * for nothing; a page that caps the width and lets the height stand gets a squashed
     * picture. Turn this off rather than find that out on the front page.
     */
    public function imageDimensions(bool|Closure $condition = true): static
    {
        $this->hasImageDimensions = $condition;

        return $this;
    }

    public function hasImageDimensions(): bool
    {
        return (bool) ($this->evaluate($this->hasImageDimensions)
            ?? config('filament-advanced-rich-editor.images.dimensions', true));
    }

    /**
     * The loading hint written onto an inserted picture: `lazy`, `eager`, or nothing.
     *
     * Nothing by default, and that is the considered answer rather than the timid one. A
     * field does not know where on a page its pictures land, and `lazy` on the one above the
     * fold delays the very thing it is usually reached for - that picture is generally the
     * largest contentful paint, and telling the browser to wait for it makes the number it
     * is measured by worse. A project that knows its layout says so, per field or in the
     * config; the measured size above is what earns the bulk of the same prize anyway.
     *
     * `false` is the way to say none where the project set one, and null is the way back to
     * whatever the project said - the same two answers `->cached(false)` gives the renderer.
     * Without the first there would be no way to keep a teaser field eager on a project that
     * turned lazy loading on everywhere.
     */
    public function imageLoading(string|Closure|false|null $loading): static
    {
        $this->imageLoading = $loading;

        return $this;
    }

    public function getImageLoading(): ?string
    {
        $loading = $this->evaluate($this->imageLoading);

        if ($loading === false) {
            return null;
        }

        $loading ??= config('filament-advanced-rich-editor.images.loading');

        // Whitelisted here rather than left to whoever writes it down. This is public and
        // says it answers with one of the two hints a browser knows; a typo in the config -
        // `lasy`, or `auto`, which is not a value `loading` has - would otherwise be handed
        // out as though it were one, and every caller would need the same list to be safe.
        return ImageAttributes::loadingHint(is_string($loading) ? $loading : null);
    }

    /**
     * The gap written beside a floated picture, as a CSS length, or null where the project
     * would rather draw it itself.
     *
     * Read here as well as in `ImageFloat` so the editor and the page write the same
     * number: this one reaches the editor as a custom property on the field, the other
     * lands in the stored markup.
     */
    public function getImageFloatGap(): ?string
    {
        return ImageFloat::gap();
    }

    /**
     * Whether an image can be dragged to a new size inside the editor.
     *
     * Filament defaults this to `false`; the package default lives in the config file so
     * that a project can flip it once instead of on every field. A value set on the field
     * still wins.
     */
    public function hasResizableImages(): bool
    {
        $condition = $this->evaluate($this->hasResizableImages);

        if ($condition !== null) {
            return (bool) $condition;
        }

        return (bool) config('filament-advanced-rich-editor.images.resizable', true);
    }
}
