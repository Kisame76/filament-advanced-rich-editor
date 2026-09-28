<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\Id3Cover;

/**
 * The picture an mp3 carries about itself.
 *
 * Read by hand rather than by a library, because the whole of what is needed is one frame
 * out of a tag whose layout has not changed since 1998 - and the alternative is a dependency
 * that reads forty other things nobody here asked about.
 *
 * The fixtures are built rather than checked in: a tag is a header, a length and some bytes,
 * and building one is what makes the test say which byte is being got wrong.
 */
beforeEach(function (): void {
    // A JPEG's first bytes, which is all anything here looks at.
    $this->jpeg = "\xFF\xD8\xFF\xE0".str_repeat('x', 40);

    $this->syncsafe = static fn (int $size): string => chr(($size >> 21) & 0x7F)
        .chr(($size >> 14) & 0x7F)
        .chr(($size >> 7) & 0x7F)
        .chr($size & 0x7F);

    // v2.3 writes a plain big-endian length; v2.4 writes a syncsafe one.
    $this->frame = fn (string $id, string $body, int $major = 3): string => $major === 4
        ? $id.($this->syncsafe)(strlen($body))."\0\0".$body
        : $id.pack('N', strlen($body))."\0\0".$body;

    $this->tag = fn (string $frames, int $major = 3): string => 'ID3'.chr($major)."\0\0"
        .($this->syncsafe)(strlen($frames)).$frames;

    // Encoding, mime, picture type, description, then the picture.
    $this->apic = fn (string $mime, string $bytes): string => "\x00".$mime."\x00\x03\x00".$bytes;
});

it('reads the cover out of a v2.3 tag', function (): void {
    $mp3 = ($this->tag)(($this->frame)('APIC', ($this->apic)('image/jpeg', $this->jpeg))).'audio bytes';

    expect(Id3Cover::read($mp3))->toBe(['mime' => 'image/jpeg', 'bytes' => $this->jpeg]);
});

it('reads a v2.4 tag, whose frame lengths are spelled differently', function (): void {
    $mp3 = ($this->tag)(($this->frame)('APIC', ($this->apic)('image/png', $this->jpeg), 4), 4);

    expect(Id3Cover::read($mp3)['mime'])->toBe('image/png');
});

it('reads the three-letter frame a v2.2 tag uses', function (): void {
    // `PIC`, with a three-byte length, no flags, and a three-character format instead of a
    // mime type.
    $body = "\x00".'JPG'."\x03\x00".$this->jpeg;
    $frames = 'PIC'.substr(pack('N', strlen($body)), 1).$body;

    $mp3 = ($this->tag)($frames, 2);

    expect(Id3Cover::read($mp3))->toBe(['mime' => 'image/jpeg', 'bytes' => $this->jpeg]);
});

it('walks past the frames in front of the picture', function (): void {
    // A tag out of any tagger has the title and the artist first, and the picture last.
    $frames = ($this->frame)('TIT2', "\x00".'The talk')
        .($this->frame)('TPE1', "\x00".'Somebody')
        .($this->frame)('APIC', ($this->apic)('image/jpeg', $this->jpeg));

    expect(Id3Cover::read(($this->tag)($frames))['bytes'])->toBe($this->jpeg);
});

it('reads past a description written in two-byte characters', function (): void {
    // Encoding 1 is UTF-16, and its terminator is two zero bytes rather than one - read as
    // one, the picture starts a byte early and is not a picture any more. The description
    // is spelled out properly: a byte order mark and then one zero byte per character, or
    // the pairs the reader steps through would not line up with the characters.
    $description = "\xFF\xFE".'C'."\x00".'o'."\x00".'v'."\x00".'e'."\x00".'r'."\x00";
    $body = "\x01".'image/jpeg'."\x00\x03".$description."\x00\x00".$this->jpeg;

    expect(Id3Cover::read(($this->tag)(($this->frame)('APIC', $body)))['bytes'])->toBe($this->jpeg);
});

it('says nothing about a file that has no tag, no picture, or no sense', function (): void {
    expect(Id3Cover::read('not an mp3 at all'))->toBeNull()
        ->and(Id3Cover::read(''))->toBeNull()
        // A tag with words in it but no picture.
        ->and(Id3Cover::read(($this->tag)(($this->frame)('TIT2', "\x00".'The talk'))))->toBeNull()
        // A frame claiming to be longer than the tag it is in.
        ->and(Id3Cover::read(($this->tag)('APIC'.pack('N', 999999)."\0\0".'x')))->toBeNull();
});

it('refuses a picture too big to be a thumbnail', function (): void {
    // Several megabytes of album art is not a cover, it is a scan - and it would be read
    // into memory whole, once per listing, on a page that is drawing forty tiles.
    //
    // Proved against a small ceiling rather than the shipped one: allocating five megabytes
    // to watch them be refused is the same waste this guard exists to prevent.
    config()->set('filament-advanced-rich-editor.media_library.covers.max_picture_bytes', 1024);

    $huge = str_repeat('x', 1025);

    expect(Id3Cover::read(($this->tag)(($this->frame)('APIC', ($this->apic)('image/jpeg', $huge)))))
        ->toBeNull();
});

it('ignores a picture whose type is not a picture', function (): void {
    // `-->` is ID3's way of storing a link instead of an image, and `text/plain` is what a
    // broken tagger writes. Neither is bytes to put in a file and draw.
    expect(Id3Cover::read(($this->tag)(($this->frame)('APIC', ($this->apic)('text/plain', 'x')))))
        ->toBeNull()
        ->and(Id3Cover::read(($this->tag)(($this->frame)('APIC', ($this->apic)('-->', 'https://x.test/a.jpg')))))
        ->toBeNull();
});

it('ships the ceiling it documents', function (): void {
    expect(Id3Cover::maxPictureBytes())->toBe(Id3Cover::MAX_PICTURE_BYTES)
        ->and(require __DIR__.'/../../config/filament-advanced-rich-editor.php')
        ->toHaveKey('media_library.covers.max_picture_bytes', Id3Cover::MAX_PICTURE_BYTES);
});
