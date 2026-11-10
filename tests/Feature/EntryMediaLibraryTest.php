<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Infolists\Components\AdvancedRichEntry;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaLibraryItem;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;

/**
 * A view page showing what a media library field saved.
 *
 * A field that stores privately saves a picture by its id alone - the address it was shown
 * with is a signed one that runs out within the hour, and Filament drops it on the way into
 * the column. The page that shows the document then has an id and nothing else, and only a
 * provider turns an id back into a file. A model without a rich content attribute declares
 * none, so the entry needs the field's own `spatieMediaLibrary()`.
 */
beforeEach(function (): void {
    requires('spatie/laravel-medialibrary');

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->post = MediaPost::create(['title' => 'Hafen', 'content' => '']);

    $this->picture = $this->post
        ->addMediaFromString($this->png)
        ->usingFileName('hafen.png')
        ->toMediaCollection('rich-editor');
});

/**
 * A picture the way a private field saves it: the id, and no address.
 */
function savedById(string $uuid): string
{
    return '<p><img data-id="'.$uuid.'" alt="Hafen"></p>';
}

function mediaEntry(MediaPost $record): AdvancedRichEntry
{
    return AdvancedRichEntry::make('content')
        ->container(Schema::make(new TestSchemaComponent)->record($record));
}

it('finds a picture saved without its address by the id it was saved under', function (): void {
    $this->post->update(['content' => savedById($this->picture->uuid)]);

    // Without the provider there is nothing to draw: an `<img>` with no source.
    expect(mediaEntry($this->post)->toEmbeddedHtml())->not->toContain('src=');

    expect(mediaEntry($this->post)->spatieMediaLibrary()->toEmbeddedHtml())
        ->toContain('src="'.$this->picture->getUrl().'"');
});

it('finds a picture the field took from a shared library as well', function (): void {
    // The record's own collection does not hold it - a library model does - so a lookup
    // in that collection alone would leave a hole where the picture was.
    $library = MediaLibraryItem::create(['title' => 'Bibliothek', 'content' => '']);

    $shared = $library
        ->addMediaFromString($this->png)
        ->usingFileName('geteilt.png')
        ->toMediaCollection('library');

    $this->post->update(['content' => savedById($shared->uuid)]);

    expect(mediaEntry($this->post)->spatieMediaLibrary()->toEmbeddedHtml())
        ->toContain('src="'.$shared->getUrl().'"');
});

it('takes the visibility the field stores with, and caps a cached page by it', function (): void {
    // A private picture's address is signed, so a page cached past the end of the hour
    // would show it broken.
    config()->set('filament.temporary_file_url_expiry_minutes', 30);

    $renderer = fn (?string $visibility) => mediaEntry($this->post)
        ->spatieMediaLibrary(visibility: $visibility)
        ->cached(86400)
        ->getRichContentRenderer(savedById($this->picture->uuid));

    expect($renderer('private')->getRenderCacheTtl())->toBe(1800)
        ->and($renderer(null)->getRenderCacheTtl())->toBe(86400);
});
