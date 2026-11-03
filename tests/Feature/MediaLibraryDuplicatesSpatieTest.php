<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A file that is already in the library, uploaded again - into a media collection.
 *
 * The same questions `MediaLibraryDuplicatesTest` asks of a disk. What a collection adds is a
 * place to keep the answer: the hash is stamped on the row as it is written, so comparing with
 * it later costs nothing, and a row from before is hashed the first time it is compared.
 */
beforeEach(function (): void {
    requires('spatie/laravel-medialibrary');

    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    config()->set('media-library.disk_name', 'public');

    $this->bytes = UploadedFile::fake()->image('hafen.png', 12, 8)->get();
    $this->post = MediaPost::create(['title' => 'Article', 'content' => '']);

    $this->hold = function (string $name, string $bytes): TemporaryUploadedFile {
        $fake = new UploadedFile((string) tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->editor = fn (string $name = 'content'): AdvancedRichEditor => AdvancedRichEditor::make($name)
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));

    $this->saved = function (AdvancedRichEditor $editor, TemporaryUploadedFile $file): string {
        $pending = $editor->registerPendingUpload($file);

        $editor->state('<p><img src="/livewire/preview" data-id="'.$pending.'"></p>');
        $editor->resolveFileAttachmentIds();

        preg_match('/data-id="([^"]+)"/', (string) $editor->getState(), $matches);

        return $matches[1] ?? '';
    };

    $this->attach = fn (MediaPost $post, ?string $owner = null): Media => $post->addMediaFromString($this->bytes)
        ->usingFileName('harbour.png')
        ->withCustomProperties($owner === null ? [] : [SpatieMediaLibraryFileAttachmentProvider::OWNER_PROPERTY => $owner])
        ->toMediaCollection('rich-editor');
});

it('stamps an upload with what is in it', function (): void {
    $uuid = ($this->saved)(($this->editor)(), ($this->hold)('hafen.png', $this->bytes));

    expect(Media::findByUuid($uuid)->getCustomProperty(SpatieMediaSource::HASH_PROPERTY))->toBe(hash('sha256', $this->bytes));
});

it('points a picture at the media the library already holds', function (): void {
    // Another article's, in the shared collection the browser lists by default.
    $existing = ($this->attach)(MediaPost::create(['title' => 'Elsewhere', 'content' => '']));

    expect(($this->saved)(($this->editor)(), ($this->hold)('IMG_0412.png', $this->bytes)))->toBe($existing->uuid)
        ->and(Media::query()->count())->toBe(1);
});

it('hashes a file from before once, the first time it is compared', function (): void {
    $existing = ($this->attach)($this->post);

    expect($existing->getCustomProperty(SpatieMediaSource::HASH_PROPERTY))->toBeNull();

    ($this->saved)(($this->editor)(), ($this->hold)('hafen.png', $this->bytes));

    expect($existing->refresh()->getCustomProperty(SpatieMediaSource::HASH_PROPERTY))->toBe(hash('sha256', $this->bytes));
});

it('reuses a copy whoever uploaded it, where the record\'s files are swept', function (?string $owner): void {
    // Each field deletes its uploads once its text stops using them, but not while another
    // column of the record still shows one - see `MediaLibraryTwoEditorsTest`. So a copy the
    // summary uploaded is as safe to point the content at as one the content did.
    $existing = ($this->attach)($this->post, owner: $owner);

    $editor = ($this->editor)()->mediaLibraryScope('record');

    expect(($this->saved)($editor, ($this->hold)('hafen.png', $this->bytes)))->toBe($existing->uuid)
        ->and(Media::query()->count())->toBe(1);
})->with([
    'its own' => ['content'],
    'another field\'s' => ['summary'],
    'nobody\'s' => [null],
]);

it('hashes the new file when one is replaced', function (): void {
    $existing = ($this->attach)($this->post);
    $replacement = UploadedFile::fake()->image('dawn.png', 20, 9);

    $source = SpatieMediaSource::make(collection: 'rich-editor', visibility: 'public', getRecordUsing: fn (): MediaPost => $this->post);

    expect($source->replace($existing->uuid, $replacement))->toBeTrue()
        ->and($existing->refresh()->getCustomProperty(SpatieMediaSource::HASH_PROPERTY))->toBe(hash('sha256', $replacement->get()));
});
