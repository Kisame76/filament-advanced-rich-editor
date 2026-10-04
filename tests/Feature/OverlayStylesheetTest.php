<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\Mention;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Livewire\Livewire;

/**
 * The package ships two stylesheets, and the line between them is first paint.
 *
 * `filament-advanced-rich-editor.css` holds everything that can be on screen before anybody
 * does anything: a stored document wherever it is drawn - in the editor, in this package's
 * entry and column, in a plain Filament text entry - and the field around it. Filament puts
 * it on every page of the panel, the way it always has, because a document can turn up on
 * any of them.
 *
 * `filament-advanced-rich-editor-overlays.css` holds what only an action opens: the pickers,
 * the menus, the media browser, find and replace, the report. It is registered
 * `loadedOnRequest()`, so a dashboard, a login page or a table never downloads it, and the
 * editor asks for it with `x-load-css` the moment Alpine reaches the field - a request that
 * starts beside the editor's own script, and long before anybody can open anything.
 *
 * What this file guards is the one way the split can go wrong without anything else in the
 * suite noticing: a rule for something PHP draws, moved to the second sheet. The page would
 * still work, and on every load it would show that element unstyled until the sheet arrived
 * - or for ever, on a page with no editor to ask for it.
 */

/**
 * Classes JavaScript draws as soon as the editor starts, before anybody acts.
 *
 * The guard below cannot tell them from a picker - PHP never writes either - so they are
 * named here, and a rule anchored on one of them belongs on the page sheet for the same
 * reason a server-drawn one does: it is on screen the moment the document is.
 *
 * @var array<int, string>
 */
const DRAWN_WHEN_THE_EDITOR_STARTS = [
    // The node views of the document itself.
    'fi-arte-code-block',
    'fi-arte-code-block-language',
    'fi-arte-embed-card',
    'fi-arte-embed-card-provider',
    'fi-arte-embed-card-start',
    'fi-arte-embed-card-title',
    'fi-arte-file-empty',
    'fi-arte-media-block',
    'fi-arte-media-block-empty',
    // Built with the editor and kept hidden by its own rules until a block is hovered: on
    // a sheet that has not arrived yet, the hiding is what would be missing.
    'fi-arte-drag-handle',
    'fi-arte-drag-handle-grip',
    'fi-arte-drag-handle-insert',
    // Offered back on opening, when the browser kept a draft.
    'fi-arte-draft-bar',
    'fi-arte-draft-discard',
    'fi-arte-draft-message',
    'fi-arte-draft-restore',
];

/**
 * What PHP renders only inside a dialog an action opened.
 *
 * Read past by the guard on purpose. These are drawn by the server, but only once somebody
 * has pressed the button that opens them - Filament renders a modal's content when its
 * action is mounted, not with the page - and by then the editor that button belongs to
 * asked for the overlay sheet long ago. The media browser asks for it again itself
 * (asserted below), because it is the one of them a project can place on its own.
 *
 * @var array<int, string>
 */
const RENDERED_ONLY_IN_A_DIALOG = [
    'resources/views/media-picker.blade.php',
    // The media dialog's own form, around the browser: its two hidden upload fields.
    'src/RichEditor/Actions/MediaLibraryAction.php',
    // HelpAction's modal.
    'src/RichEditor/ShortcutTable.php',
    // StatisticsAction's modal.
    'src/RichEditor/StatisticsTable.php',
    // PreviewAction's modal.
    'src/RichEditor/PreviewFrame.php',
];

function overlayPackage(): string
{
    return 'kisame76/filament-advanced-rich-editor';
}

function overlaySheetHref(): string
{
    return FilamentAsset::getStyleHref('filament-advanced-rich-editor-overlays', overlayPackage());
}

function pageSheetHref(): string
{
    return FilamentAsset::getStyleHref('filament-advanced-rich-editor', overlayPackage());
}

/**
 * The stylesheets an element asks Alpine to load, and the one it asks to be placed after.
 *
 * Read with DOMDocument rather than a pattern: the field's markup is full of `x-data`
 * objects whose arrow functions put a `>` in the middle of a start tag.
 *
 * @return array{sheets: array<int, string>, after: ?string}|null
 */
