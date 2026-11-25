<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Export\Jobs;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Traits\Localizable;
use Kisame76\FilamentAdvancedRichEditor\Export\DocumentExport;
use Kisame76\FilamentAdvancedRichEditor\Export\Events\DocumentExported;
use RuntimeException;
use Throwable;

/**
 * Writes a `DocumentExport` - a PDF or a Word file - on a queue worker, to a disk.
 *
 * Dispatched by `DocumentExport::queue()`, onto whatever queue the application is configured
 * with - `QUEUE_CONNECTION` unless `pdf.queue.connection` names another. Everything it
 * carries is data: the export, which brings its models as ids, the path, the disk, the
 * person to tell, and the locale the request had, which the worker does not.
 */
class ExportDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Localizable;
    use Queueable;
    use SerializesModels;

    public string $disk;

    /**
     * The request's locale. A worker runs in the application's default, and the export's
     * language, its dates and the notification would all come out in that.
     */
    public string $locale;

    /**
     * Seconds a worker may spend on it, from `pdf.queue.timeout`; null leaves it to the
     * worker's own `--timeout`.
     */
    public ?int $timeout = null;

    public function __construct(
        public DocumentExport $export,
        public string $path,
        ?string $disk = null,
        public ?Model $notifiable = null,
    ) {
        $config = (array) config('filament-advanced-rich-editor.pdf.queue', []);

        $this->disk = $disk ?? (filled($config['disk'] ?? null) ? (string) $config['disk'] : (string) config('filesystems.default'));
        $this->locale = app()->getLocale();
        $this->timeout = filled($config['timeout'] ?? null) ? (int) $config['timeout'] : null;

        // Null is the application's own default - the connection `QUEUE_CONNECTION` names and
        // that connection's default queue.
        $this->onConnection(filled($config['connection'] ?? null) ? (string) $config['connection'] : null);
        $this->onQueue(filled($config['queue'] ?? null) ? (string) $config['queue'] : null);
    }

    public function handle(): void
    {
        $this->withLocale($this->locale, function (): void {
            if (! $this->export->document()->save($this->path, $this->disk)) {
                throw new RuntimeException("The file could not be written to [{$this->path}] on the [{$this->disk}] disk.");
            }

            DocumentExported::dispatch($this->export, $this->path, $this->disk);

            if ($this->notifiable === null) {
                return;
            }

            $url = $this->export->downloadUrl($this->path, $this->disk);

            Notification::make()
                ->success()
                ->title(__($this->messageKey('ready')))
                ->body(basename($this->path))
                ->actions(($url === null) ? [] : [
                    Action::make('download')
                        ->label(__('filament-advanced-rich-editor::advanced-rich-editor.pdf.export.download'))
                        ->url($url, shouldOpenInNewTab: true)
                        ->markAsRead(),
                ])
                ->sendToDatabase($this->notifiable, isEventDispatched: true);
        });
    }

    /**
     * Called by the queue once the export has failed for good - after its last try.
     */
    public function failed(?Throwable $exception = null): void
    {
        if ($this->notifiable === null) {
            return;
        }

        $this->withLocale($this->locale, function (): void {
            Notification::make()
                ->danger()
                ->title(__($this->messageKey('failed')))
                ->body(basename($this->path))
                ->sendToDatabase($this->notifiable, isEventDispatched: true);
        });
    }

    /**
     * What the notification says, by the file it is about: a Word file is not a PDF. Read off
     * the path rather than the document, which a failed export may never have finished.
     */
    protected function messageKey(string $message): string
    {
        $format = (strtolower(pathinfo($this->path, PATHINFO_EXTENSION)) === 'docx') ? 'docx' : 'pdf';

        return "filament-advanced-rich-editor::advanced-rich-editor.{$format}.export.{$message}";
    }
}
