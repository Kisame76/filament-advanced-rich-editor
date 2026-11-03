<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

use DOMDocument;
use DOMXPath;
use finfo;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\LinkPreview;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use ValueError;

/**
 * What a page says about itself, read once for a card that links to it.
 *
 * The title, the description and the site's name come from the page's head: Open Graph first,
 * which is written to be shown on a card, then the Twitter tags, then `<title>` and the meta
 * description. The icon comes from the page's `<link rel="icon">`, or `/favicon.ico` where it
 * names none, and is kept only where it is a small picture - see `Nodes/LinkPreview`.
 *
 * Asked only of the public internet and pinned to the address that was checked, the way
 * `LinkCheck::guarded()` asks: it is somebody in the panel who types the address, and the
 * answer is shown to them. A redirect is followed - a page moving from `http` to `https` is the
 * commonest answer there is - but each step is checked again, since a redirect is the server at
 * the other end choosing where the next request goes.
 */
class LinkPreviews
{
    /** The most of a page that is downloaded. The head is at the top. */
    public const PAGE_BYTES = 2_097_152;

    /** How many redirects are followed before giving up. */
    public const REDIRECTS = 5;

    final public function __construct() {}

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The card's parts for an address, or null where the page could not be read.
     *
     * @return array{href: string, title: string|null, description: string|null, site: string|null, icon: string|null}|null
     */
    public function read(string $href): ?array
    {
        $href = LinkPreview::href($href);
        $page = ($href === null) ? null : $this->get($href, 'text/html,application/xhtml+xml', static::PAGE_BYTES, partial: true);

        if ($page === null) {
            return null;
        }

        [$url, $response] = $page;

        if (! str_contains(strtolower($response->header('Content-Type')), 'html')) {
            return null;
        }

        $document = $this->parse($response);

        if ($document === null) {
            return null;
        }

        $read = static fn (string $query): ?string => static::first($document, $query);

        return [
            'href' => (string) $href,
            'title' => LinkPreview::text(
                $read('//meta[@property="og:title" or @name="og:title"]/@content')
                    ?? $read('//meta[@name="twitter:title" or @property="twitter:title"]/@content')
                    ?? $read('//title'),
                LinkPreview::TITLE_LENGTH,
            ),
            'description' => LinkPreview::text(
                $read('//meta[@property="og:description" or @name="og:description"]/@content')
                    ?? $read('//meta[@name="twitter:description" or @property="twitter:description"]/@content')
                    ?? $read('//meta[@name="description"]/@content'),
                LinkPreview::DESCRIPTION_LENGTH,
            ),
            'site' => LinkPreview::text($read('//meta[@property="og:site_name" or @name="og:site_name"]/@content'), LinkPreview::SITE_LENGTH),
            'icon' => $this->icon($url, $read('//link[contains(concat(" ", normalize-space(translate(@rel, "ICON", "icon")), " "), " icon ")]/@href')),
        ];
    }

    /**
     * The site's icon as a `data:` address, or null.
     */
    protected function icon(string $page, ?string $declared): ?string
    {
        // An icon written into the page as a `data:` address is already what a card keeps.
        if (($declared !== null) && str_starts_with(strtolower($declared), 'data:')) {
            return LinkPreview::icon($declared);
        }

        $url = (string) UriResolver::resolve(new Uri($page), new Uri($declared ?? '/favicon.ico'));

        // Three bytes of base64 for every four of the address it is stored as.
        $answer = $this->get($url, 'image/*', intdiv(LinkPreview::ICON_LENGTH, 4) * 3);

        if ($answer === null) {
            return null;
        }

        $bytes = $answer[1]->body();
        $type = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return is_string($type) ? LinkPreview::icon('data:'.$type.';base64,'.base64_encode($bytes)) : null;
    }

