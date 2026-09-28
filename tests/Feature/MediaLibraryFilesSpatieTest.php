<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\LibraryTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\ThumbnailPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Documents in a media collection.
 *
 * The collection is the library, so a pdf uploaded for one article is the pdf the next one
 * links to. What a row is decided by here is its mime type for what is drawn and its file name
 * for what is not - the same split the disk makes, asked of a table instead of a directory.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $this->pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    $this->png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->attach = fn (string $fileName, string $contents): Media => $this->post
        ->addMediaFromString($contents)
        ->usingFileName($fileName)
        ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
        ->toMediaCollection('rich-editor');

    $this->source = fn (?LibraryTypes $types = null): SpatieMediaSource => SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $this->post,
        types: $types,
    );
});

it('lists a document as a file', function (): void {
    ($this->attach)('report.pdf', $this->pdf);
    ($this->attach)('sunset.png', $this->png);

    $items = collect(($this->source)()->page()['items'])->keyBy('fileName');

    expect($items['report.pdf']['kind'])->toBe('file')
        ->and($items['report.pdf']['badge'])->toBe('PDF')
        ->and($items['report.pdf']['thumbnail'])->toBeNull()
        ->and($items['sunset.png']['kind'])->toBe('image');
});

it('offers the documents tab and their type in the filter', function (): void {
    ($this->attach)('report.pdf', $this->pdf);
    ($this->attach)('sunset.png', $this->png);

    $page = ($this->source)()->page();

    expect($page['kinds'])->toBe(['image', 'file'])
        ->and($page['types'])->toBe(['application/pdf', 'image/png']);
});

it('narrows to documents on their tab', function (): void {
    ($this->attach)('report.pdf', $this->pdf);
    ($this->attach)('sunset.png', $this->png);

    $page = ($this->source)()->page(filters: ['kind' => 'file']);

    expect(array_column($page['items'], 'fileName'))->toBe(['report.pdf'])
        ->and($page['total'])->toBe(1);
});

it('never calls an embed a document', function (): void {
    // An embed row's file is JSON. Even where JSON is a document the project wants, the row
    // is an embed and only an embed.
    ($this->attach)('report.pdf', $this->pdf);

    $source = ($this->source)(LibraryTypes::make(['file' => ['pdf', 'json']]));

    $source->saveEmbed(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null, 'title' => 'Talk', 'ratio' => '16 / 9']);

    expect(array_column($source->page(filters: ['kind' => 'file'])['items'], 'fileName'))->toBe(['report.pdf'])
        ->and(array_column($source->page(filters: ['kind' => 'embed'])['items'], 'fileName'))
        ->toBe([Embeds::fileName('youtube', 'dQw4w9WgXcQ')])
        // Nor does its JSON turn up in the type filter, calling a video a document.
        ->and($source->page()['types'])->not->toContain('application/json');
});

it('leaves out an ending the list does not take', function (): void {
    ($this->attach)('report.pdf', $this->pdf);
    ($this->attach)('drawing.dwg', "AC1032\0\0\0\0binary");

    expect(array_column(($this->source)()->page()['items'], 'fileName'))->toBe(['report.pdf']);
});

it('takes any ending for a star, and still not a page', function (): void {
    ($this->attach)('drawing.dwg', "AC1032\0\0\0\0binary");
    ($this->attach)('page.html', '<!DOCTYPE html><html><body>x</body></html>');

    $names = array_column(($this->source)(LibraryTypes::make(['file' => ['*']]))->page()['items'], 'fileName');

    expect($names)->toBe(['drawing.dwg']);
});

it('resolves a stored document through the pool it was listed from', function (): void {
    // The scope is what authorises - the collection here - and it holds whether or not the
    // documents tab is switched on today. A card written last week must not lose its file
    // because somebody has since narrowed what the browser offers.
    $media = ($this->attach)('report.pdf', $this->pdf);

    $uuid = (string) $media->getAttributeValue('uuid');

    expect(($this->source)()->has($uuid))->toBeTrue()
        ->and(($this->source)(LibraryTypes::make(['file' => []]))->has($uuid))->toBeTrue()
        // Outside the scope it stays outside, which is the boundary that means anything.
        ->and(SpatieMediaSource::make(collection: 'other', getRecordUsing: fn (): MediaPost => $this->post)->has($uuid))->toBeFalse();
});

