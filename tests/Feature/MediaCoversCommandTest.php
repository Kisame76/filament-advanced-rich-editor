<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\CoverGenerator;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Sidecar;

/**
 * The two things that cannot be done from a dialog: forgetting every failure at once, after
 * ffmpeg has finally been installed, and clearing the companions of files that are gone.
 */
beforeEach(function (): void {
    Storage::fake('public');

    config()->set('filament-advanced-rich-editor.media_library.directory', 'library');
});

it('forgets every remembered failure so the next listing tries again', function (): void {
    Storage::disk('public')->put('library/talk.mp4', 'x');
    Sidecar::write(Storage::disk('public'), 'library/talk.mp4', [
        CoverGenerator::ATTEMPTED_KEY => true,
        'alt' => 'kept',
    ]);

    $this->artisan('arte:media-covers', ['--retry' => true, '--disk' => 'public'])
        ->assertSuccessful();

    $sidecar = Sidecar::read(Storage::disk('public'), 'library/talk.mp4');

    expect($sidecar)->not->toHaveKey(CoverGenerator::ATTEMPTED_KEY)
        // Only the marker. The description beside it is somebody's work.
        ->and($sidecar['alt'])->toBe('kept');
});

it('removes a companion whose file is gone', function (): void {
    Storage::disk('public')->put('library/talk.mp4.cover.jpg', 'x');
    Storage::disk('public')->put('library/talk.mp4.json', '{"alt":"orphan"}');
    Storage::disk('public')->put('library/kept.mp3', 'x');
    Storage::disk('public')->put('library/kept.mp3.json', '{"alt":"kept"}');

    $this->artisan('arte:media-covers', ['--prune' => true, '--disk' => 'public'])
        ->assertSuccessful();

    expect(Storage::disk('public')->exists('library/talk.mp4.cover.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('library/talk.mp4.json'))->toBeFalse()
        ->and(Storage::disk('public')->exists('library/kept.mp3.json'))->toBeTrue();
});

it('keeps a cover whose file is still there', function (): void {
    Storage::disk('public')->put('library/kept.mp3', 'x');
    Storage::disk('public')->put('library/kept.mp3.cover.jpg', 'x');

    $this->artisan('arte:media-covers', ['--prune' => true, '--disk' => 'public'])
        ->assertSuccessful();

    expect(Storage::disk('public')->exists('library/kept.mp3.cover.jpg'))->toBeTrue();
});

it('does nothing at all unless it is told which of the two to do', function (): void {
    Storage::disk('public')->put('library/talk.mp4', 'x');
    Storage::disk('public')->put('library/talk.mp4.json', '{"cover_attempted":true}');

    $this->artisan('arte:media-covers', ['--disk' => 'public'])
        ->expectsOutputToContain('--retry')
        ->assertFailed();

    expect(Sidecar::read(Storage::disk('public'), 'library/talk.mp4')[CoverGenerator::ATTEMPTED_KEY])
        ->toBeTrue();
});

it('says which directory it has nothing to look at', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.directory', null);

    $this->artisan('arte:media-covers', ['--retry' => true, '--disk' => 'public'])
        ->expectsOutputToContain('--directory')
        ->assertFailed();
});
