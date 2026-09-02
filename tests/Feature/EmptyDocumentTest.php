<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;

it('rejects a document of nothing but empty paragraphs', function (): void {
    // Filament rejects a document holding exactly one empty paragraph. Pressing return in
    // an empty editor makes a second one, and the field is just as empty as it was.
    expect(validationErrors(editor()->required(), documentOf([emptyParagraph(), emptyParagraph()])))
        ->toHaveKey('content');
});

it('rejects a document whose only text is whitespace', function (): void {
    expect(validationErrors(editor()->required(), documentOf([
        paragraphOf([['type' => 'text', 'text' => '   ']]),
    ])))->toHaveKey('content');
});

it('rejects a document whose only text is a non-breaking space', function (): void {
    // What a paste from Word leaves behind, and what `trim()` alone does not catch: a
    // non-breaking space is not ASCII whitespace, and it puts nothing on the page either.
    expect(validationErrors(editor()->required(), documentOf([
        paragraphOf([['type' => 'text', 'text' => "\u{00A0}\u{00A0}"]]),
    ])))->toHaveKey('content');
});

it('rejects a document of nothing but line breaks', function (): void {
    expect(validationErrors(editor()->required(), documentOf([
        paragraphOf([['type' => 'hardBreak'], ['type' => 'hardBreak']]),
    ])))->toHaveKey('content');
});

it('rejects an empty document that arrived as markup', function (): void {
    // State is not always a document: it is markup until something casts it, which is why
    // Filament carries its own workaround for a string arriving in `callAfterStateUpdated`.
    expect(validationErrors(editor()->required(), '<p></p>'))
        ->toHaveKey('content');
});

it('rejects a required field holding nothing at all', function (): void {
    expect(validationErrors(editor()->required(), null))
        ->toHaveKey('content');
});

it('keeps rejecting the empty document filament already knew about', function (): void {
    expect(validationErrors(editor()->required(), documentOf([emptyParagraph()])))
        ->toHaveKey('content');
});

it('lets a document with text through', function (): void {
    expect(validationErrors(editor()->required(), documentOf([
        paragraphOf([['type' => 'text', 'text' => 'hi']]),
    ])))->toBe([]);
});

it('lets a document through that holds only an image', function (): void {
    // The direction an unknown node has to fail in: anything that is not text, a paragraph
    // or a line break puts something on the page, so a field holding it is not empty.
    expect(validationErrors(editor()->required(), documentOf([
        paragraphOf([['type' => 'image', 'attrs' => ['src' => 'https://example.test/a.png']]]),
    ])))->toBe([]);
});

it('lets an empty list through, because an empty list still shows a bullet', function (): void {
    expect(validationErrors(editor()->required(), documentOf([
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [emptyParagraph()]],
        ]],
    ])))->toBe([]);
});

it('leaves a field alone that was never required', function (): void {
    expect(validationErrors(editor(), documentOf([emptyParagraph(), emptyParagraph()])))->toBe([])
        ->and(validationErrors(editor(), null))->toBe([]);
});

/**
 * What a save would write, built out of the two steps a schema takes on the way out - the
 * component dehydrates its raw state, then the schema mutates the result. Both are needed
 * here, because the second one is guarded by `mutatesDehydratedState()` and that guard is
 * part of what a field decides.
 */
function dehydratedState(AdvancedRichEditor $field, mixed $state): mixed
{
    $field->getContainer()->components([$field]);

    $field->state($state);

    $value = $field->getStateToDehydrate($field->getRawState())[$field->getStatePath()];

    return $field->mutatesDehydratedState()
        ? $field->mutateDehydratedState($value)
        : $value;
}

it('hydrates a record whose column holds an empty string', function (): void {
    // A `text NOT NULL DEFAULT ''` column is ordinary, and Filament's cast only guards
    // against null: `setContent('')` walks a document body that was never built and dies on
    // the null it finds. The form does not render at all, and the message names a DOM parser.
    expect(dehydratedState(editor(), ''))->toBe('<p></p>');
});

