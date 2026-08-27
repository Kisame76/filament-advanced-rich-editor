<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Tiptap\Core\Extension;
use Tiptap\Editor;

/**
 * One extension per name, and what happens when there are two.
 *
 * The field's TipTap editor is an `AdvancedRichContentRenderer` carrying the field's own
 * plugins, and the renderer also declares several of this package's extensions
 * unconditionally - so that a stored document keeps its videos, its callouts and its
 * markings whether or not the render was told about them. Those two lists overlap, and an
 * overlap is not harmless: `tiptap-php` applies both copies rather than letting one win.
 *
 * The damage depends on what the extension is, and the quiet one is the dangerous one. A
 * mark renders a span inside a span and grows another layer on every save. A node picks up
 * a stray closing tag, because `DOMSerializer` breaks out of its opening-tag loop on the
 * first match and out of its closing one never - and a browser drops an unbalanced tag
 * silently, so nothing downstream complains.
 */

/**
 * @return array<string, int>
 */
function extensionCounts(Editor $editor): array
{
    $counts = [];

    foreach ($editor->configuration['extensions'] ?? [] as $extension) {
        $name = ($extension instanceof Extension) ? ($extension::$name ?? null) : null;

        if (is_string($name) && $name !== '') {
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
    }

    return $counts;
}

it('declares no extension twice on a field with everything switched on', function (): void {
    $repeated = array_keys(array_filter(
        extensionCounts(editor()->getTipTapEditor()),
        static fn (int $count): bool => $count > 1,
    ));

    expect($repeated)->toBe([], 'These extensions are declared more than once: '.implode(', ', $repeated));
});

it('writes no stray closing tag for a node a plugin and the renderer both declare', function (): void {
    // The regression this guards. Before the renderer folded repeats together, every save
    // by a field with videos switched on wrote an extra `</div>` after the embed - which a
    // browser drops and a diff does not show, so it went unnoticed for as long as it did.
    $stored = '<div class="fi-arte-embed" data-type="embed" style="aspect-ratio: 16 / 9;">'
        .'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>'
        .'</div>';

    $html = editor()->getTipTapEditor()->setContent($stored)->getHTML();

    expect(substr_count($html, '</div>'))->toBe(1);
});

it('writes no span inside a span for a mark a plugin and the renderer both declare', function (): void {
    $html = editor()->getTipTapEditor()->setContent('<p><span lang="fr">La Peste</span></p>')->getHTML();

    expect($html)->toBe('<p><span lang="fr">La Peste</span></p>');
});

it('keeps the plugin instance rather than the renderer fallback', function (): void {
    // Order matters as much as the folding does: a plugin's instance carries the field's
    // own configuration, and the one declared here is the fallback for a render that was
    // never told anything. The first of a repeat is the one to keep.
    $extensions = AdvancedRichContentRenderer::make()
        ->plugins(editor()->getPlugins())
        ->getTipTapPhpExtensions();

    $callouts = array_values(array_filter(
        $extensions,
        static fn (Extension $extension): bool => $extension::$name === 'callout',
    ));

    expect($callouts)->toHaveCount(1);
});

it('leaves a document that holds nothing of ours untouched', function (): void {
    // The folding must not change what an ordinary paragraph renders as.
    expect(editor()->getTipTapEditor()->setContent('<p>Plain</p>')->getHTML())
        ->toBe('<p>Plain</p>');
});
