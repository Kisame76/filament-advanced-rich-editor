<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfImages;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaLibraryItem;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Where a picture in a printed document may come from. The engine fetches nothing; this
 * class decides what is read, and every case here is one of its limits.
 */
beforeEach(function (): void {
    config()->set('app.url', 'https://site.test');
    config()->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => 'https://site.test/storage', 'visibility' => 'public']);
    config()->set('filesystems.disks.secrets', ['driver' => 'local', 'root' => storage_path('app/secrets'), 'url' => 'https://site.test/secrets']);

    Storage::fake('public');
    Storage::fake('secrets');

    $this->png = (string) file_get_contents(__DIR__.'/../../Fixtures/media/harbour.png');
    $this->dataUri = 'data:image/png;base64,'.base64_encode($this->png);

    Storage::disk('public')->put('pictures/harbour.png', $this->png);
    Storage::disk('secrets')->put('harbour.png', $this->png);

    Http::preventStrayRequests();
});

it('keeps a picture already inline', function (): void {
    expect((new PdfImages)->embed($this->dataUri))->toBe($this->dataUri);
});

it('refuses an inline address that is not a picture', function (string $src): void {
    expect((new PdfImages)->embed($src))->toBeNull();
})->with([
    'html' => ['data:text/html;base64,PHNjcmlwdD4='],
    'no type' => ['data:,hello'],
]);

it('reads a picture on a public disk by the address the disk gives it', function (string $src): void {
    expect((new PdfImages)->embed($src))->toBe($this->dataUri);
})->with([
    'absolute' => ['https://site.test/storage/pictures/harbour.png'],
    'relative to the site' => ['/storage/pictures/harbour.png'],
    'with a query' => ['/storage/pictures/harbour.png?v=3'],
]);

it('reads a file name the address spells with escapes', function (): void {
    Storage::disk('public')->put('pictures/the harbour.png', $this->png);

    expect((new PdfImages)->embed('/storage/pictures/the%20harbour.png'))->toBe($this->dataUri);
});

it('reads against the base it is given rather than the application url', function (): void {
    config()->set('filesystems.disks.public.url', 'https://cdn.site.test/storage');

    expect((new PdfImages(baseUrl: 'https://cdn.site.test'))->embed('/storage/pictures/harbour.png'))->toBe($this->dataUri)
        ->and((new PdfImages)->embed('/storage/pictures/harbour.png'))->toBeNull();
});

it('does not read a private disk by address, which would go round whoever guards it', function (): void {
    expect((new PdfImages)->embed('https://site.test/secrets/harbour.png'))->toBeNull();
});

it('does not climb out of a disk', function (): void {
    expect((new PdfImages)->embed('/storage/../secrets/harbour.png'))->toBeNull()
        ->and((new PdfImages)->embed('/storage/%2e%2e/%2e%2e/.env'))->toBeNull();
});

it('reads nothing that is not an address on the web', function (string $src): void {
    expect((new PdfImages)->embed($src))->toBeNull();
})->with([
    'a file path' => ['file:///etc/passwd'],
    'javascript' => ['javascript:alert(1)'],
    'a phar' => ['phar://archive.phar/picture.png'],
]);

it('does not fetch a picture from a host nobody named', function (): void {
    expect((new PdfImages)->embed('https://elsewhere.example/a.png'))->toBeNull();

    Http::assertNothingSent();
});

it('fetches a picture from a host the project named', function (): void {
    Http::fake(['cdn.example/*' => Http::response($this->png, 200, ['Content-Type' => 'image/png'])]);

    expect((new PdfImages(hosts: ['CDN.example']))->embed('https://cdn.example/a.png'))->toBe($this->dataUri);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cdn.example/a.png');
});

