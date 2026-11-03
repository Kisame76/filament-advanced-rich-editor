<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichMediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\TreePost;
use League\Flysystem\UnableToCheckFileExistence;

/**
 * `arte:diagnose`: what is wrong in every stored document - files that are gone, links that
 * lead nowhere, what the accessibility check would say - and a failure where something is
 * broken for every reader, so a schedule can mail the output when it matters and stay quiet
 * when it does not.
 */

/**
 * What the command answered and wrote. Read as a whole rather than through
 * `expectsOutputToContain()`, which takes each line once - and an entry, its link and its
 * answer share one.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: string}
 */
function arteDiagnose(array $options = []): array
{
    // Not mocked: `RefreshDatabase` migrates through `artisan()`, and on the first releases of
    // Laravel 11 the mocked output that leaves bound catches every command run after it -
    // `Artisan::output()` then reads nothing.
    test()->withoutMockingConsoleOutput();

    $code = Artisan::call('arte:diagnose', $options);

    return [$code, Artisan::output()];
}

/**
 * The same, asking the links alone.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: int, 1: string}
 */
function arteLinks(array $options = []): array
{
    return arteDiagnose(['--only' => ['links'], ...$options]);
}

beforeEach(function (): void {
    Http::preventStrayRequests();

    Http::fake([
        'gone.test/*' => Http::response('', 404),
        'busy.test/*' => Http::response('', 503),
        '*' => Http::response('', 200),
    ]);
});