function overlaySheetRequestIn(string $html): ?array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $element = (new DOMXPath($document))->query('//*[@x-load-css]')->item(0);

    if (! $element instanceof DOMElement) {
        return null;
    }

    // `[@js($href)]` is an array of single-quoted JavaScript strings with JSON escapes inside.
    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $element->getAttribute('x-load-css'), $matches);

    return [
        'sheets' => array_map(static fn (string $href): string => json_decode('"'.$href.'"'), $matches[1]),
        'after' => $element->hasAttribute('data-css-after') ? $element->getAttribute('data-css-after') : null,
    ];
}

function renderedEditorHtml(): string
{
    return Livewire::test(EditorFormComponent::class)->html();
}

function renderedMediaBrowserHtml(): string
{
    View::share('errors', new ViewErrorBag);

    return MediaPicker::make('media')
        ->editorKey('editor-key')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit'))
        ->render()
        ->render();
}

/**
 * The selectors of every style rule in a stylesheet, one entry per selector of a list.
 *
 * At-rules are walked into rather than skipped: a rule inside `@media` is still a rule,
 * and the sheet's narrow-screen fallbacks are exactly the ones nobody looks at.
 *
 * @return array<int, string>
 */
function selectorsOf(string $css): array
{
    $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);

    $selectors = [];
    $prelude = '';
    $length = strlen($css);

    for ($i = 0; $i < $length; $i++) {
        $character = $css[$i];

        if ($character === '}' || $character === ';') {
            $prelude = '';

            continue;
        }

        if ($character !== '{') {
            $prelude .= $character;

            continue;
        }

        $prelude = trim($prelude);

        if (str_starts_with($prelude, '@')) {
            // The block holds rules; carry on inside it.
            $prelude = '';

            continue;
        }

        foreach (splitAtTopLevel($prelude, ',') as $selector) {
            $selectors[] = trim($selector);
        }

        // Skip the declarations, nested braces and all.
        for ($depth = 1, $i++; $i < $length && $depth > 0; $i++) {
            $depth += match ($css[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
        }

        $i--;
        $prelude = '';
    }

    return $selectors;
}

/**
 * Splits a selector at a character, but never inside parentheses or brackets - the comma in
 * `:where(.dark, .dark *)` is not the end of a selector.
 *
 * @return array<int, string>
 */
function splitAtTopLevel(string $selector, string $separator): array
{
    $parts = [];
    $current = '';
    $depth = 0;

    foreach (str_split($selector) as $character) {
        $depth += match ($character) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };

        if ($depth === 0 && $character === $separator) {
            $parts[] = $current;
            $current = '';

            continue;
        }

        $current .= $character;
    }

    return [...$parts, $current];
}

/**
 * The classes each compound of a selector cannot match without.
 *
 * Only the top level of a compound counts. A class inside `:where()`, `:not()` or `:has()`
 * is an alternative, an exclusion or a relative, never a requirement of the element itself,
 * and counting it would let a selector look safer than it is - so it is read past, which
 * can only ever make the guard stricter.
 *
 * @return array<int, array<int, string>>
 */
function requiredClassesPerCompound(string $selector): array
{
    // Combinators become spaces; what is left between the spaces is one compound each.
    $flat = '';
    $depth = 0;

    foreach (str_split($selector) as $character) {
        $depth += match ($character) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };

        if ($depth === 0 && in_array($character, ['>', '+', '~'], strict: true)) {
            $character = ' ';
        }

        $flat .= $character;
    }

    $compounds = [];

    foreach (splitAtTopLevel((string) preg_replace('/\s+/', ' ', trim($flat)), ' ') as $compound) {
        // Drop every parenthesised or bracketed part, then read the classes that remain.
        $topLevel = '';
        $depth = 0;

        foreach (str_split($compound) as $character) {
            if ($character === '(' || $character === '[') {
                $depth++;
            }

            if ($depth === 0) {
                $topLevel .= $character;
            }

            if ($character === ')' || $character === ']') {
                $depth--;
            }
        }

        preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $topLevel, $matches);

        $compounds[] = $matches[1];
    }

    return $compounds;
}

/**
 * Every `fi-arte-` name in the files PHP renders from, as written.
 *
 * Read from the files rather than from a render on purpose. A render shows what one
 * configuration draws; the sources are every configuration at once, so a switch that is off
 * in the test cannot hide a class from the guard.
 *
 * @return array<int, string>
 */
