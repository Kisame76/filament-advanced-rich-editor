<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use finfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Throwable;

/**
 * Turns every picture in a document into a `data:` address before a PDF engine sees it.
 *
 * Fetching is what a PDF engine does with an address, and a document is something somebody
 * typed: an `<img src="http://169.254.169.254/…">` in a remote-enabled DomPDF is a request
 * from the server, to wherever the document says. So the engine fetches nothing, and this
 * class does the reading instead, in an order that needs no network for the pictures a
 * document normally holds:
 *
 *  1. an attachment, by its id, through the renderer - the disk or the provider that would
 *     have written its address. Private files included, and no signed URL that a queued
 *     export could reach after it expired;
 *  2. an address under one of the application's own disks, read from that disk;
 *  3. an address on a host the project named, fetched - without following redirects, which
 *     would take the request anywhere the host pointed it.
 *
 * Anything else is not drawn. `PdfMarkup` puts the picture's description in its place, so
 * the gap is visible rather than silent.
 */
class PdfImages
{
    /**
     * The types a PDF engine draws. Everything a disk might hold under an image name and the
     * engines cannot read - a HEIC, a TIFF, a Photoshop file - is left out rather than
     * handed over to become a broken box.
     */
    public const TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];

    /**
     * @param  array<int, string>  $hosts
     */
    public function __construct(
        protected ?AdvancedRichContentRenderer $renderer = null,
        protected ?string $baseUrl = null,
        protected array $hosts = [],
        protected int $maxBytes = 10_485_760,
        protected int $timeout = 10,
    ) {}

    /**
     * The picture as a `data:` address, or null where it cannot be had.
     */
    public function embed(?string $src, ?string $attachment = null): ?string
    {
        $src = trim((string) $src);

        if (str_starts_with(strtolower($src), 'data:')) {
            return $this->isInlinePicture($src) ? $src : null;
        }

        $contents = null;

        if (filled($attachment) && ($this->renderer !== null)) {
            $contents = $this->renderer->getFileAttachmentContents($attachment);
        }

        if (($contents === null) && ($src !== '')) {
            $url = $this->absolute($src);

            $contents = ($url === null) ? null : ($this->fromDisk($url) ?? $this->fromHost($url));
        }

        return ($contents === null) ? null : $this->dataUri($contents);
    }

    /**
     * An address as an absolute URL against the document's base, or null where it is not one
     * this class will read at all - a `file:` path, a `javascript:` address.
     */
    public function absolute(string $src): ?string
    {
        if (str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $src) === 1) {
            return preg_match('/^https?:\/\//i', $src) === 1 ? $src : null;
        }

        $base = rtrim((string) ($this->baseUrl ?? config('app.url')), '/');

        if ($base === '') {
            return null;
        }

        return str_starts_with($src, '/')
            ? $base.$src
            : $base.'/'.$src;
    }

    /**
     * The file behind an address that one of the application's public disks hands out, read
     * from that disk.
     *
     * Public ones only. The path comes from the document, and on a public disk it names a
     * file anybody could have downloaded from that address anyway; on a private one it would
     * be a way round whatever decides who may see a file there. Private pictures arrive by
     * attachment id, through the provider that makes that decision.
     */
    protected function fromDisk(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $origin = $this->origin($url);

        foreach ((array) config('filesystems.disks', []) as $name => $disk) {
            $diskUrl = is_array($disk) ? ($disk['url'] ?? null) : null;

            if (! is_string($diskUrl) || ($diskUrl === '') || (($disk['visibility'] ?? null) !== 'public')) {
                continue;
            }

            $diskUrl = (string) $this->absolute($diskUrl);
            $prefix = rtrim((string) parse_url($diskUrl, PHP_URL_PATH), '/').'/';

            if (($this->origin($diskUrl) !== $origin) || ! str_starts_with($path, $prefix)) {
                continue;
            }

            $file = rawurldecode(substr($path, strlen($prefix)));

            try {
                $storage = Storage::disk((string) $name);

                if (! $storage->exists($file) || ($storage->size($file) > $this->maxBytes)) {
                    continue;
                }

                return $storage->get($file);
            } catch (Throwable) {
                // A path climbing out of the disk's root is refused by Flysystem, and a disk
                // that cannot be reached is no reason to fail the whole export.
                continue;
            }
        }

        return null;
    }

    protected function fromHost(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (($host === '') || ! in_array($host, array_map('strtolower', $this->hosts), strict: true)) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withoutRedirecting()
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return (strlen($body) > $this->maxBytes) ? null : $body;
    }

    protected function dataUri(string $contents): ?string
    {
        if (($contents === '') || (strlen($contents) > $this->maxBytes)) {
            return null;
        }

        $type = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        // `finfo` files a plain SVG as text when it has no XML declaration in front.
        if (in_array($type, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], strict: true) && Str::contains(substr($contents, 0, 1024), '<svg')) {
            $type = 'image/svg+xml';
        }

        if (! in_array($type, self::TYPES, strict: true)) {
            return null;
        }

        return 'data:'.$type.';base64,'.base64_encode($contents);
    }

    protected function isInlinePicture(string $src): bool
    {
        return preg_match('#^data:(image/(?:png|jpeg|gif|webp|bmp|svg\+xml))[;,]#i', $src) === 1;
    }

    protected function origin(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.(($port === null) ? '' : ':'.$port);
    }
}
