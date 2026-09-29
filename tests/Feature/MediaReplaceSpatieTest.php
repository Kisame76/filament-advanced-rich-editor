<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\ReplacesMedia;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\ThumbnailPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Putting a new file behind a media row.
 *
 * The row stays - its uuid, which every document points at, and everything else a project may
 * have hung off it: the owner, the order, the description. What changes is the file, and with
 * it its names - the file name, so the address changes too and nothing serves the old picture
 * from a cache under the new one's name, and the name the library and every card show.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $this->attach = fn (string $fileName, string $contents, ?MediaPost $post = null, string $collection = 'rich-editor'): Media => ($post ?? $this->post)
        ->addMediaFromString($contents)
        ->usingFileName($fileName)
        ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
        ->toMediaCollection($collection);

    $this->source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $this->post,
    );

    $this->pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
});

it('swaps the file behind a row and keeps the row', function (): void {
    $media = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());
    $media->setCustomProperty(SpatieMediaSource::ALT_PROPERTY, 'A sunset')->save();

    $replacement = UploadedFile::fake()->image('dawn.png', 12, 5);

    expect($this->source)->toBeInstanceOf(ReplacesMedia::class)
        ->and($this->source->replace($media->uuid, $replacement))->toBeTrue();

    $fresh = Media::query()->sole();

    expect($fresh->getKey())->toBe($media->getKey())
        ->and($fresh->uuid)->toBe($media->uuid)
        // The name follows the file: the tile, the panel and every card say what is there now.
        ->and($fresh->name)->toBe('dawn')
        ->and($fresh->file_name)->toBe('dawn.png')
        ->and($fresh->size)->toBe($replacement->getSize())
        ->and($fresh->getCustomProperty(SpatieMediaSource::ALT_PROPERTY))->toBe('A sunset')
        ->and($fresh->getCustomProperty('width'))->toBe(12)
        ->and($fresh->getCustomProperty('height'))->toBe(5)
        ->and(Storage::disk('public')->get($fresh->getPathRelativeToRoot()))->toBe($replacement->get())
        ->and(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeFalse();
});

it('names the row after the new file, the way an upload would have been named', function (): void {
    // The library shows what was put there last, and the name is what its tile, its panel and
    // the card of every document using it say - kept, it would go on saying the old file.
    $media = ($this->attach)('report.pdf', $this->pdf);

    expect($this->source->replace($media->uuid, UploadedFile::fake()->createWithContent('Preise 2027.pdf', $this->pdf.'% newer')))->toBeTrue();

    $details = $this->source->details($media->uuid);

    expect(Media::query()->sole()->name)->toBe('Preise 2027')
        ->and(Media::query()->sole()->file_name)->toBe('Preise-2027.pdf')
        ->and($details['name'])->toBe('Preise 2027')
        // What every card using it is written with, and downloads under.
        ->and(MediaLibraryAction::cardName($details))->toBe('Preise 2027.pdf');
});

it('keeps the name where the upload has none to give', function (): void {
    $media = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());

    expect($this->source->replace($media->uuid, UploadedFile::fake()->image('.png', 4, 4)))->toBeTrue()
        ->and(Media::query()->sole()->name)->toBe('sunset');
});

it('lets a picture change its format', function (): void {
    $media = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());

    expect($this->source->replace($media->uuid, UploadedFile::fake()->image('dawn.jpg', 6, 6)))->toBeTrue()
        ->and(Media::query()->sole()->file_name)->toBe('dawn.jpg')
        ->and(Media::query()->sole()->mime_type)->toBe('image/jpeg');
});

it('keeps a document to its ending', function (): void {
    // The card in every document using it says `report.pdf`, and downloads under that name.
    $media = ($this->attach)('report.pdf', $this->pdf);

    $text = UploadedFile::fake()->createWithContent('report.txt', 'plain words');
    $newer = UploadedFile::fake()->createWithContent('report-2026.pdf', $this->pdf.'% newer');

    expect($this->source->replace($media->uuid, $text))->toBeFalse()
        ->and($this->source->replace($media->uuid, $newer))->toBeTrue()
        ->and(Media::query()->sole()->file_name)->toBe('report-2026.pdf');
});

