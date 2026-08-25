<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarColorPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDivider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFontSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarFullscreen;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarStylePicker;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\TestCase;
use Tiptap\Core\Extension;
use Tiptap\Editor;

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
 * The styles a project declared, for the tests that work on them.
 *
 * @param  array<string, mixed>  $styles
 */
function withStyles(array $styles): void
{
    config()->set('filament-advanced-rich-editor.styles', $styles);
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
 * The plugins a field registered, by class name.
 *
 * Every feature that is a `RichContentPlugin` asks the same question of a field - is it
 * registered, and is it gone when the switch is off - so the question is asked once here.
 *
 * @return array<int, string>
 */
function pluginNames(AdvancedRichEditor $editor): array
{
    return array_map(static fn (object $plugin): string => $plugin::class, $editor->getPlugins());
}

/**
 * What one resolved toolbar item is called in an expectation. Kept apart from
 * `toolbarShape()` so that a half of a split toolbar reads the same way the whole bar
 * does.
 */
function toolbarItemName(mixed $item): string
{
    return match (true) {
        is_string($item) => $item,
        $item instanceof ToolbarDivider => 'divider',
        $item instanceof ToolbarPin => 'pin',
        $item instanceof ToolbarFontSize => 'fontSize',
        $item instanceof ToolbarFontPicker => 'fontFamily',
        $item instanceof ToolbarStylePicker => 'styles',
        $item instanceof ToolbarFullscreen => 'fullscreen',
        $item instanceof ToolbarColorPicker => $item->getName(),
        // Dropdowns are matched before their parent class, which they extend.
        $item instanceof ToolbarDropdown => 'dropdown:'.implode(',', $item->getButtons()),
        $item instanceof ToolbarButtonGroup => 'group:'.implode(',', $item->getButtons()),
        is_object($item) => $item::class,
        default => gettype($item),
    };
}

/**
 * Flattens a group of resolved toolbar items into plain strings.
 *
 * @param  array<int, array<int, mixed>>  $groups
 * @return array<int, array<int, string>>
 */
function toolbarGroupsShape(array $groups): array
{
    return array_map(
        fn (array $group): array => array_map(toolbarItemName(...), $group),
        $groups,
    );
}

/**
 * Flattens a resolved toolbar into plain strings, so an expectation reads like the
 * toolbar configuration it came from instead of a wall of object assertions.
 *
 * @return array<int, array<int, string>>
 */
function toolbarShape(AdvancedRichEditor $editor): array
{
    return toolbarGroupsShape($editor->getToolbarButtons());
}

/**
 * What `toolbarShape()` calls the overflow dropdown. Layout tests care that it is there,
 * not what is in it - and what is in it is the one list that keeps being reshuffled - so
 * they ask for the name instead of spelling the tools out. `MoreToolsTest` pins the
 * contents.
 */
function moreShape(?AdvancedRichEditor $editor = null): string
{
    return 'dropdown:'.implode(',', ($editor ?? editor())->getMoreTools());
}

/**
 * What `toolbarShape()` calls the tools menu, the same way `moreShape()` names the overflow:
 * by what it was configured with rather than by what survived resolving, since a tool that
 * is switched off is dropped when the dropdown renders and not when it is named.
 */
function toolsShape(?AdvancedRichEditor $editor = null): string
{
    return 'dropdown:'.implode(',', ($editor ?? editor())->getToolsMenu());
}

/**
 * Whatever the field hung below the editor, which is where Filament renders `belowContent()`
 * and where this package puts the character counter. Null when nothing was hung there.
 */
function belowContent(AdvancedRichEditor $editor): mixed
{
    $component = $editor->getChildSchema(AdvancedRichEditor::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0] ?? null;

    // Filament wraps anything `Htmlable` handed to `belowContent()` in its own `Html`
    // component, so the thing the field put there is one layer down.
    return $component instanceof Html ? $component->getContent() : $component;
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

/**
 * The resolved toolbar group that holds a given item, addressed by what `toolbarShape()`
 * calls it. Positions shift whenever the default layout is regrouped; what a group holds
 * does not, so tests ask for it by name.
 *
 * @return array<int, string>
 */
function toolbarGroup(AdvancedRichEditor $editor, string $item): array
{
    foreach (toolbarShape($editor) as $group) {
        if (in_array($item, $group, strict: true)) {
            return $group;
        }
    }

    return [];
}

/**
 * The resolved object behind an item, e.g. the dropdown a token expanded into.
 */
function toolbarItem(AdvancedRichEditor $editor, string $item): mixed
{
    $shape = toolbarShape($editor);

    foreach ($editor->getToolbarButtons() as $groupIndex => $group) {
        foreach ($group as $itemIndex => $resolved) {
            if (($shape[$groupIndex][$itemIndex] ?? null) === $item) {
                return $resolved;
            }
        }
    }

    return null;
}

/**
 * The shape name of the first dropdown holding a given button, so a test can say which
 * dropdown it means without counting groups.
 */
function toolbarDropdownName(AdvancedRichEditor $editor, string $button): string
{
    foreach (toolbarShape($editor) as $group) {
        foreach ($group as $item) {
            if (str_starts_with($item, 'dropdown:') && in_array($button, explode(',', substr($item, 9)), strict: true)) {
                return $item;
            }
        }
    }

    return '';
}

function toolbarDropdown(AdvancedRichEditor $editor, string $button): mixed
{
    return toolbarItem($editor, toolbarDropdownName($editor, $button));
}

/**
 * A TipTap editor holding a document, for the tests that work on parsed content rather
 * than on a form field.
 *
 * The extension list is Filament's own, so a test sees the schema the rendered page sees:
 * an attribute nothing declares is dropped on parsing, and a document built from a
 * shorter list would keep attributes production silently throws away.
 *
 * @param  array<int, Extension>  $extensions
 */
function document(string $html, array $extensions = []): Editor
{
    $editor = app(Editor::class, [
        'configuration' => [
            'extensions' => [
                ...$extensions,
                ...RichContentRenderer::make()->getTipTapPhpExtensions(),
            ],
        ],
    ]);

    $editor->setContent($html);

    return $editor;
}

/**
 * The attributes of the first `<a>` in a fragment, and how many there are.
 *
 * Attribute order in rendered markup is decided by the schema and carries no meaning, so
 * a test that spelled the tag out would fail on a reordering that changed nothing. The
 * count is part of the answer because a mark registered twice renders nested links.
 *
 * @return array{count: int, attributes: array<string, string>}
 */
function links(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $anchors = $document->getElementsByTagName('a');
    $attributes = [];

    foreach ($anchors->item(0)?->attributes ?? [] as $attribute) {
        $attributes[$attribute->nodeName] = $attribute->nodeValue;
    }

    ksort($attributes);

    return ['count' => $anchors->count(), 'attributes' => $attributes];
}

/**
 * Every mention in a fragment, as the attributes a page can actually see.
 *
 * Read back out of the markup rather than asserted as a string, for the same reason
 * `links()` does it: attribute order is the schema's business and carries no meaning, and
 * a test spelling the tag out would fail on a reordering that changed nothing.
 *
 * @return array<int, array{tag: string, class: string, href: ?string, id: ?string, char: ?string, text: string}>
 */
function mentionElements(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $mentions = [];

    foreach ((new DOMXPath($document))->query('//*[@data-type="mention"]') ?: [] as $element) {
        $mentions[] = [
            'tag' => $element->tagName,
            'class' => $element->getAttribute('class'),
            'href' => $element->hasAttribute('href') ? $element->getAttribute('href') : null,
            'id' => $element->hasAttribute('data-id') ? $element->getAttribute('data-id') : null,
            'char' => $element->hasAttribute('data-char') ? $element->getAttribute('data-char') : null,
            'text' => $element->textContent,
        ];
    }

    return $mentions;
}
