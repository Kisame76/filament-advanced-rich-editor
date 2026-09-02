<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;

/**
 * What a field may say about its content beyond how many characters it holds.
 *
 * Filament measures `maxLength()` and `minLength()` on the serialised text, and this package
 * shows that same number under the field. What neither answers is a question about words, or
 * one about the document tree: an article that needs a picture, a teaser that must stay under
 * fifty words, a page that has to carry a heading.
 */

/**
 * @param  array<int, array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function headingOf(array $content, int $level = 2): array
{
    return ['type' => 'heading', 'attrs' => ['level' => $level, 'textAlign' => 'start'], 'content' => $content];
}

/**
 * @return array<string, mixed>
 */
function textOf(string $text): array
{
    return ['type' => 'text', 'text' => $text];
}

/**
 * @return array<string, mixed>
 */
function imageOf(string $src = 'https://example.test/a.png'): array
{
    return ['type' => 'image', 'attrs' => ['src' => $src]];
}

/**
 * A document of one paragraph holding a given number of words.
 *
 * @return array<string, mixed>
 */
function wordsOf(int $words): array
{
    return documentOf([paragraphOf([textOf(trim(str_repeat('wort ', $words)))])]);
}

describe('how many words a field wants', function (): void {
    it('refuses a document over the maximum', function (): void {
        expect(validationErrors(editor()->maxWords(5), wordsOf(6)))->toHaveKey('content');
    });

    it('lets the maximum itself through', function (): void {
        // The boundary is inclusive, the same way `maxLength()` is: a limit of five words is
        // a promise that five words fit.
        expect(validationErrors(editor()->maxWords(5), wordsOf(5)))->toBe([]);
    });

    it('refuses a document under the minimum', function (): void {
        expect(validationErrors(editor()->minWords(10), wordsOf(9)))->toHaveKey('content');
    });

    it('lets the minimum itself through', function (): void {
        expect(validationErrors(editor()->minWords(10), wordsOf(10)))->toBe([]);
    });

    it('says the number it refused against', function (): void {
        $message = validationErrors(editor()->maxWords(5), wordsOf(6))['content'][0];

        expect($message)->toContain('5')
            // And it names the field, which is what tells somebody which editor on the page
            // it means.
            ->and($message)->toContain('content');
    });

    it('leaves an empty field to required, which is the rule that is about emptiness', function (): void {
        // A minimum of ten words on an optional field would otherwise make it mandatory by
        // accident. Filament's own length rules stand down on a blank value for this reason.
        //
        // Blank is the wrong question, though, and the untouched editor is why: it hands over
        // a document holding one empty paragraph, which is present, not blank, and holds no
        // words. `hasContent()` is this package's one answer to "is there anything in here",
        // and it is the answer used here too.
        expect(validationErrors(editor()->minWords(10), null))->toBe([])
            ->and(validationErrors(editor()->minWords(10), ''))->toBe([])
            ->and(validationErrors(editor()->minWords(10), []))->toBe([])
            ->and(validationErrors(editor()->minWords(10), '<p></p>'))->toBe([])
            ->and(validationErrors(editor()->minWords(10), documentOf([emptyParagraph()])))->toBe([])
            ->and(validationErrors(editor()->minWords(10), documentOf([emptyParagraph(), emptyParagraph()])))->toBe([]);
    });

    it('still refuses an empty field that was also required', function (): void {
        // Standing down is not the same as excusing: `required()` is the rule that is about
        // emptiness, and it still fires. What must not happen is two complaints about one
        // empty field, one of which asks for ten words.
        $errors = validationErrors(editor()->required()->minWords(10), documentOf([emptyParagraph()]));

        expect($errors)->toHaveKey('content')
            ->and($errors['content'])->toHaveCount(1);
    });

    it('counts the words the counter under the field shows', function (): void {
        // One answer to "how long is this". Two would disagree on the day one of them
        // learned about entities or non-breaking spaces and the other did not.
        $field = editor();
        $document = wordsOf(7);

        expect($field->measureCharacterCount($document)['words'])->toBe(7)
            ->and(validationErrors(editor()->maxWords(7), $document))->toBe([])
            ->and(validationErrors(editor()->maxWords(6), $document))->toHaveKey('content');
    });

    it('measures markup as well as a document, because state arrives as both', function (): void {
        expect(validationErrors(editor()->maxWords(2), '<p>eins zwei drei</p>'))->toHaveKey('content')
            ->and(validationErrors(editor()->maxWords(3), '<p>eins zwei drei</p>'))->toBe([]);
    });

    it('leaves a field alone that named no word rule', function (): void {
        expect(validationErrors(editor(), wordsOf(500)))->toBe([]);
    });

    it('shows words in the counter once it validates them', function (): void {
        // A field that refuses a save over three hundred words and then counts characters
        // underneath is a field that reports the wrong number.
        expect(editor()->hasCharacterCountWords())->toBeFalse()
            ->and(editor()->maxWords(300)->hasCharacterCountWords())->toBeTrue()
            ->and(editor()->minWords(50)->hasCharacterCountWords())->toBeTrue()
            // And the field still overrules it, the way it overrules the config.
            ->and(editor()->maxWords(300)->characterCountWords(false)->hasCharacterCountWords())->toBeFalse();
    });
});

