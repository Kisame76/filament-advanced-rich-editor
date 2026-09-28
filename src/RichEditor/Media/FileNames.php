<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Str;

/**
 * What a file uploaded to a disk is called there, and what it is called on screen.
 *
 * Filament stores an upload under forty random characters, which is right for a picture that
 * is only ever seen and wrong for a library: a document has no thumbnail, so the grid finds it
 * by name, the search finds it by name, and the card in the document is labelled with it. A
 * hash is none of those.
 *
 * The name the person gave it, then - made safe for an address - with a short random part
 * after it. The random part is what keeps a public disk from being a list anybody can read by
 * guessing: a pdf taken out of a draft again stays in a shared library, and
 * `/storage/gehaltsliste.pdf` is a guess, `/storage/gehaltsliste--7kq2xm.pdf` is not.
 */
class FileNames
{
    /**
     * What separates the readable name from the random part. A slug never holds two dashes in
     * a row, and the random part always opens with a digit - a file already on the disk that
     * wears the shape by accident keeps the name it has, because reading `team--photo1.png`
     * as `team.png` would show a name no file there answers to.
     */
    public const MARKER = '--';

    public static function stored(string $originalName, string $extension): string
    {
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME), '-', app()->getLocale());

        // Long enough to recognise, short enough to leave room for the rest of a path.
        $slug = rtrim(Str::limit($slug, 80, ''), '-');

        // A digit first, and that is what makes the part readable as a marker rather than as
        // a word: `report--abc123.pdf` is a name somebody typed, `report--7kq2xm.pdf` is one
        // this class wrote, and `display()` has to be able to tell them apart on a disk it
        // did not fill by itself.
        $random = Str::lower((string) random_int(0, 9).Str::random(5));

        return ($slug === '' ? 'file' : $slug).static::MARKER.$random.'.'.$extension;
    }

    /**
     * The name without the random part, for everything a person reads: the tile, the panel,
     * the card and the name a download is saved under.
     */
    public static function display(string $name): string
    {
        return (string) preg_replace('/'.preg_quote(static::MARKER, '/').'[0-9][a-z0-9]{5}(?=\.[a-z0-9]+$)/i', '', $name);
    }
}
