<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * The forked editor view has to keep offering the Alpine component exactly what upstream
 * offers it.
 *
 * `resources/views/rich-editor.blade.php` was forked from a Blade file that no longer
 * exists: since v5.7 Filament renders the rich editor from PHP, out of
 * `RichEditor::toEmbeddedHtml()`. The fork kept working because both of them hand
 * `richEditorFormComponent({...})` the same 29 keys - but they do so by hand, and nothing
 * held the two lists against each other. `ToolbarViewTest` only runs `php -l` over the
 * compiled view, so a key added upstream in the next minor would stay green here and
 * arrive in the browser as `undefined`.
 *
 * The fork is not riding a deprecated hook. `ViewComponent::toHtml()` renders `$view`
 * whenever `hasView()` is true, before it ever considers `publishedViewOverrideCheckPath`
 * or `toEmbeddedHtml()` - so a subclass that sets `$view` keeps its Blade file for as long
 * as `ViewComponent` has one. What the fork lost was not its rendering path, only the file
 * it could be diffed against. This test is the replacement for that file.
 */

/**
 * Options that are fed from a different accessor in the fork than upstream, on purpose.
 *
 * Each entry lists the callables that appear on one side and not the other, and every one
 * of them is a consequence of Blade rather than a change of behaviour. Anything not listed
 * here is drift.
 *
 * @var array<string, array{upstream: array<int, string>, fork: array<int, string>}>
 */
const OPTION_ACCESSOR_DIVERGENCES = [
    // Upstream hoists the icon into a local variable above the `ob_start()`; the fork has
    // no such block and calls the helper where the option is written.
    'deleteCustomBlockButtonIconHtml' => ['upstream' => [], 'fork' => ['generate_icon_html']],
    'editCustomBlockButtonIconHtml' => ['upstream' => [], 'fork' => ['generate_icon_html']],
    // Inside the view, `$this` already is the Livewire component that upstream has to
    // reach through `getLivewire()`.
    'livewireId' => ['upstream' => ['getLivewire'], 'fork' => []],
];

/**
 * The body of `RichEditor::toEmbeddedHtml()`, read off the installed Filament.
 *
 * Located by reflection rather than by line number so that the guard survives upstream
 * moving the method around, and fails loudly if it is renamed or removed.
 */
function upstreamEditorSource(): string
{
    $method = new ReflectionMethod(RichEditor::class, 'toEmbeddedHtml');

    $lines = file((string) $method->getFileName());

    if ($lines === false) {
        throw new RuntimeException('Cannot read '.$method->getFileName());
    }

    return implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));
}

function forkedEditorSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/rich-editor.blade.php');
}

/**
 * The literal text between the braces of `richEditorFormComponent({ ... })`.
 *
 * Found by its terminator rather than by counting braces. Both sides write the call inside
 * an `x-data="..."` attribute, so the first `})"` after the opening brace is the end of the
 * object and nothing else can be: an unescaped double quote cannot appear inside the
 * attribute it delimits. A brace counter would be the obvious approach and the wrong one -
 * two of the options are arrow functions with bodies, one value interpolates `{$statePath}`
 * inside a string, and a single upstream option holding an unpaired brace in a literal
 * would silently truncate the list and make this guard report drift that is not there.
 */
function richEditorOptionBlock(string $source): string
{
    $call = strpos($source, 'richEditorFormComponent(');

    if ($call === false) {
        throw new RuntimeException('No richEditorFormComponent() call in this source.');
    }

    $open = strpos($source, '{', $call);

    if ($open === false) {
        throw new RuntimeException('richEditorFormComponent() is not called with an object.');
    }

    $close = strpos($source, '})"', $open);

    if ($close === false) {
        throw new RuntimeException('The richEditorFormComponent() options do not end in an x-data attribute.');
    }

    return substr($source, $open + 1, $close - $open - 1);
}

/**
 * The options of that object, keyed by name, each holding its whole value.
 *
 * A key is a line indented exactly as far as the first one; everything until the next such
 * line belongs to its value, which is how the two arrow functions stay in one piece.
 *
 * Indentation rather than brace depth, for the reason `richEditorOptionBlock()` gives up
 * counting braces: one option holding an unpaired brace in a string literal would put a
 * depth counter permanently off by one and quietly swallow every option after it. Both
 * sides indent their options uniformly and indent an arrow function's body deeper, which
 * is a property of the file rather than of its punctuation.
 *
 * @return array<string, string>
 */
function richEditorOptions(string $source): array
{
    $options = [];
    $indent = null;
    $key = null;
    $value = '';

    foreach (explode("\n", richEditorOptionBlock($source)) as $line) {
        $isKey = preg_match('/^(\s*)([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $matches) === 1
            && (($indent ??= $matches[1]) === $matches[1]);

        if ($isKey) {
            if ($key !== null) {
                $options[$key] = $value;
            }

            $key = $matches[2];
            $value = $matches[3];

            continue;
        }

        if ($key !== null) {
            $value .= "\n".$line;
        }
    }

    if ($key !== null) {
        $options[$key] = $value;
    }

    ksort($options);

    return $options;
}

/**
 * Every callable named in an option's value.
 *
 * `Js::from()` and `@js()` are the same statement in two syntaxes and say nothing about
 * where the value came from, so they are dropped; what is left is the accessor the option
 * is actually fed from.
 *
 * @return array<int, string>
 */
function optionAccessors(string $value): array
{
    preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $value, $matches);

    $accessors = array_values(array_unique(array_diff($matches[1], ['from', 'js'])));

    sort($accessors);

    return $accessors;
}

