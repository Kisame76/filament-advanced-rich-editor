<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders\EmbedProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders\EmbedProviders;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedProviders\YouTube;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Covers\EmbedCover;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;

/**
 * The services an embed can come from, each described once.
 *
 * The description is read twice - here, and by `embed.js` in the browser - and the two have
 * to agree. So the description the browser is handed is kept beside the JS tests as a file,
 * and asserted here to be exactly what PHP hands out: a pattern changed on one side and not
 * the other fails a test instead of a paste.
 */
const PROVIDER_FIXTURE = __DIR__.'/../js/fixtures/embed-providers.json';

/**
 * One id per service, and the links people actually paste for it.
 *
 * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
 */
function embedSamples(): array
{
    $map = '!1m18!1m12!1m3!1d2427.0!2d13.37!3d52.51!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47a851c655f20989%3A0x26bbfb4e84674c63!2sBrandenburg%20Gate!5e0!3m2!1sen!2sde!4v1690000000000!5m2!1sen!2sde';

    return [
        'YouTube' => ['youtube', 'dQw4w9WgXcQ', ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ']],
        'Vimeo' => ['vimeo', '76979871', ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871']],
        'Spotify' => ['spotify', 'track/4uLU6hMCjMI75M1A2tKUQC', [
            'https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC?si=a1b2c3',
            'https://open.spotify.com/intl-de/track/4uLU6hMCjMI75M1A2tKUQC',
            'https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC?utm_source=generator',
        ]],
        'Loom' => ['loom', 'e5b8c04bca094dd8a5507925ab887002', ['https://www.loom.com/share/e5b8c04bca094dd8a5507925ab887002?sid=1']],
        'CodePen' => ['codepen', 'chriscoyier/gfdDu', ['https://codepen.io/chriscoyier/pen/gfdDu', 'https://codepen.io/chriscoyier/full/gfdDu']],
        'TikTok' => ['tiktok', '6718335390845095173', ['https://www.tiktok.com/@scout2015/video/6718335390845095173?lang=en']],
        'Google Maps' => ['google_maps', $map, [
            'https://www.google.com/maps/embed?pb='.$map,
            '<iframe src="https://www.google.com/maps/embed?pb='.$map.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
        ]],
    ];
}

it('hands the browser exactly the description it is tested against', function (): void {
    app()->setLocale('en');

    $providers = EmbedProviders::forJs();

    // Rewritten on request, never silently: `ARTE_WRITE_FIXTURES=1 vendor/bin/pest --filter=...`.
    if (getenv('ARTE_WRITE_FIXTURES') === '1') {
        file_put_contents(PROVIDER_FIXTURE, json_encode($providers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    expect(json_decode((string) file_get_contents(PROVIDER_FIXTURE), associative: true))->toBe($providers);
});

it('reads the links a share button writes', function (string $provider, string $id, array $links): void {
    foreach ($links as $link) {
        expect(EmbedUrl::parse($link))->toMatchArray(['provider' => $provider, 'id' => $id], $link);
    }
})->with(embedSamples());

it('reads back every address it builds', function (string $provider, string $id): void {
    // Reopening an embed shows the frame address, and a library entry hands out the share
    // address: both have to lead back to the same thing.
    expect(EmbedUrl::parse(EmbedUrl::src($provider, $id))['id'] ?? null)->toBe($id)
        ->and(EmbedUrl::parse(EmbedUrl::share($provider, $id))['id'] ?? null)->toBe($id);
})->with(embedSamples());

it('frames every service from a host the sanitiser lets through', function (string $provider, string $id): void {
    expect(EmbedProviders::allowsFrame(EmbedUrl::src($provider, $id)))->toBeTrue();
})->with(embedSamples());

it('builds the addresses each service frames', function (): void {
    expect(EmbedUrl::src('spotify', 'track/4uLU6hMCjMI75M1A2tKUQC'))->toBe('https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC')
        ->and(EmbedUrl::src('codepen', 'chriscoyier/gfdDu'))->toBe('https://codepen.io/chriscoyier/embed/gfdDu?default-tab=result')
        ->and(EmbedUrl::src('loom', 'e5b8c04bca094dd8a5507925ab887002'))->toBe('https://www.loom.com/embed/e5b8c04bca094dd8a5507925ab887002')
        ->and(EmbedUrl::src('tiktok', '6718335390845095173'))->toBe('https://www.tiktok.com/player/v1/6718335390845095173')
        // A timestamp only where the service takes one, in the spelling it takes.
        ->and(EmbedUrl::src('loom', 'e5b8c04bca094dd8a5507925ab887002', 30))->toBe('https://www.loom.com/embed/e5b8c04bca094dd8a5507925ab887002')
        ->and(EmbedUrl::share('youtube', 'dQw4w9WgXcQ', 90))->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90')
        ->and(EmbedUrl::share('vimeo', '76979871', 90))->toBe('https://vimeo.com/76979871#t=90s');
});

it('refuses what only looks like one of them', function (string $link): void {
    expect(EmbedUrl::parse($link))->toBeNull();
})->with([
    'a host that ends like one' => 'https://open.spotify.com.attacker.test/track/4uLU6hMCjMI75M1A2tKUQC',
    'a short link nobody can read without following it' => 'https://vm.tiktok.com/ZMabcdef/',
    'a place rather than a map somebody composed' => 'https://www.google.com/maps/place/Brandenburger+Tor',
    'a profile rather than a pen' => 'https://codepen.io/chriscoyier',
    'a script embed' => '<blockquote class="twitter-tweet"><a href="https://twitter.com/x/status/1"></a></blockquote><script src="https://platform.twitter.com/widgets.js"></script>',
    'an iframe from somewhere else' => '<iframe src="https://evil.example/embed/1"></iframe>',
    'a javascript address inside embed code' => '<iframe src="javascript:alert(1)"></iframe>',
]);

it('refuses an id with a line break after it, as the editor does', function (): void {
    // PCRE lets `$` match before a final line break; a script does not. The two halves read
    // one description, so they have to read it alike.
    expect(EmbedUrl::accepts('youtube', 'dQw4w9WgXcQ'))->toBeTrue()
        ->and(EmbedUrl::accepts('youtube', "dQw4w9WgXcQ\n"))->toBeFalse()
        ->and(EmbedUrl::accepts('spotify', "track/4uLU6hMCjMI75M1A2tKUQC\n"))->toBeFalse();
});

it('draws a player with a height of its own at that height', function (): void {
    $html = AdvancedRichContentRenderer::make([
        'type' => 'doc',
        'content' => [['type' => 'embed', 'attrs' => ['provider' => 'spotify', 'id' => 'track/4uLU6hMCjMI75M1A2tKUQC', 'ratio' => '16 / 9']]],
    ])->toHtml();

    expect($html)->toContain('style="width: 100%; height: 352px;"')
        ->and($html)->toContain('src="https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC"');
});

it('holds an upright frame to the width of a phone', function (): void {
    expect(Embed::box(EmbedProviders::get('tiktok'), '9 / 16'))->toBe('aspect-ratio: 9 / 16; width: 100%; max-width: 22.5rem;')
        ->and(Embed::box(EmbedProviders::get('youtube'), '16 / 9'))->toBe('aspect-ratio: 16 / 9; width: 100%;')
        // The service's own shape where the node carries none.
        ->and(Embed::box(EmbedProviders::get('google_maps'), null))->toBe('aspect-ratio: 4 / 3; width: 100%;');
});

it('draws an embed nobody picked a shape for in the service\'s own', function (): void {
    // A pasted TikTok carries no ratio, and the widescreen default would draw it on its side.
    $html = AdvancedRichContentRenderer::make([
        'type' => 'doc',
        'content' => [['type' => 'embed', 'attrs' => ['provider' => 'tiktok', 'id' => '6718335390845095173']]],
    ])->toHtml();

    expect($html)->toContain('aspect-ratio: 9 / 16; width: 100%; max-width: 22.5rem;')
        ->and(Embeds::describes(['provider' => 'tiktok', 'id' => '6718335390845095173'])['ratio'])->toBe('9 / 16');
});

it('writes nothing into the style that is not a ratio', function (): void {
    // A document kept as JSON hands its attributes over unparsed, and the sanitiser does not
    // read CSS.
    $html = AdvancedRichContentRenderer::make([
        'type' => 'doc',
        'content' => [['type' => 'embed', 'attrs' => ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'ratio' => '16 / 9; background: url(https://evil.example/x)']]],
    ])->toHtml();

    expect($html)->toContain('aspect-ratio: 16 / 9; width: 100%;')
        ->and($html)->not->toContain('evil.example');
});

it('resolves the services once for a list, and again when the list changes', function (): void {
    // Asked for every embed parsed and rendered: seven classes out of the container each time
    // was work for an answer that only changes with the configuration.
    $first = EmbedProviders::all();

    expect(EmbedProviders::all()['youtube'])->toBe($first['youtube']);

    config()->set('filament-advanced-rich-editor.embed.providers', [YouTube::class]);

    expect(array_keys(EmbedProviders::all()))->toBe(['youtube']);
});

it('renders nothing for a service the project took off the list', function (): void {
    config()->set('filament-advanced-rich-editor.embed.providers', [YouTube::class]);

    $html = AdvancedRichContentRenderer::make([
        'type' => 'doc',
        'content' => [['type' => 'embed', 'attrs' => ['provider' => 'spotify', 'id' => 'track/4uLU6hMCjMI75M1A2tKUQC']]],
    ])->toHtml();

    expect($html)->not->toContain('<iframe')
        ->and(EmbedUrl::parse('https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC'))->toBeNull()
        ->and(EmbedProviders::frameHosts())->not->toContain('open.spotify.com');
});

it('takes a service a project describes itself', function (): void {
    $dailymotion = new class extends EmbedProvider
    {
        public function name(): string
        {
            return 'dailymotion';
        }

        public function hosts(): array
        {
            return ['www.dailymotion.com', 'dai.ly'];
        }

        public function patterns(): array
        {
            return ['^dai\.ly/(?<id>[a-z0-9]+)', '^[^/]+/video/(?<id>[a-z0-9]+)'];
        }

        public function id(): string
        {
            return '[a-z0-9]{5,10}';
        }

        public function src(): string
        {
            return 'https://www.dailymotion.com/embed/video/{id}';
        }

        public function share(): string
        {
            return 'https://www.dailymotion.com/video/{id}';
        }
    };

    config()->set('filament-advanced-rich-editor.embed.providers', [...EmbedProviders::BUILT_IN, $dailymotion::class]);

    expect(EmbedUrl::parse('https://dai.ly/x8abc12'))->toBe(['provider' => 'dailymotion', 'id' => 'x8abc12', 'start' => null])
        ->and(EmbedUrl::src('dailymotion', 'x8abc12'))->toBe('https://www.dailymotion.com/embed/video/x8abc12')
        ->and(EmbedProviders::frameHosts())->toContain('www.dailymotion.com');
});

it('lets the project add hosts something else of its frames from', function (): void {
    config()->set('filament-advanced-rich-editor.embed.allowed_hosts', ['player.example.com']);

    expect(EmbedProviders::frameHosts())->toContain('player.example.com', 'youtube-nocookie.com', 'open.spotify.com');
});

it('names a library entry so the file system takes it', function (): void {
    expect(Embeds::fileName('youtube', 'dQw4w9WgXcQ'))->toBe('youtube-dQw4w9WgXcQ.embed.json')
        // A slash would be a folder, and a map's id is longer than any file name may be.
        ->and(Embeds::fileName('spotify', 'track/4uLU6hMCjMI75M1A2tKUQC'))->toMatch('/^spotify-[a-f0-9]{32}\.embed\.json$/')
        ->and(strlen(Embeds::fileName('google_maps', embedSamples()['Google Maps'][1])))->toBeLessThan(80);
});

it('fetches a still through the service\'s oEmbed endpoint', function (): void {
    Http::fake([
        'open.spotify.com/oembed*' => Http::response(['thumbnail_url' => 'https://i.scdn.co/image/cover']),
        'i.scdn.co/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')),
    ]);

    $embed = Embeds::describes(['provider' => 'spotify', 'id' => 'track/4uLU6hMCjMI75M1A2tKUQC']);

    expect(EmbedCover::bytes($embed, 2))->not->toBeNull();

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://open.spotify.com/oembed')
        && $request['url'] === 'https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC');
});

it('asks for no still where a service has none', function (): void {
    Http::fake();

    $embed = Embeds::describes(['provider' => 'google_maps', 'id' => embedSamples()['Google Maps'][1]]);

    expect(EmbedCover::bytes($embed, 2))->toBeNull();

    Http::assertNothingSent();
});
