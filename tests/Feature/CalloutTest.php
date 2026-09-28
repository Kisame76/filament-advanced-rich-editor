<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Callouts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\CalloutPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarDropdown;

/**
 * The class list and the text of the first callout in a fragment, and how many there are.
 *
 * @return array{count: int, classes: array<int, string>, text: string, type: string}
 */
function callouts(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $found = (new DOMXPath($document))->query('//div[@data-type="callout"]');
    $first = $found->item(0);

    return [
        'count' => $found->count(),
        'classes' => $first instanceof DOMElement
            ? array_values(array_filter(explode(' ', $first->getAttribute('class'))))
            : [],
        'text' => $first?->textContent ?? '',
        'type' => $first instanceof DOMElement ? $first->getAttribute('data-type') : '',
    ];
}

function storedCallout(string $variant = 'note', string $inner = '<p>Mind the gap</p>'): string
{
    return '<div class="fi-arte-callout fi-arte-callout-'.$variant.'" data-type="callout">'.$inner.'</div>';
}

it('renders a stored callout as a box that says which kind it is', function (): void {
    $rendered = callouts(AdvancedRichContentRenderer::make(storedCallout('warning'))->toHtml());

    expect($rendered['count'])->toBe(1)
        ->and($rendered['classes'])->toBe(['fi-arte-callout', 'fi-arte-callout-warning'])
        ->and($rendered['text'])->toBe('Mind the gap');
});

it('renders without being told the field had callouts on', function (): void {
    // The renderer declares the node unconditionally, for the same reason it declares the
    // embed one: a renderer that has to be told is one that silently drops every callout in
    // a document the day somebody forgets to tell it.
    expect(callouts(AdvancedRichContentRenderer::make(storedCallout('danger'))->toHtml())['count'])
        ->toBe(1);
});

it('survives the round trip a save goes through', function (): void {
    // Content is re-parsed on hydration and again on dehydration. A callout the parser
    // cannot read back is one that vanishes the first time the record is reopened.
    $stored = storedCallout('tip');

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect(callouts($once)['classes'])->toBe(['fi-arte-callout', 'fi-arte-callout-tip'])
        ->and($twice)->toBe($once);
});

it('keeps the blocks inside rather than flattening them into one paragraph', function (): void {
    // A callout that cannot hold a list or a second paragraph is a coloured sentence.
    $stored = storedCallout('note', '<p>First</p><ul><li>One</li><li>Two</li></ul>');

    $html = AdvancedRichContentRenderer::make($stored)->toHtml();

    expect($html)->toContain('<ul>')
        ->and($html)->toContain('<li>One</li>')
        ->and(callouts($html)['count'])->toBe(1);
});

it('falls back to the default kind when the class says nothing readable', function (): void {
    // A callout drawn in the wrong colour is a smaller problem than one that is not drawn
    // at all, which is what dropping the node would mean for a document already holding it.
    $stored = '<div class="fi-arte-callout" data-type="callout"><p>Hand written</p></div>';

    expect(callouts(AdvancedRichContentRenderer::make($stored)->toHtml())['classes'])
        ->toBe(['fi-arte-callout', 'fi-arte-callout-note']);
});

it('leaves a div belonging to somebody else alone', function (): void {
    // `data-type` carries task lists, grids and custom blocks as well, so the parse rule
    // checks the value rather than only the attribute.
    $stored = '<div data-type="details"><p>Not a callout</p></div>';

    expect(callouts(AdvancedRichContentRenderer::make($stored)->toHtml())['count'])->toBe(0);
});

it('survives what the sanitiser leaves of it', function (): void {
    // `class` and `data-type` are the two attributes Filament's `HtmlSanitizerConfig` keeps
    // on every element, and they are the two the kind and the node ride on. This asserts
    // that through the real sanitiser rather than by reading the allow list.
    $sanitised = AdvancedRichContentRenderer::make(storedCallout('danger'))->toHtml();

    expect(callouts($sanitised))
        ->toMatchArray([
            'type' => 'callout',
            'classes' => ['fi-arte-callout', 'fi-arte-callout-danger'],
        ]);
});

