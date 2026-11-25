<?php

declare(strict_types=1);

use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Export\Events\DocumentExported;
use Kisame76\FilamentAdvancedRichEditor\Export\Jobs\ExportDocument;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\User;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Pdf\PostExport;

/**
 * An export run on a queue worker: defined once as data, built into a PDF or a Word file on
 * the other side, written to a disk, and the person who asked for it told where it is.
 */
beforeEach(function (): void {
    $this->post = RichPost::create(['title' => 'Quarterly report', 'content' => '<p>Figures.</p>']);
    $this->user = User::create(['name' => 'Ada']);

    Storage::fake('exports');
    // Not static: Laravel binds the callback to the disk.
    Storage::disk('exports')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration): string => "https://signed.example.com/{$path}?expires={$expiration->getTimestamp()}",
    );
});

/**
 * The Filament notification `$user` was sent, as the array it is stored as.
 *
 * @return array<string, mixed>
 */
function notificationFor(User $user): array
{
    $sent = Notification::sent($user, DatabaseNotification::class);

    expect($sent)->toHaveCount(1);

    return $sent->first()->data;
}

describe('dispatching', function (): void {
    it('goes onto the queue the application is configured with', function (): void {
        Queue::fake();

        (new PostExport($this->post))->queue('exports/report.pdf', 'exports');

        Queue::assertPushed(ExportDocument::class, fn (ExportDocument $job): bool => ($job->path === 'exports/report.pdf')
            && ($job->disk === 'exports')
            // Null is Laravel's own default: the connection QUEUE_CONNECTION names.
            && ($job->connection === null)
            && ($job->queue === null)
            && ($job->timeout === null));
    });

    it('runs where the environment says - at once on sync, nowhere on a connection that drops jobs', function (): void {
        config()->set('queue.default', 'null');
        (new PostExport($this->post))->queue('exports/dropped.pdf', 'exports');

        config()->set('queue.default', 'sync');
        (new PostExport($this->post))->queue('exports/printed.pdf', 'exports');

        Storage::disk('exports')->assertMissing('exports/dropped.pdf');
        Storage::disk('exports')->assertExists('exports/printed.pdf');
    });

    it('sends exports to a connection and a queue of their own where the configuration names them', function (): void {
        Queue::fake();
        config()->set('filament-advanced-rich-editor.pdf.queue', ['connection' => 'redis', 'queue' => 'pdf', 'timeout' => 600, 'disk' => 'exports']);

        (new PostExport($this->post))->queue('exports/report.pdf');

        Queue::assertPushedOn('pdf', ExportDocument::class, fn (ExportDocument $job): bool => ($job->connection === 'redis')
            && ($job->timeout === 600)
            && ($job->disk === 'exports'));
    });

    it('takes a queue for one dispatch like any other job', function (): void {
        Queue::fake();

        (new PostExport($this->post))->queue('exports/report.pdf', 'exports')->onQueue('slow');

        Queue::assertPushedOn('slow', ExportDocument::class);
    });

    it('writes to the application\'s default disk where nothing names one', function (): void {
        Queue::fake();
        config()->set('filesystems.default', 'exports');

        (new PostExport($this->post))->queue('exports/report.pdf');

        Queue::assertPushed(ExportDocument::class, fn (ExportDocument $job): bool => $job->disk === 'exports');
    });

    it('carries the records as ids and reads them afresh on the worker', function (): void {
        $job = new ExportDocument(new PostExport($this->post), 'exports/report.pdf', 'exports', $this->user);
        $payload = serialize($job);

        $this->post->update(['title' => 'Quarterly report, revised']);

        $restored = unserialize($payload);

        expect($payload)->not->toContain('Figures.')
            ->and($restored->export->post->title)->toBe('Quarterly report, revised')
            ->and($restored->notifiable->is($this->user))->toBeTrue();
    });
});

