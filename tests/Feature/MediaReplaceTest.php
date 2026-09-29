<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Contracts\ReplacesMedia;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Sidecar;

/**
 * Putting a new file in the place of one that is already in the library.
 *
 * The id stays, and that is the whole point: every document pointing at the item shows the
 * new file the next time it is drawn - a price list is replaced once, not once per page that
 * links to it. On a disk the id is the path, so its ending is part of the id and stays too.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->disk = Storage::disk('public');

    $this->source = DiskMediaSource::make(disk: 'public', directory: 'library', visibility: 'public');
});

it('puts the upload where the file was, under the same path', function (): void {
    $this->disk->put('library/sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());

    $replacement = UploadedFile::fake()->image('dawn.png', 12, 5);

    expect($this->source)->toBeInstanceOf(ReplacesMedia::class)
        ->and($this->source->replace('library/sunset.png', $replacement))->toBeTrue()
        ->and($this->disk->get('library/sunset.png'))->toBe($replacement->get())
        ->and($this->disk->exists('library/dawn.png'))->toBeFalse()
        ->and($this->source->details('library/sunset.png'))->toMatchArray(['width' => 12, 'height' => 5]);
});

it('keeps the file\'s name, since on a disk the name is the id', function (): void {
    // A new name would be a new path, and every document would go on pointing at the old one.
    // With a media library the name follows the upload; here it cannot.
    $this->disk->put('library/report.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

    $replacement = UploadedFile::fake()->createWithContent('Report Q4.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% newer");

    expect($this->source->replace('library/report.pdf', $replacement))->toBeTrue()
        ->and($this->source->details('library/report.pdf'))->toMatchArray(['id' => 'library/report.pdf', 'name' => 'report.pdf'])
        ->and($this->disk->exists('library/Report Q4.pdf'))->toBeFalse();
});

it('keeps the description and lets go of the cover made from the old file', function (): void {
    $this->disk->put('library/talk.mp4', 'old film');
    $this->disk->put('library/talk.mp4.cover.jpg', 'old still');

    Sidecar::write($this->disk, 'library/talk.mp4', ['title' => 'The talk', 'cover_attempted' => true]);

    $replacement = UploadedFile::fake()->createWithContent('talk-v2.mp4', (string) file_get_contents(dirname(__DIR__).'/Fixtures/media/tiny.mp4'));

    expect($this->source->replace('library/talk.mp4', $replacement))->toBeTrue()
        ->and($this->disk->exists('library/talk.mp4.cover.jpg'))->toBeFalse()
        ->and(Sidecar::read($this->disk, 'library/talk.mp4'))->toBe(['title' => 'The talk']);
});

it('refuses a file of another ending, because the ending is part of the id', function (): void {
    $this->disk->put('library/sunset.png', 'old');

    expect($this->source->replace('library/sunset.png', UploadedFile::fake()->image('dawn.jpg', 4, 4)))->toBeFalse()
        ->and($this->disk->get('library/sunset.png'))->toBe('old');
});

it('refuses what the library would not take at all', function (): void {
    $this->disk->put('library/notes.txt', 'old');

    $source = DiskMediaSource::make(disk: 'public', directory: 'library', visibility: 'public');

    // Named like the file, but a script underneath: the content check an upload goes through
    // is the one a replacement goes through. A real file rather than a fake, because a fake
    // answers `getMimeType()` from its name and would never be caught by what is inside it.
    $script = (string) tempnam(sys_get_temp_dir(), 'arte-replace');
    file_put_contents($script, '<?php echo "hi";');

    $page = new UploadedFile($script, 'notes.txt', test: true);

    expect($source->replace('library/notes.txt', $page))->toBeFalse()
        ->and($this->disk->get('library/notes.txt'))->toBe('old');
});

it('refuses a path outside the pool, and one that is not there', function (): void {
    $this->disk->put('elsewhere/sunset.png', 'old');

    $replacement = UploadedFile::fake()->image('sunset.png', 4, 4);

    expect($this->source->replace('elsewhere/sunset.png', $replacement))->toBeFalse()
        ->and($this->source->replace('../elsewhere/sunset.png', $replacement))->toBeFalse()
        ->and($this->source->replace('library/missing.png', $replacement))->toBeFalse()
        ->and($this->disk->exists('library/missing.png'))->toBeFalse()
        ->and($this->disk->get('elsewhere/sunset.png'))->toBe('old');
});

it('refuses an embed, which is a link rather than a file', function (): void {
    $path = 'library/'.Embeds::fileName('youtube', 'dQw4w9WgXcQ');

    $this->disk->put($path, Embeds::encode(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null, 'title' => 'The talk', 'ratio' => '16 / 9']));

    expect($this->source->replacementTypes($path))->toBeNull()
        ->and($this->source->replace($path, UploadedFile::fake()->createWithContent('x.json', '{}')))->toBeFalse();
});

it('tells the picker to offer only files of the same ending', function (): void {
    $this->disk->put('library/sunset.png', 'x');
    $this->disk->put('library/report.pdf', '%PDF-1.4');

    expect($this->source->replacementTypes('library/sunset.png'))->toBe(['.png'])
        ->and($this->source->replacementTypes('library/report.pdf'))->toBe(['.pdf'])
        ->and($this->source->replacementTypes('library/missing.png'))->toBeNull();
});
