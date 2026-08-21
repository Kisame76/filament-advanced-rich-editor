<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;

it('reads a youtube watch link', function (): void {
    // The URL people actually copy out of the address bar. Nothing about it is an embed
    // URL, and pasting it into an iframe shows YouTube's "refused to connect" page.
    expect(EmbedUrl::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null]);
});

it('reads every other shape a youtube link comes in', function (): void {
    $shapes = [
        'https://youtu.be/dQw4w9WgXcQ',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        'youtube.com/watch?v=dQw4w9WgXcQ',
    ];

    foreach ($shapes as $shape) {
        expect(EmbedUrl::parse($shape))
            ->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'start' => null], $shape);
    }
});

it('keeps the timestamp a shared link carries', function (): void {
    // Sharing "from 1:30" is the whole point of half the links anyone pastes.
    expect(EmbedUrl::parse('https://youtu.be/dQw4w9WgXcQ?t=90')['start'])->toBe(90)
        ->and(EmbedUrl::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s')['start'])->toBe(90)
        ->and(EmbedUrl::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&start=45')['start'])->toBe(45);
});

it('reads a vimeo link', function (): void {
    expect(EmbedUrl::parse('https://vimeo.com/76979871'))
        ->toBe(['provider' => 'vimeo', 'id' => '76979871', 'start' => null]);
});

it('reads a vimeo link that carries its own player path', function (): void {
    expect(EmbedUrl::parse('https://player.vimeo.com/video/76979871'))
        ->toBe(['provider' => 'vimeo', 'id' => '76979871', 'start' => null]);
});

it('refuses a link it does not know', function (): void {
    // Not an error: a link to somewhere else is a link, and the dialog says so rather than
    // building an iframe pointing at a page that forbids being framed.
    expect(EmbedUrl::parse('https://example.com/video.mp4'))->toBeNull()
        ->and(EmbedUrl::parse('not a url at all'))->toBeNull()
        ->and(EmbedUrl::parse(''))->toBeNull();
});

it('refuses a host that merely ends in a known one', function (): void {
    // `youtube.com.attacker.test` contains "youtube.com", and a substring test - which is
    // what the obvious implementation reaches for - would frame it.
    expect(EmbedUrl::parse('https://youtube.com.attacker.test/watch?v=dQw4w9WgXcQ'))->toBeNull()
        ->and(EmbedUrl::parse('https://notyoutube.com/watch?v=dQw4w9WgXcQ'))->toBeNull();
});

it('builds the embed url a browser can frame', function (): void {
    // `youtube-nocookie` is the default because an editor that embeds a video should not
    // decide on its own to put a tracking cookie on the reader's machine.
    expect(EmbedUrl::src('youtube', 'dQw4w9WgXcQ'))
        ->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and(EmbedUrl::src('vimeo', '76979871'))
        ->toBe('https://player.vimeo.com/video/76979871');
});

it('carries the timestamp into the embed url', function (): void {
    expect(EmbedUrl::src('youtube', 'dQw4w9WgXcQ', start: 90))
        ->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90')
        ->and(EmbedUrl::src('vimeo', '76979871', start: 90))
        ->toBe('https://player.vimeo.com/video/76979871#t=90s');
});

it('lets a project keep youtube cookies where it has a reason to', function (): void {
    config()->set('filament-advanced-rich-editor.embed.youtube_nocookie', false);

    expect(EmbedUrl::src('youtube', 'dQw4w9WgXcQ'))
        ->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});
