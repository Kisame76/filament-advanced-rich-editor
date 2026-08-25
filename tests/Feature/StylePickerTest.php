<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarStylePicker;

it('puts a picker on the bar when the project named styles', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    expect(toolbarItem(editor()->toolbarButtons([['styles']]), 'styles'))
        ->toBeInstanceOf(ToolbarStylePicker::class);
});

it('puts nothing on the bar when there are none', function (): void {
    // The same as the overflow menu and the colour pickers: nothing to open onto means no
    // trigger, rather than a button that opens an empty box.
    withStyles([]);

    expect(toolbarShape(editor()->toolbarButtons([['styles', 'bold']])))->toBe([['bold']]);
});

it('offers what the field offers, not what the project does', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    $field = editor()->styles(['own' => ['label' => 'Own', 'class' => 'x']])->toolbarButtons([['styles']]);

    expect(array_column(toolbarItem($field, 'styles')->getStyles(), 'key'))->toBe(['own']);
});

it('draws every style it was given', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg'],
        'kicker' => ['label' => 'Kicker', 'class' => 'uppercase', 'scope' => 'inline'],
    ]);

    $html = toolbarItem(editor()->toolbarButtons([['styles']]), 'styles')->toEmbeddedHtml();

    expect($html)->toContain('Lead')
        ->toContain('Kicker')
        // A block style and an inline style do different things, so the menu says which is
        // which rather than leaving the reader to find out by clicking.
        ->toContain('fi-arte-style-group');
});

it('does not draw a group heading when every style is the same kind', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    expect(toolbarItem(editor()->toolbarButtons([['styles']]), 'styles')->toEmbeddedHtml())
        ->not->toContain('fi-arte-style-group');
});

it('binds each row by its key rather than by a copy of the style', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg'],
        'note' => ['label' => 'Note', 'class' => 'bg-amber-50'],
    ]);

    $html = toolbarItem(editor()->toolbarButtons([['styles']]), 'styles')->toEmbeddedHtml();

    // The list is in the component's state once. Writing it into every row's three
    // bindings as well would put the same JSON into the markup six more times.
    expect(substr_count($html, 'bg-amber-50'))->toBe(1)
        // Each row binds the key alone; the list itself lives in the component's state.
        ->and($html)->toContain("apply('note')")
        ->and($html)->toContain("isActive('note')")
        ->and($html)->toContain("applies('note')");
});

it('turns the menu upwards when there is no room below it', function (): void {
    // The picker also sits in the bubble over a selection, which itself hangs below the
    // text - so near the foot of a document the menu would open past the bottom of the
    // window with no way to scroll to it.
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    $html = toolbarItem(editor()->toolbarButtons([['styles']]), 'styles')->toEmbeddedHtml();

    // The mechanism itself is shared with every other menu in the package and is pinned in
    // `MenuPositionTest`; this only checks that the picker is wired into it.
    expect($html)->toContain('x-ref="menu"')
        ->toContain('x-ref="trigger"')
        ->toContain('positionMenu()')
        ->toContain('fi-arte-menu-up');
});

it('says what it is for while nothing is applied', function (): void {
    // A trigger reading "None" at rest tells nobody what the button does. It names the
    // feature until there is a style to name instead - which is the only moment the other
    // word carries any information.
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    $html = toolbarItem(editor()->toolbarButtons([['styles']]), 'styles')->toEmbeddedHtml();

    expect($html)->toContain('<span class="fi-arte-style-picker-label" x-text="label()">Style</span>')
        // The way back is still called what it is, in the menu where it is an action.
        ->toContain('<span>None</span>');
});

it('marks styled text in the editor only when it is asked to', function (): void {
    // Off by default, and that is the same reasoning the empty styles list follows: the
    // classes belong to the project, so the look does too, and a package that invented one
    // would be imposing a design on content it knows nothing about.
    expect(editor()->hasStylePreview())->toBeFalse();

    config()->set('filament-advanced-rich-editor.style_preview', true);

    expect(editor()->hasStylePreview())->toBeTrue()
        ->and(editor()->stylePreview(false)->hasStylePreview())->toBeFalse();
});

it('says on the editor whether styled text should be marked', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    // The class the stylesheet hangs the marking on. On the field's own wrapper rather than
    // on the styled node, so that turning it on for one field leaves the others alone.
    expect(editor()->stylePreview()->getExtraInputAttributes())
        ->toHaveKey('class')
        ->and(editor()->stylePreview()->getExtraInputAttributes()['class'])
        ->toContain('fi-arte-style-preview');

    expect(editor()->getExtraInputAttributes()['class'] ?? '')->not->toContain('fi-arte-style-preview');
});