it('still resolves a film it no longer offers', function (): void {
    // `video/*` used to be the rule, so a collection can hold an `.mkv` this browser once
    // took. It is not offered any more - no browser plays one - but the `<video>` in an
    // article that already points at it has to keep working.
    $media = ($this->attach)('talk.mkv', "\x1a\x45\xdf\xa3matroska");

    $uuid = (string) $media->getAttributeValue('uuid');

    expect(array_column(($this->source)()->page()['items'], 'fileName'))->not->toContain('talk.mkv')
        ->and(($this->source)()->has($uuid))->toBeTrue();
});

it('narrows a family whose whole list is refused to nothing', function (): void {
    // Every ending it named is on the deny list, so the group of conditions behind its tab
    // is empty - and an empty group is dropped, which would list every picture instead.
    ($this->attach)('sunset.png', $this->png);

    $page = ($this->source)(LibraryTypes::make(['image' => ['svg']]))->page();

    expect($page['items'])->toBe([])
        ->and($page['kinds'])->not->toContain('image');
});

it('points a document at its own file rather than at a conversion', function (): void {
    // A conversion is a picture made from the file. A card pointing at one would hand out a
    // JPEG of page one where the reader asked for the report.
    $media = ($this->attach)('report.pdf', $this->pdf);

    expect(MediaUrl::for($media, 'arte-thumb', 'public'))->toBe($media->getUrl());
});

it('hands a conversion only to what the row is listed as', function (): void {
    // `finfo` files a CAD drawing under `image/vnd.dwg`, and a type starting with `image/`
    // was enough for the address to keep a conversion nothing ever made - a card whose
    // download is a 404. The other way round too: a picture whose type was never recorded
    // is still a picture, and taking its conversion away would hand out the full-size file.
    $drawing = ($this->attach)('plan.dwg', "AC1032\0\0\0\0binary");

    // A picture whose type was never recorded is still a picture, and the family says so
    // where the type cannot - taking its conversion away puts the full-size file on the page.
    $recorded = ThumbnailPost::create(['title' => 'Post', 'content' => '']);

    $thumbnailed = $recorded->addMediaFromString($this->png)
        ->usingFileName('sunset.png')
        ->toMediaCollection('rich-editor');

    $thumbnailed->forceFill(['mime_type' => ''])->save();

    expect(MediaUrl::for($thumbnailed->fresh(), 'arte-thumb', 'public', MediaKinds::IMAGE))
        ->toBe($thumbnailed->fresh()->getUrl('arte-thumb'))
        ->and(MediaUrl::for($thumbnailed->fresh(), 'arte-thumb', 'public'))
        ->toBe($thumbnailed->fresh()->getUrl());

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        conversion: 'arte-thumb',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $this->post,
        types: LibraryTypes::make(['file' => ['*']]),
    );

    $items = collect($source->page()['items'])->keyBy('fileName');

    expect($items['plan.dwg']['url'])->toBe($drawing->getUrl());
});

it('names a card after the file where the title carries a dot', function (): void {
    // A media row keeps a readable name beside its file name, and `Quartalsbericht Q3.2024`
    // reads as a name ending in `2024` to any parser - so the card was labelled without its
    // `.pdf`, downloaded under a name with no ending, and badged `2024` in grey.
    $media = ($this->attach)('bericht.pdf', $this->pdf);

    $media->forceFill(['name' => 'Quartalsbericht Q3.2024'])->save();

    $livewire = new RecordingSchemaComponent;

    AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make($livewire)->operation('edit')->record($this->post))
        ->getAction('mediaBrowser')
        ?->call([
            'data' => ['src' => null, 'media' => (string) $media->getAttributeValue('uuid')],
            'arguments' => ['editorSelection' => null],
        ]);

    expect($livewire->commands()[0]['arguments'][0]['name'] ?? null)->toBe('Quartalsbericht Q3.2024.pdf');
});
