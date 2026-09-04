<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;

/**
 * How many covers one listing is allowed to make, and how each kind of file is asked for one.
 *
 * The budget is the whole reason this is an object rather than three static calls. A library
 * of forty films with no covers would be forty ffmpeg processes inside one Livewire request -
 * a dialog that takes a minute to open, the first time, for a person who only wanted to pick
 * a picture. A handful per opening means the library fills itself in over a few visits and
 * nothing ever stalls.
 *
 * One instance per listing request, made by the source at the top of `page()`. It is
 * deliberately not a singleton: a budget that survived between requests would be a budget
 * that is already spent.
 */
class CoverGenerator
{
    /**
     * The name of the conversion the Spatie source writes. Written by this package rather
     * than by Spatie - a package cannot register a conversion on somebody else's model - and
     * marked as generated, which is what makes Spatie serve it.
     */
    public const CONVERSION = 'arte-cover';

    /** Where a failure is remembered on a media row. */
    public const ATTEMPTED_PROPERTY = 'arte_cover_attempted';

    /** ...and in a sidecar. */
    public const ATTEMPTED_KEY = 'cover_attempted';

    protected int $budget;

    final public function __construct(?int $budget = null)
    {
        $this->budget = $budget ?? max(0, (int) config('filament-advanced-rich-editor.media_library.covers.per_page', 3));
    }

    public static function make(?int $budget = null): static
    {
        return new static($budget);
    }

    public static function enabled(): bool
    {
        return (bool) (config('filament-advanced-rich-editor.media_library.covers.enabled') ?? true);
    }

    /**
     * Whether there is budget left. Asked before anything is read off the disk, so a listing
     * whose budget is spent does not even stream a file to a temporary path.
     */
    public function mayGenerate(): bool
    {
        return static::enabled() && $this->budget > 0;
    }

    /**
     * A cover for one file, or null.
     *
     * Spends a unit of the budget whether or not it succeeds: the cost being rationed is the
     * work, and a file that takes five seconds to fail took five seconds.
     */
    public function bytes(string $kind, string $localPath): ?string
    {
        if (! $this->mayGenerate()) {
            return null;
        }

        $this->budget--;

        return match ($kind) {
            MediaKinds::VIDEO => FrameCover::read(
                $localPath,
                (string) (config('filament-advanced-rich-editor.media_library.covers.ffmpeg') ?? 'ffmpeg'),
                static::timeout(),
            ),
            MediaKinds::AUDIO => static::fromTag($localPath),
            // A picture is its own thumbnail, and nothing else has a cover to find.
            default => null,
        };
    }

    /**
     * A cover for an embed, spending the same budget a file spends - the work being rationed
     * is a request over the network rather than a process, and neither belongs forty at a
     * time in one Livewire request.
     *
     * @param  array{provider: string, id: string, start: int|null, title: string|null, ratio: string}  $embed
     */
    public function embed(array $embed): ?string
    {
        if (! $this->mayGenerate()) {
            return null;
        }

        $this->budget--;

        return EmbedCover::bytes($embed, static::timeout());
    }

    public static function timeout(): int
    {
        return (int) (config('filament-advanced-rich-editor.media_library.covers.timeout') ?? 5);
    }

    protected static function fromTag(string $localPath): ?string
    {
        $handle = @fopen($localPath, 'rb');

        if (! is_resource($handle)) {
            return null;
        }

        // Only the front of the file: a tag is at the front by definition, and reading a
        // hundred-megabyte podcast into memory to find out it has no picture would be the
        // expensive way to learn nothing.
        $header = @stream_get_contents($handle, Id3Cover::HEADER_BYTES);

        @fclose($handle);

        if (! is_string($header) || $header === '') {
            return null;
        }

        return Id3Cover::read($header)['bytes'] ?? null;
    }
}
