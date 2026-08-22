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
 * Where an upload lives between arriving and being saved.
 *
 * Not in the dialog: a modal's form is thrown away when the modal closes, so a picture that
 * had been uploaded but not used yet went with it. It belongs to the field instead, alongside
 * every other pending attachment - which is what lets a queue of uploads survive any number of
 * trips through the dialog, and what makes "only the ones the content uses are written" fall
 * out of Filament's own save rather than needing a rule of its own.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new TestSchemaComponent;

    // A directory of its own, because that is what turns a disk into a pool the browser can
    // show - without one the field has nothing browsable and Filament keeps its own dialog.
    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->file = function (string $name): TemporaryUploadedFile {
        $fake = UploadedFile::fake()->image($name, 12, 8);
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $fake->get());

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->pending = fn (): array => array_column($this->editor->getPendingMediaItems(), 'name');
});

it('takes an upload over from the dialog', function (): void {
    $this->editor->registerPendingUploads([($this->file)('hafen.png')]);

    expect(($this->pending)())->toBe(['hafen.png']);
});

it('keeps every upload of a batch', function (): void {
    // Several pictures for one article arrive together; queueing them one dialog at a time is
    // the tedious way to do it.
    // A plain list, which is how Filament hands them over the moment they arrive.
    $this->editor->registerPendingUploads([
        ($this->file)('one.png'),
        ($this->file)('two.png'),
        ($this->file)('three.png'),
    ]);

    expect(($this->pending)())->toHaveCount(3)
        ->and(($this->pending)())->toContain('one.png', 'two.png', 'three.png');
});

it('does not queue the same upload twice', function (): void {
    // The dialog reports its whole set every time one more file arrives, so the same file is
    // handed over again and again - and under different keys each time, because how that set is
    // keyed is Filament's business. Naming an upload after the file itself is what makes
    // re-reporting land in the same place instead of queueing a second copy.
    $file = ($this->file)('hafen.png');

    $this->editor->registerPendingUploads([$file]);
    $this->editor->registerPendingUploads(['some-uuid' => $file, 7 => ($this->file)('wald.png')]);

    expect(($this->pending)())->toHaveCount(2);
});

it('takes uploads however they are keyed', function (): void {
    // Whatever the incoming keys are - a list, ids, or something with a dot in it that would be
    // read as a path - the upload is stored under a name derived from the file.
    $this->editor->registerPendingUploads([
        'with.dot' => ($this->file)('hafen.png'),
        '' => ($this->file)('wald.png'),
        7 => ($this->file)('meer.png'),
    ]);

    expect(($this->pending)())->toHaveCount(3);
});

it('ignores anything that is not an upload', function (): void {
    $this->editor->registerPendingUploads(['not a file', null, 42]);

    expect(($this->pending)())->toBe([]);
});

it('describes an upload as fully as a stored picture', function (): void {
    // Measured off the file, which Livewire is holding on local disk - so the list is complete
    // from the first moment rather than filling in later.
    $this->editor->registerPendingUploads([($this->file)('hafen.png')]);

    expect($this->editor->getPendingMediaItems()[0])
        ->toMatchArray(['name' => 'hafen.png', 'width' => 12, 'height' => 8, 'pending' => true]);
});

it('lets an upload be picked by id, like anything else in the browser', function (): void {
    // One list and one selection: the dialog inserts whatever is selected, and an upload is
    // selected the same way a library picture is.
    $this->editor->registerPendingUploads([($this->file)('hafen.png')]);

    $id = $this->editor->getPendingMediaItems()[0]['id'];

    expect($this->editor->findMediaItem($id))->toMatchArray(['id' => $id, 'name' => 'hafen.png'])
        ->and($this->editor->findMediaItem('never-uploaded'))->toBeNull();
});

it('lets go of every upload once the form has been saved', function (): void {
    // The moment they have all been decided: the ones the content references were written to
    // disk and carry real ids now, and the rest are pictures somebody fetched and did not use.
    $this->editor->registerPendingUploads([($this->file)('used.png'), ($this->file)('spare.png')]);

    expect(($this->pending)())->toHaveCount(2);

    $this->editor->discardPendingUploads();

    expect(($this->pending)())->toBe([]);
});

it('takes the temporary files with it', function (): void {
    // Livewire prunes its own directory eventually, but "eventually" is a lot of abandoned
    // uploads on a busy editor - and these are known to be finished with.
    $file = ($this->file)('spare.png');

    $this->editor->registerPendingUploads([$file]);

    expect(Storage::disk('tmp-for-tests')->exists($file->getRealPath() ? 'livewire-tmp/'.basename($file->getRealPath()) : 'livewire-tmp/'.$file->getFilename()))->toBeTrue();

    $this->editor->discardPendingUploads();

    expect(Storage::disk('tmp-for-tests')->exists('livewire-tmp/'.$file->getFilename()))->toBeFalse();
});

it('shows a saved picture once, not twice', function (): void {
    // The bug this exists for: upload, insert, save, reopen - and the same picture was in the
    // browser twice, once as the file it had become and once as the upload it used to be.
    $this->editor->registerPendingUploads([($this->file)('hafen.png')]);
    $this->editor->discardPendingUploads();

    expect(array_filter($this->editor->getMediaLibraryPageForJs()['items'], fn (array $i): bool => $i['pending'] ?? false))
        ->toBe([]);
});

it('has nothing to let go of twice', function (): void {
    $this->editor->discardPendingUploads();
    $this->editor->discardPendingUploads();

    expect(($this->pending)())->toBe([]);
});

it('takes over what the dialog is holding when the browser asks', function (): void {
    // Read out of the mounted action rather than pushed in as each file lands: uploading is a
    // request per file, and writing to the component in the middle of one forces a render
    // between the first file and the second - enough for Filament to rebuild its schema cache
    // and refuse the next upload.
    data_set($this->livewire, 'mountedActions', [
        ['data' => ['file' => [($this->file)('one.png'), ($this->file)('two.png')]]],
    ]);

    expect(($this->pending)())->toBe([]);

    $this->editor->adoptMountedUploads();

    expect(($this->pending)())->toHaveCount(2);
});

it('takes them over on the way to listing them', function (): void {
    // Which is what the browser calls, and it calls it the moment an upload finishes.
    data_set($this->livewire, 'mountedActions', [
        ['data' => ['file' => [($this->file)('one.png')]]],
    ]);

    $names = array_column($this->editor->getMediaLibraryPageForJs()['items'], 'name');

    expect($names)->toContain('one.png');
});

it('is not confused by a dialog that is holding nothing', function (): void {
    data_set($this->livewire, 'mountedActions', [
        ['data' => ['file' => null, 'media' => 'something']],
        ['data' => []],
        'not an action',
    ]);

    $this->editor->adoptMountedUploads();

    expect(($this->pending)())->toBe([]);
});
