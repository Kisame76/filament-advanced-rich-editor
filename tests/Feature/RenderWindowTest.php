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

it('resolves the toolbar a few times per editor while a repeater of them is filled', function (): void {
    // Filling a form hydrates every field's state, and that is outside the windows Filament
    // opens: each question asked while the state cast ran - whether the bar takes uploads,
    // which pool answers for them, which tools there are - built the whole bar again, and
    // building it asked the same questions from inside. Measured on a page of twenty editors:
    // 172 resolutions per editor, two thirds of the request.
    $resolved = 0;

    config()->set('filament-advanced-rich-editor.tokens', [
        'counted' => function () use (&$resolved): string {
            $resolved++;

            return 'bold';
        },
    ]);
    config()->set('filament-advanced-rich-editor.toolbar', [
        ...ToolbarPresets::shipped()['default']['toolbar'],
        ['counted'],
    ]);

    Livewire::test(RepeatedEditorsFormComponent::class)->html();

    // Three editors: once while each is filled, and the few times the render windows ask.
    // Twelve a field before the hydration remembered its answers.
    expect($resolved)->toBeLessThan(3 * 5);
});

it('builds the toolbar names once while they are asked for, not again from inside', function (): void {
    // Whether the field takes uploads is read off the names on the bar. Expanding the bar runs
    // the tools menu's token, which asked for the tools; the tools ask for the media pool, and
    // the pool asks whether the field takes uploads - so the bar was expanded a second time
    // from inside the first, on every editor, on every hydration.
    $expanded = 0;

    config()->set('filament-advanced-rich-editor.tokens', [
        'counted' => function () use (&$expanded): string {
            $expanded++;

            return 'bold';
        },
    ]);
    config()->set('filament-advanced-rich-editor.toolbar', [
        ...ToolbarPresets::shipped()['default']['toolbar'],
        ['counted'],
    ]);

    $field = editor();

    Component::withVisibilityCache(fn (): bool => $field->hasFileAttachments());

    expect($expanded)->toBe(1);
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

it('measures the document the field holds rather than the markup made from it', function (): void {
    // `getState()` runs the state cast, which writes the document out as HTML - twice, the
    // profiler said - and measuring then parsed that HTML back into the document it came from.
    // The field already holds the document once the form was filled - and a state that was
    // set as markup without a fill is still read, the way it always was.
    MeasuringEditor::$measuredFrom = null;

    Livewire::test(RepeatedEditorsFormComponent::class)->html();

    expect(MeasuringEditor::$measuredFrom)->toBe('array');

    Livewire::test(MeasuredEditorFormComponent::class)->html();

    expect(MeasuringEditor::$measuredFrom)->toBe('string')
        ->and(MeasuringEditor::$measured)->toBeGreaterThan(0);
});

it('measures each document in a repeater once a render', function (): void {
    MeasuringEditor::$measured = 0;

    Livewire::test(RepeatedEditorsFormComponent::class)->html();

    expect(MeasuringEditor::$measured)->toBe(3);
});
