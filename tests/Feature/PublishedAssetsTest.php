<?php

declare(strict_types=1);

/**
 * Every class the package writes into markup has to exist in the stylesheet it ships.
 *
 * This is not pedantry: the emoji picker, the colour pickers, the image panels, the
 * fullscreen overlay and the shortcut list all shipped at one point with their components
 * finished, their tests green and no rules behind them at all - which the browser draws as
 * a pile of unstyled boxes. Nothing else in the suite can see that, because nothing else
 * looks at the CSS.
 */

/**
 * Classes that are hooks rather than rules: they ride on an element Filament already
 * styles, and exist so a project can reach it from its own theme. A class listed here is a
 * deliberate choice, not a gap.
 *
 * @var array<int, string>
 */
const STYLE_HOOKS = [
    // Dropdown wrappers - the menu inside each one carries the rules.
    'fi-arte-color-picker',
    'fi-arte-font-picker',
    'fi-arte-font-size',
    'fi-arte-image-panel',
    'fi-arte-list-panel',
    // A toolbar button, drawn by Filament's own `fi-fo-rich-editor-tool`.
    'fi-arte-fullscreen-toggle',
    // A second class on the find bar's own row, which `fi-arte-find-row` already styles.
    // It marks the replacing half so a project can reach it, and so the row can be shown
    // and hidden by name.
    'fi-arte-find-replacing',
    // A second class on an input that `fi-arte-image-panel-input` already styles.
    'fi-arte-image-panel-input-text',
    // Written into rendered content rather than into the editor: the anchor marker on a
    // heading and the table of contents both only exist on the page the content ends up
    // on, which this stylesheet is not loaded into. Styling them is the project's own
    // decision, and the class names are configurable for exactly that reason.
    'fi-arte-anchor',
    'fi-arte-toc',
    // The same: a mention inside the editor is drawn by Filament's own
    // `fi-fo-rich-editor-mention`, and this class only ever appears on the rendered page.
    // It exists because nothing else on that page identifies a mention - the sanitiser
    // removes every attribute a mention carries except `data-id` and `data-type`.
    'fi-arte-mention',
    // The wrapper of the infolist entry. What draws the document inside it is Filament's
    // own `fi-prose`, the same class the editor uses; this one is here so a project can
    // reach the entry from its theme without reaching every rendered document.
    'fi-arte-entry',
];

/**
 * Whether the stylesheet defines a rule for exactly this class.
 *
 * A plain substring test would answer yes for `fi-arte-font-size` on the strength of
 * `.fi-arte-font-size-default`, which is a different element entirely.
 */
function styleSheetDefines(string $css, string $class): bool
{
    return preg_match('/\.'.preg_quote($class, '/').'(?![a-z0-9-])/', $css) === 1;
}

/**
 * @return array<int, string>
 */
function emittedArteClasses(): array
{
    $root = dirname(__DIR__, 2);

    $files = [];

    foreach (['src', 'resources/js', 'resources/views'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], strict: true)) {
                $files[] = $file->getPathname();
            }
        }
    }

    $classes = [];

    foreach ($files as $file) {
        // The lookbehind skips custom properties: `--fi-arte-sticky-offset` is a value
        // the markup writes, not a class the stylesheet has to define.
        preg_match_all('/(?<![-a-z0-9])fi-arte-[a-z0-9-]+/', (string) file_get_contents($file), $matches);

        foreach ($matches[0] as $class) {
            // Interpolated prefixes such as "fi-arte-toolbar-align-{$alignment}" arrive
            // here with the variable half missing; the whole names are asserted by the
            // tests that build them.
            if (! str_ends_with($class, '-')) {
                $classes[$class] = true;
            }
        }
    }

    ksort($classes);

    return array_keys($classes);
}

it('styles every class it writes into the markup', function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-advanced-rich-editor.css');

    $missing = array_values(array_filter(
        emittedArteClasses(),
        fn (string $class): bool => (! styleSheetDefines($css, $class)) && (! in_array($class, STYLE_HOOKS, strict: true)),
    ));

    expect($missing)->toBe([], 'These classes are written into the markup but have no rule: '.implode(', ', $missing));
});

it('keeps the published assets identical to the source ones', function (): void {
    // The browser is served the published copies, so a rule - or an extension - that only
    // ever reached `resources/css` and `resources/js` is one nobody gets. There is no build
    // step between the two, only `composer build-assets`, which is exactly why this is
    // asserted rather than assumed.
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/resources/dist/filament-advanced-rich-editor.css'))
        ->toBe(file_get_contents($root.'/resources/css/filament-advanced-rich-editor.css'));

    $sources = glob($root.'/resources/js/*.js') ?: [];

    expect($sources)->not->toBeEmpty();

    foreach ($sources as $source) {
        $published = $root.'/resources/dist/js/'.basename($source);

        expect($published)->toBeReadableFile()
            ->and(file_get_contents($published))->toBe(file_get_contents($source), basename($source).' differs from its published copy');
    }

    // And nothing published that no longer has a source.
    $orphans = array_map('basename', array_diff(
        glob($root.'/resources/dist/js/*.js') ?: [],
        array_map(fn (string $file): string => $root.'/resources/dist/js/'.basename($file), $sources),
    ));

    expect(array_values($orphans))->toBe([]);
});

it('lists no hook that has since been given rules of its own', function (): void {
    // A hook that grew a rule is no longer a hook, and leaving it on the list would hide
    // the next thing that goes missing behind it.
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-advanced-rich-editor.css');

    $stale = array_values(array_filter(
        STYLE_HOOKS,
        fn (string $class): bool => styleSheetDefines($css, $class),
    ));

    expect($stale)->toBe([], 'These are listed as unstyled hooks but the stylesheet defines them: '.implode(', ', $stale));
});

it('watches a list that is actually populated', function (): void {
    // A broken collector would make the coverage test above pass by finding nothing.
    expect(emittedArteClasses())->toHaveCount(count(emittedArteClasses()))
        ->and(count(emittedArteClasses()))->toBeGreaterThan(40)
        ->and(emittedArteClasses())->toContain('fi-arte-emoji-popup')
        ->and(emittedArteClasses())->toContain('fi-arte-task-item-box');
});
