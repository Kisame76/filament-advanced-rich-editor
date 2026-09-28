<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\MediaPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

/**
 * A video or a sound that lives on this server: what may be pointed at, which element
 * draws it, and what survives the round trip.
 */
$render = fn (string $html): string => AdvancedRichContentRenderer::make($html)->toHtml();

/** Every name the slash menu offers, flattened out of its groups. */
$slashNames = static fn (AdvancedRichEditor $editor): array => array_merge(...array_map(
    static fn (array $group): array => array_column($group['items'], 'name'),
    SlashMenu::for($editor)['groups'],
));

it('offers both buttons and the media browser behind them', function (): void {
    $editor = editor()->fileAttachmentsDirectory('article-attachments');
    $tools = $editor->getTools();

    expect($tools)->toHaveKey('video')
        ->and($tools)->toHaveKey('audio')
        ->and($tools['video']->getLabel())->toBe('Video file')
        ->and($tools['audio']->getLabel())->toBe('Audio file')
        ->and($tools['video']->getIcon())->toBe('heroicon-o-play-circle')
        ->and($tools['audio']->getIcon())->toBe('heroicon-o-musical-note')
        // The browser rather than a dialog of their own: a second door for video was the
        // whole reason this was rebuilt.
        ->and($tools['video']->getJsHandler())->toContain("mountAction('mediaBrowser'")
        ->and($tools['audio']->getJsHandler())->toContain("mountAction('mediaBrowser'")
        ->and($tools['video']->getActiveKey())->toBe('media');
});

it('opens the browser on the tab the button is about', function (): void {
    $tools = editor()->fileAttachmentsDirectory('article-attachments')->getTools();

    expect($tools['audio']->getJsHandler())->toContain("kind: 'audio'")
        ->and($tools['video']->getJsHandler())->toContain("kind: 'video'");
});

it('ships registered and unplaced, reachable through the slash menu', function () use ($slashNames): void {
    expect(array_merge(...toolbarShape(editor())))->not->toContain('video')
        ->and($slashNames(editor()))->toContain('video', 'audio');
});

it('takes the buttons and the plugin away where the field says so', function () use ($slashNames): void {
    $editor = editor()->media(false);

    expect($editor->hasMedia())->toBeFalse()
        ->and($editor->getTools())->not->toHaveKey('video')
        ->and($editor->getTools())->not->toHaveKey('audio')
        ->and($slashNames($editor))->not->toContain('video')
        ->and(array_filter($editor->getPlugins(), fn (object $plugin): bool => $plugin instanceof MediaPlugin))
        ->toBe([]);

    config()->set('filament-advanced-rich-editor.media.enabled', false);

    expect(editor()->hasMedia())->toBeFalse()
        ->and(editor()->media()->hasMedia())->toBeTrue();
});

it('still renders a stored player after the buttons are taken away', function () use ($render): void {
    // Turning a feature off is not the same as deleting what was written while it was on.
    config()->set('filament-advanced-rich-editor.media.enabled', false);

    expect($render('<video src="/storage/talk.mp4" controls></video>'))->toContain('<video');
});

it('takes a path, and a link a browser fetches a file over', function (): void {
    expect(MediaUrl::src('/storage/talk.mp4'))->toBe('/storage/talk.mp4')
        ->and(MediaUrl::src('  clips/talk.mp4  '))->toBe('clips/talk.mp4')
        ->and(MediaUrl::src('https://cdn.test/talk.mp4'))->toBe('https://cdn.test/talk.mp4')
        ->and(MediaUrl::src('HTTP://cdn.test/talk.mp4'))->toBe('HTTP://cdn.test/talk.mp4')
        // No scheme at all, which is a path and not a host with one missing.
        ->and(MediaUrl::src('//cdn.test/talk.mp4'))->toBe('//cdn.test/talk.mp4');
});

it('refuses a scheme that is not a way of fetching a file', function (): void {
    // The whole of the attack: a `javascript:` or a `data:` in a `src` is what turns a
    // player into a script.
    expect(MediaUrl::src('javascript:alert(1)'))->toBeNull()
        ->and(MediaUrl::src('data:text/html;base64,PHNjcmlwdD4='))->toBeNull()
        ->and(MediaUrl::src('vbscript:msgbox(1)'))->toBeNull()
        // Refused rather than stripped: a browser reads this as `javascript:` and a check
        // that only looks at the front of the string does not.
        ->and(MediaUrl::src("java\nscript:alert(1)"))->toBeNull()
        ->and(MediaUrl::src('/storage/a b.mp4'))->toBeNull()
        ->and(MediaUrl::src(''))->toBeNull()
        ->and(MediaUrl::src(null))->toBeNull();
});

