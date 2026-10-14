<?php

declare(strict_types=1);

/**
 * What a rule costs the page it sits on, as opposed to what it draws.
 *
 * A browser does not try every rule on every element. It files each rule under the last
 * compound of its selector - by id, class, attribute or tag - and an element is only ever
 * tried against the rules filed under something it has. A rule whose last compound names
 * none of those is filed under "anything", and every element on the page is tried against
 * it on every style recalculation.
 *
 * For most pseudo-classes that try is cheap. For `:has()` it is not: answering it means
 * walking the element's children or descendants, and a change anywhere inside them puts
 * the question to every ancestor again. A page of editors changes the inside of its
 * elements constantly while it starts - ProseMirror draws five documents, Alpine shows and
 * hides a few hundred toolbar parts - and the image float rules, written with a bare
 * `:has()`, were where most of the style work of starting those editors went.
 *
 * So a `:has()` always shares its compound with something the rule can be filed under.
 */

/**
 * The compounds of a selector, split at the combinators between them.
 *
 * A combinator inside parentheses, brackets or quotes is part of a compound rather than
 * between two: the `>` in `:has(> img)` and the space in `[style*='float: left']` are not
 * where anything ends.
 *
 * @return array<int, string>
 */
function selectorCompounds(string $selector): array
{
    $compounds = [];
    $current = '';
    $depth = 0;
    $quote = null;

    foreach (str_split(trim($selector)) as $character) {
        if ($quote !== null) {
            $current .= $character;
            $quote = $character === $quote ? null : $quote;

            continue;
        }

        if ($character === "'" || $character === '"') {
            $quote = $character;
            $current .= $character;

            continue;
        }

        $depth += match ($character) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };

        if ($depth === 0 && in_array($character, [' ', '>', '+', '~'], strict: true)) {
            if ($current !== '') {
                $compounds[] = $current;
            }

            $current = '';

            continue;
        }

        $current .= $character;
    }

    return $current === '' ? $compounds : [...$compounds, $current];
}

/**
 * The compounds of a selector that hold a `:has()` and nothing a rule could be filed under:
 * no tag, no class, no id, no attribute.
 *
 * Only the top level of a compound is read. A class inside `:is()` or `:where()` is one of
 * several alternatives, and a rule can only be filed under what every match must have.
 *
 * @return array<int, string>
 */
function unanchoredHasCompounds(string $selector): array
{
    $unanchored = [];

    foreach (selectorCompounds($selector) as $compound) {
        $topLevel = '';
        $depth = 0;
        $quote = null;

        foreach (str_split($compound) as $character) {
            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;
            } elseif ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($depth === 0) {
                $topLevel .= $character;
            }
        }

        if (str_contains($topLevel, ':has') && preg_match('/^[a-zA-Z]|[.#\[]/', $topLevel) !== 1) {
            $unanchored[] = $compound;
        }
    }

    return $unanchored;
}

/**
 * @return array<string, array{string}>
 */
function shippedStylesheetSources(): array
{
    $sheets = [];

    foreach (glob(dirname(__DIR__, 2).'/resources/css/*.css') ?: [] as $path) {
        $sheets[basename($path)] = [$path];
    }

    return $sheets;
}

it('files every :has() under something an element has', function (string $path): void {
    $unanchored = [];

    foreach (selectorsOf((string) file_get_contents($path)) as $selector) {
        if (unanchoredHasCompounds($selector) !== []) {
            $unanchored[] = $selector;
        }
    }

    expect($unanchored)->toBe([]);
})->with(shippedStylesheetSources());

it('tells a :has() that can be filed from one that cannot', function (): void {
    expect(unanchoredHasCompounds('.fi-arte :has(> [data-resize-wrapper] img)'))
        ->toBe([':has(> [data-resize-wrapper] img)'])
        ->and(unanchoredHasCompounds('*:has(.a)'))->toBe(['*:has(.a)'])
        ->and(unanchoredHasCompounds(':is(.a, .b):has(.c)'))->toBe([':is(.a, .b):has(.c)'])
        ->and(unanchoredHasCompounds(".fi-arte [data-resize-container]:has(> [data-resize-wrapper] img[style*='float: left'])"))->toBe([])
        ->and(unanchoredHasCompounds('.fi-modal.fi-modal-open:has(.x) > .y'))->toBe([])
        ->and(unanchoredHasCompounds('figure:has(img)'))->toBe([])
        ->and(unanchoredHasCompounds('#a:has(img)'))->toBe([])
        ->and(unanchoredHasCompounds('.a :where(.dark, .dark *)'))->toBe([])
        // A bracket in a quoted value opens nothing: what follows it is still read.
        ->and(unanchoredHasCompounds("img[style*='rotate('] :has(.a)"))->toBe([':has(.a)']);
});

it('watches every stylesheet the package ships', function (): void {
    expect(array_keys(shippedStylesheetSources()))
        ->toContain('filament-advanced-rich-editor.css', 'filament-advanced-rich-editor-overlays.css');
});
