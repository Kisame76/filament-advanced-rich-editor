<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Schemas\Components\Component;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPresets;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\MeasuredEditorFormComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RepeatedEditorsFormComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor\MeasuringEditor;
use Livewire\Livewire;

/**
 * The toolbar is worked out once per render, not once per question about it.
 *
 * Every tool, plugin and helper that wants to know something about the field asks the bar:
 * whether it takes uploads, whether there is a library to browse, which tools survive. Each
 * of those questions used to resolve the whole bar again - dropdowns, colour palettes,
 * dividers - and a page of five editors in a repeater spent most of its request doing that,
 * three hundred and seventy-five times. Measured with a profiler, not guessed.
 *
 * Remembered only for as long as Filament remembers visibility: while a schema renders,
 * validates or takes a snapshot - the windows in which no state changes - and per field
 * object, so a repeater's clones each keep an answer of their own.
 */
it('resolves the toolbar a few times a render rather than once a question', function (): void {
    $resolved = 0;

    // A token is the one thing on the bar that is code of the project's, so counting its
    // calls counts resolutions without reaching into the package.
    config()->set('filament-advanced-rich-editor.tokens', [
        'counted' => function () use (&$resolved): string {
            $resolved++;

            return 'bold';
        },
    ]);
    // The bar the package ships, which is what a field nobody configured asks about.
    config()->set('filament-advanced-rich-editor.toolbar', [
        ...ToolbarPresets::shipped()['default']['toolbar'],
        ['counted'],
    ]);

    Livewire::test(EditorFormComponent::class)->html();

    // Uncached, this was one resolution per question, and the shipped bar is asked dozens of
    // them. Cached, it is one per window Filament opens around the render.
    expect($resolved)->toBeLessThan(10);
});

it('asks again in the next render, where the state may have moved on', function (): void {
    $button = 'bold';

    $field = editor()->toolbarButtons(function () use (&$button): array {
        return [[$button]];
    });

    $first = Component::withVisibilityCache(fn (): array => toolbarShape($field));

    $button = 'italic';

    $second = Component::withVisibilityCache(fn (): array => toolbarShape($field));

    expect($first)->toBe([['bold']])
        ->and($second)->toBe([['italic']]);
});

it('remembers nothing outside a render', function (): void {
    $button = 'bold';

    $field = editor()->toolbarButtons(function () use (&$button): array {
        return [[$button]];
    });

    toolbarShape($field);

    $button = 'italic';

    expect(toolbarShape($field))->toBe([['italic']]);
});

it('gives a clone an answer of its own', function (): void {
    // Repeaters and custom block modals clone fields. An answer kept on the field itself would
    // be copied into every clone along with everything else.
    Component::withVisibilityCache(function (): void {
        $original = editor()->toolbarButtons([['bold']]);

        toolbarShape($original);

        $clone = (clone $original)->toolbarButtons([['italic']]);

        expect(toolbarShape($clone))->toBe([['italic']]);
    });
});

it('keeps the bar it remembers built from the tools the field can honour', function (): void {
    // Asking for the tools reads the bar from inside the answer, with the unfiltered set - the
    // question there is only whether the bar takes an upload. A bar remembered at that moment
    // would offer File in a dropdown on a field with no library to browse, where it opens
    // Filament's picture dialog instead.
    Component::withVisibilityCache(function (): void {
        $field = editor()->toolbarButtons([[ToolbarDropdown::make('Insert', ['image', 'file'])]]);

        $field->getTools();

        $dropdown = $field->getToolbarButtons()[0][0];

        expect(array_map(
            static fn (RichEditorTool $tool): string => $tool->getName(),
            $dropdown->getResolvedButtons(),
        ))->toBe(['image']);
    });
});

it('measures the document for the counter once a render', function (): void {
    // Filament asks for what sits under a field more than once while it draws it, and every
    // asking measured the whole document again - two passes through TipTap each time.
    MeasuringEditor::$measured = 0;

    Livewire::test(MeasuredEditorFormComponent::class)->html();

    expect(MeasuringEditor::$measured)->toBe(1);
});

it('measures each document in a repeater once a render', function (): void {
    MeasuringEditor::$measured = 0;

    Livewire::test(RepeatedEditorsFormComponent::class)->html();

    expect(MeasuringEditor::$measured)->toBe(3);
});
