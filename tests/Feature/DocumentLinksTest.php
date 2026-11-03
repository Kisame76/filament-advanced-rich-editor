<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\DocumentLinks;

/**
 * Every address a stored document sends its readers to, read out of either shape a column
 * holds. A link on text and a link on a picture count; a download card and an embed do not -
 * the one points into the library, the other is never followed anywhere.
 */
it('reads the links out of markup, in order and each once', function (): void {
    $markup = '<p>See <a href="https://example.com/a">a</a>, <a href="/about">about</a> '
        .'and <a href="https://example.com/a">a again</a>.</p>'
        .'<p><img src="/storage/x.png" data-href="https://example.com/picture"></p>';

    expect(DocumentLinks::of($markup))->toBe(['https://example.com/a', '/about', 'https://example.com/picture']);
});

it('leaves download cards out of the markup', function (): void {
    // A card is an `<a>` with an `href` like any link - it says what it is by the type its
    // node writes, or by the `download` a hand-written one carries.
    $markup = '<a href="/storage/report.pdf" data-type="file" data-id="1">Report</a>'
        .'<a href="/storage/old.pdf" download>Old</a>'
        .'<p><a href="https://example.com/">kept</a></p>';

    expect(DocumentLinks::of($markup))->toBe(['https://example.com/']);
});

it('reads the links out of a tree, from marks on text however deep they sit', function (): void {
    $link = static fn (string $href, string $text): array => [
        'type' => 'text',
        'text' => $text,
        'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => $href]]],
    ];

    $document = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [$link('https://example.com/one', 'one')]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [$link('https://example.com/two', 'two'), $link('https://example.com/one', 'one')]],
            ]],
        ]],
        ['type' => 'image', 'attrs' => ['src' => '/x.png', 'href' => 'https://example.com/picture']],
        ['type' => 'image', 'attrs' => ['src' => '/y.png']],
    ]];

    expect(DocumentLinks::of($document))->toBe(['https://example.com/one', 'https://example.com/two', 'https://example.com/picture']);
});

it('finds nothing where there is nothing to find', function (mixed $document): void {
    expect(DocumentLinks::of($document))->toBe([]);
})->with([
    'nothing' => [null],
    'an empty string' => [''],
    'text without a link' => ['<p>Plain text</p>'],
    'an empty href' => ['<p><a href=" ">blank</a></p>'],
    'an empty tree' => [['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
]);
