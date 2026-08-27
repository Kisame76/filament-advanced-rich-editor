<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Support\Facades\Cache;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Fingerprint;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

function cachedRenderer(string $html = '<p>Ein Absatz</p>'): AdvancedRichContentRenderer
{
    return AdvancedRichContentRenderer::make($html)->cached();
}

/**
 * A provider that answers with ordinary URLs, which is what every real one does.
 */
function permanentAttachmentProvider(): FileAttachmentProvider
{
    return new class implements FileAttachmentProvider
    {
        public function attribute(RichContentAttribute $attribute): static
        {
            return $this;
        }

        public function getFileAttachmentUrl(mixed $file): ?string
        {
            return '/storage/'.$file;
        }

        public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
        {
            return null;
        }

        public function getDefaultFileAttachmentVisibility(): ?string
        {
            return 'private';
        }

        public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
        {
            return false;
        }

        public function cleanUpFileAttachments(array $exceptIds): void {}
    };
}

it('keeps nothing until a render asks for it', function (): void {
    $renderer = AdvancedRichContentRenderer::make('<p>Ein Absatz</p>');

    $renderer->toHtml();

    expect(Cache::has($renderer->getRenderCacheKey('html')))->toBeFalse();
});

it('answers a second render out of the store', function (): void {
    $renderer = cachedRenderer();

    expect($renderer->toHtml())->toBe('<p>Ein Absatz</p>');

    // Written over from outside, so that a second identical answer can only have come
    // from the store and not from rendering the same document again.
    Cache::put($renderer->getRenderCacheKey('html'), '<p>Von woanders</p>', 60);

    expect($renderer->toHtml())->toBe('<p>Von woanders</p>');
});

it('keeps the markup, the text and the Markdown apart', function (): void {
    // One document is three answers, and a single key would hand whoever asked second
    // whatever the first one wanted.
    $renderer = cachedRenderer();

    $renderer->toHtml();
    $renderer->toText();
    $renderer->toMarkdown();

    expect(Cache::get($renderer->getRenderCacheKey('html')))->toBe('<p>Ein Absatz</p>')
        ->and(Cache::get($renderer->getRenderCacheKey('text')))->toBe('Ein Absatz')
        ->and(Cache::get($renderer->getRenderCacheKey('markdown.'.Fingerprint::of([]))))->toBe('Ein Absatz');
});

it('tells two Markdown conversions apart by the options they were given', function (): void {
    $renderer = cachedRenderer('<h1>Titel</h1>');

    expect($renderer->toMarkdown())->toBe('# Titel')
        ->and($renderer->toMarkdown(['header_style' => 'setext']))->toBe("Titel\n=====");
});

it('gives two documents two keys', function (): void {
    expect(cachedRenderer('<p>Eins</p>')->getRenderCacheKey('html'))
        ->not->toBe(cachedRenderer('<p>Zwei</p>')->getRenderCacheKey('html'));
});

it('gives the same document under the same settings the same key', function (): void {
    expect(cachedRenderer()->getRenderCacheKey('html'))
        ->toBe(cachedRenderer()->getRenderCacheKey('html'));
});

it('gives a document rendered differently a different key', function (string $method, array $arguments): void {
    $plain = cachedRenderer()->getRenderCacheKey('html');
    $configured = cachedRenderer()->{$method}(...$arguments)->getRenderCacheKey('html');

    expect($configured)->not->toBe($plain);
})->with([
    'anchors' => ['anchorHeadings', []],
    'a code theme' => ['highlightCode', ['github-dark']],
    'plain links' => ['linkAttributes', [false]],
    'named styles' => ['styles', [[['key' => 'lead', 'label' => 'Lead', 'class' => 'lead', 'scope' => 'block', 'types' => ['paragraph']]]]],
    'another disk' => ['fileAttachmentsDisk', ['s3']],
    'another visibility' => ['fileAttachmentsVisibility', ['private']],
    'merge tags' => ['mergeTags', [['name' => 'Ada']]],
    'link protocols' => ['linkProtocols', [['https']]],
]);

