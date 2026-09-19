<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * What the media browser offers, and what it takes an upload of.
 *
 * One object for three questions - what a listing shows, what a stored id may resolve to, and
 * what an upload may be - because three lists would drift, and the drift would either be a
 * file the grid shows and nothing can insert, or one that is accepted and never shown again.
 *
 * Each family is a list. For a picture, a film and a sound an entry is an ending (`mp4`), a
 * mime type (`image/png`) or a family pattern (`image/*`) - the vocabulary of an HTML `accept`
 * attribute, and the one Filament writes its own picture list in. A document is named by its
 * ending alone: its mime type says nothing a person could pick by, since `application/*`
 * holds a pdf and a program alike. `*` takes every ending, and only for documents.
 *
 * An upload is taken when its content agrees with its ending. The ending is also what the
 * file is stored under, and a web server hands a file out by its ending - so what arrives
 * named `.pdf` leaves as a pdf, and nothing in `DENIED` ever arrives at all.
 */
class LibraryTypes
{
    /** Every ending, in the one family where that can mean anything. */
    public const ANY = '*';

    /**
     * Endings that are never taken, whatever a list says.
     *
     * The first half is run on the server by a handler that matches on the ending - an
     * `.php` in a public directory is a program, not a download. The second half is run in
     * the browser as the site that served it: a page, a drawing with a script in it, a
     * stylesheet of behaviour. Either is somebody else's code under this site's name, and a
     * library is not where a site should be taking that from.
     *
     * @var array<int, string>
     */
    public const DENIED = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'cgi', 'pl', 'asp', 'aspx', 'jsp', 'htaccess',
        'htm', 'html', 'xhtml', 'shtml', 'svg', 'xml', 'xsl', 'js', 'mjs',
    ];

    /**
     * Endings a star never reaches.
     *
     * `DENIED` is about this site: a file that would run as your own pages. These run
     * somewhere else - on the machine of whoever opens the download - and a star is a
     * statement about documents rather than an invitation to hand out a program. Named
     * outright they are a project's own call, the way every other ending is.
     *
     * @var array<int, string>
     */
    public const RISKY = [
        'exe', 'msi', 'com', 'scr', 'bat', 'cmd', 'hta', 'cpl', 'msc', 'reg', 'lnk', 'scf',
        'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ws', 'ps1', 'psm1', 'psd1',
        'jar', 'apk', 'app', 'dmg', 'pkg', 'deb', 'rpm', 'appimage', 'run',
        'sh', 'bash', 'zsh', 'ksh', 'csh', 'command',
    ];

    /**
     * What `finfo` answers for an ending, beside what the ending officially is.
     *
     * A spreadsheet written as text is text to anything reading its bytes, a Word document it
     * did not look inside is a zip - and so is every OpenDocument and iWork file - and an old
     * Office file is a compound document before it is anything else. Measured, not guessed:
     * these are the answers the check would otherwise have refused.
     *
     * @var array<string, array<int, string>>
     */
    public const SNIFFED = [
        'csv' => ['text/plain'],
        'tsv' => ['text/plain'],
        'md' => ['text/plain'],
        'docx' => ['application/zip'],
        'xlsx' => ['application/zip'],
        'pptx' => ['application/zip'],
        'odt' => ['application/zip'],
        'ods' => ['application/zip'],
        'odp' => ['application/zip'],
        'pages' => ['application/zip'],
        'numbers' => ['application/zip'],
        'key' => ['application/zip'],
        'doc' => ['application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xls' => ['application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'ppt' => ['application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office'],
    ];

    /**
     * What a file with an ending nothing here knows may not turn out to be. An unknown ending
     * has no content to agree with, so the only question left is whether it would run.
     *
     * @var array<int, string>
     */
    public const ACTIVE = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        'application/javascript', 'text/javascript', 'application/x-javascript',
        'text/x-php', 'application/x-php', 'application/x-httpd-php',
        'application/x-sh', 'text/x-shellscript',
    ];

    /**
     * @param  array<string, array<int, string>>  $accept  family => normalised entries
     */
    final public function __construct(protected array $accept = []) {}

    /**
     * A family that is missing or null gets its default; an empty list or `false` switches
     * it off.
     *
     * Missing means default because a project that published its configuration before this
     * key existed has no `types` at all - and reading that as "offer nothing" would take the
     * browser away from every such project on update.
     *
     * @param  array<string, mixed>|null  $types
     */
    public static function make(?array $types = null): static
    {
        $accept = [];

        foreach (static::kindsInOrder() as $kind) {
            $value = $types[$kind] ?? null;

            $entries = match (true) {
                $value === null => static::defaults($kind),
                is_array($value) => $value,
                is_string($value) => [$value],
                default => [],
            };

            $accept[$kind] = static::normalise($kind, $entries);
        }

        return app(static::class, ['accept' => $accept]);
    }

    /**
     * What a family offers when nobody said otherwise.
     *
     * The pictures, films and sounds `MediaKinds` lists, which are the ones a browser can
     * draw or play - `image/*` would take a drawing `finfo` calls `image/vnd.dwg`, and a tile
     * for it is a broken picture. For documents, exactly the endings the card has a colour
     * for, so a card and the browser behind it never disagree about what a file is.
     *
     * @return array<int, string>
     */
    public static function defaults(string $kind): array
    {
        return match ($kind) {
            MediaKinds::IMAGE, MediaKinds::VIDEO, MediaKinds::AUDIO => array_keys(MediaKinds::TYPES[$kind]),
            MediaKinds::FILE => array_merge(...array_values(FileTypes::TINTS)),
            default => [],
        };
    }

    /**
     * The families on offer, in the order their tabs are drawn.
     *
     * @return array<int, string>
     */
    public function kinds(): array
    {
        return array_values(array_filter(static::kindsInOrder(), $this->offers(...)));
    }

    public function offers(string $kind): bool
    {
        // A document list holding nothing but refused endings offers nothing, and a tab over
        // it would be a door onto a wall.
        if ($kind === MediaKinds::FILE) {
            return ($this->fileTypes() !== []) || $this->takesAnyFile();
        }

        // What survives the deny list, rather than what was written down. A family whose
        // whole list is refused offers nothing, and the query behind its tab would be a
        // group holding no conditions at all - which a database reads as every row.
        return ($this->patternsOf($kind) !== []) || ($this->endingsOf($kind) !== []);
    }

    /**
     * The mime types and family patterns a family was given. Documents have none.
     *
     * @return array<int, string>
     */
    public function patternsOf(string $kind): array
    {
        return array_values(array_filter(
            $this->accept[$kind] ?? [],
            static fn (string $entry): bool => str_contains($entry, '/'),
        ));
    }

    /**
     * The endings a family was given by name, the refused ones left out.
     *
     * @return array<int, string>
     */
    public function endingsOf(string $kind): array
    {
        return array_values(array_filter(
            $this->accept[$kind] ?? [],
            static fn (string $entry): bool => ! str_contains($entry, '/')
                && $entry !== static::ANY
                && ! in_array($entry, static::DENIED, strict: true),
        ));
    }

    /**
     * The endings that make a document.
     *
     * @return array<int, string>
     */
    public function fileTypes(): array
    {
        return $this->endingsOf(MediaKinds::FILE);
    }

    public function takesAnyFile(): bool
    {
        return in_array(static::ANY, $this->accept[MediaKinds::FILE] ?? [], strict: true);
    }

    /**
     * Which family a file on a disk belongs to, read off its name - a listing has nothing
     * else, and asking the disk for a mime type is a request per file.
     */
    public function kindOfPath(string $path): ?string
    {
        // The same question `kindOf()` answers, with the name standing in for both halves:
        // a listing has no content to sniff, and the table says what a name of that ending
        // would have been filed under anyway.
        return $this->kindOf(MediaKinds::mimeOf($path), $path);
    }

    /**
     * Which family a row or an upload belongs to: the mime type for what is drawn, the name
     * for what is not.
     */
    public function kindOf(?string $mime, ?string $name): ?string
    {
        $ending = static::endingOf($name);

        // Asked here rather than only on the way in. A refused ending is refused wherever
        // the question comes up: listing a drawing with a script in it hands it out as
        // surely as taking one would have, and a stored id resolves through this too.
        if (in_array($ending, static::DENIED, strict: true)) {
            return null;
        }

        $mime = Str::lower((string) $mime);
        $family = MediaKinds::of($mime);

        if (($family !== null) && $this->takesDrawable($family, $mime, $ending)) {
            return $family;
        }

        // Then the family the ending names, which is not always the one the content is
        // filed under: an `.m4a` is an MP4 container and `finfo` answers `video/mp4` for
        // one, so asking the type alone refused a sound the sound list names outright.
        $named = static::drawnFamilyOf($ending);

        if (($named !== null) && ($named !== $family) && $this->takesDrawable($named, MediaKinds::TYPES[$named][$ending], $ending)) {
            return $named;
        }

        return $this->takesAsFile($ending, explicitly: $named !== null) ? MediaKinds::FILE : null;
    }

    /**
     * What a file name says it is, or an empty string. The table for what is drawn, and
     * Symfony's list - the one Laravel's own validation reads - for everything else.
     */
    public function mimeOf(string $path): string
    {
        $drawn = MediaKinds::mimeOf($path);

        if ($drawn !== '') {
            return $drawn;
        }

        $ending = static::endingOf($path);

        return $ending === '' ? '' : (MimeTypes::getDefault()->getMimeTypes($ending)[0] ?? '');
    }

    /**
     * Whether an upload may come in.
     *
     * A picture, a film or a sound is taken on what it is, the way it always was. A document
     * is taken on the ending it was sent under, and then only where its content agrees with
     * that ending - a page sent as `report.pdf` is refused here rather than served later.
     */
    public function accepts(UploadedFile $file): bool
    {
        $name = $file->getClientOriginalName();
        $mime = Str::lower((string) $file->getMimeType());

        // One decision, so what is taken in is what the grid will show it as. A picture
        // sent as `photo.svg` is refused there, since the deny list is read first.
        $kind = $this->kindOf($mime, $name);

        if ($kind === null) {
            return false;
        }

        // A document is taken on the ending it was sent under, and then only where its
        // content agrees with that ending - a page sent as `report.pdf` is refused here
        // rather than served later. What is drawn was measured on what it is.
        return ($kind !== MediaKinds::FILE) || $this->fits($mime, static::endingOf($name));
    }

    /**
     * Everything the upload widget may be handed, as one list.
     *
     * A coarse gate - the widget checks what the browser says a file is, and the server what
     * its bytes say - which is why the answers `finfo` gives are on it too. `accepts()` is
     * the fine one. Null where documents take every ending, since a star cannot be written
     * as a list of types and the ending check is then the only gate.
     *
     * @return array<int, string>|null
     */
    public function mimeTypes(): ?array
    {
        if ($this->takesAnyFile()) {
            return null;
        }

        $mimes = [];

        foreach (MediaKinds::families() as $family) {
            foreach ($this->accept[$family] ?? [] as $entry) {
                // An ending in a drawn family is checked by name later; here it only has to
                // get past a widget that asks what the file is.
                $mimes[] = str_contains($entry, '/') ? $entry : "{$family}/*";
            }
        }

        foreach ($this->fileTypes() as $ending) {
            $mimes = [...$mimes, ...MimeTypes::getDefault()->getMimeTypes($ending), ...(static::SNIFFED[$ending] ?? [])];
        }

        return array_values(array_unique($mimes));
    }

    /**
     * The ending a file is stored under.
     *
     * The one it was checked against where there is one - `finfo` calls a spreadsheet
     * written as text what it is, and storing it under a guess would turn `prices.csv` into
     * `.txt`. A picture under an ending of another family is stored under its own instead.
     */
    public function extensionFor(UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();
        $ending = static::endingOf($name);
        $kind = $this->kindOf((string) $file->getMimeType(), $name);

        if (($kind === MediaKinds::FILE) && ($ending !== '')) {
            return $ending;
        }

        if (($kind !== null) && isset(MediaKinds::TYPES[$kind][$ending])) {
            return $ending;
        }

        $guessed = Str::lower((string) $file->guessExtension());

        if ($guessed !== '') {
            return $guessed;
        }

        return ($ending !== '' && ! in_array($ending, static::DENIED, strict: true)) ? $ending : 'bin';
    }

    /**
     * Whether an ending makes a document here. `explicitly` is for a file named like a
     * picture, a film or a sound: a star does not reach those, a named ending does.
     */
    public function takesAsFile(string $ending, bool $explicitly = false): bool
    {
        if (($ending === '') || in_array($ending, static::DENIED, strict: true)) {
            return false;
        }

        $entries = $this->accept[MediaKinds::FILE] ?? [];

        if (in_array($ending, $entries, strict: true)) {
            return true;
        }

        return (! $explicitly)
            && ! in_array($ending, static::RISKY, strict: true)
            && in_array(static::ANY, $entries, strict: true);
    }

    /**
     * Whether an ending names something a browser draws or plays.
     *
     * Asked of the name rather than of the type, and that is the point: `finfo` calls a CAD
     * drawing `image/vnd.dwg`, and nothing draws one - so a star takes it as a document. A
     * `.png` its own family refused is not offered as a download instead; that takes the
     * ending being named outright.
     */
    public static function isDrawnEnding(string $ending): bool
    {
        return static::drawnFamilyOf($ending) !== null;
    }

    /**
     * The family whose table holds an ending, or null where none does.
     */
    public static function drawnFamilyOf(string $ending): ?string
    {
        foreach (MediaKinds::TYPES as $family => $extensions) {
            if (array_key_exists($ending, $extensions)) {
                return $family;
            }
        }

        return null;
    }

    /**
     * The ending of a name, lower-cased, or an empty string where it has none worth reading.
     * A query string and a fragment are taken off first, since this reads addresses too.
     */
    public static function endingOf(?string $name): string
    {
        if (! is_string($name)) {
            return '';
        }

        $ending = Str::lower(pathinfo(Str::before(Str::before($name, '#'), '?'), PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,16}$/', $ending) === 1 ? $ending : '';
    }

    protected function takesDrawable(string $family, string $mime, string $ending): bool
    {
        foreach ($this->accept[$family] ?? [] as $entry) {
            if (str_contains($entry, '/') ? static::matches($mime, $entry) : ($entry === $ending)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a document's content agrees with its ending.
     */
    protected function fits(string $mime, string $ending): bool
    {
        $known = array_map(Str::lower(...), [
            ...MimeTypes::getDefault()->getMimeTypes($ending),
            ...(static::SNIFFED[$ending] ?? []),
        ]);

        if ($known === []) {
            return ! in_array($mime, static::ACTIVE, strict: true);
        }

        return in_array($mime, $known, strict: true);
    }

    /**
     * `image/*` is as valid an entry as `image/png`, on Filament's own setter as much as here,
     * so both spellings have to mean what they say.
     */
    protected static function matches(string $mime, string $pattern): bool
    {
        return str_ends_with($pattern, '/*')
            ? str_starts_with($mime, substr($pattern, 0, -1))
            : $mime === $pattern;
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<int, string>
     */
    protected static function normalise(string $kind, array $entries): array
    {
        $normalised = [];

        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $entry = Str::lower(trim($entry));

            if ($entry === static::ANY) {
                // A star in a drawn family would be a picture list that takes a program.
                if ($kind === MediaKinds::FILE) {
                    $normalised[] = $entry;
                }

                continue;
            }

            if (str_contains($entry, '/')) {
                if (preg_match('#^[a-z0-9.+-]+/([a-z0-9.+-]+|\*)$#', $entry) !== 1) {
                    continue;
                }

                // A document is named by its ending, so a mime type given for one is turned
                // into the endings it goes by. A pattern has none, and is dropped.
                if ($kind === MediaKinds::FILE) {
                    array_push($normalised, ...MimeTypes::getDefault()->getExtensions($entry));

                    continue;
                }

                $normalised[] = $entry;

                continue;
            }

            $entry = ltrim($entry, '.');

            if (preg_match('/^[a-z0-9]{1,16}$/', $entry) === 1) {
                $normalised[] = $entry;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * @return array<int, string>
     */
    protected static function kindsInOrder(): array
    {
        return [...MediaKinds::families(), MediaKinds::FILE];
    }
}
