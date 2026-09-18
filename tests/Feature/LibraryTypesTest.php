<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\LibraryTypes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;

/**
 * What the media browser offers and what it takes an upload of, asked of one object.
 *
 * The browser used to carry a list of mime types, and a mime type is the one thing a document
 * does not have a family for: `application/*` holds a pdf and a program alike. So a file that
 * is not drawn is named by its ending, and the content only has to agree with that ending.
 */
beforeEach(function (): void {
    // Real bytes, because the answer is read off the content: Symfony's guesser asks
    // `finfo`, and a fake with random bytes is an `application/octet-stream` to it.
    $this->file = function (string $name, string $bytes): UploadedFile {
        $path = (string) tempnam(sys_get_temp_dir(), 'arte');

        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    };

    $this->pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    $this->png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    // A Word document is a zip with a known first entry, which is what `finfo` looks for.
    $this->docx = function (): string {
        $path = sys_get_temp_dir().'/arte-'.uniqid().'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->close();

        return (string) file_get_contents($path);
    };
});

it('offers every family by default', function (): void {
    expect(LibraryTypes::make()->kinds())->toBe(['image', 'video', 'audio', 'file']);
});

it('offers exactly the endings the card has a colour for', function (): void {
    // A card with a colour and a browser that refuses the file behind it would be two
    // answers to one question.
    expect(LibraryTypes::make()->fileTypes())
        ->toEqualCanonicalizing(array_merge(...array_values(FileTypes::TINTS)));
});

it('reads a missing family as its default and an empty one as off', function (): void {
    // A project that published the configuration before `types` existed has no key at all,
    // and must not lose every family over it.
    expect(LibraryTypes::make(['file' => null])->offers(MediaKinds::FILE))->toBeTrue()
        ->and(LibraryTypes::make(['video' => []])->offers(MediaKinds::VIDEO))->toBeFalse()
        ->and(LibraryTypes::make(['audio' => false])->offers(MediaKinds::AUDIO))->toBeFalse()
        ->and(LibraryTypes::make(['video' => []])->kinds())->toBe(['image', 'audio', 'file']);
});

it('takes an ending however it is spelled', function (): void {
    expect(LibraryTypes::make(['file' => ['.PDF', ' Docx ']])->fileTypes())->toBe(['pdf', 'docx']);
});

it('files a path under the family its ending names', function (): void {
    $types = LibraryTypes::make();

    expect($types->kindOfPath('library/report.pdf'))->toBe('file')
        ->and($types->kindOfPath('library/Report.PDF'))->toBe('file')
        ->and($types->kindOfPath('library/sunset.png'))->toBe('image')
        ->and($types->kindOfPath('library/talk.mp4'))->toBe('video')
        ->and($types->kindOfPath('library/talk.mp3'))->toBe('audio')
        // Not on the list, and nothing on the list is guessed at.
        ->and($types->kindOfPath('library/setup.exe'))->toBeNull()
        ->and($types->kindOfPath('library/README'))->toBeNull();
});

it('lets a picture be a picture only where pictures are offered', function (): void {
    $types = LibraryTypes::make(['image' => []]);

    // A switched-off family is not quietly turned into downloads ...
    expect($types->kindOfPath('sunset.png'))->toBeNull()
        // ... unless the project asked for exactly that ending as a file.
        ->and(LibraryTypes::make(['image' => [], 'file' => ['png']])->kindOfPath('sunset.png'))->toBe('file');
});

it('reads a picture list written as mime types, the way Filament writes one', function (): void {
    $types = LibraryTypes::make(['image' => ['image/png', 'image/jpeg']]);

    expect($types->kindOfPath('a.png'))->toBe('image')
        ->and($types->kindOfPath('a.jpg'))->toBe('image')
        ->and($types->kindOfPath('a.avif'))->toBeNull();
});

it('takes any ending for a star, and never one that runs as the site', function (): void {
    $types = LibraryTypes::make(['file' => ['*']]);

    expect($types->kindOfPath('drawing.dwg'))->toBe('file')
        ->and($types->kindOfPath('page.html'))->toBeNull()
        ->and($types->kindOfPath('shell.php'))->toBeNull()
        ->and($types->kindOfPath('logo.svg'))->toBe('image');

    // Named outright, it is still refused: the list is a wish, the refusal is not.
    expect(LibraryTypes::make(['file' => ['html', 'phtml', 'js']])->kindOfPath('page.html'))->toBeNull();
});

