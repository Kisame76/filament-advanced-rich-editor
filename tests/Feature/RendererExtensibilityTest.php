<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Contracts\TransformsRenderedHtml;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CalloutPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Anchor;
use Tiptap\Core\Extension;
use Tiptap\Core\Node;

/**
 * The half of a plugin this package owns.
 *
 * The editor half is Filament's and was already open. What was closed is rendering: a node
 * that renders in the form and vanishes on the page, because the renderer builds its own
 * extension list and the call this package is built around - `make($content)->toHtml()` with
 * nothing else said - was never told about the plugin.
 */
class SpyPlugin implements RichContentPlugin
{
    public function __construct(public string $marker = 'spy') {}

    public static function make(): static
    {
        return app(static::class);
    }

    /** @return array<Extension> */
    public function getTipTapPhpExtensions(): array
    {
        return [app(Anchor::class)];
    }

    /** @return array<string> */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /** @return array<RichEditorTool> */
    public function getEditorTools(): array
    {
        return [];
    }

    /** @return array<Action> */
    public function getEditorActions(): array
    {
        return [];
    }
}

class MarkingPlugin extends SpyPlugin implements TransformsRenderedHtml
{
    public function transformRenderedHtml(string $html): string
    {
        return str_replace('</p>', '<span class="'.$this->marker.'"></span></p>', $html);
    }
}

afterEach(function (): void {
    // Static state outlives a test, and a plugin left registered would quietly change every
    // render in every test after this file.
    AdvancedRichContentRenderer::forgetExtensions();
});

it('runs a registered pass on a render nobody configured', function (): void {
    // The whole point. This is the call a front end makes.
    AdvancedRichContentRenderer::extendWith(new MarkingPlugin);

    expect(AdvancedRichContentRenderer::make('<p>Hallo</p>')->toHtml())
        ->toContain('class="spy"');
});

it('leaves rendering alone until something is registered', function (): void {
    expect(AdvancedRichContentRenderer::make('<p>Hallo</p>')->toHtml())
        ->not->toContain('class="spy"');
});

it('registers a plugin once however often a service provider names it', function (): void {
    // Service providers run in an order nobody controls, and a plugin declared in two of
    // them should not render its node twice.
    AdvancedRichContentRenderer::extendWith(new MarkingPlugin, new MarkingPlugin);

    expect(AdvancedRichContentRenderer::getGlobalPlugins())->toHaveCount(1)
        ->and(substr_count(AdvancedRichContentRenderer::make('<p>Hallo</p>')->toHtml(), 'class="spy"'))
        ->toBe(1);
});

it('runs a plugin once when it is both registered and handed over', function (): void {
    // The case the test above does not reach, and the one that actually happened: a package
    // registers its plugin in a service provider, a field hands the same plugin to its own
    // render, and a pass that wraps what it finds wrapped it inside a second copy of itself.
    AdvancedRichContentRenderer::extendWith(new MarkingPlugin);

    $html = AdvancedRichContentRenderer::make('<p>Hallo</p>')
        ->plugins([new MarkingPlugin])
        ->toHtml();

    expect(substr_count($html, 'class="spy"'))->toBe(1);
});

it('lets a render keep its own copy of a plugin', function (): void {
    // The render's own comes first, and an instance carries configuration - the same rule
    // the extension list applies when it keeps the first of a name.
    AdvancedRichContentRenderer::extendWith(new MarkingPlugin('registered'));

    $plugins = AdvancedRichContentRenderer::make('<p>Hallo</p>')
        ->plugins([new MarkingPlugin('handed')])
        ->getPlugins();

    expect($plugins[0]->marker)->toBe('handed');
});

it('hands a registered plugin its extensions as well', function (): void {
    // A plugin is not only a pass: what it declares has to reach the schema too, or the node
    // it adds is parsed away before any pass sees it.
    AdvancedRichContentRenderer::extendWith(new SpyPlugin);

    $names = array_map(
        static fn (Extension $extension): string => $extension::class,
        AdvancedRichContentRenderer::make('<p>Hallo</p>')->getTipTapPhpExtensions(),
    );

    expect($names)->toContain(Anchor::class);
});

it('tells two cached renders apart by what was registered', function (): void {
    // A plugin that only transforms markup declares no extension, so the extension list the
    // fingerprint is built from cannot see it. Without this the first render of a page would
    // be handed back to every reader after the plugin was switched on.
    $plain = AdvancedRichContentRenderer::make('<p>Hallo</p>')->getRenderFingerprint();

    AdvancedRichContentRenderer::extendWith(new MarkingPlugin);

    expect(AdvancedRichContentRenderer::make('<p>Hallo</p>')->getRenderFingerprint())
        ->not->toBe($plain);
});

