<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Sidecar;

/**
 * The two things a dialog cannot do to a library of covers.
 *
 * A cover that could not be made is remembered so nothing tries again on every listing for
 * ever - which is right until the day somebody installs ffmpeg, and then it is a library
 * that will never show a still no matter how many times it is opened. `--retry` is the
 * answer to that day.
 *
 * `--prune` is the other direction: a file deleted outside this package leaves its cover and
 * its description behind, and nothing in the browser will ever mention them again because
 * the browser lists media rather than companions.
 *
 * The disk source only. A media collection needs neither: Spatie deletes a row's conversions
 * with the row, and a marker on a row goes when the row does.
 */
class MediaCoversCommand extends Command
{
    protected $signature = 'arte:media-covers
        {--retry : Forget every remembered failure, so the next listing tries again}
        {--prune : Delete covers and descriptions whose file is gone}
        {--disk= : The disk to work on, default the one the editor uploads to}
        {--directory= : The library directory, default the configured one}';

    protected $description = 'Maintain the covers and descriptions the media browser keeps beside your files.';

    public function handle(): int
    {
        if (! $this->option('retry') && ! $this->option('prune')) {
            $this->error('Nothing to do. Pass --retry to forget remembered failures, or --prune to clear orphans.');

            return static::FAILURE;
        }

        $directory = (string) ($this->option('directory')
            ?? config('filament-advanced-rich-editor.media_library.directory')
            ?? '');

        if (blank($directory)) {
            $this->error('No library directory is configured. Pass --directory, or set media_library.directory.');

            return static::FAILURE;
        }

        $disk = Storage::disk($this->option('disk') ?: config('filament-advanced-rich-editor.spatie.disk'));

        $retried = 0;
        $pruned = 0;

        foreach ($disk->allFiles(trim($directory, '/')) as $path) {
            $name = basename($path);

            // An embed entry is a library entry rather than a companion, even though it is
            // a JSON document: it describes nothing beside it, it IS the thing. Spelled out
            // rather than taken from `Embeds::SUFFIX`, which does not exist yet - it arrives
            // with the embed listing, and this guard has to be here before it does or a
            // prune would delete every embed in the library.
            if (str_ends_with($name, '.embed.json')) {
                continue;
            }

            if (str_contains($name, '.cover.') || str_ends_with($name, '.json')) {
                if (! $this->option('prune')) {
                    continue;
                }

                // The medium a companion belongs to is its own name with the suffix taken
                // off. Gone means the companion is describing nothing.
                $medium = str_contains($name, '.cover.')
                    ? substr($path, 0, (int) strrpos($path, '.cover.'))
                    : substr($path, 0, -strlen('.json'));

                if (! $disk->exists($medium)) {
                    $disk->delete($path);
                    $pruned++;
                }

                continue;
            }

            if (! $this->option('retry') || MediaKinds::ofPath($path) === null) {
                continue;
            }

            $sidecar = Sidecar::read($disk, $path);

            if (! ($sidecar[CoverGenerator::ATTEMPTED_KEY] ?? false)) {
                continue;
            }

            // Only the marker. Whatever else the sidecar holds is somebody's work.
            Sidecar::write($disk, $path, [CoverGenerator::ATTEMPTED_KEY => null]);
            $retried++;
        }

        if ($this->option('retry')) {
            $this->info("Forgot {$retried} remembered failure(s). The next listing will try again, a few at a time.");
        }

        if ($this->option('prune')) {
            $this->info("Deleted {$pruned} orphaned companion(s).");
        }

        return static::SUCCESS;
    }
}
