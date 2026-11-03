<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\ResponsivePost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;

/**
 * The sizes Spatie made of a picture, offered to the browser beside the picture itself.
 *
 * Spatie makes them only where a project asked for them, on the collection or on a conversion,
 * so a picture nobody asked for any is rendered exactly as before. They are read from the media
 * row each time the page is drawn and never from the document: a `srcset` is a list of
 * addresses the sanitiser does not look at.
 */
beforeEach(function (): void {
    requires('spatie/laravel-medialibrary');

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = ResponsivePost::create(['title' => 'Post', 'content' => '']);

    // Big enough, and with enough in it, for Spatie to make more than the one size: it stops
    // once a smaller one would weigh under ten kilobytes, which a plain fake picture does at once.
    $this->picture = function (string $name = 'harbour.png'): UploadedFile {
        mt_srand(76);

        $image = imagecreatetruecolor(1600, 900);

        for ($x = 0; $x < 1600; $x += 8) {
            for ($y = 0; $y < 900; $y += 8) {
                imagefilledrectangle($image, $x, $y, $x + 7, $y + 7, (int) imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }

        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    };

    $this->provider = fn (?string $conversion = null, string $visibility = 'public', mixed $record = null): SpatieMediaLibraryFileAttachmentProvider => SpatieMediaLibraryFileAttachmentProvider::make('rich-editor', conversion: $conversion, visibility: $visibility)
        ->recordUsing(fn (): mixed => $record ?? $this->post);

    $this->rendered = function (string $html, ?SpatieMediaLibraryFileAttachmentProvider $provider = null): DOMElement {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?>'.AdvancedRichContentRenderer::make($html)->fileAttachmentProvider($provider ?? ($this->provider)())->toHtml());

        return $document->getElementsByTagName('img')->item(0);
    };

    $this->upload = function (?UploadedFile $fake = null): string {
        Storage::fake('tmp-for-tests');

        // A plain one where the number of sizes does not matter: making them is most of the time
        // this file takes.
        $fake ??= UploadedFile::fake()->image('harbour.png', 640, 360);
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

        return ($this->provider)()->ownerUsing(fn (): string => 'content')->saveUploadedFileAttachment(TemporaryUploadedFile::createFromLivewire($stored));
    };
});

it('offers the sizes Spatie made of an uploaded picture', function (): void {
    $uuid = ($this->upload)(($this->picture)());
    $media = Media::findByUuid($uuid);

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'"></p>');

    $expected = $media->responsiveImages()->files
        ->map(fn (ResponsiveImage $image): string => $image->url().' '.$image->width().'w')
        ->implode(', ');

    expect($media->responsiveImages()->files->count())->toBeGreaterThan(1)
        ->and($img->getAttribute('src'))->toBe($media->getUrl())
        ->and($img->getAttribute('srcset'))->toBe($expected)
        ->and($img->getAttribute('sizes'))->toBe('100vw');
});

it('leaves the blurred placeholder out of the list', function (): void {
    // Spatie's own list ends in an inline picture 32 pixels wide, meant for its own component,
    // which loads that first and swaps in the right size with a script. Without the script it
    // is a few kilobytes of markup per picture that no browser ever picks.
    $uuid = ($this->upload)();

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'"></p>');

    expect(Media::findByUuid($uuid)->getSrcset())->toContain('data:image/svg+xml')
        ->and($img->getAttribute('srcset'))->not->toContain('data:')
        ->and($img->getAttribute('srcset'))->not->toStartWith(',');
});

it('tells the browser how wide the picture is drawn', function (string $attributes, string $sizes): void {
    $uuid = ($this->upload)();

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'" '.$attributes.'></p>');

    expect($img->getAttribute('sizes'))->toBe($sizes);
})->with([
    'nothing said' => ['', '100vw'],
    'a width' => ['width="320" height="180"', '(max-width: 320px) 100vw, 320px'],
    'a width in pixels' => ['width="320px" height="180px"', '(max-width: 320px) 100vw, 320px'],
    // Relative to a column nobody here knows the width of.
    'a share of the column' => ['width="50%"', '100vw'],
    // A picture loaded once it is laid out can be measured by the browser itself, which knows
    // the column; the rest of the list is for the browsers that cannot.
    'a lazy picture with a width' => ['width="320" height="180" loading="lazy"', 'auto, (max-width: 320px) 100vw, 320px'],
    // Only beside a width in pixels, which is what the editor writes. Measured with no width, a
    // picture that has not loaded yet is the browser's default box, three hundred pixels wide,
    // and would be handed a size to match.
    'a lazy picture with no width' => ['loading="lazy"', '100vw'],
    'a lazy share of the column' => ['width="50%" loading="lazy"', '100vw'],
]);

it('renders a picture Spatie made no sizes of as before', function (): void {
    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString(($this->picture)()->get())->usingFileName('harbour.png')->toMediaCollection('rich-editor');

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$media->uuid.'" width="320" height="180"></p>', ($this->provider)(record: $post));

    expect($img->getAttribute('src'))->toBe($media->getUrl())
        ->and($img->hasAttribute('srcset'))->toBeFalse()
        ->and($img->hasAttribute('sizes'))->toBeFalse();
});

it('never takes a list from the stored markup', function (): void {
    // Nothing checks the addresses in a `srcset`: Symfony's sanitiser knows `src` and `href`,
    // not this. One the document carried would reach the page as written.
    $img = ($this->rendered)('<p><img src="/storage/harbour.png" srcset="https://elsewhere.example/tracker.png 1w" sizes="1px"></p>');

    expect($img->hasAttribute('srcset'))->toBeFalse()
        ->and($img->hasAttribute('sizes'))->toBeFalse();
});

it('never takes one from a document kept as JSON either', function (): void {
    // JSON hands its attributes over as they were stored, past any parse.
    $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'image', 'attrs' => ['src' => '/storage/harbour.png', 'srcset' => 'https://elsewhere.example/tracker.png 1w']],
    ]]]];

    $html = AdvancedRichContentRenderer::make($document)->fileAttachmentProvider(($this->provider)())->toHtml();

    expect($html)->toContain('/storage/harbour.png')
        ->and($html)->not->toContain('elsewhere.example');
});

it('keeps a private picture to its signed address', function (): void {
    // Spatie's sizes are always public addresses; on a private disk they lead nowhere.
    $uuid = ($this->upload)();

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'"></p>', ($this->provider)(visibility: 'private'));

    expect($img->hasAttribute('srcset'))->toBeFalse();
});

it('offers the sizes of the conversion the picture is shown as', function (): void {
    $uuid = ($this->upload)();
    $media = Media::findByUuid($uuid);

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'"></p>', ($this->provider)(conversion: 'arte-wide'));

    expect($img->getAttribute('src'))->toBe($media->getUrl('arte-wide'))
        ->and($media->responsiveImages('arte-wide')->files)->not->toBeEmpty()
        ->and($img->getAttribute('srcset'))->toContain('arte-wide')
        ->and($img->getAttribute('srcset'))->not->toContain('media_library_original');
});

it('offers none where the conversion has none, rather than the original\'s', function (): void {
    // The original's sizes are a different picture - another crop, perhaps - at another width.
    $uuid = ($this->upload)();

    $img = ($this->rendered)('<p><img src="/storage/stale.png" data-id="'.$uuid.'"></p>', ($this->provider)(conversion: 'arte-thumb'));

    expect($img->getAttribute('src'))->toContain('arte-thumb')
        ->and($img->hasAttribute('srcset'))->toBeFalse();
});
