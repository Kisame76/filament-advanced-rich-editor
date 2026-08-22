<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaDimensions;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Filtering, sorting, counting and measuring - the parts of the browser that turn a wall of
 * thumbnails into something you can find one picture in.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Post', 'content' => '']);

    // Real bytes and two different formats, because the filter is built from the mime types
    // the pool actually holds.
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $this->gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    $this->attach = function (string $name, ?string $contents = null, string $extension = 'png'): Media {
        return $this->post
            ->addMediaFromString($contents ?? $this->png)
            ->usingFileName($name.'.'.$extension)
            ->usingName($name)
            ->toMediaCollection('rich-editor');
    };

    $this->source = fn (): SpatieMediaSource => SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $this->post,
    );
});

it('counts the library rather than the page', function (): void {
    // The footer says how many pictures there are, and the page numbers are built from it - so
    // it has to be the size of the pool, not of the tiles on screen.
    foreach (range(1, 5) as $index) {
        ($this->attach)("picture-{$index}");
    }

    $page = ($this->source)()->page(page: 1, perPage: 2);

    expect($page['items'])->toHaveCount(2)
        ->and($page['total'])->toBe(5)
        ->and($page['hasMore'])->toBeTrue();

    expect(($this->source)()->page(page: 3, perPage: 2)['hasMore'])->toBeFalse();
});

it('offers the kinds the pool holds and no others', function (): void {
    // A filter offering WebP in a library that has none is a control that can only empty the
    // grid. The list is derived, the same way the slash menu is.
    ($this->attach)('drawing', $this->gif, 'gif');
    ($this->attach)('photo');

    expect(($this->source)()->page()['types'])->toBe(['image/gif', 'image/png']);
});

it('narrows to one kind without narrowing what the filter offers', function (): void {
    ($this->attach)('drawing', $this->gif, 'gif');
    ($this->attach)('photo');

    $page = ($this->source)()->page(filters: ['type' => 'image/gif']);

    expect(array_column($page['items'], 'name'))->toBe(['drawing'])
        ->and($page['total'])->toBe(1)
        // Still both, so the filter can be changed back rather than being a one-way door.
        ->and($page['types'])->toBe(['image/gif', 'image/png']);
});

it('sorts by name, by size and by age', function (): void {
    $small = ($this->attach)('zebra');
    $large = ($this->attach)('apple', $this->png.str_repeat("\0", 500));

    $names = fn (string $sort): array => array_column(
        ($this->source)()->page(filters: ['sort' => $sort])['items'],
        'name',
    );

    expect($names('name'))->toBe(['apple', 'zebra'])
        ->and($names('largest'))->toBe(['apple', 'zebra'])
        ->and($names('smallest'))->toBe(['zebra', 'apple'])
        // Newest first is the default, and the second one attached is the newer.
        ->and($names('newest'))->toBe(['apple', 'zebra'])
        ->and($names('oldest'))->toBe(['zebra', 'apple'])
        ->and($large->size)->toBeGreaterThan($small->size);
});

it('reads the measurements it was given without opening the file', function (): void {
    $media = ($this->attach)('photo');

    $media->setCustomProperty('width', 1204)->setCustomProperty('height', 746)->save();

    $item = ($this->source)()->page()['items'][0];

    expect($item['width'])->toBe(1204)
        ->and($item['height'])->toBe(746);
});

it('measures a listing off the files themselves', function (): void {
    // Nothing stamped this one, and there is nowhere else the numbers could come from - which
    // is the normal case for anything the editor did not upload, and the only case at all on a
    // plain disk. The read is remembered per file, so it is paid once rather than per listing.
    ($this->attach)('photo');

    $item = ($this->source)()->page()['items'][0];

    expect($item['width'])->toBe(1)
        ->and($item['height'])->toBe(1);
});

it('measures the one picture the details panel is showing', function (): void {
    ($this->attach)('photo');

    $id = ($this->source)()->page()['items'][0]['id'];

    expect(($this->source)()->details($id))
        ->toMatchArray(['width' => 1, 'height' => 1]);
});

it('writes a measurement down once the panel has shown it', function (): void {
    // The cache already spares the second file read. What writing it to the row adds is that
    // it survives a cache flush - and it is one write for the picture being looked at, where
    // doing it while listing would be one per row.
    $media = ($this->attach)('photo');

    expect($media->fresh()->getCustomProperty('width'))->toBeNull();

    ($this->source)()->details((string) $media->uuid);

    expect($media->fresh()->getCustomProperty('width'))->toBe(1)
        ->and($media->fresh()->getCustomProperty('height'))->toBe(1);
});

it('stamps an upload with its measurements, where measuring is free', function (): void {
    // At upload time the file is a local copy that has already been read, so this costs
    // nothing - and it is what stops the details panel from having to open it later.
    Storage::fake('tmp-for-tests');

    $fake = UploadedFile::fake()->image('hafen.png', 21, 13);
    $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

    Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

    $uuid = SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
        ->recordUsing(fn (): MediaPost => $this->post)
        ->ownerUsing(fn (): string => 'content')
        ->saveUploadedFileAttachment(
            TemporaryUploadedFile::createFromLivewire($stored),
        );

    $item = ($this->source)()->find($uuid);

    // Straight off the listing, with no file ever opened to read them.
    expect($item['width'])->toBe(21)
        ->and($item['height'])->toBe(13);
});

it('measures a picture from its bytes', function (): void {
    expect(MediaDimensions::fromString($this->png))->toBe(['width' => 1, 'height' => 1])
        ->and(MediaDimensions::fromString('not a picture'))->toBeNull()
        ->and(MediaDimensions::fromString(null))->toBeNull()
        ->and(MediaDimensions::fromPath('/nowhere/at/all.png'))->toBeNull()
        ->and(MediaDimensions::fromStream(null))->toBeNull();
});