it('stores nothing for an empty document when the field asks for it', function (): void {
    expect(dehydratedState(editor()->nullWhenEmpty(), '<p></p>'))->toBeNull()
        ->and(dehydratedState(editor()->nullWhenEmpty(), '<p><br></p>'))->toBeNull()
        ->and(dehydratedState(editor()->nullWhenEmpty(), ''))->toBeNull();
});

it('still stores a document that has something in it', function (): void {
    expect(dehydratedState(editor()->nullWhenEmpty(), '<p>hi</p>'))->toBe('<p>hi</p>');
});

it('keeps storing filaments empty paragraph unless it is asked not to', function (): void {
    // Not the default: a column that is `NOT NULL` without a default takes `<p></p>` and
    // refuses a null, so flipping this for everyone would break a save that works today.
    expect(dehydratedState(editor(), '<p></p>'))->toBe('<p></p>');
});

it('reads whether to store nothing from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.null_when_empty', true);

    expect(editor()->shouldBeNullWhenEmpty())->toBeTrue()
        ->and(dehydratedState(editor(), '<p></p>'))->toBeNull()
        // A field still wins over the config, in both directions.
        ->and(dehydratedState(editor()->nullWhenEmpty(false), '<p></p>'))->toBe('<p></p>');
});

it('stores nothing for an empty document in json mode too', function (): void {
    // `json()` dehydrates the document rather than markup, so the emptiness question is
    // asked of an array here and of a string above - one answer has to cover both.
    expect(dehydratedState(editor()->json()->nullWhenEmpty(), '<p></p>'))->toBeNull()
        ->and(dehydratedState(editor()->json()->nullWhenEmpty(), '<p>hi</p>'))->toBeArray();
});

/**
 * The same question asked of a column rather than of a field.
 *
 * `DocumentContent::isBlank()` is what the docs hand an observer, a queued job or a model
 * hook, and none of the three can narrow the shape first: a JSON column gives back a `doc`
 * array and a `text` one gives back markup. Answering only for the array would have made this
 * right for half the records and quietly wrong for the other half, which is worse than being
 * wrong for all of them, because nothing would report it.
 */
it('calls a document of empty paragraphs blank in either shape it is stored in', function (mixed $stored): void {
    expect(DocumentContent::isBlank($stored))->toBeTrue();
})->with([
    // Every row wrapped, because a dataset spreads an array over the test's parameters and
    // a document is one argument rather than a list of them.
    'nothing at all' => [null],
    'an empty column' => [''],
    'the paragraph TipTap always keeps' => ['<p></p>'],
    'the second one a stray return leaves behind' => ['<p></p><p></p>'],
    'a line break' => ['<p><br></p>'],
    'a paragraph carrying an attribute' => ['<p style="text-align: start"></p>'],
    'the space a paste from Word leaves' => ['<p>&nbsp;</p>'],
    'a zero width space' => ["<p>\u{200B}</p>"],
    'the same document as a tree' => [['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
]);

it('calls a document with something in it content in either shape', function (mixed $stored): void {
    expect(DocumentContent::isBlank($stored))->toBeFalse();
})->with([
    'words' => ['<p>hi</p>'],
    'a heading, empty or not' => ['<h2></h2>'],
    'a picture' => ['<p><img src="https://example.test/a.png"></p>'],
    'a list' => ['<ul><li>eins</li></ul>'],
    'a rule' => ['<hr>'],
    'a node this package never heard of' => ['<product-card id="7"></product-card>'],
    'the same document as a tree' => [['type' => 'doc', 'content' => [['type' => 'image']]]],
]);

it('reads a smaller than as text, the way somebody writing it means it', function (): void {
    // Blank, because `a < b` is a sentence rather than a tag - and the tag test is what
    // decides whether markup holds anything, so it has to know the difference.
    expect(DocumentContent::isBlank('<p></p>'))->toBeTrue()
        ->and(DocumentContent::isBlank('<p>a < b</p>'))->toBeFalse();
});

it('believes a shape it cannot read, rather than calling it empty', function (): void {
    // The direction the whole class is written in: an unknown node counts as content, and so
    // does an unknown column. The mistake worth making is a listener that ran for nothing.
    expect(DocumentContent::isBlank(new stdClass))->toBeFalse()
        ->and(DocumentContent::isBlank(42))->toBeFalse();
});
