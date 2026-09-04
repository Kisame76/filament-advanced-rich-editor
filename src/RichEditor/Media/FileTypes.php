<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Str;

/**
 * What a document card wears: the few letters that name the file's kind, and the colour
 * behind them.
 *
 * "A symbol by file type" is what the roadmap asked for, and a drawing is precisely what
 * cannot be delivered. Filament sanitises rendered content with Symfony's `HtmlSanitizer`,
 * whose safe element list holds neither `svg` nor `path` - measured, not assumed - so an
 * inline icon is not stripped at the edges but removed whole, and the card would reach the
 * page as a coloured gap. Linking one is no better: an `<img>` pointing into this package's
 * assets is a broken picture on any page that did not publish them.
 *
 * So the symbol is the thing every file already carries and every reader already reads: its
 * ending, set in a tile. `PDF` in a red tile says what a red document glyph says, in text
 * that survives a sanitiser, an email client and a plain-text copy of the page.
 *
 * The tints are grouped by what a person would do with the file rather than by format -
 * every spreadsheet is one green whether it came from Excel or from a comma - because the
 * colour is there to be recognised at a glance and eleven shades are not glanceable.
 */
class FileTypes
{
    /**
     * Endings grouped by the colour they get. Everything unlisted is grey, which is the
     * honest answer for a file this package has nothing to say about.
     *
     * @var array<string, array<int, string>>
     */
    public const TINTS = [
        // A page to read.
        '#dc2626' => ['pdf'],
        // Something written.
        '#2563eb' => ['doc', 'docx', 'odt', 'rtf', 'pages', 'txt', 'md'],
        // Something counted.
        '#16a34a' => ['csv', 'numbers', 'ods', 'tsv', 'xls', 'xlsx'],
        // Something presented.
        '#ea580c' => ['key', 'odp', 'ppt', 'pptx'],
        // Something packed.
        '#a16207' => ['7z', 'bz2', 'gz', 'rar', 'tar', 'zip'],
    ];

    public const DEFAULT_TINT = '#52525b';

    /**
     * The letters on the tile.
     *
     * Capped at four, because the tile is a square and `POSTSCRIPT` in a square is a grey
     * smear. A file with no ending at all gets `FILE` rather than an empty box - a tile
     * with nothing in it reads as a picture that failed to load, which is the one thing
     * this card exists not to look like.
     */
    public static function label(?string $name, ?string $fallback = null): string
    {
        $extension = static::extension($name, $fallback);

        return $extension === '' ? 'FILE' : Str::upper(Str::substr($extension, 0, 4));
    }

    /**
     * The colour behind them.
     */
    public static function tint(?string $name, ?string $fallback = null): string
    {
        $extension = static::extension($name, $fallback);

        foreach (static::TINTS as $tint => $extensions) {
            if (in_array($extension, $extensions, strict: true)) {
                return $tint;
            }
        }

        return static::DEFAULT_TINT;
    }

    /**
     * The ending of the name, or of the address behind it.
     *
     * Two readings because a name is allowed not to have one. `<a download>Handbuch</a>`
     * names a file the way a person would, and the ending is in the address it points at -
     * a tile reading `FILE` beside a `.docx` would be this class declining to answer a
     * question it can answer.
     */
    protected static function extension(?string $name, ?string $fallback): string
    {
        $extension = static::extensionOf($name);

        return $extension === '' ? static::extensionOf($fallback) : $extension;
    }

    /**
     * The ending of a name or of an address, with a query string and a fragment taken off
     * first - `/files/report.pdf?v=2` is a pdf, and reading the ending off the whole string
     * would call it a `pdf?v=2`.
     */
    protected static function extensionOf(?string $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        $path = Str::before(Str::before($name, '#'), '?');
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        // Endings are letters and digits. Anything else came from a path that has no ending
        // at all, and reading it would put punctuation on the tile.
        return preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1 ? $extension : '';
    }
}