function arteNamesInServerSources(): array
{
    $root = dirname(__DIR__, 2);

    $names = [];

    foreach (['src', 'resources/views', 'config'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (in_array(substr($file->getPathname(), strlen($root) + 1), RENDERED_ONLY_IN_A_DIALOG, strict: true)) {
                continue;
            }

            // The lookbehind skips custom properties: `--fi-arte-sticky-offset` is a value,
            // not a class.
            preg_match_all('/(?<![-a-z0-9])fi-arte-[a-z0-9-]+/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[0] as $name) {
                $names[$name] = true;
            }
        }
    }

    ksort($names);

    return array_keys($names);
}

/**
 * Every class PHP can write. A name ending in a hyphen is a prefix whose value is
 * interpolated, and it is spelled out from where that value comes from: taken as it stands
 * it would claim every class that starts with it, and `fi-arte-media-` - the audio and video
 * element - would take the whole media browser along. A prefix a project finishes itself
 * stays a prefix, and claims exactly that.
 *
 * @return array<int, string>
 */
function serverWrittenArteClasses(): array
{
    $classes = [];
    $interpolated = interpolatedArteClassValues();

    foreach (arteNamesInServerSources() as $name) {
        if (! str_ends_with($name, '-')) {
            $classes[] = $name;

            continue;
        }

        // Unknown prefixes claim nothing here; the test below refuses to let one exist.
        if (! array_key_exists($name, $interpolated)) {
            continue;
        }

        if ($interpolated[$name] === null) {
            $classes[] = $name;

            continue;
        }

        foreach ($interpolated[$name] as $value) {
            $classes[] = $name.$value;
        }
    }

    return $classes;
}

/**
 * What each interpolated prefix can become, read from where the value comes from. Null is a
 * prefix a project finishes with names of its own.
 *
 * @return array<string, array<int, string>|null>
 */
function interpolatedArteClassValues(): array
{
    return [
        // Callouts::CLASS_PREFIX - a project adds kinds of its own, under any name.
        'fi-arte-callout-' => null,
        // CharacterCount: the counter's state as it nears and passes the limit.
        'fi-arte-character-count-' => ['warning', 'danger'],
        // Nodes\Media: the element it renders.
        'fi-arte-media-' => MediaUrl::KINDS,
        // TipTapExtensions\Mention: the trigger's name.
        'fi-arte-mention-' => array_values(Mention::TRIGGERS),
        // BuildsTheToolbar: where the groups sit, and which edge the pinned buttons keep.
        'fi-arte-toolbar-align-' => ['start', 'center', 'end', 'between'],
        'fi-arte-toolbar-pin-' => ['start', 'end'],
    ];
}

/**
 * Whether a selector can only ever match something JavaScript built after an action.
 *
 * That is true when one of its compounds needs a class nothing on the server writes and
 * nothing in the editor draws on its own: before then there is no element it could match.
 *
 * @param  array<int, string>  $serverClasses
 */
function needsAnOverlayClass(string $selector, array $serverClasses): bool
{
    foreach (requiredClassesPerCompound($selector) as $classes) {
        foreach ($classes as $class) {
            if (! str_starts_with($class, 'fi-arte-') || in_array($class, DRAWN_WHEN_THE_EDITOR_STARTS, strict: true)) {
                continue;
            }

            $written = array_filter(
                $serverClasses,
                static fn (string $server): bool => str_ends_with($server, '-') ? str_starts_with($class, $server) : $class === $server,
            );

            if ($written === []) {
                return true;
            }
        }
    }

    return false;
}

function overlaySheetSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/filament-advanced-rich-editor-overlays.css');
}

it('leaves the overlay sheet off the pages Filament draws', function (): void {
    expect(FilamentAsset::renderStyles())
        ->toContain(pageSheetHref())
        ->not->toContain(overlaySheetHref());
});

it('asks for the overlay sheet wherever an editor is drawn', function (): void {
    expect(overlaySheetRequestIn(renderedEditorHtml())['sheets'] ?? null)->toBe([overlaySheetHref()]);
});