it('tells two code themes apart', function (): void {
    expect(cachedRenderer()->highlightCode('github-dark')->getRenderCacheKey('html'))
        ->not->toBe(cachedRenderer()->highlightCode('github-light')->getRenderCacheKey('html'));
});

it('tells two anchor positions apart', function (): void {
    expect(cachedRenderer()->anchorHeadings(position: 'before')->getRenderCacheKey('html'))
        ->not->toBe(cachedRenderer()->anchorHeadings(position: 'after')->getRenderCacheKey('html'));
});

it('tells two node processors apart, as long as they are written in two places', function (): void {
    $one = cachedRenderer()->processNodesUsing(function (object &$node): void {})->getRenderCacheKey('html');
    $two = cachedRenderer()->processNodesUsing(function (object &$node): void {})->getRenderCacheKey('html');

    expect($one)->not->toBe($two);
});

it('takes the key it is handed instead of working one out', function (): void {
    $renderer = cachedRenderer()->cacheKey('post-1-2026-08-27');

    expect($renderer->getRenderCacheKey('html'))->toBe('arte.render.html.post-1-2026-08-27');
});

it('resolves a key given as a closure', function (): void {
    expect(cachedRenderer()->cacheKey(fn (): string => 'spät')->getRenderCacheKey('html'))
        ->toBe('arte.render.html.spät');
});

it('carries the configured prefix', function (): void {
    config()->set('filament-advanced-rich-editor.render_cache.prefix', 'blog.render');

    expect(cachedRenderer()->getRenderCacheKey('text'))->toStartWith('blog.render.text.');
});

it('can be switched back off', function (): void {
    $renderer = cachedRenderer()->cached(false);

    $renderer->toHtml();

    expect(Cache::has($renderer->getRenderCacheKey('html')))->toBeFalse();
});

it('keeps a render for the configured lifetime', function (): void {
    config()->set('filament-advanced-rich-editor.render_cache.ttl', 300);

    expect(cachedRenderer()->fileAttachmentsVisibility('public')->getRenderCacheTtl())->toBe(300);
});

it('lets the call name its own lifetime', function (): void {
    expect(AdvancedRichContentRenderer::make('<p>x</p>')->cached(120)->fileAttachmentsVisibility('public')->getRenderCacheTtl())
        ->toBe(120);
});

it('will not keep a page of temporary URLs longer than the URLs last', function (): void {
    config()->set('filament-advanced-rich-editor.render_cache.ttl', 86400);
    config()->set('filament.temporary_file_url_expiry_minutes', 30);

    expect(cachedRenderer()->fileAttachmentsVisibility('private')->getRenderCacheTtl())->toBe(1800);
});

it('leaves the lifetime alone where an attachment provider hands out ordinary URLs', function (): void {
    config()->set('filament-advanced-rich-editor.render_cache.ttl', 86400);

    expect(cachedRenderer()->fileAttachmentsVisibility('private')->fileAttachmentProvider(permanentAttachmentProvider())->getRenderCacheTtl())
        ->toBe(86400);
});

it('keeps a render for ever where the config names no lifetime', function (): void {
    config()->set('filament-advanced-rich-editor.render_cache.ttl', null);

    $renderer = cachedRenderer()->fileAttachmentsVisibility('public');

    expect($renderer->getRenderCacheTtl())->toBeNull();

    $renderer->toHtml();

    expect(Cache::get($renderer->getRenderCacheKey('html')))->toBe('<p>Ein Absatz</p>');
});

it('remembers the excerpt through the text it is cut from', function (): void {
    $renderer = cachedRenderer('<p>Ein längerer Absatz, der abgeschnitten wird.</p>');

    expect($renderer->toExcerpt(10))->toBe('Ein…');

    Cache::put($renderer->getRenderCacheKey('text'), 'Etwas ganz anderes steht hier', 60);

    expect($renderer->toExcerpt(10))->toBe('Etwas ganz…');
});
