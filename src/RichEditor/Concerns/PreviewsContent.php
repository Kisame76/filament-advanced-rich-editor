<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;

/**
 * The document as the front end draws it, rather than as the editor does.
 *
 * Every other view this package offers is a view of the editor. The counter, the statistics
 * dialog and the source view all answer questions about the document; none of them answers
 * the one an author actually asks before publishing, which is what it will look like.
 *
 * The panel cannot answer it in place. Its document has already loaded this package's whole
 * stylesheet - the one asset registered without `loadedOnRequest()` - and the content rules
 * in it are deliberately unscoped so that they apply wherever content is rendered. Anything
 * drawn inside that document inherits all of it and is therefore not the front end, whatever
 * it is labelled. The preview is an isolated frame for that reason and not for a better one.
 *
 * Which means the package cannot supply the answer either, only the frame. A front end's CSS
 * is the project's, and this package has said so twice already in writing: `styles` ships
 * empty because "a style is a set of your classes, and none of them resolve in an admin panel
 * that never loaded your front end's stylesheet", and the callout and task list sections tell
 * a project to copy those rules into its own stylesheet. So this is a seam, not a capability.
 * A project names its stylesheets; until one does, there is nothing to preview *with* and the
 * tool is not offered at all - the same answer the media browser gives a field with nothing
 * browsable behind it, and the styles trigger gives a field with no styles.
 */
trait PreviewsContent
{
    protected bool|Closure|null $hasPreview = null;

    /**
     * @var array<int, string>|Closure|null
     */
    protected array|Closure|null $previewStylesheets = null;

    protected string|Closure|null $previewWrapperClass = null;

    /**
     * The switch. On by default, which costs nothing on a field nobody gave a stylesheet:
     * `hasPreviewFrontEnd()` is what decides whether anything is drawn, and this is what
     * takes the tool away from a field that has one and does not want it.
     */
    public function preview(bool|Closure $condition = true): static
    {
        $this->hasPreview = $condition;

        return $this;
    }

    public function hasPreview(): bool
    {
        return (bool) ($this->evaluate($this->hasPreview)
            ?? config('filament-advanced-rich-editor.preview.enabled')
            ?? true);
    }

    /**
     * The stylesheets the frame loads, as URLs the browser can reach.
     *
     * A project's own built stylesheet, whatever it is - the same file the site loads. Not
     * this package's: the frame is meant to show what a reader sees, and smuggling the panel's
     * sheet in would make it show what the editor sees while claiming otherwise. A preview
     * that disagrees with the page is worse than none, because it is believed.
     *
     * That has a consequence worth stating rather than discovering: a stylesheet built by a
     * purging Tailwind may not carry this package's class names at all, because they live in
     * this package's config rather than in the project's templates. The frame then shows the
     * document nearly unstyled - which is not the preview being wrong, it is the preview
     * being right about a stylesheet that is missing the rules.
     *
     * @param  array<int, string>|Closure|null  $stylesheets
     */
    public function previewStylesheets(array|Closure|null $stylesheets): static
    {
        $this->previewStylesheets = $stylesheets;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getPreviewStylesheets(): array
    {
        $stylesheets = $this->evaluate($this->previewStylesheets)
            ?? config('filament-advanced-rich-editor.preview.stylesheets')
            ?? [];

        if (! is_array($stylesheets)) {
            return [];
        }

        // Trimmed and emptied out, because a blank entry is a `<link href="">` - which the
        // browser resolves against the frame's own address and fetches, and a preview has no
        // business making a request nobody asked for.
        return array_values(array_filter(
            array_map(static fn (mixed $url): string => is_string($url) ? trim($url) : '', $stylesheets),
            static fn (string $url): bool => $url !== '',
        ));
    }

    /**
     * The class the frame's `<body>` carries.
     *
     * On the body rather than on a wrapper inside it, and that is the whole reason this key
     * exists as well as the stylesheets. A stylesheet on its own draws almost nothing on a
     * modern front end: rendered content usually sits inside a container that sets the
     * measure and inside a `prose` class that styles the tags at all, and a dark theme is
     * usually a class on an ancestor. One string on the body covers all three at once -
     * `'prose dark:prose-invert mx-auto max-w-2xl'` - where a class on an inner wrapper
     * could not carry the theme.
     */
    public function previewWrapperClass(string|Closure|null $class): static
    {
        $this->previewWrapperClass = $class;

        return $this;
    }

    public function getPreviewWrapperClass(): ?string
    {
        $class = $this->evaluate($this->previewWrapperClass)
            ?? config('filament-advanced-rich-editor.preview.wrapper_class');

        return (is_string($class) && trim($class) !== '') ? trim($class) : null;
    }

    /**
     * Whether the tool is offered at all, which is two questions rather than one: the switch,
     * and whether a project named anything to draw the document with.
     *
     * Asked in one place because two readers need the same answer and neither is allowed to
     * be almost right - `ToolbarLayout` decides whether the button is registered, and the
     * field decides whether the plugin behind it is. The second question is the honest one: a
     * frame with no stylesheet shows unstyled HTML, and a button labelled "preview" that
     * opens onto that is a button that lies about what it did.
     */
    public function hasPreviewFrontEnd(): bool
    {
        return $this->hasPreview() && $this->getPreviewStylesheets() !== [];
    }
}
