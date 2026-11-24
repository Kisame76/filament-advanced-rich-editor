<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Pdf\Jobs\ExportPdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An export a project defines once and runs either way: now, in the request, or later, on
 * a queue worker.
 *
 *     class ArticlesPdf extends PdfExport
 *     {
 *         public function __construct(public array $ids) {}
 *
 *         public function pdf(): RichEditorPdf
 *         {
 *             return RichEditorPdf::combine(Article::findMany($this->ids)->map(...))->tableOfContents();
 *         }
 *     }
 *
 *     (new ArticlesPdf($ids))->download();                                   // now
 *     (new ArticlesPdf($ids))->queue('exports/articles.pdf', notify: $user); // on the queue
 *
 * Why a class and not the `RichEditorPdf` itself: a renderer holds closures and objects - its
 * node processors, merge tags that are closures, mention providers - and none of it survives
 * a trip through the queue. What does is data. So the export carries data, the record ids,
 * the attribute, anything its constructor takes, and builds the PDF from it on the worker.
 * Models among its properties travel as their ids and are read afresh there, the way a
 * Laravel mailable's do.
 */
abstract class PdfExport
{
    use SerializesModels;

    /**
     * The PDF, built from what this export carries. Runs on the worker for a queued export,
     * in the locale the export was queued in.
     */
    abstract public function pdf(): RichEditorPdf;

    /**
     * Prints in the background and writes the PDF to a disk - the application's default
     * where none is named, or the one the configuration names for exports.
     *
     * It goes onto the queue the application is configured with: `QUEUE_CONNECTION`, so
     * redis, database, SQS or sync, whatever the environment says. `pdf.queue.connection`
     * and `pdf.queue.queue` send exports elsewhere, and the dispatch that comes back takes
     * `onConnection()`, `onQueue()` and `delay()` as any other does.
     *
     * Whoever is given as `$notify` gets a Filament database notification when the PDF is
     * written, with a link to it, and one if it could not be.
     */
    public function queue(string $path, ?string $disk = null, ?Model $notify = null): PendingDispatch
    {
        return ExportPdf::dispatch($this, $path, $disk, $notify);
    }

    public function save(string $path, ?string $disk = null): bool
    {
        return $this->pdf()->save($path, $disk);
    }

    public function download(?string $filename = null): StreamedResponse
    {
        return $this->pdf()->download($filename);
    }

    public function toString(): string
    {
        return $this->pdf()->toString();
    }

    /**
     * Where the notification links to once a queued export is written, or null for no link.
     *
     * A disk that signs its links - S3, or a local one with `serve` - gives a link that lapses
     * after `pdf.queue.link_lifetime` minutes; a disk with an address of its own, such as
     * `public`, its plain URL. Any other disk is private and has no address a browser can
     * reach: override this method to point at a route of the project's that serves the file.
     */
    public function downloadUrl(string $path, string $disk): ?string
    {
        $filesystem = Storage::disk($disk);

        if ($filesystem->providesTemporaryUrls()) {
            return $filesystem->temporaryUrl($path, now()->addMinutes((int) config('filament-advanced-rich-editor.pdf.queue.link_lifetime', 1440)));
        }

        if (filled(config("filesystems.disks.{$disk}.url"))) {
            return $filesystem->url($path);
        }

        return null;
    }
}