it('refuses a file of another family', function (): void {
    $media = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());

    expect($this->source->replace($media->uuid, UploadedFile::fake()->createWithContent('report.pdf', $this->pdf)))->toBeFalse()
        ->and(Media::query()->sole()->file_name)->toBe('sunset.png');
});

it('refuses a row outside the pool', function (): void {
    $elsewhere = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get(), collection: 'avatars');

    expect($this->source->replacementTypes($elsewhere->uuid))->toBeNull()
        ->and($this->source->replace($elsewhere->uuid, UploadedFile::fake()->image('dawn.png', 4, 4)))->toBeFalse()
        ->and($this->source->replace('not-a-uuid', UploadedFile::fake()->image('dawn.png', 4, 4)))->toBeFalse();
});

it('refuses an embed, which is a link rather than a file', function (): void {
    $id = $this->source->saveEmbed(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null, 'title' => 'The talk', 'ratio' => '16 / 9']);

    expect($id)->toBeString()
        ->and($this->source->replacementTypes($id))->toBeNull()
        ->and($this->source->replace($id, UploadedFile::fake()->createWithContent('talk.json', '{}')))->toBeFalse();
});

it('lets go of the cover made from the old film', function (): void {
    $film = (string) file_get_contents(dirname(__DIR__).'/Fixtures/media/tiny.mp4');
    $media = ($this->attach)('talk.mp4', $film);

    $cover = $media->id.'/conversions/talk-'.CoverGenerator::CONVERSION.'.jpg';

    Storage::disk('public')->put($cover, 'old still');
    $media->markAsConversionGenerated(CoverGenerator::CONVERSION);

    expect($this->source->replace($media->uuid, UploadedFile::fake()->createWithContent('talk-v2.mp4', $film)))->toBeTrue()
        ->and(Storage::disk('public')->exists($cover))->toBeFalse()
        ->and(Media::query()->sole()->hasGeneratedConversion(CoverGenerator::CONVERSION))->toBeFalse();
});

it('makes the conversions again from the new file', function (): void {
    $post = ThumbnailPost::create(['title' => 'Post', 'content' => '']);

    $media = $post->addMediaFromString(UploadedFile::fake()->image('sunset.png', 40, 40)->get())
        ->usingFileName('sunset.png')
        ->toMediaCollection('rich-editor');

    $old = $media->getPathRelativeToRoot('arte-thumb');

    $source = SpatieMediaSource::make(collection: 'rich-editor', visibility: 'public', getRecordUsing: fn (): ThumbnailPost => $post);

    expect(Storage::disk('public')->exists($old))->toBeTrue()
        ->and($source->replace($media->uuid, UploadedFile::fake()->image('dawn.png', 128, 32)))->toBeTrue();

    $fresh = Media::query()->sole();
    $thumbnail = $fresh->getPathRelativeToRoot('arte-thumb');

    expect($thumbnail)->not->toBe($old)
        ->and(Storage::disk('public')->exists($old))->toBeFalse()
        // Drawn from the new picture, whose shape is nothing like the old one's square.
        ->and(array_slice((array) getimagesizefromstring((string) Storage::disk('public')->get($thumbnail)), 0, 2))->toBe([64, 16]);
});

it('tells the picker what may take the place of each kind of file', function (): void {
    $picture = ($this->attach)('sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());
    $document = ($this->attach)('report.pdf', $this->pdf);

    expect($this->source->replacementTypes($picture->uuid))->toContain('.png', '.jpg', '.webp')
        ->and($this->source->replacementTypes($picture->uuid))->not->toContain('.svg', '.pdf')
        ->and($this->source->replacementTypes($document->uuid))->toBe(['.pdf']);
});
