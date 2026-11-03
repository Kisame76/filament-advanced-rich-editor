<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Whether the addresses a document links to still answer.
 *
 * Every address is asked once however often it is written, many at a time, with `HEAD` first
 * and `GET` where that was refused: a server that answers `HEAD` wrongly - 405, 403, even 404
 * for a page that is there - is common enough that an error is only believed once `GET` says
 * it too. The body is never downloaded.
 *
 * A link to the site itself - `/about` - is asked of `app.url`, which is where a reader's
 * browser would take it. A link to a place on the same page, an e-mail address or a phone
 * number is not asked at all.
 */
class LinkCheck
{
    protected bool $isGuarded = false;

    final public function __construct() {}

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Asks nothing but the public internet, and follows no redirect.
     *
     * For a check somebody in the panel sets off by typing an address, and is then shown the
     * answer to - see `PublicHosts`. A redirect is an answer: where it leads is not followed,
     * because that is the same question asked again by somebody else.
     */
    public function guarded(bool $condition = true): static
    {
        $this->isGuarded = $condition;

        return $this;
    }

    /**
     * @param  array<int, string>  $hrefs
     * @return array<string, LinkStatus> by the address as it was given
     */
    public function check(array $hrefs): array
    {
        $targets = [];

        foreach ($hrefs as $href) {
            $targets[$href] = static::target($href);
        }

        $answers = $this->ask(array_values(array_unique(array_filter($targets))));

        return array_map(
            static fn (?string $target): LinkStatus => $answers[$target ?? ''] ?? new LinkStatus(LinkState::Unchecked),
            $targets,
        );
    }

    public function status(string $href): LinkStatus
    {
        return $this->check([$href])[$href];
    }

    /**
     * The address to ask for a link as it was written, or null for one that leads nowhere a
     * request can go.
     */
    public static function target(string $href): ?string
    {
        $href = trim($href);

        $href = match (true) {
            str_starts_with($href, '//') => 'https:'.$href,
            str_starts_with($href, '/') => rtrim((string) config('app.url'), '/').$href,
            default => $href,
        };

        // A fragment is the browser's and never sent - `page#a` and `page#b` are one question.
        $href = Str::before($href, '#');

        $parts = parse_url($href);

        if (! is_array($parts) || blank($parts['host'] ?? null) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], strict: true)) {
            return null;
        }

        return $href;
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<string, LinkStatus>
     */
    protected function ask(array $targets): array
    {
        $statuses = [];
        $pins = [];

        if ($this->isGuarded) {
            $hosts = PublicHosts::make();

            foreach ($targets as $index => $target) {
                $pin = $hosts->pin($target);

                if (! is_string($pin)) {
                    $statuses[$target] = ($pin === null)
                        ? new LinkStatus(LinkState::Broken, host: (string) parse_url($target, PHP_URL_HOST))
                        : new LinkStatus(LinkState::Unchecked);
                    unset($targets[$index]);

                    continue;
                }

                $pins[$target] = $pin;
            }
        }

        $answers = $this->send('HEAD', $targets, $pins);

        $refused = array_filter(
            $targets,
            static fn (string $target): bool => ($answers[$target] instanceof Response) && ($answers[$target]->status() >= 400),
        );

        if ($refused !== []) {
            $answers = array_replace($answers, $this->send('GET', $refused, $pins));
        }

        foreach ($targets as $target) {
            $statuses[$target] = $this->judge($target, $answers[$target]);
        }

        return $statuses;
    }

    /**
     * @param  array<int, string>  $targets
     * @param  array<string, string>  $pins
     * @return array<string, Response|Throwable>
     */
    protected function send(string $method, array $targets, array $pins): array
    {
        $targets = array_values($targets);
        $timeout = static::timeout();
        $guarded = $this->isGuarded;
        $results = [];

        // In batches, rather than through the `concurrency` of `Http::pool()`: Laravel 11 only
        // gained it partway through, and before that every request in a pool goes at once.
        $batches = array_chunk($targets, max(1, (int) (config('filament-advanced-rich-editor.link.check.concurrency') ?? 10)), preserve_keys: true);

        foreach ($batches as $batch) {
            $results += Http::pool(function (Pool $pool) use ($method, $batch, $pins, $timeout, $guarded): void {
                foreach ($batch as $index => $target) {
                    $options = [
                        'allow_redirects' => $guarded ? false : ['max' => 5],
                        // The answer is the status line, so the transfer is stopped once the
                        // headers are in and the page behind them is never downloaded - Guzzle
                        // still hands the response over. Not `stream`, which leaves cURL for
                        // PHP's own streams: their errors do not say a host does not exist, and
                        // they ignore the pin.
                        'on_headers' => static function (ResponseInterface $response) use ($guarded): void {
                            if (static::stopsAt($response->getStatusCode(), $guarded)) {
                                throw new RuntimeException('Answered.');
                            }
                        },
                    ];

                    if (isset($pins[$target])) {
                        $options = [...$options, ...PublicHosts::options($pins[$target])];
                    }

                    $pool->as((string) $index)
                        ->withUserAgent(static::userAgent())
                        ->connectTimeout($timeout)
                        ->timeout($timeout)
                        ->withOptions($options)
                        ->send($method, $target);
                }
            });
        }

        $answers = [];

        foreach ($targets as $index => $target) {
            $answers[$target] = $results[$index] ?? new RuntimeException('No answer.');
        }

        return $answers;
    }

    /**
     * Whether a request can stop at its status line: at every answer but a redirect that is
     * going to be followed, whose body is how Guzzle gets to the next request. Guarded, none is
     * followed - and a server that answers a redirect with a body that never ends is otherwise
     * read until the timeout, on every address somebody types.
     */
    public static function stopsAt(int $status, bool $guarded): bool
    {
        return $guarded || ($status < 300) || ($status >= 400);
    }

    protected function judge(string $target, Response|Throwable $answer): LinkStatus
    {
        if ($answer instanceof Response) {
            $status = $answer->status();

            return new LinkStatus(
                state: match (true) {
                    $status < 400 => LinkState::Working,
                    in_array($status, [404, 410], strict: true) => LinkState::Broken,
                    default => LinkState::Erroring,
                },
                status: $status,
                reason: $answer->reason(),
            );
        }

        // cURL names the way a connection failed in the message it writes, and 6 is a host
        // that does not resolve - which, unlike a timeout, is not going to be different
        // tomorrow.
        return new LinkStatus(
            state: str_contains($answer->getMessage(), 'cURL error 6:') ? LinkState::Broken : LinkState::Unreachable,
            host: (string) parse_url($target, PHP_URL_HOST),
        );
    }

    /**
     * Seconds a request may take, here and wherever else this package asks an address.
     */
    public static function timeout(): int
    {
        return max(1, (int) (config('filament-advanced-rich-editor.link.check.timeout') ?? 5));
    }

    /**
     * What a request calls itself, here and wherever else this package asks an address.
     */
    public static function userAgent(): string
    {
        $agent = config('filament-advanced-rich-editor.link.check.user_agent');

        return (is_string($agent) && filled($agent))
            ? $agent
            : 'Mozilla/5.0 (compatible; link check; '.config('app.name', 'Laravel').')';
    }
}
