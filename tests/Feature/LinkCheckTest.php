<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkCheck;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkState;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\PublicHosts;

/**
 * Whether an address still answers - asked once however often it is written, with `HEAD`
 * first and `GET` where that was refused, and in the panel only of the public internet.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('says a page that answers is working, and says nothing about it', function (): void {
    Http::fake(['example.com/*' => Http::response('', 200)]);

    $status = LinkCheck::make()->status('https://example.com/page');

    expect($status->state)->toBe(LinkState::Working)
        ->and($status->problem())->toBeNull();
});

it('calls a page that is gone broken', function (int $code, string $answer): void {
    Http::fake(['example.com/*' => Http::response('', $code)]);

    $status = LinkCheck::make()->status('https://example.com/page');

    expect($status->state)->toBe(LinkState::Broken)
        ->and($status->problem())->toBe("This address answers {$answer}.");
})->with([
    'not found' => [404, '404 Not Found'],
    'gone' => [410, '410 Gone'],
]);

it('believes an error only once GET says it too', function (): void {
    // Plenty of servers answer HEAD with 405, 403 or even 404 for a page that is there.
    Http::fake(['example.com/*' => Http::sequence()->push('', 405)->push('', 200)]);

    expect(LinkCheck::make()->status('https://example.com/page')->state)->toBe(LinkState::Working);

    $methods = [];

    Http::assertSent(static function (Request $request) use (&$methods): bool {
        $methods[] = $request->method();

        return true;
    });

    expect($methods)->toBe(['HEAD', 'GET']);
});

it('calls any other error an error rather than a broken link', function (int $code): void {
    // A server that turns checkers away, or is down for the afternoon, is not a link
    // anybody has to fix.
    Http::fake(['example.com/*' => Http::response('', $code)]);

    expect(LinkCheck::make()->status('https://example.com/page')->state)->toBe(LinkState::Erroring);
})->with([403, 429, 500, 503]);

it('calls a host that does not exist broken, and one that does not answer unreachable', function (): void {
    Http::fake([
        'gone.test/*' => failedConnection(),
        'slow.test/*' => failedConnection('cURL error 28: Operation timed out after 5001 milliseconds'),
    ]);

    $statuses = LinkCheck::make()->check(['https://gone.test/', 'https://slow.test/']);

    expect($statuses['https://gone.test/']->state)->toBe(LinkState::Broken)
        ->and($statuses['https://gone.test/']->problem())->toBe('There is no host called gone.test.')
        ->and($statuses['https://slow.test/']->state)->toBe(LinkState::Unreachable)
        ->and($statuses['https://slow.test/']->problem())->toBe('This address did not answer.');
});

it('stops reading at the status line, except where a redirect is going to be followed', function (): void {
    // `Http::fake()` never calls the hook that stops a transfer, so the decision is asked
    // directly. Guarded, no redirect is followed and its body is nobody's business either.
    expect(LinkCheck::stopsAt(200, guarded: false))->toBeTrue()
        ->and(LinkCheck::stopsAt(404, guarded: false))->toBeTrue()
        ->and(LinkCheck::stopsAt(302, guarded: false))->toBeFalse()
        ->and(LinkCheck::stopsAt(302, guarded: true))->toBeTrue();
});

it('asks every address once however often it is written', function (): void {
    Http::fake(['example.com/*' => Http::response('', 200)]);

    $statuses = LinkCheck::make()->check([
        'https://example.com/page',
        'https://example.com/page#top',
        'https://example.com/page#bottom',
    ]);

    // Every link answered for as it was written, and one request behind all three.
    expect(array_keys($statuses))->toBe(['https://example.com/page', 'https://example.com/page#top', 'https://example.com/page#bottom']);

    Http::assertSentCount(1);
});

it('asks a link to the site itself of app.url', function (): void {
    config()->set('app.url', 'https://site.test/');

    expect(LinkCheck::target('/about'))->toBe('https://site.test/about')
        ->and(LinkCheck::target('//cdn.example.com/x.js'))->toBe('https://cdn.example.com/x.js')
        ->and(LinkCheck::target(' https://example.com/page#part '))->toBe('https://example.com/page');
});

it('does not ask what is not a web address', function (string $href): void {
    expect(LinkCheck::target($href))->toBeNull()
        ->and(LinkCheck::make()->status($href)->state)->toBe(LinkState::Unchecked)
        ->and(LinkCheck::make()->status($href)->problem())->toBeNull();

    Http::assertNothingSent();
})->with([
    'a place on the page' => ['#top'],
    'an e-mail address' => ['mailto:a@example.com'],
    'a phone number' => ['tel:+49301234'],
    'a script' => ['javascript:alert(1)'],
    'a path relative to the page' => ['about'],
    'ftp' => ['ftp://example.com/file'],
]);

it('says who is asking', function (): void {
    Http::fake(['example.com/*' => Http::response('', 200)]);

    config()->set('app.name', 'Shop');
    LinkCheck::make()->status('https://example.com/a');

    config()->set('filament-advanced-rich-editor.link.check.user_agent', 'Checker/1.0');
    LinkCheck::make()->status('https://example.com/b');

    $agents = [];

    Http::assertSent(static function (Request $request) use (&$agents): bool {
        $agents[] = $request->header('User-Agent')[0] ?? null;

        return true;
    });

    expect($agents)->toBe(['Mozilla/5.0 (compatible; link check; Shop)', 'Checker/1.0']);
});

it('asks nothing but the public internet when guarded', function (string $href, array $addresses): void {
    resolvingHosts(['inside.test' => $addresses]);

    expect(LinkCheck::make()->guarded()->status($href)->state)->toBe(LinkState::Unchecked);

    Http::assertNothingSent();
})->with([
    'the machine itself' => ['http://127.0.0.1:8080/admin', []],
    'a private network' => ['http://inside.test/', ['10.0.0.5']],
    'the cloud metadata address' => ['http://inside.test/latest/meta-data', ['169.254.169.254']],
    'a name with one public and one private address' => ['http://inside.test/', ['93.184.215.14', '192.168.1.10']],
    'the IPv6 loopback' => ['http://[::1]/', []],
]);

it('asks a public address when guarded', function (): void {
    resolvingHosts(['example.com' => ['93.184.215.14']]);

    Http::fake(['example.com/*' => Http::response('', 404)]);

    expect(LinkCheck::make()->guarded()->status('https://example.com/gone')->state)->toBe(LinkState::Broken);
});

it('asks nothing when guarded where the connection cannot be held to the address it checked', function (): void {
    // Without cURL, the name would be resolved again for the request - by then perhaps to
    // somewhere private.
    $hosts = new class extends PublicHosts
    {
        protected function canPin(): bool
        {
            return false;
        }

        protected function addresses(string $host): array
        {
            return ['93.184.215.14'];
        }
    };

    app()->bind(PublicHosts::class, $hosts::class);

    expect(LinkCheck::make()->guarded()->status('https://example.com/')->state)->toBe(LinkState::Unchecked);

    Http::assertNothingSent();
});

it('calls a host that does not resolve broken without asking it, when guarded', function (): void {
    resolvingHosts(['gone.test' => []]);

    expect(LinkCheck::make()->guarded()->status('https://gone.test/')->problem())
        ->toBe('There is no host called gone.test.');

    Http::assertNothingSent();
});
