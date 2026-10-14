<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\DescriptionListPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

/**
 * The definition list: `<dl>`, its terms and their descriptions - semantically right for a
 * glossary, which otherwise ends up as a bulleted list with bold words at the front.
 *
 * How it behaves under the caret is tested in `tests/js/description-list.test.js` and was
 * checked in a running editor. What is tested here is the wiring, and the three places a
 * stored list has to survive: the renderer, the Markdown on the way out, and the Markdown
 * on the way back in.
 */
const GLOSSARY = '<dl class="fi-arte-description-list"><dt>Apple</dt><dd>A <strong>fruit</strong>.</dd><dt>Pear</dt><dd>Another one.</dd></dl>';

it('ships off, because most fields never hold a glossary', function (): void {
    // A fourth list type in every lists dropdown and one more script on every page is a cost
    // for a feature most projects do not use. The name stays in the list and resolves away.
    expect(editor()->hasDescriptionList())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('descriptionList')
        ->and(editor()->getListTypes())->toBe(['bulletList', 'orderedList', 'taskList', 'descriptionList'])
        ->and(config('filament-advanced-rich-editor.lists'))->toBe(['bulletList', 'orderedList', 'taskList', 'descriptionList'])
        ->and(resolvedButtonNames(toolbarItem(editor(), 'dropdown:bulletList,orderedList,taskList,descriptionList')))
        ->toBe(['bulletList', 'orderedList', 'taskList']);
});

it('becomes one more kind of list in the lists dropdown once switched on', function (): void {
    $lists = toolbarItem(editor()->descriptionList(), 'dropdown:bulletList,orderedList,taskList,descriptionList');

    expect(editor()->descriptionList()->getTools())->toHaveKey('descriptionList')
        ->and($lists)->toBeInstanceOf(ToolbarDropdown::class)
        ->and(resolvedButtonNames($lists))->toBe(['bulletList', 'orderedList', 'taskList', 'descriptionList']);
});

it('reads the switch from the config file, and lets a field overrule it', function (): void {
    config()->set('filament-advanced-rich-editor.description_list', true);

    expect(editor()->hasDescriptionList())->toBeTrue()
        ->and(editor()->descriptionList(false)->hasDescriptionList())->toBeFalse();
});

it('is offered by the slash menu with the other lists', function (): void {
    expect(SlashMenu::GROUPS['style'])->toContain('descriptionList')
        ->and(config('filament-advanced-rich-editor.slash.groups.style'))->toContain('descriptionList');

    $items = collect(SlashMenu::for(editor()->descriptionList())['groups'])->flatMap(fn (array $group): array => $group['items']);
    $entry = $items->firstWhere('name', 'descriptionList');

    expect($entry['label'])->toBe('Definition list')
        ->and($entry['aliases'])->toContain('dl', 'glossary');
});

it('switches the list through its own command', function (): void {
    $tool = DescriptionListPlugin::make()->getEditorTools()[0];

    expect($tool->getName())->toBe('descriptionList')
        ->and($tool->getJsHandler())->toBe('$getEditor()?.chain().focus().toggleDescriptionList().run()')
        ->and($tool->getIcon())->toBe(Icons::get('description_list'))
        ->and(Icons::get('description_list'))->toBe('arte-description-list')
        ->and(file_exists(__DIR__.'/../../resources/svg/description-list.svg'))->toBeTrue()
        ->and(DescriptionListPlugin::make()->getTipTapJsExtensions())->toHaveCount(1)
        ->and(DescriptionListPlugin::make()->getTipTapJsExtensions()[0])->toContain('description-list');
});

it('renders a stored list as it was written', function (): void {
    expect(AdvancedRichContentRenderer::make(GLOSSARY)->toHtml())->toBe(GLOSSARY);
});