it('guesses the kind from the file, and lets the answer be overruled', function (): void {
    expect(MediaUrl::guess('/clips/talk.MP4'))->toBe('video')
        ->and(MediaUrl::guess('/clips/talk.mp3?token=abc'))->toBe('audio')
        ->and(MediaUrl::guess('/clips/talk'))->toBeNull()
        // What was asked for wins; a file whose ending says nothing is a video, because a
        // video element playing a sound still has working controls and an audio element
        // handed a film plays it invisibly.
        ->and(MediaUrl::kind('audio', '/clips/talk.mp4'))->toBe('audio')
        ->and(MediaUrl::kind('something', '/clips/talk.mp3'))->toBe('audio')
        ->and(MediaUrl::kind(null, '/clips/talk'))->toBe('video');
});

it('keeps a video across the round trip, controls and all', function () use ($render): void {
    $rendered = $render('<video src="/storage/talk.mp4" controls preload="metadata"></video>');

    expect($rendered)->toContain('<video')
        ->and($rendered)->toContain('src="/storage/talk.mp4"')
        ->and($rendered)->toContain('controls')
        ->and($rendered)->toContain('preload="metadata"');
});

it('writes controls whether or not they were there', function () use ($render): void {
    // A player nobody can start is a file nobody can play and nobody can see is there.
    expect($render('<video src="/storage/talk.mp4"></video>'))->toContain('controls');
});

it('never writes autoplay, which the sanitiser would strip anyway', function () use ($render): void {
    expect($render('<video src="/storage/talk.mp4" autoplay muted></video>'))
        ->not->toContain('autoplay');
});

it('reads the address off a source element too', function () use ($render): void {
    // What a hand-written document and most other editors produce.
    expect($render('<video controls><source src="/storage/talk.webm" type="video/webm"></video>'))
        ->toContain('src="/storage/talk.webm"');
});

it('keeps a sound a sound', function () use ($render): void {
    $rendered = $render('<audio src="/storage/talk.mp3" controls></audio>');

    expect($rendered)->toContain('<audio')
        ->and($rendered)->not->toContain('<video');
});

it('drops a poster from a sound, which has nothing to show', function () use ($render): void {
    expect($render('<audio src="/storage/talk.mp3" poster="/storage/cover.jpg"></audio>'))
        ->not->toContain('poster');

    expect($render('<video src="/storage/talk.mp4" poster="/storage/cover.jpg"></video>'))
        ->toContain('poster="/storage/cover.jpg"');
});

it('keeps a loop and a title', function () use ($render): void {
    $rendered = $render('<video src="/storage/talk.mp4" loop title="The talk"></video>');

    expect($rendered)->toContain('loop')
        ->and($rendered)->toContain('title="The talk"');
});

it('renders nothing at all where there is nothing to play', function () use ($render): void {
    // An element with no address is a broken control bar in the middle of the page: the
    // reader sees something is wrong and the author sees nothing.
    expect($render('<video controls></video>'))->not->toContain('<video')
        ->and($render('<video src="javascript:alert(1)"></video>'))->not->toContain('<video')
        ->and($render('<video src="javascript:alert(1)"></video>'))->not->toContain('javascript');
});

it('holds the preload to the three a browser knows', function () use ($render): void {
    expect(MediaUrl::preload('none'))->toBe('none')
        ->and(MediaUrl::preload('everything'))->toBe('metadata')
        ->and(MediaUrl::preload(null))->toBe('metadata')
        ->and($render('<video src="/a.mp4" preload="everything"></video>'))
        ->toContain('preload="metadata"');
});

it('carries the width with the markup rather than leaving it to a class', function () use ($render): void {
    // This package's stylesheet is loaded into the admin panel; the page the content ends
    // up on is somebody else's, where a video with only a class on it is drawn at its own
    // pixel size and overflows the column.
    expect($render('<video src="/a.mp4"></video>'))->toContain('width: 100%')
        ->and($render('<video src="/a.mp4"></video>'))->toContain('fi-arte-media');
});
