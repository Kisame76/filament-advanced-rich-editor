<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\Js;
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

it('hands itself to the module that measures, rather than carrying the measuring', function (): void {
    // Turning and capping are `resources/js/menu-position.js`, tested under `tests/js`. They
    // used to be inlined here, in every menu of every editor - two dozen copies of the same
    // few kilobytes per field. What the dropdown has to get right is calling the module with
    // itself, at the address the service provider registered it under.
    $module = Js::from(FilamentAsset::getScriptSrc('advanced-rich-editor/menu-position', 'kisame76/filament-advanced-rich-editor'))->toHtml();

    expect(listsDropdown())
        ->toContain(e("import({$module})"))
        ->toContain('positionMenu(this)');
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

/**
 * The icons of a dropdown's options, as the menu draws them.
 *
 * @return array<string, string>
 */
function optionIcons(ToolbarDropdown $dropdown): array
{
    $icons = [];

    foreach ($dropdown->getResolvedButtons() as $button) {
        $icons[$button->getName()] = generate_icon_html($button->getIcon(), alias: $button->getIconAlias())?->toHtml() ?? '';
    }

    return $icons;
}

/**
 * The trigger's effect as Alpine reads it, once the browser has unescaped the attribute.
 */
function triggerEffect(string $html): string
{
    preg_match('/x-effect="([^"]*)"/', $html, $effect);

    return html_entity_decode($effect[1] ?? '', ENT_QUOTES);
}

it('draws each option icon once, in the menu, and not again for the trigger', function (): void {
    // Filament's trigger effect carries every option's icon as a string, so the trigger can
    // show the one the caret is in - a second copy of what the menu right below it already
    // holds, seven dropdowns and 17 KB per editor. The trigger reads it out of the menu now.
    foreach ([
        ToolbarDropdown::headings([1, 2, 3]),
        ToolbarDropdown::lists(['bulletList', 'orderedList']),
        ToolbarDropdown::callouts(['note', 'tip', 'warning']),
    ] as $dropdown) {
        $html = $dropdown->resolve(editor()->getTools())->toEmbeddedHtml();
        $effect = triggerEffect($html);
        // The trigger's own icon, which it rests at and the one string the effect still
        // carries. A note callout shares it with the family.
        $resting = generate_icon_html($dropdown->getIcon())->toHtml();

        expect($effect)->toEndWith(' ?? '.Js::from($resting)->toHtml());

        foreach (array_diff(optionIcons($dropdown), [$resting]) as $name => $icon) {
            expect(substr_count($html, $icon))->toBe(1, "{$name} is drawn once")
                ->and($effect)->not->toContain(Js::from($icon)->toHtml());
        }
    }
});

it('rests at its first option when it has no icon of its own', function (): void {
    // The alignment trigger: no icon of its own, so the first option's icon is what it shows
    // while no option is active - one copy as the fallback, none for the other three.
    $dropdown = ToolbarDropdown::alignment(['alignStart', 'alignCenter', 'alignEnd', 'alignJustify']);
    $effect = triggerEffect($dropdown->resolve(editor()->getTools())->toEmbeddedHtml());
    $icons = array_values(optionIcons($dropdown));
    $first = array_shift($icons);

    expect($effect)->toEndWith(' ?? '.Js::from($first)->toHtml());

    foreach ($icons as $icon) {
        expect($effect)->not->toContain(Js::from($icon)->toHtml());
    }
});

it('finds the active option by its place in the menu', function (): void {
    $html = ToolbarDropdown::lists(['bulletList', 'orderedList'])->resolve(editor()->getTools())->toEmbeddedHtml();

    // The first option whose state is on, counted the way the menu lists them - the same
    // order the parent checked them in, so the first match still wins - and its icon copied
    // out of that option.
    expect(triggerEffect($html))
        ->toStartWith("triggerContent = \$el.querySelectorAll(':scope > .fi-fo-rich-editor-dropdown-tool-menu > .fi-fo-rich-editor-dropdown-tool-option')[")
        ->toContain("isActive('bulletList', [])) return 0; if (")
        ->toContain("isActive('orderedList', [])) return 1; return -1;")
        ->toContain("]?.querySelector(':scope > .fi-icon')?.outerHTML ?? ");

    // What the effect counts is what the menu lists: an option for each index, and the menu
    // as a direct child of the element the effect sits on.
    expect(substr_count($html, 'class="fi-fo-rich-editor-dropdown-tool-option'))->toBe(2)
        ->and($html)->toMatch('/^\s*<div x-data="[^"]*"\s+x-effect="[^"]*"[^>]*>\s*<button [^>]*>.*?<\/button>\s*<div [^>]*class="fi-fo-rich-editor-dropdown-tool-menu"/s');
});

it('counts the options by their class, not by a role a tool may carry of its own', function (): void {
    // A tool's attributes win over the parent's, so an option can say `menuitemcheckbox` - and
    // counted by role, every option after it would hand the trigger its neighbour's icon.
    $tools = editor()->getTools();
    $tools['bulletList']->extraAttributes(['role' => 'menuitemcheckbox'], merge: true);
    $html = ToolbarDropdown::lists(['bulletList', 'orderedList'])->resolve($tools)->toEmbeddedHtml();

    expect($html)->toContain('role="menuitemcheckbox"')
        ->and(substr_count($html, 'class="fi-fo-rich-editor-dropdown-tool-option'))->toBe(2)
        ->and(triggerEffect($html))->toContain('> .fi-fo-rich-editor-dropdown-tool-option');
});

it('leaves a static trigger with its own icon', function (): void {
    $dropdown = ToolbarDropdown::more(['subscript', 'superscript']);
    $icon = generate_icon_html($dropdown->getIcon())->toHtml();

    expect(triggerEffect($dropdown->resolve(editor()->getTools())->toEmbeddedHtml()))
        ->toBe('triggerContent = '.Js::from($icon)->toHtml());
});
