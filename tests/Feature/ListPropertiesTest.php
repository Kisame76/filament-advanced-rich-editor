<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ListProperties;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\ListPropertiesPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarListPanel;

/**
 * The attributes of the first list in a fragment.
 *
 * @return array<string, string>
 */
function listAttributes(string $html, string $tag = 'ol'): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);

    $element = $document->getElementsByTagName($tag)->item(0);

    $attributes = [];

    foreach ($element?->attributes ?? [] as $attribute) {
        $attributes[$attribute->nodeName] = $attribute->nodeValue;
    }

    ksort($attributes);

    return $attributes;
}

it('renders the marker, the start and the direction a list was given', function (): void {
    $stored = '<ol type="a" start="3" reversed><li>One</li><li>Two</li></ol>';

    expect(listAttributes(AdvancedRichContentRenderer::make($stored)->toHtml()))
        ->toBe([
            'reversed' => 'reversed',
            'start' => '3',
            'style' => 'list-style-type: lower-alpha;',
            'type' => 'a',
        ]);
});

it('renders the marker a bullet list was given', function (): void {
    $stored = '<ul type="square"><li>One</li></ul>';

    expect(listAttributes(AdvancedRichContentRenderer::make($stored)->toHtml(), 'ul'))
        ->toBe(['style' => 'list-style-type: square;', 'type' => 'square']);
});

it('renders without being told the field had the properties on', function (): void {
    // Declared unconditionally by the renderer, for the reason the embed node is: a list
    // somebody set to start at twelve should still start at twelve, whatever this render
    // was told about the field it was written in.
    expect(listAttributes(AdvancedRichContentRenderer::make('<ol start="12"><li>a</li></ol>')->toHtml()))
        ->toBe(['start' => '12']);
});

it('survives the round trip a save goes through', function (): void {
    $stored = '<ol type="I" start="4" reversed><li><p>One</p></li></ol>';

    $once = editor()->getTipTapEditor()->setContent($stored)->getHTML();
    $twice = editor()->getTipTapEditor()->setContent($once)->getHTML();

    expect(listAttributes($once))->toBe([
        'reversed' => 'reversed',
        'start' => '4',
        'style' => 'list-style-type: upper-roman;',
        'type' => 'I',
    ])
        ->and($twice)->toBe($once);
});

it('keeps the attributes through the sanitiser', function (): void {
    // `type`, `start` and `reversed` are all on Symfony's safe list, so nothing has to be
    // allowed in the application's own sanitiser config. Asserted through the real
    // sanitiser rather than by reading the list.
    $rendered = AdvancedRichContentRenderer::make('<ol type="i" start="2" reversed><li>a</li></ol>')->toHtml();

    expect(listAttributes($rendered))->toBe([
        'reversed' => 'reversed',
        'start' => '2',
        'style' => 'list-style-type: lower-roman;',
        'type' => 'i',
    ]);
});

it('tells the two kinds of marker apart', function (): void {
    // `square` is a bullet and `a` is a numbering, and neither belongs on the other list.
    expect(ListProperties::type('a', 'orderedList'))->toBe('a')
        ->and(ListProperties::type('a', 'bulletList'))->toBeNull()
        ->and(ListProperties::type('square', 'bulletList'))->toBe('square')
        ->and(ListProperties::type('square', 'orderedList'))->toBeNull();
});

it('keeps the case of a marker, because a and A are different alphabets', function (): void {
    // The one place this package does not fold case: `a`/`A` and `i`/`I` are four different
    // things, so lowercasing them the way callout kinds and language codes are lowercased
    // would turn four choices into two.
    expect(ListProperties::type('A', 'orderedList'))->toBe('A')
        ->and(ListProperties::type('I', 'orderedList'))->toBe('I')
        ->and(listAttributes(AdvancedRichContentRenderer::make('<ol type="A"><li>a</li></ol>')->toHtml()))
        ->toBe(['style' => 'list-style-type: upper-alpha;', 'type' => 'A']);
});

it('writes the marker as CSS as well, because the attribute alone loses', function (): void {
    // Filament's prose styles set `list-style-type` on every list, and a stylesheet beats a
    // presentational attribute every time - so a list marked `type="a"` would still be drawn
    // with numbers. The inline copy is what survives somebody else's CSS, which is the same
    // reason the embed wrapper carries its aspect ratio inline.
    $rendered = AdvancedRichContentRenderer::make('<ol type="i"><li>a</li></ol>')->toHtml();

    expect($rendered)->toContain('list-style-type: lower-roman;');
});

it('reads a marker back out of the CSS a foreign document carries', function (): void {
    // Word and Google Docs write the style and not the attribute, so a list pasted from one
    // of them arrives as plain numbers unless the style is read as well.
    $stored = '<ol style="list-style-type: upper-alpha"><li>One</li></ol>';

    expect(listAttributes(AdvancedRichContentRenderer::make($stored)->toHtml()))
        ->toBe(['style' => 'list-style-type: upper-alpha;', 'type' => 'A']);
});

it('reads the plain numbering back out of the CSS as well', function (): void {
    // `1` is an integer array key by the time the map is read back out, which is exactly
    // the kind of thing that turns into a silent hole - `decimal` would map to nothing and
    // an imported list would lose the one marker most of them use.
    expect(ListProperties::fromStyle('list-style-type: decimal', 'orderedList'))->toBe('1');
});