/**
 * The element classes a source writes, without the ones this package adds itself.
 *
 * @return array<int, string>
 */
function upstreamClasses(string $source): array
{
    preg_match_all('/(?<![-a-z0-9])fi-[a-z0-9-]+/', $source, $matches);

    $classes = array_values(array_unique(array_filter(
        $matches[0],
        fn (string $class): bool => ! str_starts_with($class, 'fi-arte'),
    )));

    sort($classes);

    return $classes;
}

it('offers the Alpine component exactly the options upstream offers it', function (): void {
    $upstream = array_keys(richEditorOptions(upstreamEditorSource()));
    $fork = array_keys(richEditorOptions(forkedEditorSource()));

    $missing = array_values(array_diff($upstream, $fork));
    $extra = array_values(array_diff($fork, $upstream));

    expect($missing)->toBe([], 'Upstream passes these and the fork does not, so they arrive as undefined: '.implode(', ', $missing))
        ->and($extra)->toBe([], 'The fork passes these and upstream does not, so nothing reads them: '.implode(', ', $extra));
});

it('feeds every option from the same accessor upstream feeds it from', function (): void {
    // A matching key list is not enough: upstream can keep the name and change what fills
    // it - `getPlaceholder()` becoming `getPlaceholderForJs()` reaches the browser as a
    // value of the wrong shape, which is worse than a missing one.
    $upstream = richEditorOptions(upstreamEditorSource());
    $fork = richEditorOptions(forkedEditorSource());

    $drifted = [];

    foreach ($upstream as $option => $value) {
        if (! array_key_exists($option, $fork)) {
            continue;
        }

        $allowed = OPTION_ACCESSOR_DIVERGENCES[$option] ?? ['upstream' => [], 'fork' => []];

        $upstreamOnly = array_values(array_diff(optionAccessors($value), optionAccessors($fork[$option]), $allowed['upstream']));
        $forkOnly = array_values(array_diff(optionAccessors($fork[$option]), optionAccessors($value), $allowed['fork']));

        if ($upstreamOnly !== [] || $forkOnly !== []) {
            $drifted[] = $option.' (upstream only: '.(implode(', ', $upstreamOnly) ?: '-').'; fork only: '.(implode(', ', $forkOnly) ?: '-').')';
        }
    }

    expect($drifted)->toBe([], 'These options no longer come from the same place: '.implode(' | ', $drifted));
});

it('lists no accessor divergence that has since gone away', function (): void {
    // A divergence that upstream has caught up with is no longer an exception, and leaving
    // it on the list hides the next real one behind it.
    $upstream = richEditorOptions(upstreamEditorSource());
    $fork = richEditorOptions(forkedEditorSource());

    $stale = [];

    foreach (OPTION_ACCESSOR_DIVERGENCES as $option => $allowed) {
        if (! array_key_exists($option, $upstream) || ! array_key_exists($option, $fork)) {
            $stale[] = $option.' (no longer an option at all)';

            continue;
        }

        $upstreamOnly = array_diff(optionAccessors($upstream[$option]), optionAccessors($fork[$option]));
        $forkOnly = array_diff(optionAccessors($fork[$option]), optionAccessors($upstream[$option]));

        $unused = array_merge(
            array_diff($allowed['upstream'], $upstreamOnly),
            array_diff($allowed['fork'], $forkOnly),
        );

        if ($unused !== []) {
            $stale[] = $option.': '.implode(', ', $unused);
        }
    }

    expect($stale)->toBe([], 'These are listed as deliberate divergences but no longer diverge: '.implode(' | ', $stale));
});

it('renders every element upstream renders', function (): void {
    // The fork carries upstream's panels, floating toolbars and validation messages
    // verbatim. A class added to that markup upstream is an element the fork stopped
    // drawing, which the stylesheet then styles for nobody.
    //
    // The limit, because a guard that promises more than it checks is worse than none:
    // this compares two sets of names, so it sees a class the fork stopped writing
    // altogether and not one it writes in fewer places than upstream does.
    // `fi-fo-rich-editor-panel-heading` appears twice in the fork; deleting one of the two
    // leaves this green.
    $missing = array_values(array_diff(
        upstreamClasses(upstreamEditorSource()),
        upstreamClasses(forkedEditorSource()),
    ));

    expect($missing)->toBe([], 'Upstream draws these and the fork does not: '.implode(', ', $missing));
});

it('is looking at the code it claims to be looking at', function (): void {
    // Every assertion above is satisfied by two empty lists, which is the one way this
    // guard passes while seeing nothing at all.
    $upstream = richEditorOptions(upstreamEditorSource());

    expect($upstream)->toHaveCount(count(richEditorOptions(forkedEditorSource())))
        ->and(count($upstream))->toBeGreaterThan(20)
        ->and($upstream)->toHaveKey('statePath')
        ->and($upstream)->toHaveKey('extensions')
        ->and(upstreamClasses(upstreamEditorSource()))->toContain('fi-fo-rich-editor-toolbar');
});

it('still renders the fork rather than the embedded HTML', function (): void {
    // `ViewComponent::toHtml()` prefers `$view` over `toEmbeddedHtml()`, and the field sets
    // one. If that ever inverts, none of the markup this package forked is on the page and
    // every assertion above is measuring a file nobody renders.
    $field = AdvancedRichEditor::make('content');

    expect($field->hasView())->toBeTrue()
        ->and($field->getView())->toBe('filament-advanced-rich-editor::rich-editor');
});