describe('on the worker', function (): void {
    beforeEach(function (): void {
        Notification::fake();
        Event::fake([DocumentExported::class]);
        Carbon::setTestNow('2026-11-24 10:00:00');
    });

    afterEach(function (): void {
        Carbon::setTestNow();
    });

    it('writes the PDF to the disk and links to it in the notification', function (): void {
        $job = new ExportDocument(new PostExport($this->post), 'exports/report.pdf', 'exports', $this->user);
        $job->handle();

        Storage::disk('exports')->assertExists('exports/report.pdf');
        Event::assertDispatched(DocumentExported::class, fn (DocumentExported $event): bool => ($event->path === 'exports/report.pdf') && ($event->disk === 'exports'));

        $notification = notificationFor($this->user);

        expect($notification['title'])->toBe('Your PDF is ready')
            ->and($notification['body'])->toBe('report.pdf')
            ->and($notification['status'])->toBe('success')
            // A signed link that lapses after a day, the default lifetime.
            ->and($notification['actions'][0]['url'])->toBe('https://signed.example.com/exports/report.pdf?expires='.now()->addDay()->getTimestamp())
            ->and($notification['actions'][0]['label'])->toBe('Download');
    });

    it('prints in the locale of the request it was queued from, not the worker\'s', function (): void {
        app()->setLocale('de');
        $job = new ExportDocument(new PostExport($this->post), 'exports/report.pdf', 'exports', $this->user);
        app()->setLocale('en');

        $job->handle();

        expect(Storage::disk('exports')->get('exports/report.pdf'))
            ->toContain('<html lang="de">')
            ->toContain('24. November 2026')
            ->and(notificationFor($this->user)['title'])->toBe('Deine PDF ist fertig')
            ->and(app()->getLocale())->toBe('en');
    });

    it('links a disk with an address of its own plainly, and leaves the link out where a disk has none', function (): void {
        config()->set('filesystems.disks.cdn', ['driver' => 'local', 'root' => sys_get_temp_dir().'/arte-cdn', 'url' => 'https://cdn.example.com/files']);
        config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => sys_get_temp_dir().'/arte-vault']);

        $export = new PostExport($this->post);

        expect($export->downloadUrl('exports/report.pdf', 'cdn'))->toBe('https://cdn.example.com/files/exports/report.pdf')
            ->and($export->downloadUrl('exports/report.pdf', 'vault'))->toBeNull();

        (new ExportDocument($export, 'exports/report.pdf', 'vault', $this->user))->handle();

        expect(notificationFor($this->user)['actions'])->toBe([]);
    });

    it('links where the export says, for a route of the project\'s', function (): void {
        (new ExportDocument(new PostExport($this->post, link: 'https://app.example.com/exports/7'), 'exports/report.pdf', 'exports', $this->user))->handle();

        expect(notificationFor($this->user)['actions'][0]['url'])->toBe('https://app.example.com/exports/7');
    });

    it('tells nobody where nobody asked', function (): void {
        (new ExportDocument(new PostExport($this->post), 'exports/report.pdf', 'exports'))->handle();

        Storage::disk('exports')->assertExists('exports/report.pdf');
        Notification::assertNothingSent();
    });

    it('says so when the export has failed for good', function (): void {
        app()->setLocale('de');
        $job = new ExportDocument(new PostExport($this->post), 'exports/report.pdf', 'exports', $this->user);
        app()->setLocale('en');

        $job->failed(new RuntimeException('Gotenberg is down'));

        $notification = notificationFor($this->user);

        expect($notification['title'])->toBe('Deine PDF konnte nicht erstellt werden')
            ->and($notification['status'])->toBe('danger')
            ->and($notification['body'])->toBe('report.pdf');
    });
});

describe('as a Word file', function (): void {
    beforeEach(function (): void {
        requires('phpoffice/phpword');
        Notification::fake();
    });

    it('writes the Word file the export builds, and says so in Word\'s terms', function (): void {
        (new ExportDocument(new PostExport($this->post, format: 'docx'), 'exports/report.docx', 'exports', $this->user))->handle();

        expect(substr((string) Storage::disk('exports')->get('exports/report.docx'), 0, 2))->toBe('PK')
            ->and(notificationFor($this->user)['title'])->toBe('Your Word file is ready')
            ->and(notificationFor($this->user)['body'])->toBe('report.docx');
    });

    it('says a Word file failed as a Word file, in the request\'s language', function (): void {
        app()->setLocale('de');
        $job = new ExportDocument(new PostExport($this->post, format: 'docx'), 'exports/report.docx', 'exports', $this->user);
        app()->setLocale('en');

        $job->failed(new RuntimeException('Disk full'));

        expect(notificationFor($this->user)['title'])->toBe('Deine Word-Datei konnte nicht erstellt werden');
    });
});

describe('now', function (): void {
    it('prints the same export in the request as well', function (): void {
        $export = new PostExport($this->post);

        expect($export->toString())->toContain('Quarterly report')
            ->and($export->save('exports/now.pdf', 'exports'))->toBeTrue()
            ->and($export->download('report.pdf')->headers->get('Content-Type'))->toBe('application/pdf');

        Storage::disk('exports')->assertExists('exports/now.pdf');
    });
});
