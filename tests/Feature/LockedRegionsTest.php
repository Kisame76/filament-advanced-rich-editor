<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\LockedRegions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Locked;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\LockedPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * Parts of a document the people writing in it cannot change. The editor's guard is tested in
 * `tests/js/locked.test.js`; what is tested here is the node, the switches, and the check every
 * save goes through on the server - which is what actually keeps a part locked.
 */
const TEMPLATE = '<div data-type="locked"><p>Clause one stays as it is.</p></div><p>Write here.</p>';

/**
 * A field holding the template, as a record that was stored with it.
 */
function lockedField(?Closure $configure = null): AdvancedRichEditor
{
    $field = editor()->lockedRegions();

    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->setRawAttributes(['content' => TEMPLATE], sync: true);
    $field->getContainer()->record($record);

    return $configure ? $configure($field) : $field;
}

it('ships off, with nothing loaded and nothing checked', function (): void {
    // Most fields hold something one person writes from top to bottom.
    expect(editor()->hasLockedRegions())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('lock')
        ->and(editor()->getLockedRegionSettingsForJs())->toBeNull()
        ->and(editor()->getLockedRegionRules())->toBe([]);

    config()->set('filament-advanced-rich-editor.locked_regions', true);

    expect(editor()->hasLockedRegions())->toBeTrue();
});

it('keeps a locked part through the editor, and unwraps it on a field without the feature', function (): void {
    $document = editor()->lockedRegions()->toDocument(TEMPLATE);

    expect($document['content'][0]['type'])->toBe('locked')
        ->and(editor()->lockedRegions()->getTipTapEditor()->setContent(TEMPLATE)->getHTML())
        ->toContain('<div class="fi-arte-locked" data-type="locked"><p>Clause one stays as it is.</p></div>');

    // Switched off, the editor in the browser has no such node and keeps the text without
    // the frame - so the next save unlocks it. Only the script that knows the node is missing.
    expect(editor()->getTipTapJsExtensions())->each->not->toContain('advanced-rich-editor/locked')
        ->and(editor()->lockedRegions()->getTipTapJsExtensions())
        ->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/locked', 'kisame76/filament-advanced-rich-editor'));
});

it('offers the padlock only to somebody who may lock', function (): void {
    $writer = editor()->lockedRegions();
    $author = editor()->lockedRegions()->lockedRegionsEditable();

    expect($writer->getTools())->not->toHaveKey('lock')
        ->and(ToolbarLayout::tokens()['lock']($writer))->toBe([])
        ->and($author->getTools())->toHaveKey('lock')
        ->and(ToolbarLayout::tokens()['lock']($author))->toBe('lock')
        // In the shipped more menu, so a field that may lock has it without naming it.
        ->and(config('filament-advanced-rich-editor.more'))->toContain('lock')
        ->and($writer->getLockedRegionSettingsForJs())->toMatchArray(['editable' => false, 'label' => 'Locked'])
        ->and($author->getLockedRegionSettingsForJs())->toMatchArray(['editable' => true])
        ->and(LockedPlugin::make()->getTipTapPhpExtensions()[0])->toBeInstanceOf(Locked::class);
});

it('compares the locked parts as a collection, by what they hold', function (): void {
    $render = static fn (array $part): string => json_encode($part, JSON_THROW_ON_ERROR);
    $locked = static fn (string $text): array => ['type' => 'locked', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    $paragraph = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'free']]];

    $before = documentOf([$locked('a'), $paragraph, $locked('b')]);

    expect(LockedRegions::in($before))->toHaveCount(2)
        // Writing around them, and moving them, is not changing them.
        ->and(LockedRegions::kept($before, documentOf([$locked('a'), $locked('b')]), $render))->toBeTrue()
        ->and(LockedRegions::kept($before, documentOf([$locked('b'), $paragraph, $locked('a')]), $render))->toBeTrue()
        // Changing, removing and adding one is.
        ->and(LockedRegions::kept($before, documentOf([$locked('a'), $locked('B')]), $render))->toBeFalse()
        ->and(LockedRegions::kept($before, documentOf([$locked('a')]), $render))->toBeFalse()
        ->and(LockedRegions::kept($before, documentOf([$locked('a'), $locked('b'), $locked('c')]), $render))->toBeFalse()
        // A part inside another is part of it.
        ->and(LockedRegions::in(documentOf([['type' => 'locked', 'content' => [$locked('x')]]])))->toHaveCount(1);
});

it('accepts a save that only changed what is not locked', function (): void {
    expect(validationErrors(lockedField(), '<div data-type="locked"><p>Clause one stays as it is.</p></div><p>Written.</p>'))->toBe([]);

    // And in the shape the browser sends, with every attribute its own schema adds - which the
    // record, read from markup, does not have.
    $paragraph = static fn (string $text): array => [
        'type' => 'paragraph',
        'attrs' => ['textAlign' => 'start'],
        'content' => [['type' => 'text', 'text' => $text]],
    ];

    expect(validationErrors(lockedField(), documentOf([
        ['type' => 'locked', 'content' => [$paragraph('Clause one stays as it is.')]],
        $paragraph('Written.'),
    ])))->toBe([]);
});

it('refuses a save that changed, removed or emptied a locked part, whatever the browser let through', function (string $state): void {
    expect(validationErrors(lockedField(), $state)['content'] ?? [])
        ->toContain('Locked parts of the content cannot be changed.');
})->with([
    'changed' => '<div data-type="locked"><p>Clause one says something else.</p></div><p>Write here.</p>',
    'removed' => '<p>Write here.</p>',
    // Laravel skips a closure rule on an empty value; this one is not a closure.
    'emptied' => '',
    'added' => TEMPLATE.'<div data-type="locked"><p>Mine now.</p></div>',
]);

it('lets somebody who may lock change the locked parts', function (): void {
    $author = lockedField(fn ($field) => $field->lockedRegionsEditable());

    expect($author->getLockedRegionRules())->toBe([])
        ->and(validationErrors($author, '<p>Write here.</p>'))->toBe([]);
});

it('measures a new record against the default it starts from, which is where a template comes in', function (): void {
    $field = editor()->lockedRegions()->default(TEMPLATE);

    expect($field->keepsLockedRegions(TEMPLATE.'<p>More.</p>'))->toBeTrue()
        ->and($field->keepsLockedRegions('<p>Write here.</p>'))->toBeFalse();
});

it('gives a reader the text of a locked part, in a plain wrapper', function (): void {
    $html = AdvancedRichContentRenderer::make(editor()->lockedRegions()->toDocument(TEMPLATE))->toHtml();

    expect($html)->toContain('Clause one stays as it is.')
        ->and($html)->toContain('class="fi-arte-locked"');
});