    /**
     * The address the answer came from and the answer, or null where there was none worth
     * reading: an address that is not on the public internet, an error, or more than `$bytes`.
     *
     * `$partial` keeps the first `$bytes` of a larger answer instead: a page says what it is
     * in its head, at the top, and a long page is no reason to have nothing to say about it.
     * A picture cut short is no picture, so an icon is all or nothing.
     *
     * @return array{0: string, 1: Response}|null
     */
    protected function get(string $url, string $accept, int $bytes, bool $partial = false): ?array
    {
        for ($redirects = 0; $redirects <= static::REDIRECTS; $redirects++) {
            $pin = (LinkPreview::href($url) === null) ? null : PublicHosts::make()->pin($url);

            if (! is_string($pin)) {
                return null;
            }

            // The body goes somewhere it can still be read after the transfer is stopped, and
            // the status line and headers are kept as they arrive, since a stopped transfer
            // hands back no response of its own.
            $sink = fopen('php://temp', 'w+b');
            $head = null;
            $cut = false;

            try {
                $response = Http::withUserAgent(LinkCheck::userAgent())
                    ->accept($accept)
                    ->connectTimeout(LinkCheck::timeout())
                    ->timeout(LinkCheck::timeout())
                    ->sink($sink)
                    ->withOptions([
                        'allow_redirects' => false,
                        'on_headers' => static function (ResponseInterface $answer) use (&$head): void {
                            $head = $answer;
                        },
                        // Stopped once it is larger than anything worth reading, rather than
                        // downloaded to the end.
                        'progress' => static function (mixed $total, mixed $done) use ($bytes, &$cut): void {
                            if ($done > $bytes) {
                                $cut = true;

                                throw new RuntimeException('Larger than a card reads.');
                            }
                        },
                        ...PublicHosts::options($pin),
                    ])
                    ->get($url);
            } catch (Throwable) {
                $response = ($partial && $cut) ? static::cutShort($head, $sink, $bytes) : null;

                if ($response === null) {
                    return null;
                }
            }

            if ($response->redirect() && filled($response->header('Location'))) {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));

                continue;
            }

            return ($response->successful() && (strlen($response->body()) <= $bytes)) ? [$url, $response] : null;
        }

        return null;
    }

    /**
     * What arrived of an answer that was stopped for its size: its status and headers, and its
     * first `$bytes` - or null where nothing worth reading had arrived.
     *
     * @param  resource|false  $sink
     */
    protected static function cutShort(?ResponseInterface $head, mixed $sink, int $bytes): ?Response
    {
        if (! ($head instanceof ResponseInterface) || ! is_resource($sink) || ($head->getStatusCode() >= 300)) {
            return null;
        }

        rewind($sink);

        return new Response(new Psr7Response(
            $head->getStatusCode(),
            $head->getHeaders(),
            (string) stream_get_contents($sink, $bytes),
        ));
    }

    /**
     * The page as a document, in UTF-8 whatever it was written in.
     */
    protected function parse(Response $response): ?DOMDocument
    {
        $html = $response->body();

        // The character set the server names, then the one the page names in its first lines.
        $charset = (preg_match('/charset=["\']?([\w-]+)/i', $response->header('Content-Type'), $matches) === 1)
            || (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($html, 0, 4096), $matches) === 1)
                ? $matches[1]
                : 'UTF-8';

        if (strcasecmp($charset, 'UTF-8') !== 0) {
            try {
                $html = mb_convert_encoding($html, 'UTF-8', $charset);
            } catch (ValueError) {
                // A character set nobody has heard of. Read as UTF-8, which most pages are.
            }
        }

        $document = new DOMDocument;

        // The processing instruction keeps DOMDocument from reading the page as Latin-1.
        return @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)
            ? $document
            : null;
    }

    protected static function first(DOMDocument $document, string $query): ?string
    {
        $found = (new DOMXPath($document))->query($query);
        $value = ($found === false) ? null : $found->item(0)?->textContent;

        return (is_string($value) && (trim($value) !== '')) ? $value : null;
    }
}
