<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Throwing a file away from inside the browser.
 *
 * Offered only where the library is this record's own attachments. In a shared library the
 * file may be in another record's content that nobody standing here can see, and a delete
 * button there is a button that quietly breaks somebody else's page - so it says nothing
 * rather than lying.
 */
beforeEach(function (): void {
    Storage::fake('public');

    Storage::disk('public')->put('article-attachments/sunset.png', 'x');

    $this->livewire = new TestSchemaComponent;

    // A field with no `mediaLibraryDirectory()`: the pool is this record's own directory,
    // which is what `isRecordScoped()` means for a disk.
    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));
});

it('deletes the file and everything written beside it', function (): void {
    Storage::disk('public')->put('article-attachments/sunset.png.json', '{"alt":"x"}');
    Storage::disk('public')->put('article-attachments/sunset.png.cover.jpg', 'x');

    expect($this->editor->deleteMediaForJs('article-attachments/sunset.png')['deleted'])->toBeTrue()
        ->and(Storage::disk('public')->exists('article-attachments/sunset.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('article-attachments/sunset.png.json'))->toBeFalse()
        ->and(Storage::disk('public')->exists('article-attachments/sunset.png.cover.jpg'))->toBeFalse();
});

it('refuses in a library shared across records', function (): void {
    Storage::disk('public')->put('library/shared.png', 'x');

    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->deleteMediaForJs('library/shared.png')['deleted'])->toBeFalse()
        ->and(Storage::disk('public')->exists('library/shared.png'))->toBeTrue();
});

it('refuses an upload that is not saved yet', function (): void {
    // That is "discard the upload", which the upload widget already does - and there is no
    // file on the disk to delete anyway.
    expect($this->editor->deleteMediaForJs(FileAttachments::PENDING_PREFIX.'held')['deleted'])->toBeFalse();
});

it('refuses a path outside the pool', function (): void {
    Storage::disk('public')->put('elsewhere/other.png', 'x');

    expect($this->editor->deleteMediaForJs('elsewhere/other.png')['deleted'])->toBeFalse()
        ->and($this->editor->deleteMediaForJs('../../.env')['deleted'])->toBeFalse()
        ->and(Storage::disk('public')->exists('elsewhere/other.png'))->toBeTrue();
});

it('refuses where there is no library at all', function (): void {
    $editor = AdvancedRichEditor::make('content')
        ->mediaLibrary(false)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit'));

    expect($editor->deleteMediaForJs('article-attachments/sunset.png')['deleted'])->toBeFalse();
});

it('says so to the source rather than only to the browser', function (): void {
    // The rule lives on the source too, so a caller that is not the exposed method - a
    // command, a future bulk action - cannot get round it.
    $shared = DiskMediaSource::make(disk: 'public', directory: 'library', visibility: 'public', isRecordScoped: false);

    Storage::disk('public')->put('library/shared.png', 'x');

    expect($shared->delete('library/shared.png'))->toBeFalse();
});

it('deletes an embed entry like any other', function (): void {
    $embed = ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null, 'title' => 'The talk', 'ratio' => '16 / 9'];
    $path = 'article-attachments/'.Embeds::fileName('youtube', 'dQw4w9WgXcQ');

    Storage::disk('public')->put($path, Embeds::encode($embed));

    expect($this->editor->deleteMediaForJs($path)['deleted'])->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('deletes a media row and its file', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($png)->usingFileName('sunset.png')->toMediaCollection('rich-editor');

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
        scope: 'record',
    );

    expect($source->delete((string) $media->uuid))->toBeTrue()
        ->and(Media::query()->count())->toBe(0);
});

it('refuses a media row in a collection shared across records', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($png)->usingFileName('sunset.png')->toMediaCollection('rich-editor');

    // The default scope: the collection is the library, and the collection is shared.
    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
    );

    expect($source->delete((string) $media->uuid))->toBeFalse()
        ->and(Media::query()->count())->toBe(1);
});

it('deletes in a shared library once the field says it may', function (): void {
    // The project's decision, not the package's: it knows whether anything else draws from
    // the library, and a closure can say who may.
    Storage::disk('public')->put('library/shared.png', 'x');

    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->mediaLibraryDeletable()
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->canDeleteFromMediaLibrary())->toBeTrue()
        ->and($editor->deleteMediaForJs('library/shared.png')['deleted'])->toBeTrue()
        ->and(Storage::disk('public')->exists('library/shared.png'))->toBeFalse();
});

it('refuses in the record\'s own library when the field says no', function (): void {
    $editor = $this->editor->mediaLibraryDeletable(fn (): bool => false);

    expect($editor->canDeleteFromMediaLibrary())->toBeFalse()
        ->and($editor->deleteMediaForJs('article-attachments/sunset.png')['deleted'])->toBeFalse()
        ->and(Storage::disk('public')->exists('article-attachments/sunset.png'))->toBeTrue();
});

it('takes the answer from the configuration where the field gives none', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.deletable', true);

    Storage::disk('public')->put('library/shared.png', 'x');

    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->deleteMediaForJs('library/shared.png')['deleted'])->toBeTrue();
});

it('lets a shared pool delete only when it was told it may', function (): void {
    Storage::disk('public')->put('library/shared.png', 'x');

    $allowed = DiskMediaSource::make(disk: 'public', directory: 'library', visibility: 'public', deletable: true);
    $refused = DiskMediaSource::make(disk: 'public', directory: 'library', visibility: 'public', isRecordScoped: true, deletable: false);

    expect($refused->delete('library/shared.png'))->toBeFalse()
        ->and($allowed->delete('library/shared.png'))->toBeTrue();
});

it('deletes a media row in a shared collection when it was told it may', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    config()->set('media-library.disk_name', 'public');

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($png)->usingFileName('sunset.png')->toMediaCollection('rich-editor');

    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): MediaPost => $post,
        deletable: true,
    );

    expect($source->delete((string) $media->uuid))->toBeTrue()
        ->and(Media::query()->count())->toBe(0);
});

it('leaves a document that still points at a deleted file savable', function (): void {
    // Deleting takes the file out of the stored entries, but an editor open somewhere else
    // still holds it until it is saved - and that save must not turn into a tampering error:
    // the id was the record's before the edit, so it is still the record's to keep. What it
    // shows is a dead link, like any link to something that is gone.
    Storage::disk('public')->put('article-attachments/talk.mp4', 'x');

    $post = Post::create([
        'title' => 'P',
        'content' => '<video src="/storage/article-attachments/talk.mp4" data-id="article-attachments/talk.mp4" controls></video>',
    ]);

    $editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    expect($editor->deleteMediaForJs('article-attachments/talk.mp4')['deleted'])->toBeTrue()
        ->and($editor->isFileAttachmentPathAuthorized('article-attachments/talk.mp4'))->toBeTrue();
});
