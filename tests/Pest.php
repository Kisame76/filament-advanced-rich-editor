<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontSize;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/**
 * A schema that is complete enough for a field to resolve its tools, plugins and record.
 *
 * The operation is pinned so that nothing in the field falls back to inspecting the
 * Livewire component's class name, which would tie the assertions to the fixture.
 */
function testSchema(): Schema
{
    return Schema::make(new TestSchemaComponent)->operation('edit');
}

/**
 * A field bound to a fresh container. Every call gets its own schema, because the root
 * container is memoised on the component the first time it is asked for.
 */
function editor(string $name = 'content'): AdvancedRichEditor
{
    return AdvancedRichEditor::make($name)->container(testSchema());
}

/**
 * Flattens a resolved toolbar into plain strings, so an expectation reads like the
 * toolbar configuration it came from instead of a wall of object assertions.
 *
 * @return array<int, array<int, string>>
 */
function toolbarShape(AdvancedRichEditor $editor): array
{
    return array_map(
        fn (array $group): array => array_map(
            fn (mixed $item): string => match (true) {
                is_string($item) => $item,
                $item instanceof ToolbarDivider => 'divider',
                $item instanceof ToolbarFontSize => 'fontSize',
                // Dropdowns are matched before their parent class, which they extend.
                $item instanceof ToolbarDropdown => 'dropdown:'.implode(',', $item->getButtons()),
                $item instanceof ToolbarButtonGroup => 'group:'.implode(',', $item->getButtons()),
                is_object($item) => $item::class,
                default => gettype($item),
            },
            $group,
        ),
        $editor->getToolbarButtons(),
    );
}

/**
 * The names of the tools a dropdown actually rendered, as opposed to the names it was
 * configured with - unknown and disabled buttons are dropped while resolving.
 *
 * @return array<int, string>
 */
function resolvedButtonNames(ToolbarButtonGroup $group): array
{
    return array_map(
        fn (RichEditorTool $tool): string => $tool->getName(),
        $group->getResolvedButtons(),
    );
}
