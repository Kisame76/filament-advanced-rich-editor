<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;

/**
 * The media browser over a plain filesystem disk - the fields that store attachments as files
 * rather than as media library media.
 *
 * The interesting half is `has()`. On the media library side a UUID is meaningless outside the
 * pool; here an id IS a path, so the same method has to answer "is this inside the library"
 * for strings a person can type. Everything about traversal below is that question.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->put = function (string $path): string {
        Storage::disk('public')->put($path, $this->png);

        return $path;
    };

    $this->source = fn (string $directory = 'library'): DiskMediaSource => DiskMediaSource::make(
        disk: 'public',
        directory: $directory,
        visibility: 'public',
    );
});

it('lists the pictures in its directory', function (): void {
    ($this->put)('library/one.png');
    ($this->put)('library/two.jpg');

    $page = ($this->source)()->page();

    expect(array_column($page['items'], 'id'))
        ->toHaveCount(2)
        ->each->toStartWith('library/');
});

it('leaves everything outside its directory alone', function (): void {
    ($this->put)('library/mine.png');
    ($this->put)('elsewhere/theirs.png');

    expect(array_column(($this->source)()->page()['items'], 'id'))->toBe(['library/mine.png']);
});

it('lists only what can be drawn as an image', function (): void {
    ($this->put)('library/picture.png');
    Storage::disk('public')->put('library/notes.txt', 'not a picture');
    Storage::disk('public')->put('library/archive.zip', 'not a picture either');

    expect(array_column(($this->source)()->page()['items'], 'id'))->toBe(['library/picture.png']);
});

it('offers the folders it holds, and the way back up', function (): void {
    ($this->put)('library/top.png');
    ($this->put)('library/press/inside.png');

    $root = ($this->source)()->page();

    expect(array_column($root['folders'], 'path'))->toBe(['library/press'])
        ->and($root['parent'])->toBeNull()
        // A folder listing is the folder, not the folder plus everything under it.
        ->and(array_column($root['items'], 'id'))->toBe(['library/top.png']);

    $inside = ($this->source)()->page(folder: 'library/press');

    expect(array_column($inside['items'], 'id'))->toBe(['library/press/inside.png'])
        ->and($inside['parent'])->toBe('library');
});

it('searches the whole library rather than the open folder', function (): void {
    // Someone typing a file name is looking for a file, not for a file in the directory that
    // happens to be on screen.
    ($this->put)('library/press/hafen.png');
    ($this->put)('library/top.png');

    expect(array_column(($this->source)()->page(search: 'hafen')['items'], 'id'))
        ->toBe(['library/press/hafen.png']);
});

it('refuses a path that climbs out of the library', function (): void {
    ($this->put)('library/mine.png');
    ($this->put)('elsewhere/theirs.png');

    $source = ($this->source)();

    expect($source->has('library/mine.png'))->toBeTrue()
        ->and($source->has('elsewhere/theirs.png'))->toBeFalse()
        ->and($source->has('library/../elsewhere/theirs.png'))->toBeFalse()
        ->and($source->has('../../.env'))->toBeFalse()
        ->and($source->has('/library/mine.png'))->toBeTrue()
        ->and($source->has('library\\mine.png'))->toBeTrue();
});

it('refuses a path that is not an image, even inside the library', function (): void {
    Storage::disk('public')->put('library/secret.txt', 'not a picture');

    expect(($this->source)()->has('library/secret.txt'))->toBeFalse();
});

it('refuses a path to nothing', function (): void {
    $source = ($this->source)();

    expect($source->has('library/missing.png'))->toBeFalse()
        ->and($source->has(''))->toBeFalse()
        ->and($source->has(null))->toBeFalse()
        ->and($source->has(42))->toBeFalse()
        ->and($source->find('library/missing.png'))->toBeNull();
});

it('measures its pictures without a database to read them from', function (): void {
    // The whole point on a plain disk: there is no row to stamp and no custom properties to
    // read, so the numbers can only come off the file. The listing shows them anyway.
    Storage::disk('public')->put('library/wide.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAQAAAACCAYAAAB/qH1jAAAAEklEQVR42mNkYPhfz0AEYBxVSF+FABJADveWkH6oAAAAAElFTkSuQmCC',
    ));

    $item = ($this->source)()->page()['items'][0];

    expect($item['width'])->toBe(4)
        ->and($item['height'])->toBe(2)
        // And the details panel says the same thing, because there is one way to measure.
        ->and(($this->source)()->details('library/wide.png'))
        ->toMatchArray(['width' => 4, 'height' => 2]);
});

it('finds a picture it lists, with a URL', function (): void {
    ($this->put)('library/one.png');

    $item = ($this->source)()->find('library/one.png');

    expect($item['id'])->toBe('library/one.png')
        ->and($item['name'])->toBe('one.png')
        ->and($item['mime'])->toBe('image/png')
        ->and($item['url'])->toBeString()->not->toBeEmpty();
});

it('pages without dropping a picture at the boundary', function (): void {
    foreach (range(1, 5) as $index) {
        ($this->put)("library/{$index}.png");
    }

    $seen = [];

    foreach ([1, 2, 3] as $page) {
        $result = ($this->source)()->page(page: $page, perPage: 2);

        $seen = [...$seen, ...array_column($result['items'], 'id')];

        expect($result['hasMore'])->toBe($page < 3);
    }

    expect(array_unique($seen))->toHaveCount(5);
});

it('is an empty library rather than an error when the directory is not there', function (): void {
    $page = ($this->source)('nothing-here')->page();

    expect($page['items'])->toBe([])
        ->and($page['folders'])->toBe([]);
});

it('browses the whole disk when it is given no directory', function (): void {
    ($this->put)('anywhere/one.png');

    $source = DiskMediaSource::make(disk: 'public', directory: '', visibility: 'public');

    expect($source->has('anywhere/one.png'))->toBeTrue()
        ->and($source->has('../outside.png'))->toBeFalse();
});

it('never lists the companions it writes beside a file', function (): void {
    // A `.json` is dropped already, because nothing here draws one. A `.cover.jpg` is a
    // picture by its extension, and without this it would sit in the grid as a tile of its
    // own - the same film twice, once as itself and once as its first frame.
    ($this->put)('library/talk.mp4.cover.jpg');
    ($this->put)('library/sunset.png');
    Storage::disk('public')->put('library/sunset.png.json', '{"alt":"x"}');
    Storage::disk('public')->put('library/youtube-dQw4w9WgXcQ.embed.json', '{}');

    expect(array_column(($this->source)()->page()['items'], 'id'))->toBe(['library/sunset.png']);
});

it('refuses to resolve a companion through a stored id', function (): void {
    // The listing and the lookup are one object: something that cannot be listed must not be
    // reachable by hand-writing its path into a document either.
    ($this->put)('library/talk.mp4.cover.jpg');

    expect(($this->source)()->has('library/talk.mp4.cover.jpg'))->toBeFalse()
        ->and(($this->source)()->find('library/talk.mp4.cover.jpg'))->toBeNull();
});
