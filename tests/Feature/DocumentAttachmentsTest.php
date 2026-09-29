<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentAttachments;

/**
 * A file deleted or replaced in the library, in the documents as they are stored.
 *
 * The same discipline as `DocumentTasks`: what changes is the element pointing at the file,
 * and everything around it comes back out as it went in - the paragraph beside a picture has
 * no business being rewritten because the picture was.
 */
$card = static fn (string $id, string $href, string $name, string $size): string => '<a class="fi-arte-file" data-type="file" data-id="'.$id.'" href="'.$href.'" download="'.$name.'" style="display: inline-flex;">'
    .'<span class="fi-arte-file-kind" style="width: 2.25rem;">PDF</span> '
    .'<span class="fi-arte-file-text" style="margin: 0;"><span class="fi-arte-file-name" style="margin: 0;">'.$name.'</span> '
    .'<span class="fi-arte-file-size" style="margin: 0;">'.$size.'</span></span></a>';

it('says whether a document points at a file', function () use ($card): void {
    $html = '<p>Über uns '.$card('a', '/storage/a.pdf', 'Preise.pdf', '1 KB').'</p>';

    $json = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => 'a', 'src' => '/a.png']]]],
    ]];

    expect(DocumentAttachments::contains($html, 'a'))->toBeTrue()
        ->and(DocumentAttachments::contains($html, 'b'))->toBeFalse()
        ->and(DocumentAttachments::contains($json, 'a'))->toBeTrue()
        ->and(DocumentAttachments::contains($json, 'b'))->toBeFalse()
        ->and(DocumentAttachments::contains(null, 'a'))->toBeFalse();
});

it('takes every element pointing at the file out of stored markup, and nothing else', function () use ($card): void {
    $html = '<p>Über uns <img src="/storage/a.png" data-id="a" width="40" height="20" style="width: 40px; height: 20px"> und '
        .$card('a', '/storage/a.png', 'a.png', '1 KB').'</p>'
        .'<video src="/storage/a.png" data-id="a" controls="controls"></video>'
        .'<p><img src="/storage/b.png" data-id="b"> bleibt</p>';

    expect(DocumentAttachments::remove($html, 'a'))->toBe(
        '<p>Über uns  und </p><p><img src="/storage/b.png" data-id="b"> bleibt</p>',
    );
});

it('leaves a custom block that happens to carry the same id alone', function (): void {
    // A custom block's `data-id` names the block, not a file - only a picture, a player and a
    // card point at the library.
    $html = '<div data-type="customBlock" data-id="a" data-config="{}"></div>';

    expect(DocumentAttachments::remove($html, 'a'))->toBeNull()
        ->and(DocumentAttachments::contains($html, 'a'))->toBeFalse();
});

it('answers null where nothing pointed at the file', function (): void {
    expect(DocumentAttachments::remove('<p><img src="/b.png" data-id="b"></p>', 'a'))->toBeNull()
        ->and(DocumentAttachments::remove('', 'a'))->toBeNull()
        ->and(DocumentAttachments::update('<p>Text</p>', 'a', ['src' => '/new.png']))->toBeNull();
});

it('takes the nodes out of a stored document tree', function (): void {
    $document = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Siehe '],
            ['type' => 'file', 'attrs' => ['id' => 'a', 'src' => '/a.pdf', 'name' => 'a.pdf', 'size' => '1 KB']],
        ]],
        ['type' => 'media', 'attrs' => ['id' => 'a', 'src' => '/a.mp4', 'kind' => 'video']],
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => 'b', 'src' => '/b.png']]]],
    ]];

    expect(DocumentAttachments::remove($document, 'a'))->toBe(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Siehe ']]],
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => 'b', 'src' => '/b.png']]]],
    ]]);
});

it('points a sized picture at the new file and gives it the new shape', function (): void {
    // The width somebody chose stays; the height follows the new picture, or it would be
    // squashed into the old one's box.
    $html = '<p><img src="/storage/1/old.png" data-id="a" width="400" height="300" style="width: 400px; height: 300px"></p>';

    expect(DocumentAttachments::update($html, 'a', ['src' => '/storage/1/new.jpg', 'width' => 1200, 'height' => 600]))->toBe(
        '<p><img src="/storage/1/new.jpg" data-id="a" width="400" height="200" style="width: 400px; height: 200px"></p>',
    );
});

it('leaves a size in another unit alone', function (): void {
    $html = '<p><img src="/old.png" data-id="a" width="50%" height="300" style="width: 50%; height: 300px"></p>';

    expect(DocumentAttachments::update($html, 'a', ['src' => '/new.png', 'width' => 1200, 'height' => 600]))->toBe(
        '<p><img src="/new.png" data-id="a" width="50%" height="300" style="width: 50%; height: 300px"></p>',
    );
});

it('rewrites a card: its address, the name it downloads under, and what it says', function () use ($card): void {
    $html = '<p>'.$card('a', '/storage/3/report.pdf', 'Bericht.pdf', '1 KB').'</p>';

    expect(DocumentAttachments::update($html, 'a', ['src' => '/storage/3/report-2026.pdf', 'name' => 'Bericht 2026.pdf', 'size' => '2 KB']))->toBe(
        '<p>'.$card('a', '/storage/3/report-2026.pdf', 'Bericht 2026.pdf', '2 KB').'</p>',
    );
});

it('points a player at the new file', function (): void {
    $html = '<video src="/storage/2/talk.mp4" data-id="a" controls="controls" preload="metadata"></video>';

    expect(DocumentAttachments::update($html, 'a', ['src' => '/storage/2/talk-v2.mp4']))->toBe(
        '<video src="/storage/2/talk-v2.mp4" data-id="a" controls="controls" preload="metadata"></video>',
    );
});

it('rewrites the nodes of a stored document tree', function (): void {
    $document = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'image', 'attrs' => ['id' => 'a', 'src' => '/old.png', 'width' => 400, 'height' => 300]],
            ['type' => 'file', 'attrs' => ['id' => 'a', 'src' => '/old.pdf', 'name' => 'old.pdf', 'size' => '1 KB']],
        ]],
        ['type' => 'media', 'attrs' => ['id' => 'a', 'src' => '/old.mp4', 'kind' => 'video']],
    ]];

    expect(DocumentAttachments::update($document, 'a', ['src' => '/new', 'name' => 'new.pdf', 'size' => '2 KB', 'width' => 1200, 'height' => 600]))->toBe(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'image', 'attrs' => ['id' => 'a', 'src' => '/new', 'width' => 400, 'height' => 200]],
            ['type' => 'file', 'attrs' => ['id' => 'a', 'src' => '/new', 'name' => 'new.pdf', 'size' => '2 KB']],
        ]],
        ['type' => 'media', 'attrs' => ['id' => 'a', 'src' => '/new', 'kind' => 'video']],
    ]]);
});
