<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
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
    // Real bytes rather than a fake, because everything downstream reads the file itself:
    // Livewire guesses the mime type off the content, and the browser files the upload under
    // a family by that guess. A fake with a declared type and random bytes is an
    // `application/octet-stream` to both of them.
    $this->hold = function (string $name, string $bytes): TemporaryUploadedFile {
        $fake = new UploadedFile(tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        // Built the way Livewire builds one: the original file name is encoded into the
        // stored name, and that is where `getClientOriginalName()` reads it back from. A file
        // simply stored under a random name would come back called something else.
        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->upload = function (string $name, ?string $id = null): string {
        $id ??= 'pending-'.str_replace('.', '-', $name);

        $file = ($this->hold)($name, UploadedFile::fake()->image($name, 8, 8)->get());

        data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", $file);

        return $id;
    };

    // The smallest film ffmpeg will write: two black frames, 16 by 16, and `file` calls it
    // `video/mp4`. Checked in, because a video cannot be faked the way a picture can.
    $this->video = fn (): string => (string) file_get_contents(__DIR__.'/../Fixtures/media/tiny.mp4');

    // A quarter of a second of silence, written by hand: a WAV is a 44-byte header and
    // samples, and `finfo` reads the header.
    $this->audio = function (): string {
        $samples = str_repeat("\0\0", 2000);

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
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

it('does not offer an upload of a kind nothing here can draw', function (): void {
    // A document is neither a picture, a film nor a sound: no node draws it, so a tile for
    // it would offer a file nothing can insert.
    $file = ($this->hold)('notes.pdf', '%PDF-1.4 x');

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.pending-pdf", $file);

    expect($this->editor->getPendingMediaItems())->toBe([])
        ->and($this->editor->findMediaItem('pending-pdf'))->toBeNull();
});

it('lets a film through only under the id the browser stamped', function (): void {
    // The two lists meet at the id. A file the browser holds carries its prefix and is
    // measured against the browser's wider list; a file Filament's own dialog holds carries
    // Filament's id and is measured against Filament's picture-only list. Same film, two
    // answers - and the second one is right, because Filament's dialog would put it in an
    // <img>.
    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.filament-id", ($this->hold)('clip.mp4', ($this->video)()));

    expect($this->editor->getUploadedFileAttachment('filament-id'))->toBeNull()
        ->and($this->editor->getUploadedFileAttachment(FileAttachments::PENDING_PREFIX.'x'))->toBeNull();

    $stamped = FileAttachments::PENDING_PREFIX.'film';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$stamped}", ($this->hold)('clip.mp4', ($this->video)()));

    expect($this->editor->getUploadedFileAttachment($stamped))->not->toBeNull();
});

it('shows a film and a sound that have not been saved yet', function (): void {
    // The bug this pins: both came back null. The address was asked for through Filament's
    // `getUploadedFileAttachmentTemporaryUrl()`, which takes the file OBJECT back through
    // `getUploadedFileAttachment()` - and an object, unlike an `arte-` id, carries nothing to
    // say it came through the browser, so it was measured against Filament's picture-only
    // list and refused. Accepted, held, and then invisible.
    // Under the browser's own prefix, which is how the browser stamps what it holds - and
    // the only key a film or a sound is let through under. An id without it is measured
    // against Filament's picture-only list, because nothing else says where it came from.
    $film = FileAttachments::PENDING_PREFIX.'film';
    $sound = FileAttachments::PENDING_PREFIX.'sound';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$film}", ($this->hold)('clip.mp4', ($this->video)()));
    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$sound}", ($this->hold)('take.wav', ($this->audio)()));

    $items = collect($this->editor->getPendingMediaItems())->keyBy('name');

    expect($items)->toHaveKeys(['clip.mp4', 'take.wav'])
        ->and($items['clip.mp4']['kind'])->toBe('video')
        ->and($items['take.wav']['kind'])->toBe('audio')
        ->and($items['clip.mp4']['url'])->toBeString()->not->toBeEmpty()
        // No picture to stand in for it, which is what lets the grid draw a sign instead of
        // a broken image.
        ->and($items['clip.mp4']['thumbnail'])->toBeNull()
        ->and($items['clip.mp4']['width'])->toBeNull()
        ->and($this->editor->findMediaItem($film)['kind'])->toBe('video');
});

it('adopts every file out of one dialog, not the first', function (): void {
    // `->multiple()` hands the whole set over at once, keyed however Filament keys it.
    data_set($this->livewire, 'mountedActions.0.data.file', [
        'one' => ($this->hold)('clip.mp4', ($this->video)()),
        'two' => ($this->hold)('take.wav', ($this->audio)()),
        'three' => ($this->hold)('hafen.png', UploadedFile::fake()->image('hafen.png', 8, 8)->get()),
    ]);

    $page = $this->editor->getMediaLibraryPageForJs();

    expect(array_column($page['items'], 'name'))->toContain('clip.mp4', 'take.wav', 'hafen.png')
        ->and($page['total'])->toBe(3);
});

it('counts the uploads it is holding among the families on the page', function (): void {
    // The tabs are drawn from `kinds`, and a library holding nothing but two fresh uploads
    // answered `[]` - a video and a sound sitting under a tab row that stayed hidden.
    $film = FileAttachments::PENDING_PREFIX.'film';
    $sound = FileAttachments::PENDING_PREFIX.'sound';

    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$film}", ($this->hold)('clip.mp4', ($this->video)()));
    data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$sound}", ($this->hold)('take.wav', ($this->audio)()));

    expect($this->editor->getMediaLibraryPageForJs()['kinds'])->toBe(['video', 'audio']);
});

it('lets Livewire preview every kind of file the browser accepts', function (): void {
    // A held upload has no address until it is saved; what the grid shows meanwhile is
    // Livewire's preview URL, and Livewire hands one out only for the extensions in its own
    // list. That list stops at mp4, mov, mp3, wav and m4a - so a webm or a flac was
    // accepted, held, and invisible. The package widens the list by adding to it, never by
    // replacing what a project put there.
    $previewable = config('livewire.temporary_file_upload.preview_mimes');

    expect($previewable)->toContain('webm', 'ogv', 'ogg', 'flac', 'aac', 'opus', 'weba', 'avif')
        // And Livewire's own entries are still there.
        ->and($previewable)->toContain('png', 'mp4', 'mp3', 'wav');
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