it('registers one tool per kind, in the configured order', function (): void {
    $tools = editor()->calloutVariants(['danger', 'note'])->getTools();

    expect(array_keys($tools))->toContain('calloutDanger', 'calloutNote')
        ->and(array_keys($tools))->not->toContain('calloutTip')
        ->and($tools['calloutDanger']->getJsHandler())
        ->toBe("\$getEditor()?.chain().focus().toggleCallout('danger').run()");
});

it('lights the button up only for the kind the caret is in', function (): void {
    $tool = editor()->getTools()['calloutWarning'];

    expect($tool->getActiveKey())->toBe('callout')
        ->and($tool->getActiveOptions())->toBe(['variant' => 'warning']);
});

it('puts the kinds behind one dropdown on the bar', function (): void {
    $group = collect(editor()->getToolbarButtons())
        ->flatten()
        ->first(static fn (mixed $item): bool => $item instanceof ToolbarDropdown
            && $item->getButtons() === ['calloutNote', 'calloutTip', 'calloutWarning', 'calloutDanger']);

    expect($group)->not->toBeNull();
});

it('drops the trigger, the tools and the extension when the field switched them off', function (): void {
    $editor = editor()->callouts(false);

    expect(pluginNames($editor))->not->toContain(CalloutPlugin::class)
        ->and(array_keys($editor->getTools()))->not->toContain('calloutNote')
        ->and(toolbarShape($editor)[8])->toBe([
            'dropdown:bulletList,orderedList,taskList', 'mediaBrowser', 'table',
        ]);
});

it('drops the trigger when every configured kind was dropped as unusable', function (): void {
    // A trigger opening onto nothing is worse than no trigger, which is the rule the
    // spacing and colour dropdowns follow as well.
    $editor = editor()->calloutVariants(['Not A Variant', '']);

    expect($editor->getCalloutVariants())->toBe([])
        ->and(pluginNames($editor))->not->toContain(CalloutPlugin::class)
        ->and(toolbarShape($editor)[8])->toBe([
            'dropdown:bulletList,orderedList,taskList', 'mediaBrowser', 'table',
        ]);
});

it('refuses a name that could not be a class or an argument', function (): void {
    // A variant travels into a CSS class and into the JavaScript a button carries, which is
    // assembled by interpolation. Names that do not fit are dropped rather than escaped:
    // there is no legitimate variant called `note') || alert('`. Case is forgiven, since
    // `Note` names the same box as `note` and nothing downstream can tell them apart.
    expect(Callouts::normalize(["note') || alert('", ' Tip ', 'tip', '9lives', 'a-b', 'legal notice']))
        ->toBe(['tip', 'a-b']);
});

it('lists the kinds in the slash menu under what a block is', function (): void {
    $groups = collect(SlashMenu::for(editor())['groups'])->keyBy('key');

    expect(array_column($groups['style']['items'], 'name'))
        ->toContain('calloutNote', 'calloutDanger')
        ->and(array_column($groups['insert']['items'], 'name'))
        ->not->toContain('calloutNote');
});

it('answers to the words somebody types instead of the label', function (): void {
    $item = collect(SlashMenu::for(editor())['groups'])
        ->pluck('items')
        ->flatten(1)
        ->firstWhere('name', 'calloutWarning');

    expect($item['aliases'])->toContain('warning', 'caution')
        ->and($item['label'])->toBe('Warning');
});

it('titles a kind the translations have never heard of from its own name', function (): void {
    $editor = editor()->calloutVariants(['note', 'legal-notice']);

    expect($editor->getTools()['calloutLegalNotice']->getLabel())->toBe('Legal Notice')
        // And gives it the family's icon rather than throwing for a key nobody registered.
        ->and($editor->getTools()['calloutLegalNotice']->getIcon())
        ->toBe($editor->getTools()['calloutNote']->getIcon());
});

it('reads its defaults from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.callouts', [
        'enabled' => true,
        'variants' => ['tip'],
    ]);

    expect(editor()->getCalloutVariants())->toBe(['tip']);

    config()->set('filament-advanced-rich-editor.callouts.enabled', false);

    expect(editor()->hasCallouts())->toBeFalse()
        // The field still wins over the config, both ways round.
        ->and(editor()->callouts()->hasCallouts())->toBeTrue();
});