it('does not follow a named host\'s redirect to somewhere else', function (): void {
    Http::fake([
        'cdn.example/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        '169.254.169.254/*' => Http::response($this->png),
    ]);

    expect((new PdfImages(hosts: ['cdn.example']))->embed('https://cdn.example/a.png'))->toBeNull();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254.169.254'));
});

it('gives up on a host that fails or does not answer', function (): void {
    Http::fake([
        'down.example/*' => Http::response('', 500),
        'slow.example/*' => fn () => throw new ConnectionException('timed out'),
    ]);

    $images = new PdfImages(hosts: ['down.example', 'slow.example']);

    expect($images->embed('https://down.example/a.png'))->toBeNull()
        ->and($images->embed('https://slow.example/a.png'))->toBeNull();
});

it('refuses a file larger than the limit', function (): void {
    Http::fake(['cdn.example/*' => Http::response($this->png)]);

    $images = new PdfImages(hosts: ['cdn.example'], maxBytes: strlen($this->png) - 1);

    expect($images->embed('/storage/pictures/harbour.png'))->toBeNull()
        ->and($images->embed('https://cdn.example/a.png'))->toBeNull();
});

it('refuses a file that is not a picture an engine draws, whatever it is called', function (): void {
    Storage::disk('public')->put('pictures/page.png', '<html><body>Not a picture</body></html>');
    Storage::disk('public')->put('pictures/scan.tiff', "II*\0".str_repeat("\0", 64));

    expect((new PdfImages)->embed('/storage/pictures/page.png'))->toBeNull()
        ->and((new PdfImages)->embed('/storage/pictures/scan.tiff'))->toBeNull();
});

it('recognises a drawing without an XML declaration as one', function (): void {
    Storage::disk('public')->put('pictures/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');

    expect((new PdfImages)->embed('/storage/pictures/mark.svg'))->toStartWith('data:image/svg+xml;base64,');
});

it('reads an attachment by its id from the render\'s disk, private ones included', function (): void {
    $renderer = AdvancedRichContentRenderer::make('<p>x</p>')->fileAttachmentsDisk('secrets');

    expect((new PdfImages(renderer: $renderer))->embed('https://site.test/expired-signed-url', 'harbour.png'))->toBe($this->dataUri);
});

it('falls back to the address where the id finds no file', function (): void {
    $renderer = AdvancedRichContentRenderer::make('<p>x</p>')->fileAttachmentsDisk('secrets');

    expect((new PdfImages(renderer: $renderer))->embed('/storage/pictures/harbour.png', 'gone.png'))->toBe($this->dataUri);
});

it('asks a provider for the file where the render has one', function (): void {
    requires('spatie/laravel-medialibrary');

    config()->set('media-library.disk_name', 'public');

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $media = $post->addMediaFromString($this->png)->usingFileName('harbour.png')->toMediaCollection('rich-editor');

    $renderer = AdvancedRichContentRenderer::make('<p>x</p>')
        ->fileAttachmentProvider(SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')->recordUsing(fn (): MediaPost => $post));

    expect((new PdfImages(renderer: $renderer))->embed(null, $media->uuid))->toBe($this->dataUri);
});

it('lets the provider keep a file outside the record\'s scope out of the PDF', function (): void {
    requires('spatie/laravel-medialibrary');

    config()->set('media-library.disk_name', 'public');

    $stranger = MediaLibraryItem::create(['title' => 'Someone else', 'content' => '']);
    $secret = $stranger->addMediaFromString($this->png)->usingFileName('secret.png')->toMediaCollection('private-collection');

    $renderer = AdvancedRichContentRenderer::make('<p>x</p>')
        ->fileAttachmentProvider(
            SpatieMediaLibraryFileAttachmentProvider::make('rich-editor')
                ->recordUsing(fn (): MediaPost => MediaPost::create(['title' => 'Mine', 'content' => '']))
                ->ownerUsing(fn (): string => 'content'),
        );

    expect((new PdfImages(renderer: $renderer))->embed(null, $secret->uuid))->toBeNull();
});

it('reads nothing by id from a provider that cannot hand over files', function (): void {
    $provider = new class implements FileAttachmentProvider
    {
        public function attribute(RichContentAttribute $attribute): static
        {
            return $this;
        }

        public function getFileAttachmentUrl(mixed $file): string
        {
            return 'https://elsewhere.example/'.$file;
        }

        public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
        {
            return null;
        }

        public function getDefaultFileAttachmentVisibility(): ?string
        {
            return null;
        }

        public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
        {
            return false;
        }

        public function cleanUpFileAttachments(array $exceptIds): void {}
    };

    $renderer = AdvancedRichContentRenderer::make('<p>x</p>')->fileAttachmentProvider($provider);

    expect($renderer->getFileAttachmentContents('harbour.png'))->toBeNull();
});

it('reads no attachment id that is not a name', function (mixed $id): void {
    expect(AdvancedRichContentRenderer::make('<p>x</p>')->fileAttachmentsDisk('secrets')->getFileAttachmentContents($id))->toBeNull();
})->with([
    'empty' => [''],
    'a number' => [7],
    'nothing' => [null],
]);