it('asks for it from the media browser as well', function (): void {
    // The browser opens from an editor, which asked first. Asking again costs nothing -
    // Alpine loads a sheet once per page - and keeps the browser drawn wherever it is used.
    expect(overlaySheetRequestIn(renderedMediaBrowserHtml())['sheets'] ?? null)->toBe([overlaySheetHref()]);
});

it('puts the overlay sheet straight after the page sheet, ahead of the theme', function (string $surface): void {
    // Appended to the end of <head>, the sheet would land after the panel's theme and win
    // every tie against it: a project's override of a picker would stop working the day
    // the picker's rules moved. Placed after the page sheet, it sits exactly where its
    // rules sat while they were part of it.
    $after = overlaySheetRequestIn($surface === 'editor' ? renderedEditorHtml() : renderedMediaBrowserHtml())['after'] ?? null;

    expect($after)->not->toBeNull()
        ->and(str_contains(pageSheetHref(), (string) $after))->toBeTrue('The target does not name the page sheet: '.$after)
        ->and(str_contains(overlaySheetHref(), (string) $after))->toBeFalse('The target names the overlay sheet itself: '.$after);
})->with(['editor', 'media browser']);

it('keeps every rule for what PHP draws on the sheet every page loads', function (): void {
    $serverClasses = serverWrittenArteClasses();

    $misplaced = array_values(array_filter(
        selectorsOf(overlaySheetSource()),
        fn (string $selector): bool => ! needsAnOverlayClass($selector, $serverClasses),
    ));

    expect($misplaced)->toBe([], "These rules style something drawn before anybody acts, and belong in filament-advanced-rich-editor.css:\n".implode("\n", $misplaced));
});

it('knows where every interpolated class comes from', function (): void {
    // A prefix with no entry would claim nothing, and its classes would pass for ones only
    // JavaScript writes.
    $prefixes = array_filter(arteNamesInServerSources(), static fn (string $name): bool => str_ends_with($name, '-'));

    expect(array_values(array_diff($prefixes, array_keys(interpolatedArteClassValues()))))
        ->toBe([], 'A class is built from a prefix the guard cannot resolve - add its values to interpolatedArteClassValues()');
});

it('lists nothing the editor no longer draws', function (): void {
    // A class that left the scripts would leave a hole in this list that the next new one
    // could not be told apart from.
    $scripts = implode("\n", array_map('file_get_contents', glob(dirname(__DIR__, 2).'/resources/js/*.js') ?: []));

    expect(array_values(array_filter(
        DRAWN_WHEN_THE_EDITOR_STARTS,
        fn (string $class): bool => preg_match('/(?<![-a-z0-9])'.preg_quote($class, '/').'(?![a-z0-9-])/', $scripts) !== 1,
    )))->toBe([]);
});

it('reads selectors the way the browser does', function (): void {
    // The guard is only as good as these two readers; a reader that finds nothing would
    // pass every selector as safe.
    expect(selectorsOf('/* a, b */ .a, .b:where(.dark, .dark *) { color: red; } @media (min-width: 1px) { .c > .d { x: y; } }'))
        ->toBe(['.a', '.b:where(.dark, .dark *)', '.c > .d'])
        ->and(requiredClassesPerCompound('.fi-arte .fi-arte-slash-menu > li.fi-active:not(.x):where(.dark, .dark *)'))
        ->toBe([['fi-arte'], ['fi-arte-slash-menu'], ['fi-active']])
        ->and(needsAnOverlayClass('.fi-arte-callout:where(.dark, .dark *)', serverWrittenArteClasses()))->toBeFalse()
        ->and(needsAnOverlayClass('.fi-arte .fi-arte-slash-menu > li', serverWrittenArteClasses()))->toBeTrue()
        ->and(needsAnOverlayClass('.fi-arte-media-video', serverWrittenArteClasses()))->toBeFalse()
        ->and(needsAnOverlayClass('.fi-arte-media-grid', serverWrittenArteClasses()))->toBeTrue()
        ->and(needsAnOverlayClass('.fi-arte-draft-bar', serverWrittenArteClasses()))->toBeFalse();
});

it('watches a sheet that is actually populated', function (): void {
    expect(count(selectorsOf(overlaySheetSource())))->toBeGreaterThan(100);
});