it('lets a star reach a drawing finfo calls a picture, and not a picture it refused', function (): void {
    // `finfo` calls a CAD drawing `image/vnd.dwg`, and nothing draws one. What decides is the
    // name: an ending no browser draws is a document under a star, and a `.avif` the picture
    // list left out is not quietly turned into a download.
    $types = LibraryTypes::make(['image' => ['png'], 'file' => ['*']]);

    expect($types->kindOf('image/vnd.dwg', 'drawing.dwg'))->toBe('file')
        ->and($types->kindOf('image/avif', 'photo.avif'))->toBeNull();
});

it('files a row by its mime and its name together', function (): void {
    $types = LibraryTypes::make();

    expect($types->kindOf('application/pdf', 'report.pdf'))->toBe('file')
        ->and($types->kindOf('image/png', 'sunset.png'))->toBe('image')
        ->and($types->kindOf('text/plain', 'notes.txt'))->toBe('file')
        ->and($types->kindOf('application/octet-stream', 'blob.bin'))->toBeNull()
        ->and($types->kindOf(null, null))->toBeNull();
});

it('spells the mime types of each ending out', function (): void {
    $types = LibraryTypes::make();

    expect($types->mimeOf('report.pdf'))->toBe('application/pdf')
        ->and($types->mimeOf('sunset.png'))->toBe('image/png')
        ->and($types->mimeOf('something.unheardof'))->toBe('');
});

it('takes a document whose content agrees with its ending', function (): void {
    $types = LibraryTypes::make();

    expect($types->accepts(($this->file)('report.pdf', $this->pdf)))->toBeTrue()
        ->and($types->accepts(($this->file)('letter.docx', ($this->docx)())))->toBeTrue()
        // `finfo` calls a spreadsheet written as text what it is: text.
        ->and($types->accepts(($this->file)('prices.csv', "name,price\nTea,3\n")))->toBeTrue()
        ->and($types->accepts(($this->file)('notes.md', "# Notes\n\nSome text.\n")))->toBeTrue()
        ->and($types->accepts(($this->file)('sunset.png', $this->png)))->toBeTrue();
});

it('refuses a page wearing a document\'s ending', function (): void {
    // Served by its ending it would be harmless, but nothing that says it is a pdf and reads
    // as HTML came here by accident.
    $page = '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>';

    expect(LibraryTypes::make()->accepts(($this->file)('report.pdf', $page)))->toBeFalse();
});

it('refuses what would run as the site, whatever the list says', function (): void {
    $types = LibraryTypes::make(['file' => ['*']]);

    expect($types->accepts(($this->file)('page.html', '<html><body>x</body></html>')))->toBeFalse()
        ->and($types->accepts(($this->file)('shell.php', "<?php echo 'x';")))->toBeFalse()
        ->and($types->accepts(($this->file)('notes.phtml', 'x')))->toBeFalse();
});

it('refuses an ending the list does not name', function (): void {
    $types = LibraryTypes::make(['file' => ['pdf']]);

    expect($types->accepts(($this->file)('letter.docx', ($this->docx)())))->toBeFalse()
        ->and(LibraryTypes::make(['file' => []])->accepts(($this->file)('report.pdf', $this->pdf)))->toBeFalse();
});

it('hands the upload widget one list covering everything it may be given', function (): void {
    $mimes = LibraryTypes::make()->mimeTypes();

    expect($mimes)->toContain('image/*', 'video/*', 'audio/*', 'application/pdf', 'text/csv')
        // What `finfo` answers for a spreadsheet written as text, and for a document that
        // is a zip it did not look inside - or the coarse check would refuse both.
        ->and($mimes)->toContain('text/plain', 'application/zip')
        // A star cannot be written as a list, so there is none: the ending check is the gate.
        ->and(LibraryTypes::make(['file' => ['*']])->mimeTypes())->toBeNull();
});

it('keeps the ending that was checked when a file is stored', function (): void {
    $types = LibraryTypes::make();

    // `finfo` says text/plain, and guessing from that would store a spreadsheet as `.txt`.
    expect($types->extensionFor(($this->file)('prices.csv', "name,price\nTea,3\n")))->toBe('csv')
        ->and($types->extensionFor(($this->file)('Photo.PNG', $this->png)))->toBe('png')
        // A picture under a page's ending is stored under the picture's own.
        ->and($types->extensionFor(($this->file)('photo.html', $this->png)))->toBe('png');
});