it('renders it whether or not the render was told the field had lists of this kind', function (): void {
    // The rule the renderer states for every node it declares: a renderer that has to be
    // told is one that drops the thing the day somebody forgets to say so. A glossary that
    // came back as four bare runs of text would be the whole document, run together.
    $document = AdvancedRichContentRenderer::make(GLOSSARY)->toArray();

    expect(array_column($document['content'][0]['content'], 'type'))
        ->toBe(['descriptionTerm', 'descriptionDetails', 'descriptionTerm', 'descriptionDetails'])
        ->and($document['content'][0]['type'])->toBe('descriptionList');
});

it('reads the list through the field own schema too', function (): void {
    $document = editor()->descriptionList()->getTipTapEditor()->setContent(GLOSSARY)->getDocument();

    expect($document['content'][0]['type'])->toBe('descriptionList')
        ->and($document['content'][0]['content'][1]['content'][1]['marks'][0]['type'])->toBe('bold');
});

it('writes the list as Markdown in the spelling Markdown Extra and CommonMark agree on', function (): void {
    // A term on its own line, its description after a colon, and a blank line between one
    // entry and the next. Left to itself the converter strips the tags and runs the four
    // parts together into one line.
    expect(AdvancedRichContentRenderer::make('<p>Before.</p>'.GLOSSARY.'<p>After.</p>')->toMarkdown())
        ->toBe("Before.\n\nApple\n: A **fruit**.\n\nPear\n: Another one.\n\nAfter.");
});

it('keeps a term with two descriptions as one entry', function (): void {
    expect(AdvancedRichContentRenderer::make('<dl><dt>Bank</dt><dd>Where money is kept.</dd><dd>The side of a river.</dd></dl>')->toMarkdown())
        ->toBe("Bank\n: Where money is kept.\n: The side of a river.");
});

it('keeps two terms that share a description together', function (): void {
    // A blank line between them would make the first a paragraph of its own: CommonMark
    // only reads lines with no blank between them as one entry's terms.
    $html = '<dl><dt>Color</dt><dt>Colour</dt><dd>A hue.</dd><dt>Grey</dt><dd>Between.</dd></dl>';
    $markdown = AdvancedRichContentRenderer::make($html)->toMarkdown();

    expect($markdown)->toBe("Color\nColour\n: A hue.\n\nGrey\n: Between.")
        ->and(array_column(AdvancedRichContentRenderer::make()->fromMarkdown($markdown)['content'][0]['content'], 'type'))
        ->toBe(['descriptionTerm', 'descriptionTerm', 'descriptionDetails', 'descriptionTerm', 'descriptionDetails']);
});

it('reads the list back in from Markdown', function (): void {
    $document = AdvancedRichContentRenderer::make()->fromMarkdown("Apple\n: A **fruit**.\n\nPear\n: Another one.");

    expect($document['content'][0]['type'])->toBe('descriptionList')
        ->and(array_column($document['content'][0]['content'], 'type'))
        ->toBe(['descriptionTerm', 'descriptionDetails', 'descriptionTerm', 'descriptionDetails'])
        ->and(AdvancedRichContentRenderer::make($document)->toHtml())->toBe(GLOSSARY);
});

it('reads a loose list as text in its parts rather than as paragraphs they cannot hold', function (): void {
    // A blank line between a term and its description makes CommonMark write the description
    // as `<dd><p>…</p></dd>`. A part holds text, and a paragraph inside one would be a
    // document the editor refuses to open.
    $document = AdvancedRichContentRenderer::make()->fromMarkdown("Apple\n\n: A fruit.");

    expect($document['content'][0]['content'][1])->toBe([
        'type' => 'descriptionDetails',
        'content' => [['type' => 'text', 'text' => 'A fruit.']],
    ]);
});

it('leaves the extension to a caller that brought their own', function (): void {
    // CommonMark registers a parser per extension, and a second copy raises.
    $document = AdvancedRichContentRenderer::make()->fromMarkdown("Apple\n: A fruit.", [], [new DescriptionListExtension]);

    expect($document['content'][0]['type'])->toBe('descriptionList');
});

it('names the list in a sentence a writer reads', function (): void {
    expect(validationErrors(editor()->descriptionList()->mustContain('descriptionList'), '<p>Only text.</p>')['content'] ?? [])
        ->toContain('The content must contain a definition list.');
});