it('reads no marker out of a style that names one for the other kind of list', function (): void {
    // `lower-alpha` is a numbering, and a bullet list cannot count.
    expect(listAttributes(
        AdvancedRichContentRenderer::make('<ul style="list-style-type: lower-alpha"><li>a</li></ul>')->toHtml(),
        'ul',
    ))->toBe([]);
});

it('drops a marker nothing draws', function (): void {
    $rendered = AdvancedRichContentRenderer::make('<ol type="javascript:alert(1)"><li>a</li></ol>')->toHtml();

    expect(listAttributes($rendered))->toBe([])
        // And the list itself survives: refusing the attribute must not eat the items.
        ->and($rendered)->toContain('<li>');
});

it('writes no start for the number a list would count from anyway', function (): void {
    // `start="1"` on every list would be an attribute saying exactly what its absence says.
    expect(ListProperties::start(1))->toBeNull()
        ->and(ListProperties::start(0))->toBeNull()
        ->and(ListProperties::start(-5))->toBeNull()
        ->and(ListProperties::start('7'))->toBe(7)
        ->and(ListProperties::start(ListProperties::MAX_START + 1))->toBeNull()
        ->and(ListProperties::start('not a number'))->toBeNull();
});

it('reads reversed as the boolean attribute it is', function (): void {
    // What a browser reads is whether the attribute is there, not what it says - so null
    // has to mean absent, and it is never false.
    expect(ListProperties::reversed(null))->toBeNull()
        ->and(ListProperties::reversed(''))->toBeTrue()
        ->and(ListProperties::reversed('reversed'))->toBeTrue()
        ->and(ListProperties::reversed(true))->toBeTrue()
        // The one spelling that is refused: nothing writes it on purpose, and reading it as
        // true would mean disagreeing with whoever typed it.
        ->and(ListProperties::reversed('false'))->toBeNull();
});

it('puts the controls in the bubble that appears while the caret is in a list', function (): void {
    // Not on the bar. These are three controls that mean nothing anywhere else in a
    // document, and a bar already carrying five dropdowns has no room to say so
    // permanently.
    $toolbars = editor()->getFloatingToolbars();

    expect($toolbars)->toHaveKeys(['bulletList', 'orderedList'])
        ->and($toolbars['bulletList'][0])->toBeInstanceOf(ToolbarListPanel::class)
        ->and($toolbars['orderedList'][0]->isOrdered())->toBeTrue()
        ->and($toolbars['bulletList'][0]->isOrdered())->toBeFalse();
});

it('offers the numberings on one list and the bullets on the other', function (): void {
    // Without the one a browser already draws unasked: a button for `disc` beside the
    // Default button is two buttons that draw the same list, and `1` beside Default is the
    // same again for the numbers.
    expect(array_column(ToolbarListPanel::ordered()->getMarkers(), 'value'))
        ->toBe(['a', 'A', 'i', 'I'])
        ->and(array_column(ToolbarListPanel::bullet()->getMarkers(), 'value'))
        ->toBe(['circle', 'square']);
});

it('still accepts the implied marker in a document that carries it', function (): void {
    // What is valid and what is worth offering are two questions. A list that already says
    // `type="disc"` keeps it rather than having it quietly stripped on the next save.
    expect(ListProperties::type('disc', 'bulletList'))->toBe('disc')
        ->and(ListProperties::type('1', 'orderedList'))->toBe('1')
        ->and(listAttributes(AdvancedRichContentRenderer::make('<ul type="disc"><li>a</li></ul>')->toHtml(), 'ul'))
        ->toBe(['style' => 'list-style-type: disc;', 'type' => 'disc']);
});

it('lights Default up for a list carrying the marker Default draws', function (): void {
    // Otherwise a list stored as `type="disc"` would show no active button at all, because
    // the panel no longer offers one for it.
    expect(ToolbarListPanel::bullet()->toEmbeddedHtml())->toContain("this.marker = type === 'disc' ? null : type")
        ->and(ToolbarListPanel::ordered()->toEmbeddedHtml())->toContain("this.marker = type === '1' ? null : type");
});

it('names a numbering by an example rather than by a name', function (): void {
    // "a, b, c" says what the choice does; "Lower alpha" says what somebody called it.
    $markers = collect(ToolbarListPanel::ordered()->getMarkers())->keyBy('value');

    expect($markers['a']['label'])->toBe('a, b, c')
        ->and($markers['I']['label'])->toBe('I, II, III');
});

it('draws the start field and the direction only for a list that counts', function (): void {
    expect(ToolbarListPanel::ordered()->toEmbeddedHtml())->toContain('setListStart')
        ->and(ToolbarListPanel::bullet()->toEmbeddedHtml())->not->toContain('setListStart')
        ->and(ToolbarListPanel::bullet()->toEmbeddedHtml())->not->toContain('toggleListReversed');
});

it('drops the panels and the extension when the field switched them off', function (): void {
    $editor = editor()->listProperties(false);

    expect(pluginNames($editor))->not->toContain(ListPropertiesPlugin::class)
        ->and($editor->getFloatingToolbars())->not->toHaveKey('bulletList')
        ->and($editor->getFloatingToolbars())->not->toHaveKey('orderedList');
});

it('reads its default from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.list_properties', false);

    expect(editor()->hasListProperties())->toBeFalse()
        ->and(editor()->listProperties()->hasListProperties())->toBeTrue();
});
