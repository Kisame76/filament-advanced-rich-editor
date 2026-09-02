<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\FormatBrushPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * The brush that carries formatting from one passage to another.
 *
 * The PHP half is small on purpose: what the brush does happens entirely in the browser and
 * is tested in `tests/js/format-brush.test.js`. What is tested here is the wiring - that the
 * tool exists, that the switch takes it away, that the token resolves, and above all the one
 * line whose failure mode is silent and permanent.
 */
it('offers the tool, and takes it away with the switch', function (): void {
    expect(editor()->getTools())->toHaveKey('formatBrush')
        ->and(editor()->formatBrush(false)->getTools())->not->toHaveKey('formatBrush');
});

it('reads the switch from the config file, and lets a field overrule it', function (): void {
    config()->set('filament-advanced-rich-editor.format_brush', false);

    expect(editor()->hasFormatBrush())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('formatBrush')
        ->and(editor()->formatBrush()->hasFormatBrush())->toBeTrue();
});

it('keeps its place in the toolbar, and resolves away where it is switched off', function (): void {
    // A raw name in a group with no token behind it raises while the view renders and costs
    // the whole field, which is why the token exists at all.
    expect(array_keys(ToolbarLayout::tokens()))->toContain('formatBrush')
        ->and(ToolbarLayout::tokens()['formatBrush'](editor()))->toBe('formatBrush')
        ->and(ToolbarLayout::tokens()['formatBrush'](editor()->formatBrush(false)))->toBe([]);
});

it('never says a button is pressed because its script is missing', function (): void {
    // The one line here whose failure is silent, permanent and reaches a screen reader.
    // Filament wraps the expression as `editorUpdatedAt && (...)`, and `$getEditor()` is
    // undefined until the editor has loaded - so a strict `!== null` is *true* for a field
    // whose script never arrived, and every brush button renders armed with
    // `aria-pressed="true"` for a button that does nothing. `!= null` is false for both.
    $expression = collect(FormatBrushPlugin::make()->getEditorTools())
        ->firstWhere(fn ($tool): bool => $tool->getName() === 'formatBrush')
        ?->getActiveJsExpression();

    expect($expression)->toContain('!= null')
        ->and($expression)->not->toContain('!== null');
});

it('announces its state to a screen reader rather than only colouring it', function (): void {
    // Both halves, and the second is why the first was not enough. `toggle()` asks Filament
    // for `aria-pressed`, and Filament writes it - on the bar. Measured on the rendered
    // button, a tool placed in the overflow menu goes through the dropdown-option path,
    // which draws the active class and no `aria-pressed` at all; and the menu is where this
    // one is put, since it ships with no button of its own. So it is written here too, and
    // stands down on the bar where Filament's own wins.
    $tool = collect(FormatBrushPlugin::make()->getEditorTools())
        ->firstWhere(fn ($tool): bool => $tool->getName() === 'formatBrush');

    expect($tool->isToggle())->toBeTrue()
        ->and($tool->getExtraAttributes())->toHaveKey('x-bind:aria-pressed')
        ->and($tool->getExtraAttributes()['x-bind:aria-pressed'])->toContain('editorUpdatedAt')
        // The same loose comparison, for the same reason: strict would read a field whose
        // script never arrived as permanently pressed.
        ->and($tool->getExtraAttributes()['x-bind:aria-pressed'])->toContain('!= null');
});

it('says which of the two armed states it is, and keeps saying it', function (): void {
    // For the stylesheet, and for anyone reading the DOM. It cannot ride on `x-bind:class`:
    // `RichEditorTool` writes its own and treats these as defaults, so a second one would
    // be dropped without a word.
    //
    // The `editorUpdatedAt` in it is the half that was missing, and it was found by reading
    // the attribute out of a running panel rather than by reasoning: Alpine re-evaluates a
    // binding when something it read changes, and the editor's storage is a plain object it
    // does not watch. Without a reference to the reactive property the attribute is written
    // once at first paint and never again - the button lit and unlit correctly while this
    // stayed empty through all three states.
    $tool = collect(FormatBrushPlugin::make()->getEditorTools())
        ->firstWhere(fn ($tool): bool => $tool->getName() === 'formatBrush');

    expect($tool->getExtraAttributes())->toHaveKey('x-bind:data-arte-brush')
        ->and($tool->getExtraAttributes()['x-bind:data-arte-brush'])->toContain('editorUpdatedAt');
});

it('teaches the schema nothing, because it puts down only what is already there', function (): void {
    // No PHP extension and no mark, for the reason the case switcher has none: the brush
    // writes marks the schema already declares. Switching the feature off later leaves
    // every passage already brushed exactly as it is.
    expect(FormatBrushPlugin::make()->getTipTapPhpExtensions())->toBe([])
        ->and(FormatBrushPlugin::make()->getEditorActions())->toBe([]);
});

it('loads its script only where the button is', function (): void {
    expect(FormatBrushPlugin::make()->getTipTapJsExtensions())
        ->toHaveCount(1)
        ->and(FormatBrushPlugin::make()->getTipTapJsExtensions()[0])
        ->toContain('format-brush');
});

it('ships the styles behind the two states it announces', function (): void {
    // The rule for the armed-for-good state shipped as `.fi-rich-editor [data-arte-brush=…]`
    // and matched nothing at all: measured in a running panel, the wrapper is
    // `fi-fo-rich-editor`, nine levels above the button, and the name written was one this
    // package uses exactly once. The attribute went on the button, the button carried it,
    // and the two armed states looked identical - which is the shape of a defect nothing
    // fails on. So the selector is unscoped, and this is the test that keeps it that way.
    $css = file_get_contents(__DIR__.'/../../resources/dist/filament-advanced-rich-editor.css');

    expect($css)->toContain("[data-arte-brush='sticky']")
        // Not behind an ancestor that the editor does not render. `data-arte-brush` is
        // written by one tool in one package, so there is nothing on any page for it to
        // collide with and nothing an ancestor would buy.
        //
        // Asked as "the rule starts the line" rather than as "no ancestor appears anywhere",
        // which two earlier drafts of this test got wrong in two different ways: the comment
        // above the rule quotes the selector that was broken, so a substring search cannot
        // tell the record of a mistake from the mistake, and a regex reaching for the
        // opening brace simply bridged the comment and found the good rule's own.
        ->and($css)->toMatch("/^\[data-arte-brush='sticky'\]\s*\{/m")
        ->and($css)->toContain('.fi-arte-brush-armed')
        // On the element, not on its descendants: `cursor` inherits, and the `*` that used
        // to be here also painted over the image handles, the drag grip and the table
        // grips - three cursors that say what dragging them does.
        ->and($css)->not->toContain('.fi-arte-brush-armed *')
        // The published stylesheet is the one the browser gets, so it must not drift.
        ->and($css)->toBe(file_get_contents(__DIR__.'/../../resources/css/filament-advanced-rich-editor.css'));
});

it('ships the script the button calls into', function (): void {
    // Same reason as the stylesheet beside it: the tool's handler names three commands, and
    // a published bundle that had drifted would leave the button calling into nothing.
    $script = file_get_contents(__DIR__.'/../../resources/dist/js/format-brush.js');

    expect($script)->toContain('cycleFormatBrush', 'applyFormatBrush', 'clearFormatBrush')
        ->and($script)->toBe(file_get_contents(__DIR__.'/../../resources/js/format-brush.js'));
});