describe('what a field wants the document to hold', function (): void {
    it('refuses a document that is missing what was asked for', function (): void {
        expect(validationErrors(editor()->mustContain('image'), wordsOf(20)))->toHaveKey('content');
    });

    it('accepts one that holds it, however deep it sits', function (): void {
        expect(validationErrors(editor()->mustContain('image'), documentOf([
            paragraphOf([textOf('hi'), imageOf()]),
        ])))->toBe([]);
    });

    it('finds a mark as well as a node, so a link can be asked for', function (): void {
        // A link is a mark on text rather than a node of its own, and somebody asking for
        // one does not care about that difference.
        $linked = documentOf([paragraphOf([
            ['type' => 'text', 'text' => 'hi', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test']]]],
        ])]);

        expect(validationErrors(editor()->mustContain('link'), $linked))->toBe([])
            ->and(validationErrors(editor()->mustContain('link'), wordsOf(3)))->toHaveKey('content');
    });

    it('takes several at once and refuses until every one is there', function (): void {
        $field = fn (): object => editor()->mustContain(['heading', 'image']);

        expect(validationErrors($field(), documentOf([headingOf([textOf('Titel')])])))->toHaveKey('content')
            ->and(validationErrors($field(), documentOf([imageOf()])))->toHaveKey('content')
            ->and(validationErrors($field(), documentOf([headingOf([textOf('Titel')]), imageOf()])))->toBe([]);
    });

    it('names what is missing in words rather than in node types', function (): void {
        $message = validationErrors(editor()->mustContain('image'), wordsOf(3))['content'][0];

        // "an image", not "image": the message is a sentence somebody reads, and the node
        // type is TipTap's vocabulary rather than theirs.
        expect($message)->toContain('an image')
            ->and($message)->not->toContain('advanced-rich-editor.validation');
    });

    it('falls back to the type name for a node nothing has a word for', function (): void {
        // A project's own node has no entry in the translation file, and a message saying
        // nothing at all would be worse than one saying the type.
        $message = validationErrors(editor()->mustContain('productCard'), wordsOf(3))['content'][0];

        expect($message)->toContain('productCard');
    });

    it('leaves an empty field to required', function (): void {
        expect(validationErrors(editor()->mustContain('image'), null))->toBe([])
            // The untouched editor again: present, not blank, and holding nothing.
            ->and(validationErrors(editor()->mustContain('image'), documentOf([emptyParagraph()])))->toBe([])
            ->and(validationErrors(editor()->mustContain('image'), '<p></p>'))->toBe([]);
    });

    it('leaves a field alone that asked for nothing', function (): void {
        expect(validationErrors(editor(), documentOf([emptyParagraph()])))->toBe([])
            ->and(validationErrors(editor()->mustContain([]), documentOf([emptyParagraph()])))->toBe([]);
    });

    it('answers the same question without a field, for an observer or a job', function (): void {
        // The static half, the same way `DocumentContent::isBlank()` is the static half of
        // `hasContent()`: where there is only the column, there is no field to ask.
        $document = documentOf([headingOf([textOf('Titel')]), imageOf()]);

        expect(DocumentContent::contains($document, 'image'))->toBeTrue()
            ->and(DocumentContent::contains($document, 'heading'))->toBeTrue()
            ->and(DocumentContent::contains($document, 'table'))->toBeFalse();
    });
});
