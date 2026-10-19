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

/**
 * Whether the installed Filament draws its toolbar dropdowns itself, positioned and turned
 * over by its own `filamentDropdown()` component - which Filament 5.10 started doing. Its
 * markup carries none of the anchors the injection threads into, and needs none of it.
 */
function filamentTurnsItsOwnMenus(): bool
{
    return str_contains(listsDropdown(), 'filamentDropdown()');
}

const INJECTED = 'Filament draws the menus it turns over itself from 5.10 on';

it('gives the menu and its trigger the references the measuring needs', function (): void {
    $html = listsDropdown();

    expect($html)->toContain('x-ref="trigger"')
        ->and($html)->toContain('x-ref="menu"');
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

it('carries the trait state beside the parent own', function (): void {
    $html = listsDropdown();

    // Spliced in rather than replacing: the parent's `open` and `triggerContent` have to
    // survive, or the dropdown stops opening at all.
    expect($html)->toContain('x-data="{ open: false, ')
        ->and($html)->toContain('dropUp: false')
        ->and($html)->toContain('positionMenu()')
        ->and($html)->toContain('triggerContent');
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

it('measures once the menu has been opened, not while it is closing', function (): void {
    expect(listsDropdown())->toContain('x-on:click="open = !open; open &amp;&amp; positionMenu()"');
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

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
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

it('hands itself to the module that measures, rather than carrying the measuring', function (): void {
    // Turning and capping are `resources/js/menu-position.js`, tested under `tests/js`. They
    // used to be inlined here, in every menu of every editor - two dozen copies of the same
    // few kilobytes per field. What the dropdown has to get right is calling the module with
    // itself, at the address the service provider registered it under.
    $module = Js::from(FilamentAsset::getScriptSrc('advanced-rich-editor/menu-position', 'kisame76/filament-advanced-rich-editor'))->toHtml();

    expect(listsDropdown())
        ->toContain(e("import({$module})"))
        ->toContain('positionMenu(this)');
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

it('threads nothing into a Filament that turns its own menus over', function (): void {
    // From 5.10 on Filament's dropdown is its own component, floated and flipped away from the
    // edge by Filament. Half an injection would be worse than none: a menu bound to
    // `menuUpClass` and `dropUp` with nothing declaring them is an Alpine error on every page.
    $html = listsDropdown();

    expect($html)->not->toContain('menuUpClass')
        ->and($html)->not->toContain('dropUp')
        ->and($html)->not->toContain('positionMenu')
        ->and($html)->not->toContain('x-ref="menu"');
})->skip(fn (): bool => ! filamentTurnsItsOwnMenus(), 'Filament before 5.10 leaves the turning over to this package');

it('tells the dropdown its one trigger is the trigger, rather than having it measured', function (): void {
    // Filament 5.10's dropdown finds the trigger it hangs its ARIA on by asking the browser
    // which one is visible, on start and on every change to the panel - a style recalculation
    // each time, and with Filament's `:has()` rules one over the whole page: 4.3 s of a page
    // of twenty editors. With a single trigger the answer is that trigger either way.
    $html = listsDropdown();

    expect(substr_count($html, e('arteMeasuredTrigger: filamentDropdown().getTrigger, ')))->toBe(1)
        ->and(substr_count($html, 'getTrigger() {'))->toBe(1)
        // After the spread, or upstream's own method would win.
        ->and($html)->toContain(e('{ ...filamentDropdown(), arteMeasuredTrigger: '))
        // More than one, or a different one from before, goes the measured way.
        ->and($html)->toContain(e('(triggers.length === 1) && ((this.activeTrigger ?? triggers[0]) === triggers[0])'))
        ->and($html)->toContain(e(': this.arteMeasuredTrigger()'))
        // And the parent's own state is still there beside it.
        ->and($html)->toContain('triggerContent: ');
})->skip(fn (): bool => ! filamentTurnsItsOwnMenus(), 'Filament before 5.10 measures nothing');

it('leaves the trigger to upstream where the dropdown is not the one it expects', function (): void {
    // No `filamentDropdown()` - a Filament before 5.10 - or more than one in the markup: nothing
    // is threaded in, because an override in the wrong object is an override of nothing.
    $dropdown = new class('Lists') extends ToolbarDropdown
    {
        public function thread(string $html): string
        {
            return $this->takingTheOneTriggerAtItsWord($html);
        }
    };

    $older = '<div x-data="{ open: false, triggerContent: null }"></div>';
    $twice = '<div x-data="{ ...filamentDropdown(), a: 1 }"></div><div x-data="{ ...filamentDropdown(), b: 2 }"></div>';

    expect($dropdown->thread($older))->toBe($older)
        ->and($dropdown->thread($twice))->toBe($twice);
});

it('threads all of the anchors in or none of them', function (): void {
    // Markup with one anchor and not the others - the menu's class, say, without the state
    // its binding reads - is left exactly as it came.
    $dropdown = new class('Lists') extends ToolbarDropdown
    {
        public function thread(string $html): string
        {
            return $this->openingAwayFromTheEdge($html);
        }
    };

    $partial = '<div x-data="{ ...filamentDropdown() }"><div class="fi-fo-rich-editor-dropdown-tool-menu"></div></div>';

    expect($dropdown->thread($partial))->toBe($partial);
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
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

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
})->skip(filamentTurnsItsOwnMenus(...), INJECTED);

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
        ->toStartWith("triggerContent = \$el.querySelectorAll('.fi-fo-rich-editor-dropdown-tool-menu > .fi-fo-rich-editor-dropdown-tool-option')[")
        // The options Filament writes after the name differ between its versions - `null`
        // in 5.7, `[]` later - and are Filament's business; the order is this one's.
        ->toMatch("/isActive\\('bulletList', [^)]*\\)\\) return 0; if \\(/")
        ->toMatch("/isActive\\('orderedList', [^)]*\\)\\) return 1; return -1;/")
        ->toContain("]?.querySelector(':scope > .fi-icon')?.outerHTML ?? ");

    // What the effect counts is what the menu lists: an option for each index.
    expect(preg_match_all('/class="[^"]*\bfi-fo-rich-editor-dropdown-tool-option\b/', $html))->toBe(2)
        // Inside the element the effect sits on: the menu follows it in the same markup.
        ->and($html)->toMatch('/^\s*<div x-data="[^"]*"\s+x-effect="[^"]*".*class="fi-fo-rich-editor-dropdown-tool-menu"/s');
});

it('counts the options by their class, not by a role a tool may carry of its own', function (): void {
    // A tool's attributes win over the parent's, so an option can say `menuitemcheckbox` - and
    // counted by role, every option after it would hand the trigger its neighbour's icon.
    $tools = editor()->getTools();
    $tools['bulletList']->extraAttributes(['role' => 'menuitemcheckbox'], merge: true);
    $html = ToolbarDropdown::lists(['bulletList', 'orderedList'])->resolve($tools)->toEmbeddedHtml();

    expect($html)->toContain('role="menuitemcheckbox"')
        ->and(preg_match_all('/class="[^"]*\bfi-fo-rich-editor-dropdown-tool-option\b/', $html))->toBe(2)
        ->and(triggerEffect($html))->toContain('> .fi-fo-rich-editor-dropdown-tool-option');
});

it('leaves a static trigger with its own icon', function (): void {
    $dropdown = ToolbarDropdown::more(['subscript', 'superscript']);
    $icon = generate_icon_html($dropdown->getIcon())->toHtml();

    expect(triggerEffect($dropdown->resolve(editor()->getTools())->toEmbeddedHtml()))
        ->toBe('triggerContent = '.Js::from($icon)->toHtml());
});

it('hangs the menus this package builds itself under their trigger, whatever Filament draws for its own', function (): void {
    // Until 5.10 Filament's rule for the menu was the box and `position: absolute`; from 5.10
    // its own dropdowns float a panel and the menu is only a row of buttons. The colour
    // pickers, the font size and the panels have no panel, and without a rule of this
    // package's own their menus stood in the toolbar's flow and pushed the bar apart.
    $css = file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor.css');

    expect($css)->toMatch('/\.fi-fo-rich-editor-dropdown-tool > \.fi-fo-rich-editor-dropdown-tool-menu \{[^}]*position: absolute;[^}]*top: calc\(100% \+ 0\.25rem\);/s')
        // Before the rule that turns a menu upwards, and no more specific, so that one wins.
        ->and(strpos($css, '.fi-fo-rich-editor-dropdown-tool > .fi-fo-rich-editor-dropdown-tool-menu {'))
        ->toBeLessThan(strpos($css, '.fi-fo-rich-editor-dropdown-tool-menu.fi-arte-menu-up {'))
        ->and(file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css'))->toBe($css);
});
