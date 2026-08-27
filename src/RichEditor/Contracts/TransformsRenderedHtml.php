<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Contracts;

/**
 * A plugin that wants a look at the finished markup.
 *
 * The half of rendering a schema cannot do. An attribute travels through the document and
 * out the other side as an attribute; what it cannot do is become a structure - a `<figure>`
 * around a picture, an `<a>` around it, a `<colgroup>` in front of a table. This package
 * needs that four times over and does it with passes over the rendered markup, and a plugin
 * adding a node of its own needs it for exactly the same reasons.
 *
 * Implement this alongside `RichContentPlugin` and the pass runs on every render the plugin
 * is part of, whether it was handed to that render or registered once with
 * `AdvancedRichContentRenderer::extendWith()`.
 *
 * Two things to know about when it runs:
 *
 *  - after this package's own passes and after the code highlighting, so what arrives is the
 *    finished markup rather than a half-built version of it;
 *  - before the sanitiser, which is the point. Markup produced here goes through the same
 *    door as everything else, so a pass cannot put an attribute on a page that a document
 *    could not have carried there itself. A pass that needs one anyway widens the sanitiser
 *    the way this package widens it for embeds - `$this->app->extend(HtmlSanitizerConfig::class, ...)`
 *    in a service provider, which composes rather than replaces.
 */
interface TransformsRenderedHtml
{
    /**
     * The markup, changed or handed straight back.
     *
     * Called on every render, so a pass that has nothing to do in a given document should
     * say so cheaply - a `str_contains()` for the attribute it acts on, before any parsing,
     * is what the passes in this package open with.
     */
    public function transformRenderedHtml(string $html): string;
}
