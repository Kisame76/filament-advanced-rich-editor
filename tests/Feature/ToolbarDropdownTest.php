<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

/**
 * A dropdown that opens upwards when there is no room below it.
 *
 * Every menu in this package hangs off its trigger with `position: absolute`, and the
 * editor's content box scrolls its own overflow - so a menu opening low in the editor is
 * cut off by the editor itself. The bar over a selection makes that the normal case rather
 * than the edge case, since it hangs below the text it belongs to.
 *
 * The package's own dropdowns build their own markup and drop `OpensAwayFromTheEdge` into
 * it. This one does not build its markup; Filament does, and the three attributes the trait
 * needs are threaded into what the parent rendered. That is a coupling to upstream's markup,
 * and this is where it is pinned: if Filament renames an anchor the injection silently stops
 * happening, and these are the tests that stop being silent about it.
 */
function listsDropdown(): string
{
    return ToolbarDropdown::lists(['bulletList', 'orderedList'])
        ->resolve(editor()->getTools())
        ->toEmbeddedHtml();
}

it('gives the menu and its trigger the references the measuring needs', function (): void {
    $html = listsDropdown();

    expect($html)->toContain('x-ref="trigger"')
        ->and($html)->toContain('x-ref="menu"');
});

it('carries the trait state beside the parent own', function (): void {
    $html = listsDropdown();

    // Spliced in rather than replacing: the parent's `open` and `triggerContent` have to
    // survive, or the dropdown stops opening at all.
    expect($html)->toContain('x-data="{ open: false, ')
        ->and($html)->toContain('dropUp: false')
        ->and($html)->toContain('positionMenu()')
        ->and($html)->toContain('triggerContent');
});

it('measures once the menu has been opened, not while it is closing', function (): void {
    expect(listsDropdown())->toContain('x-on:click="open = !open; open &amp;&amp; positionMenu()"');
});

it('turns the menu over through the class the stylesheet knows', function (): void {
    $html = listsDropdown();

    // A trait constant cannot be read off the trait, so it is read off a class using it -
    // which is the same value every dropdown in this package binds to.
    $class = ToolbarDropdown::MENU_UP_CLASS;

    expect($html)->toContain('x-bind:class="{ [menuUpClass]: dropUp }"')
        // The binding names the trait's Alpine property; the constant behind it is what the
        // stylesheet is written against. This is the one place the two have to agree.
        // Escaped, because the state is spliced into an attribute the parent already
        // escaped - Alpine reads it back after the browser unescapes it.
        ->and($html)->toContain(e("menuUpClass: '{$class}'"))
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-advanced-rich-editor.css'))
        ->toContain(".fi-fo-rich-editor-dropdown-tool-menu.{$class}");
});

it('caps the menu to the room it has when neither side can hold it whole', function (): void {
    // Turning the menu over only helps where the other side fits it. A field four hundred
    // pixels tall with the bar over a selection in the middle of it has under two hundred
    // above and under two hundred below, and seven languages are taller than either - so
    // without a cap the menu is cut off whichever way it goes, which is what it did.
    $html = listsDropdown();

    expect($html)->toContain('menu.offsetHeight')
        ->and($html)->toContain('maxHeight')
        ->and($html)->toContain('overflowY')
        // Measured unconstrained first, or the cap from the last opening is what gets
        // measured and the menu ratchets shorter every time it is used.
        ->and($html)->toContain(e("menu.style.maxHeight = ''"))
        ->and($html)->toContain((string) ToolbarDropdown::MENU_MIN_HEIGHT)
        ->and($html)->toContain((string) ToolbarDropdown::MENU_MARGIN);
});

it('leaves the parent markup alone when there is nothing to open', function (): void {
    // An empty group renders nothing at all, and threading attributes into nothing would be
    // a string with three orphaned fragments in it.
    expect(ToolbarDropdown::make('Empty', [])->resolve(editor()->getTools())->toEmbeddedHtml())->toBe('');
});

it('injects each anchor exactly once', function (): void {
    $html = listsDropdown();

    expect(substr_count($html, 'x-ref="menu"'))->toBe(1)
        ->and(substr_count($html, 'x-ref="trigger"'))->toBe(1)
        ->and(substr_count($html, 'positionMenu()'))->toBeGreaterThan(0)
        // The options carry their own class and must not have been caught by the menu's.
        ->and(substr_count($html, 'x-bind:class="{ [menuUpClass]: dropUp }"'))->toBe(1);
});

it('reaches every dropdown the package builds, not just the one in the bubble', function (): void {
    // The language dropdown is the one that showed the problem - it sits in the bar over a
    // selection, which already hangs below the text - but the overflow menu and the headings
    // open into the same clipped box.
    $editor = editor();

    foreach ([
        ToolbarDropdown::headings([1, 2]),
        ToolbarDropdown::callouts(['note', 'warning']),
        ToolbarDropdown::languages([['code' => 'fr', 'label' => 'Français']]),
        ToolbarDropdown::more(['code', 'details']),
    ] as $dropdown) {
        expect($dropdown->resolve($editor->getTools())->toEmbeddedHtml())
            ->toContain('x-ref="menu"')
            ->and($dropdown->resolve($editor->getTools())->toEmbeddedHtml())
            ->toContain('x-bind:class="{ [menuUpClass]: dropUp }"');
    }
});