it('lists the broken links with where they are written, and fails', function (): void {
    $post = Post::create(['title' => 'Preise', 'content' => '<p><a href="https://gone.test/old">old</a> and <a href="https://example.com/">fine</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(1)
        ->and($output)->toContain("Post #{$post->getKey()} “Preise”")
        ->toContain('https://gone.test/old')
        ->toContain('This address answers 404 Not Found.')
        ->not->toContain('https://example.com/')
        ->toContain('1 broken, 0 not answering, 0 answering with an error, 1 working.');
});

it('succeeds where every link works', function (): void {
    Post::create(['title' => 'Fine', 'content' => '<p><a href="https://example.com/">fine</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('0 broken, 0 not answering, 0 answering with an error, 1 working.');
});

it('lists an error without failing over it', function (): void {
    // A server down for the afternoon is not a link anybody has to fix - but somebody may
    // want to look.
    Post::create(['title' => 'Busy', 'content' => '<p><a href="https://busy.test/">busy</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('This address answers 503 Service Unavailable.');
});

it('asks an address once however many entries link to it', function (): void {
    Post::create(['title' => 'One', 'content' => '<p><a href="https://gone.test/x">x</a></p>']);
    Post::create(['title' => 'Two', 'content' => '<p><a href="https://gone.test/x#part">x</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    // Both links listed, each where it is written, and one answer counted.
    expect($code)->toBe(1)
        ->and($output)->toContain('Asked 1 address, found in 2 entries.')
        ->toContain('“One”')
        ->toContain('“Two”')
        ->toContain('1 broken, 0 not answering');

    // HEAD, then GET to be sure - once for both.
    Http::assertSentCount(2);
});

it('reads every column it is told to', function (): void {
    Post::create(['title' => 'Two columns', 'content' => '<p>none</p>', 'summary' => '<p><a href="https://gone.test/summary">s</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content,summary']]);

    expect($code)->toBe(1)
        ->and($output)->toContain('| summary |');
});

it('reads the columns a model registered when none are named', function (): void {
    RichPost::create(['title' => 'Registered', 'content' => '<p><a href="https://gone.test/">x</a></p>']);

    [$code, $output] = arteLinks(['--model' => [RichPost::class]]);

    expect($code)->toBe(1)
        ->and($output)->toContain('“Registered”');
});

it('reads a document stored as a tree', function (): void {
    TreePost::create(['title' => 'Tree', 'content' => ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'gone', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://gone.test/tree']]]],
        ]],
    ]]]);

    [$code, $output] = arteLinks(['--model' => [TreePost::class.':content']]);

    expect($code)->toBe(1)
        ->and($output)->toContain('https://gone.test/tree');
});

it('reads the places the documents key names when no model is named, and the key they were kept under before', function (string $key): void {
    config()->set("filament-advanced-rich-editor.{$key}", [Post::class => ['content']]);

    Post::create(['title' => 'Configured', 'content' => '<p><a href="https://gone.test/">x</a></p>']);

    [$code, $output] = arteLinks();

    expect($code)->toBe(1)
        ->and($output)->toContain('“Configured”');
})->with(['documents', 'media_library.documents']);

it('finds the models that registered rich content under app/Models', function (): void {
    // Written out at run time rather than kept in the suite: a class in the `App\Models`
    // namespace has no place in this package's autoloader.
    $app = sys_get_temp_dir().'/arte-diagnose-'.uniqid();
    mkdir($app.'/Models', recursive: true);
    file_put_contents($app.'/Models/LinkStory.php', '<?php namespace App\Models; class LinkStory extends \\'.RichPost::class.' {}');

    require_once $app.'/Models/LinkStory.php';

    // Asked before the path moves: Laravel reads the namespace off composer.json by matching
    // the app path against it, and remembers the answer.
    app()->getNamespace();
    app()->useAppPath($app);

    RichPost::create(['title' => 'Discovered', 'content' => '<p><a href="https://gone.test/">x</a></p>']);

    try {
        [$code, $output] = arteLinks();
    } finally {
        unlink($app.'/Models/LinkStory.php');
        rmdir($app.'/Models');
        rmdir($app);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain('LinkStory #');
});

it('says so where there is nothing to read', function (): void {
    [$code, $output] = arteLinks();

    expect($code)->toBe(1)
        ->and($output)->toContain('No documents to read.');
});

it('refuses a model that is not one', function (): void {
    [$code, $output] = arteLinks(['--model' => ['App\Models\Nothing']]);

    expect($code)->toBe(1)
        ->and($output)->toContain('App\Models\Nothing is not a model.');
});

it('leaves out what is not a web address', function (): void {
    Post::create(['title' => 'Mail', 'content' => '<p><a href="mailto:a@example.com">mail</a> <a href="#top">top</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('No links to check.');

    Http::assertNothingSent();
});

it('says when not one address answered', function (): void {
    Http::fake(['*' => failedConnection('cURL error 7: Failed to connect')]);

    Post::create(['title' => 'Offline', 'content' => '<p><a href="https://one.test/">1</a> <a href="https://two.test/">2</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('0 broken, 2 not answering')
        ->toContain('Not one address answered.');
});

it('says when the site itself did not answer at app.url', function (): void {
    // A container or a build server is not the site, and `app.url` may point at nobody there.
    config()->set('app.url', 'http://localhost:8123');

    Http::fake([
        'localhost:8123/*' => failedConnection('cURL error 7: Failed to connect to localhost port 8123'),
        '*' => Http::response('', 200),
    ]);

    Post::create(['title' => 'Internal', 'content' => '<p><a href="/about">about</a> <a href="https://example.com/">out</a></p>']);

    [$code, $output] = arteLinks(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Not one link to this site answered at http://localhost:8123.')
        ->not->toContain('Not one address answered.');
});

it('refuses a check it does not have', function (): void {
    [$code, $output] = arteDiagnose(['--only' => ['spelling'], '--model' => [Post::class.':content']]);

    expect($code)->toBe(1)
        ->and($output)->toContain('spelling is not a check. Pass files, links, accessibility.');
});

it('runs every check unless told otherwise', function (): void {
    Post::create(['title' => 'All', 'content' => '<p>Nothing wrong.</p>']);

    [$code, $output] = arteDiagnose(['--model' => [Post::class.':content']]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Files')
        ->toContain('No files to look for.')
        ->toContain('No links to check.')
        ->toContain('Accessibility')
        ->toContain('Read 1 document: nothing to report.');
});

describe('files', function (): void {
    beforeEach(function (): void {
        config()->set('filament.default_filesystem_disk', 'public');

        Storage::fake('public');
        Storage::disk('public')->put('attachments/here.png', 'png');
    });

    it('lists a file a document points at that is gone, by the name it was saved with, and fails', function (): void {
        // A model that registered its rich content says where its files are - here, nowhere
        // but Filament's default disk - exactly as it says it to every page rendering them.
        RichPost::create(['title' => 'Pictures', 'content' => '<p><img src="/storage/attachments/here.png" data-id="attachments/here.png" alt="Here"></p>'
            .'<p><img src="/storage/attachments/gone.png?v=2" data-id="attachments/gone.png" alt="Gone"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichPost::class]]);

        expect($code)->toBe(1)
            ->and($output)->toContain('Looked for 2 files.')
            ->toContain('| gone.png |')
            ->not->toContain('here.png')
            ->toContain('1 missing, 1 found.');
    });

    it('succeeds where every file is there, and reads a stored tree', function (): void {
        RichPost::create(['title' => 'Fine', 'content' => '<p><img src="/storage/attachments/here.png" data-id="attachments/here.png" alt="Here"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichPost::class]]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Looked for 1 file.')
            ->not->toContain('missing');
    });

    it('counts an upload that never finished as gone', function (): void {
        RichPost::create(['title' => 'Pending', 'content' => '<p><img src="blob:x" data-id="arte-3f2a" alt="Pending"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichPost::class]]);

        expect($code)->toBe(1)
            ->and($output)->toContain('1 missing, 0 found.');
    });

    it('asks where the files are kept when not one of them was found', function (): void {
        RichPost::create(['title' => 'Elsewhere', 'content' => '<p><img src="/a.png" data-id="9b1c-a" alt="A"><img src="/b.png" data-id="9b1c-b" alt="B"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichPost::class]]);

        expect($code)->toBe(1)
            ->and($output)->toContain('Not one file in '.RichPost::class.'.content was found.');
    });

    it('finds a file another entry uploaded to a shared media collection, and not one that was deleted', function (): void {
        requires('spatie/laravel-medialibrary');

        config()->set('media-library.disk_name', 'public');

        // Read outside a form, an entry resolves its own files only - but a library shared
        // between entries is the default, and a picture from it is not gone for being somebody
        // else's upload.
        $media = RichMediaPost::create(['title' => 'Owner', 'content' => ''])
            ->addMediaFromString(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='))
            ->usingFileName('shared.png')
            ->toMediaCollection('rich-editor');

        RichMediaPost::create(['title' => 'Borrower', 'content' => "<p><img src=\"/storage/1/shared.png\" data-id=\"{$media->uuid}\" alt=\"Shared\"></p>"
            .'<p><img src="/storage/9/deleted.png" data-id="00000000-dead-4bad-8000-000000000000" alt="Deleted"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichMediaPost::class]]);

        expect($code)->toBe(1)
            ->and($output)->toContain('| deleted.png |')
            ->not->toContain('| shared.png |')
            ->toContain('1 missing, 1 found.');
    });

    it('calls a file it could not ask about unknown rather than gone, and does not fail over it', function (): void {
        // A disk that does not answer on the night of a scheduled run is not a list of every
        // file on it, mailed to the editors as deleted.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andThrow(UnableToCheckFileExistence::forLocation('attachments/here.png'));
        Storage::set('public', $disk);

        RichPost::create(['title' => 'Outage', 'content' => '<p><img src="/storage/attachments/here.png" data-id="attachments/here.png" alt="Here"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [RichPost::class]]);

        expect($code)->toBe(0)
            ->and($output)->toContain('1 file could not be looked for: where they are kept did not answer.')
            ->not->toContain('missing');
    });

    it('passes over a document it cannot read, and reads the ones after it', function (): void {
        RichPost::create(['title' => 'First', 'content' => '<p>Fine.</p>']);
        RichPost::create(['title' => 'Second', 'content' => '<h2>Two</h2><h4>Four</h4>']);

        // The first document read throws; the second is still asked.
        $calls = 0;
        AdvancedRichContentRenderer::extendWith(new class($calls) implements RichContentPlugin
        {
            public function __construct(private int &$calls) {}

            public function getTipTapPhpExtensions(): array
            {
                if ($this->calls++ === 0) {
                    throw new RuntimeException('Unreadable.');
                }

                return [];
            }

            public function getTipTapJsExtensions(): array
            {
                return [];
            }

            public function getEditorTools(): array
            {
                return [];
            }

            public function getEditorActions(): array
            {
                return [];
            }
        });

        try {
            [$code, $output] = arteDiagnose(['--only' => ['accessibility'], '--model' => [RichPost::class]]);
        } finally {
            AdvancedRichContentRenderer::forgetExtensions();
        }

        expect($code)->toBe(0)
            ->and($output)->toContain('Heading level skipped: 1.')
            ->toContain('1 document could not be read and was passed over.');
    });

    it('says which files it could not look for, rather than calling them gone', function (): void {
        // A plain model says nothing about where its files are, and a guess would be a list
        // of files that are perfectly well where somebody else keeps them.
        Post::create(['title' => 'Plain', 'content' => '<p><img src="/storage/x.png" data-id="attachments/x.png" alt="X"></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['files'], '--model' => [Post::class.':content']]);

        expect($code)->toBe(0)
            ->and($output)->toContain('Files in '.Post::class.'.content were not looked for');
    });
});

describe('accessibility', function (): void {
    it('lists what the check would say about each document, without failing over it', function (): void {
        // An archive written before anybody asked for alt text would otherwise fail every run.
        Post::create(['title' => 'Unchecked', 'content' => '<h2>Prices</h2><h4>Small print</h4>'
            .'<p><img src="/storage/cat.png"> <a href="https://example.com/report">click here</a></p>']);

        [$code, $output] = arteDiagnose(['--only' => ['accessibility'], '--model' => [Post::class.':content']]);

        expect($code)->toBe(0)
            ->and($output)->toMatch('/\| Image without alt text\s+\| \/storage\/cat\.png/')
            ->toMatch('/\| Heading level skipped\s+\| Small print/')
            ->toMatch('/\| Link text says nothing\s+\| click here/')
            ->toContain('Heading level skipped: 1, Image without alt text: 1, Link text says nothing: 1.');

        Http::assertNothingSent();
    });

    it('leaves out the rules the configuration leaves out', function (): void {
        config()->set('filament-advanced-rich-editor.accessibility.rules', ['skipped_heading']);

        Post::create(['title' => 'Some', 'content' => '<h2>Two</h2><h4>Four</h4><p><img src="/storage/cat.png"></p>']);

        [, $output] = arteDiagnose(['--only' => ['accessibility'], '--model' => [Post::class.':content']]);

        expect($output)->toContain('Heading level skipped: 1.')
            ->not->toContain('Image without alt text');
    });

    it('reads a stored tree like markup', function (): void {
        TreePost::create(['title' => 'Tree', 'content' => ['type' => 'doc', 'content' => [
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Q1']]]]],
            ]]]],
        ]]]);

        [, $output] = arteDiagnose(['--only' => ['accessibility'], '--model' => [TreePost::class.':content']]);

        expect($output)->toContain('Table without a header row: 1.');
    });
});
