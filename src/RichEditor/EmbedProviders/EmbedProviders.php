<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders;

use Illuminate\Contracts\Foundation\Application;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedHostSanitizer;
use WeakMap;

/**
 * The services an embed may come from: the ones in `embed.providers`, by class.
 *
 * Taking one off the list refuses its links in the dialog and renders its stored embeds as
 * nothing - a frame for a service the project said no to is not drawn. Adding one is a class
 * extending `EmbedProvider`.
 */
final class EmbedProviders
{
    /**
     * @var array<int, class-string<EmbedProvider>>
     */
    public const BUILT_IN = [
        YouTube::class,
        Vimeo::class,
        Spotify::class,
        Loom::class,
        CodePen::class,
        TikTok::class,
        GoogleMaps::class,
    ];

    /**
     * What `all()` answered, per application and for the list it was answered for.
     *
     * Asked for every embed a document parses and renders, and every link pasted - seven classes
     * out of the container each time, for an answer that only changes with the configuration.
     * Kept beside the application rather than in a plain static, so a test suite that boots a
     * new one starts from nothing.
     *
     * @var WeakMap<Application, array{0: array<mixed>, 1: array<string, EmbedProvider>}>|null
     */
    private static ?WeakMap $resolved = null;

    /**
     * @return array<string, EmbedProvider>
     */
    public static function all(): array
    {
        $configured = (array) config('filament-advanced-rich-editor.embed.providers', self::BUILT_IN);
        $application = app();

        self::$resolved ??= new WeakMap;

        [$for, $known] = self::$resolved[$application] ?? [null, null];

        if (($known !== null) && ($for === $configured)) {
            return $known;
        }

        $providers = [];

        foreach ($configured as $class) {
            $provider = (is_string($class) && class_exists($class)) ? app($class) : $class;

            if ($provider instanceof EmbedProvider) {
                $providers[$provider->name()] ??= $provider;
            }
        }

        self::$resolved[$application] = [$configured, $providers];

        return $providers;
    }

    public static function get(mixed $name): ?EmbedProvider
    {
        return is_string($name) ? (self::all()[$name] ?? null) : null;
    }

    public static function forHost(string $host): ?EmbedProvider
    {
        $host = strtolower($host);

        foreach (self::all() as $provider) {
            if (in_array($host, $provider->hosts(), strict: true)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * What an `iframe` on a rendered page may point at: every provider's frame hosts, and
     * whatever else the project embeds, from `embed.allowed_hosts`.
     *
     * @return array<int, string>
     */
    public static function frameHosts(): array
    {
        return array_values(array_unique([
            ...array_merge(...array_values(array_map(
                static fn (EmbedProvider $provider): array => $provider->frameHosts(),
                self::all(),
            ))),
            ...array_filter((array) config('filament-advanced-rich-editor.embed.allowed_hosts', []), 'is_string'),
        ]));
    }

    public static function allowsFrame(string $src): bool
    {
        $host = parse_url($src, PHP_URL_HOST);

        return is_string($host) && EmbedHostSanitizer::allows($host, self::frameHosts());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forJs(): array
    {
        return array_values(array_map(
            static fn (EmbedProvider $provider): array => $provider->toArray(),
            self::all(),
        ));
    }
}
