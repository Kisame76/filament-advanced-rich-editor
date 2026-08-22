<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A picture that has been uploaded but not saved yet.
 *
 * Filament holds an upload as a pending attachment and only writes it to the disk or the media
 * collection when the form is saved. A browser that listed only what is stored would therefore
 * answer "no such picture" about the file somebody had just chosen - which is exactly the
 * moment they go looking for it.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    // Livewire keeps pending uploads on its own disk, which in a test run is a name with no
    // driver behind it until something fakes it.
    Storage::fake('tmp-for-tests');

    $this->livewire = new TestSchemaComponent;

    // A directory of its own, because that is what turns a disk into a pool the browser can
    // show - without one the field has nothing browsable and Filament keeps its own dialog.
    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    // No dots in the id: `componentFileAttachments` is addressed with `data_get()`, which reads
    // a dot as a level of nesting. Filament's own ids are ordered UUIDs, so this is a property
    // of the fixture rather than of the browser.
    $this->upload = function (string $name, ?string $id = null): string {
        $id ??= 'pending-'.str_replace('.', '-', $name);

        // Built the way Livewire builds one: the original file name is encoded into the
        // stored name, and that is where `getClientOriginalName()` reads it back from. A file
        // simply stored under a random name would come back called something else.
        $fake = UploadedFile::fake()->image($name, 8, 8);
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

        $file = TemporaryUploadedFile::createFromLivewire($stored);

        data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", $file);

        return $id;
    };
});

it('shows an upload that has not been saved yet', function (): void {
    $id = ($this->upload)('hafen.png');

    $items = $this->editor->getMediaLibraryPageForJs()['items'];

    expect(array_column($items, 'id'))->toContain($id)
        ->and(array_column($items, 'name'))->toContain('hafen.png');
});

it('marks it as not saved', function (): void {
    // The tile is drawn differently and says so, because navigating away now loses the file.
    ($this->upload)('hafen.png');

    expect($this->editor->getPendingMediaItems()[0]['pending'])->toBeTrue();
});

it('puts it in front of the library', function (): void {
    // The newest things are already at the top of the grid, and nothing is newer than the file
    // that was chosen a second ago.
    ($this->upload)('hafen.png');

    expect($this->editor->getMediaLibraryPageForJs()['items'][0]['name'])->toBe('hafen.png');
});

it('shows the newest upload first when there are several', function (): void {
    ($this->upload)('first.png');
    ($this->upload)('second.png');

    expect(array_column($this->editor->getPendingMediaItems(), 'name'))
        ->toBe(['second.png', 'first.png']);
});

it('searches the pending uploads too', function (): void {
    ($this->upload)('hafen.png');
    ($this->upload)('wald.png');

    expect(array_column($this->editor->getPendingMediaItems('hafen'), 'name'))->toBe(['hafen.png'])
        ->and($this->editor->getPendingMediaItems('nothing'))->toBe([]);
});

it('carries a URL that can be drawn', function (): void {
    ($this->upload)('hafen.png');

    $item = $this->editor->getPendingMediaItems()[0];

    expect($item['url'])->toBeString()->not->toBeEmpty()
        ->and($item['thumbnail'])->toBe($item['url'])
        ->and($item['mime'])->toStartWith('image/');
});

it('resolves a pending id the dialog sends back', function (): void {
    // Picking a pending tile has to insert the same attachment the upload tab would have,
    // rather than fall through to the pool and find nothing.
    $id = ($this->upload)('hafen.png');

    expect($this->editor->findMediaItem($id)['id'])->toBe($id);
});

it('refuses an id it is not holding', function (): void {
    ($this->upload)('hafen.png');

    expect($this->editor->findMediaItem('made-up-id'))->toBeNull()
        ->and($this->editor->findMediaItem(null))->toBeNull();
});

it('does not offer an upload that is not a picture', function (): void {
    // It would insert a broken image, and the image dialog is the only way it could get in.
    $fake = UploadedFile::fake()->create('notes.pdf', 4, 'application/pdf');
    $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

    Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

    $file = TemporaryUploadedFile::createFromLivewire($stored);

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.pending-pdf", $file);

    expect($this->editor->getPendingMediaItems())->toBe([])
        ->and($this->editor->findMediaItem('pending-pdf'))->toBeNull();
});

it('keeps pending uploads off a folder listing', function (): void {
    // A pending upload has no folder to be in, and repeating it under every folder would be
    // worse than not showing it there at all.
    ($this->upload)('hafen.png');

    $editor = $this->editor->mediaLibraryDirectory('library');

    expect(array_column($editor->getMediaLibraryPageForJs(folder: 'library/press')['items'], 'name'))
        ->not->toContain('hafen.png')
        ->and(array_column($editor->getMediaLibraryPageForJs()['items'], 'name'))
        ->toContain('hafen.png');
});

it('keeps pending uploads off the second page', function (): void {
    // They belong at the front of the first page. Repeating them on every page would grow the
    // grid by one copy per scroll.
    ($this->upload)('hafen.png');

    expect(array_column($this->editor->getMediaLibraryPageForJs(page: 2)['items'], 'name'))
        ->not->toContain('hafen.png');
});

it('has no pending uploads while the browser is off', function (): void {
    ($this->upload)('hafen.png');

    expect($this->editor->mediaLibrary(false)->getMediaLibraryPageForJs()['items'])->toBe([]);
});
