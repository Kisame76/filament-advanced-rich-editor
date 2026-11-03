<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

/**
 * Whether the server may ask an address somebody in the panel typed - and where it connects.
 *
 * Anything the panel fetches on somebody's behalf and then shows them the result of is a way to
 * find out which machines answer on the server's own network, unless it refuses to go there. So
 * a host is asked only where every address it resolves to is on the public internet, and the
 * connection is pinned to the address that was checked - a name that resolved somewhere public a
 * moment ago may resolve somewhere else by the time the request is made.
 */
class PublicHosts
{
    final public function __construct() {}

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * cURL's resolve entry for the address's host - false where the host resolves somewhere that
     * is not the public internet, and null where it does not resolve at all.
     */
    public function pin(string $url): string|false|null
    {
        // A check that cannot hold the connection to the address it checked is no check: the
        // name is resolved again for the request, by then perhaps somewhere private. Without
        // cURL, Guzzle falls back to PHP's streams, which take no such option.
        if (! $this->canPin()) {
            return false;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $port = parse_url($url, PHP_URL_PORT) ?? (str_starts_with(strtolower($url), 'https:') ? 443 : 80);

        $addresses = ($host === '') ? [] : $this->addresses($host);

        if ($addresses === []) {
            return null;
        }

        // Every address rather than the first: a name that resolves to a public address and
        // a private one is a name somebody chose that way.
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return false;
            }
        }

        $address = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

        return "{$host}:{$port}:{$address}";
    }

    /**
     * Whether a connection can be held to an address: cURL's resolve list, which only exists
     * where PHP has cURL.
     */
    protected function canPin(): bool
    {
        return defined('CURLOPT_RESOLVE');
    }

    /**
     * The request options that hold a connection to the pinned address.
     *
     * @return array<string, mixed>
     */
    public static function options(string $pin): array
    {
        return defined('CURLOPT_RESOLVE') ? ['curl' => [CURLOPT_RESOLVE => [$pin]]] : [];
    }

    /**
     * What a host name resolves to. An address resolves to itself.
     *
     * @return array<int, string>
     */
    protected function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
