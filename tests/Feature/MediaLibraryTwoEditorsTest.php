<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TwoEditorsMediaFormComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Two editors on one record, sharing the record's media collection.
 *
 * Each picture is stamped with the field that uploaded it, and saving a field sweeps the
 * pictures it uploaded and no longer shows. That is only safe while no other field can show
 * them - and on one record the other field can: the record-scoped browser lists the whole
 * collection, and a picture copied from one editor into the other resolves there too. Removing
 * it from the field that uploaded it then deleted the file the other field was still showing.
 */
beforeEach(function (): void {
    requires('spatie/laravel-medialibrary');

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Article', 'content' => '', 'summary' => '']);

    // Uploaded through `content`, the way the provider stores an upload.
    $this->picture = $this->post
        ->addMediaFromString(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))
        ->usingFileName('hafen.png')
        ->withCustomProperties([SpatieMediaLibraryFileAttachmentProvider::OWNER_PROPERTY => 'content'])
        ->toMediaCollection('rich-editor');

    $this->image = '<img src="'.$this->picture->getUrl().'" data-id="'.$this->picture->uuid.'">';

    $this->survives = function (): void {
        expect(Media::find($this->picture->getKey()))->not->toBeNull()
            ->and(Storage::disk('public')->exists($this->picture->getPathRelativeToRoot()))->toBeTrue();
    };
});

it('keeps a picture the other editor shows when the one that uploaded it lets go', function (string $browser): void {
    // `summary` took the picture earlier - picked from the record's library, or copied across
    // from `content` - and was saved with it.
    $this->post->update([
        'content' => '<p>Der Hafen.</p><p>'.$this->image.'</p>',
        'summary' => '<p>Kurz.</p><p>'.$this->image.'</p>',
    ]);

    Livewire::test(TwoEditorsMediaFormComponent::class, ['record' => $this->post, 'browser' => $browser])
        ->set('data.content', '<p>Der Hafen, ohne Bild.</p>')
        ->call('save');

    ($this->survives)();

    expect($this->post->refresh()->summary)->toContain($this->picture->uuid);
})->with([
    'from the record\'s library' => 'record',
    'copied across, no browser' => 'off',
]);

it('keeps a picture the other editor takes in the same save', function (): void {
    // Nothing on the record says so yet: `summary` is being written by the very save that
    // sweeps `content`, and the sweep runs before the record is.
    $this->post->update(['content' => '<p>Der Hafen.</p><p>'.$this->image.'</p>']);

    Livewire::test(TwoEditorsMediaFormComponent::class, ['record' => $this->post])
        ->set('data.summary', '<p>Kurz.</p><p>'.$this->image.'</p>')
        ->set('data.content', '<p>Der Hafen, ohne Bild.</p>')
        ->call('save');

    ($this->survives)();

    expect($this->post->refresh()->summary)->toContain($this->picture->uuid);
});

it('still deletes the picture once neither editor shows it', function (): void {
    // Keeping what the other field shows must not turn into keeping everything: a record-scoped
    // library is swept so that the disk does not grow forever.
    $this->post->update(['content' => '<p>Der Hafen.</p><p>'.$this->image.'</p>']);

    Livewire::test(TwoEditorsMediaFormComponent::class, ['record' => $this->post])
        ->set('data.content', '<p>Der Hafen, ohne Bild.</p>')
        ->call('save');

    expect(Media::find($this->picture->getKey()))->toBeNull();
});
