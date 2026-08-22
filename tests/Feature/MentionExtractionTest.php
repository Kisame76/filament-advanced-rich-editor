<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Mentions;

function mentionDocument(): string
{
    return '<p>Ping <span data-type="mention" data-id="2" data-label="Ada Lovelace" data-char="@"></span>'
        .' and <span data-type="mention" data-id="7" data-label="Backend" data-char="#"></span>,'
        .' and <span data-type="mention" data-id="2" data-label="Ada Lovelace" data-char="@"></span> again.</p>';
}

it('reads every mention in the order it was written', function (): void {
    expect(Mentions::in(mentionDocument())->all())->toBe([
        ['char' => '@', 'id' => '2', 'label' => 'Ada Lovelace'],
        ['char' => '#', 'id' => '7', 'label' => 'Backend'],
        ['char' => '@', 'id' => '2', 'label' => 'Ada Lovelace'],
    ]);
});

it('answers which ids one trigger mentioned, each of them once', function (): void {
    // What a notification needs: the people, not the number of times somebody typed them.
    expect(Mentions::in(mentionDocument())->ids('@'))->toBe(['2'])
        ->and(Mentions::in(mentionDocument())->ids('#'))->toBe(['7']);
});

it('answers with every id when no trigger is named', function (): void {
    expect(Mentions::in(mentionDocument())->ids())->toBe(['2', '7']);
});

it('groups the ids by the trigger they were written with', function (): void {
    expect(Mentions::in(mentionDocument())->grouped())->toBe([
        '@' => ['2'],
        '#' => ['7'],
    ]);
});

it('reads a document held as an array as readily as one held as HTML', function (): void {
    // A field with a rich content attribute stores a TipTap document, not markup.
    $document = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'mention',
                'attrs' => ['id' => '2', 'label' => 'Ada Lovelace', 'char' => '@'],
            ]],
        ]],
    ];

    expect(Mentions::in($document)->ids())->toBe(['2']);
});

it('finds a mention wherever it is nested', function (): void {
    $html = '<ul><li><p>See <span data-type="mention" data-id="9" data-label="Ops" data-char="@"></span></p></li></ul>';

    expect(Mentions::in($html)->ids())->toBe(['9']);
});

it('says nothing about a document that has nothing in it', function (): void {
    expect(Mentions::in(null)->all())->toBe([])
        ->and(Mentions::in('')->ids())->toBe([])
        ->and(Mentions::in('<p>Plain</p>')->grouped())->toBe([]);
});

it('ignores a mention that lost its id, which is the only part that identifies anyone', function (): void {
    $html = '<p><span data-type="mention" data-label="Nobody" data-char="@"></span></p>';

    expect(Mentions::in($html)->all())->toBe([]);
});

it('reports a missing label as missing rather than inventing one', function (): void {
    // The label is a copy of a name at the moment it was typed. A caller that wants the
    // name now has the id to look it up with.
    $html = '<p><span data-type="mention" data-id="4" data-char="@"></span></p>';

    expect(Mentions::in($html)->all())->toBe([
        ['char' => '@', 'id' => '4', 'label' => null],
    ]);
});