it('runs the pass after this package has finished with the markup', function (): void {
    // A pass that arrived halfway through would have the order of `toUnsafeHtml()` as its
    // problem. What it gets is the finished document.
    AdvancedRichContentRenderer::extendWith(new class extends SpyPlugin implements TransformsRenderedHtml
    {
        public function transformRenderedHtml(string $html): string
        {
            // The caption pass has already built the figure by the time this runs. Said
            // with a class rather than a comment: the sanitiser strips comments, and this
            // test is about the order of the passes rather than about what survives it.
            return str_contains($html, '<figure') ? $html.'<p class="saw-the-figure"></p>' : $html;
        }
    });

    expect(AdvancedRichContentRenderer::make('<p><img src="/a.png" data-caption="Katze" /></p>')->toHtml())
        ->toContain('saw-the-figure');
});

/**
 * The promise, end to end: a node a foreign package adds renders on the page.
 *
 * `data-type` is used to carry what it is, because that is one of the handful of attributes
 * Filament's sanitiser keeps on any element - the same one the callouts and the embeds ride
 * on. Which is the other half of this story and the reason it is written here rather than in
 * a sentence: opening the renderer is not enough on its own if the markup a node renders
 * cannot survive the sanitiser. A package needing more than the allow list widens it from its
 * own service provider with `$this->app->extend(HtmlSanitizerConfig::class, ...)`, which
 * composes rather than replaces - so this package's embed rule still applies afterwards.
 * That was already possible and needed nothing built here.
 */
class AsideNode extends Node
{
    public static $name = 'arteTestAside';

    public function parseHTML(): array
    {
        return [['tag' => 'div[data-type="aside"]']];
    }

    public function renderHTML($node, $HTMLAttributes = []): array
    {
        return ['div', ['data-type' => 'aside'], 0];
    }
}

class AsidePlugin extends SpyPlugin
{
    public function getTipTapPhpExtensions(): array
    {
        return [app(AsideNode::class)];
    }
}

it('renders a node a foreign package added, on a render nobody configured', function (): void {
    // The bug this seam exists to prevent: a node that renders in the form and vanishes on
    // the page, because the renderer builds its own extension list and was never told.
    $html = '<div data-type="aside">Ein fremder Knoten</div>';

    expect(AdvancedRichContentRenderer::make($html)->toHtml())
        ->not->toContain('data-type="aside"');

    AdvancedRichContentRenderer::extendWith(new AsidePlugin);

    expect(AdvancedRichContentRenderer::make($html)->toHtml())
        ->toContain('data-type="aside"')
        ->toContain('Ein fremder Knoten');
});

/**
 * Two callers naming the same plugin.
 *
 * The ordinary case rather than the odd one: a field registers what its switches turned on,
 * and a project hands the same renderer a list through `configureRenderer()` because that is
 * how a page describes itself. Neither knows about the other, and `getPlugins()` above is
 * where the two are reconciled. Written down here because the reconciliation is easy to
 * mistake for an accident and delete - it is what stops a `TransformsRenderedHtml` pass from
 * running twice and wrapping its own output.
 */
it('registers a plugin once however many callers name it', function (): void {
    $renderer = AdvancedRichContentRenderer::make()
        ->plugins([TaskListPlugin::make()])
        ->plugins([TaskListPlugin::make(), CalloutPlugin::make()]);

    expect($renderer->getPlugins())->toHaveCount(2)
        ->and(array_map('get_class', array_values($renderer->getPlugins())))
        ->toBe([TaskListPlugin::class, CalloutPlugin::class]);
});

it('keeps the order the first caller assembled, because that is the order the browser gets', function (): void {
    // The extensions reach the browser in registration order, so a second mention of a
    // plugin must not move it to the back of the list.
    $renderer = AdvancedRichContentRenderer::make()
        ->plugins([TaskListPlugin::make(), CalloutPlugin::make()])
        ->plugins([TaskListPlugin::make()]);

    expect(array_map('get_class', array_values($renderer->getPlugins())))
        ->toBe([TaskListPlugin::class, CalloutPlugin::class]);
});

it('does not let a field and its renderer configuration register the same plugin twice', function (): void {
    // The path that made this worth pinning: `getTipTapEditor()` runs through
    // `getRichContentRenderer()`, so a project's `configureRenderer()` closure reaches the
    // schema a save is parsed through. That is wanted - a node the renderer draws and the
    // parser strips disappears on the next save - and it means a field and a page routinely
    // name the same plugin.
    $plain = editor();
    $configured = editor()->configureRenderer(
        fn (AdvancedRichContentRenderer $renderer) => $renderer->plugins([TaskListPlugin::make()]),
    );

    expect($configured->getRichContentRenderer()->getPlugins())
        ->toHaveCount(count($plain->getRichContentRenderer()->getPlugins()));
});

it('still adds a plugin the field did not have', function (): void {
    // The other direction, and the one worth guarding: collapsing by class must not become
    // dropping. Silently losing what a closure adds is a worse fault than counting a plugin
    // twice, because the missing node reaches the parser and is stripped on the next save.
    $plain = editor()->taskList(false);
    $configured = editor()->taskList(false)->configureRenderer(
        fn (AdvancedRichContentRenderer $renderer) => $renderer->plugins([TaskListPlugin::make()]),
    );

    expect($configured->getRichContentRenderer()->getPlugins())
        ->toHaveCount(count($plain->getRichContentRenderer()->getPlugins()) + 1);
});
