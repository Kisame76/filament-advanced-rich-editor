<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\FrameCover;

/**
 * A still out of a film, which is the only picture of a film that exists without asking
 * somebody to make one.
 *
 * The binary is not a dependency and may not be there at all, so most of this is about what
 * happens when it is not: no exception, nothing logged, and no second attempt.
 */
beforeEach(function (): void {
    $this->film = (string) tempnam(sys_get_temp_dir(), 'arte-film');

    file_put_contents($this->film, 'not really a film');

    $this->jpeg = "\xFF\xD8\xFF\xE0".str_repeat('x', 40);

    // The real binary writes to the file it was handed; a fake cannot, so the file is
    // written here instead and the process is only asked to succeed. The path is read off
    // the command rather than guessed at, which is also what pins the argument order.
    $this->writes = fn (string $bytes): Closure => function (PendingProcess $process) use ($bytes): mixed {
        $command = is_array($process->command) ? $process->command : [];

        file_put_contents((string) end($command), $bytes);

        return Process::result(exitCode: 0);
    };
});

afterEach(function (): void {
    @unlink($this->film);
});

it('asks ffmpeg for one frame, scaled, as a jpeg', function (): void {
    Process::fake();

    FrameCover::read($this->film, 'ffmpeg', 5);

    Process::assertRan(function (PendingProcess $process): bool {
        $parts = is_array($process->command) ? $process->command : [];
        $command = implode(' ', $parts);

        return str_contains($command, 'ffmpeg')
            // A second in, because frame zero of a great many films is black.
            && str_contains($command, '-ss')
            && str_contains($command, '-frames:v')
            && str_contains($command, FrameCover::SCALE)
            // Without this ffmpeg exits zero and writes nothing at all: the image2 muxer
            // refuses a single file unless it is told the output is one image.
            && str_contains($command, '-update')
            // And the name has to end in `.jpg`, because that is where ffmpeg reads the
            // output format from.
            && str_ends_with((string) end($parts), '.jpg');
    });
});

it('hands back what ffmpeg wrote', function (): void {
    Process::fake(($this->writes)($this->jpeg));

    expect(FrameCover::read($this->film, 'ffmpeg', 5))->toBe($this->jpeg);
});

it('says nothing when the binary is not there', function (): void {
    Process::fake(fn (): mixed => Process::result(errorOutput: 'ffmpeg: not found', exitCode: 127));

    expect(FrameCover::read($this->film, 'ffmpeg', 5))->toBeNull();
});

it('says nothing when ffmpeg succeeds but writes nothing', function (): void {
    // A container it cannot decode: exit code zero, an empty file, and a tile that would be
    // a zero-byte JPEG if this did not check.
    Process::fake(fn (): mixed => Process::result(exitCode: 0));

    expect(FrameCover::read($this->film, 'ffmpeg', 5))->toBeNull();
});

it('leaves nothing behind in the temporary directory', function (): void {
    Process::fake(fn (): mixed => Process::result(exitCode: 1));

    $before = count(glob(sys_get_temp_dir().'/arte-cover*') ?: []);

    FrameCover::read($this->film, 'ffmpeg', 5);

    expect(count(glob(sys_get_temp_dir().'/arte-cover*') ?: []))->toBe($before);
});

it('says nothing about a file that is not there', function (): void {
    Process::fake();

    expect(FrameCover::read('/no/such/film.mp4', 'ffmpeg', 5))->toBeNull();

    Process::assertNothingRan();
});
