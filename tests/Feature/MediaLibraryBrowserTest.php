<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\OtherMediaPost;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The media browser over a media library collection.
 *
 * Everything here turns on one rule: the pool the grid lists from is the pool a stored
 * `data-id` may resolve against. Most of these tests exist to prove the two halves of that
 * sentence stay equal - a grid that shows more than the lookup allows is a dead thumbnail,
 * and a lookup that allows more than the grid shows is a hole.
 */
beforeEach(function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    Storage::fake('public');

    config()->set('media-library.disk_name', 'public');

    $this->post = MediaPost::create(['title' => 'Post', 'content' => '']);
    $this->other = MediaPost::create(['title' => 'Other', 'content' => '']);

    // Real bytes, not the string "a picture": the media library sniffs the mime type off the
    // file, and the browser lists images. A text file called `.jpg` is a text file, and the
    // pool is right to drop it - which would make every assertion here pass for the wrong
    // reason.
    $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $this->attach = function (MediaPost $record, string $collection = 'rich-editor', string $name = 'picture', ?string $contents = null, string $extension = 'png'): Media {
        return $record
            ->addMediaFromString($contents ?? $this->png)
            ->usingFileName($name.'-'.uniqid().'.'.$extension)
            ->usingName($name)
            ->toMediaCollection($collection);
    };

    $this->source = fn (?Closure $poolQuery = null, ?MediaPost $record = null, string $scope = 'collection', ?array $acceptedMimeTypes = null): SpatieMediaSource => SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        poolQuery: $poolQuery,
        getRecordUsing: fn (): MediaPost => $record ?? $this->post,
        acceptedMimeTypes: $acceptedMimeTypes,
        scope: $scope,
    );

    $this->field = function (?Closure $poolQuery = null): AdvancedRichEditor {
        $field = AdvancedRichEditor::make('content')
            ->spatieMediaLibrary('rich-editor')
            ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($this->post));

        return $poolQuery ? $field->mediaLibraryQuery($poolQuery) : $field;
    };
});

it('lists pictures newest first', function (): void {
    $first = ($this->attach)($this->post);
    $second = ($this->attach)($this->post);

    $page = ($this->source)()->page();

    expect(array_column($page['items'], 'id'))
        ->toBe([(string) $second->uuid, (string) $first->uuid])
        ->and($page['hasMore'])->toBeFalse()
        ->and($page['folders'])->toBe([]);
});

it('lists the pictures of every record', function (): void {
    // The whole point of the browser: a picture uploaded for one article is exactly the picture
    // the next article wants, and making somebody upload it again costs a second copy on disk
    // for nothing.
    $mine = ($this->attach)($this->post);
    $theirs = ($this->attach)($this->other);

    expect(array_column(($this->source)()->page()['items'], 'id'))
        ->toContain((string) $mine->uuid, (string) $theirs->uuid);
});

it('narrows to one record when it is told to', function (): void {
    // For content whose pictures have no business being seen from another record.
    $mine = ($this->attach)($this->post);
    ($this->attach)($this->other);

    expect(array_column(($this->source)(scope: 'record')->page()['items'], 'id'))
        ->toBe([(string) $mine->uuid]);
});

it('reaches across models, because the collection is the library', function (): void {
    // An article and a post that both upload to `rich-editor` are drawing from one pool. Making
    // the post fetch a picture the article already has would cost a second copy on disk for
    // nothing, and that is the whole point of the browser.
    $elsewhere = OtherMediaPost::create(['title' => 'Another model', 'content' => '']);

    $theirs = $elsewhere->addMediaFromString($this->png)
        ->usingFileName('from-another-model.png')
        ->toMediaCollection('rich-editor');

    expect(array_column(($this->source)()->page()['items'], 'id'))
        ->toContain((string) $theirs->uuid);
});

it('stops at the collection', function (): void {
    // Separate libraries are separate collections - that is what a collection is for - and the
    // boundary is also what a stored `data-id` may not cross.
    $outside = ($this->attach)($this->post, collection: 'somewhere-else');

    $source = ($this->source)();

    expect(($this->source)()->page()['items'])->toBe([])
        ->and($source->has((string) $outside->uuid))->toBeFalse();
});

it('narrows to one model when it is told to', function (): void {
    $mine = ($this->attach)($this->post);

    $elsewhere = OtherMediaPost::create(['title' => 'Another model', 'content' => '']);
    $theirs = $elsewhere->addMediaFromString($this->png)
        ->usingFileName('from-another-model.png')
        ->toMediaCollection('rich-editor');

    $source = ($this->source)(scope: 'model');

    expect(array_column($source->page()['items'], 'id'))->toBe([(string) $mine->uuid])
        ->and($source->has((string) $theirs->uuid))->toBeFalse();
});

it('does not list what is not a picture', function (): void {
    // The image dialog inserts an `<img>`; offering a PDF there is offering a broken image.
    ($this->attach)($this->post, name: 'notes', contents: '%PDF-1.4 not a picture', extension: 'pdf');
    $image = ($this->attach)($this->post);

    expect(array_column(($this->source)()->page()['items'], 'id'))->toBe([(string) $image->uuid]);
});

it('searches the name and the file name', function (): void {
    $harbour = ($this->attach)($this->post, name: 'Hamburger Hafen');
    ($this->attach)($this->post, name: 'Something else');

    expect(array_column(($this->source)()->page(search: 'hafen')['items'], 'id'))
        ->toBe([(string) $harbour->uuid]);

    // The stored file name carries the uniqid, so this proves the second column is searched
    // rather than merely that the first one matched twice.
    expect(array_column(($this->source)()->page(search: $harbour->file_name)['items'], 'id'))
        ->toBe([(string) $harbour->uuid]);
});

it('finds a file whose name holds a LIKE wildcard', function (): void {
    // Camera file names are full of underscores, and an underscore is a single-character
    // wildcard in SQL. Escaping it needs an ESCAPE clause, which cannot be written portably -
    // the literal that means one backslash differs between MySQL and Postgres, and SQLite adds
    // no clause of its own - so an escaped term simply matched nothing there. Filament's own
    // table search does not escape either: the term is a pattern, which over-matches at worst.
    $camera = ($this->attach)($this->post, name: 'IMG_2043');
    ($this->attach)($this->post, name: 'Something else');

    expect(array_column(($this->source)()->page(search: 'IMG_2043')['items'], 'id'))
        ->toBe([(string) $camera->uuid]);
});

it('understands a wildcard in the accepted types', function (): void {
    // `image/*` is a value Filament accepts on `fileAttachmentsAcceptedFileTypes()` and Laravel
    // validates against, so a field configured that way is configured correctly - and matching
    // the list exactly turned its browser permanently, silently empty.
    $image = ($this->attach)($this->post);

    expect(array_column(($this->source)(acceptedMimeTypes: ['image/*'])->page()['items'], 'id'))
        ->toBe([(string) $image->uuid])
        ->and(array_column(($this->source)(acceptedMimeTypes: ['image/png'])->page()['items'], 'id'))
        ->toBe([(string) $image->uuid])
        // An exact type that does not match still narrows to nothing, as it always did.
        ->and(($this->source)(acceptedMimeTypes: ['image/webp'])->page()['items'])->toBe([]);
});

it('refuses a pool query that hands back something other than the query', function (): void {
    // Failing open here would be the worst possible direction: the pool is also what a stored
    // `data-id` may resolve to, so a closure that forgets its `return` would quietly widen the
    // browser *and* the lookup to every image in the media table.
    $source = ($this->source)(poolQuery: fn () => 'not a query');

    expect(fn () => $source->page())->toThrow(RuntimeException::class);
});

it('reports another page without counting the whole pool', function (): void {
    foreach (range(1, 3) as $ignored) {
        ($this->attach)($this->post);
    }

    $first = ($this->source)()->page(page: 1, perPage: 2);
    $second = ($this->source)()->page(page: 2, perPage: 2);

    expect($first['items'])->toHaveCount(2)
        ->and($first['hasMore'])->toBeTrue()
        ->and($second['items'])->toHaveCount(1)
        ->and($second['hasMore'])->toBeFalse();
});

it('carries a URL for every picture it lists', function (): void {
    ($this->attach)($this->post);

    $item = ($this->source)()->page()['items'][0];

    expect($item['url'])->toBeString()->not->toBeEmpty()
        ->and($item['thumbnail'])->toBe($item['url'])
        ->and($item['mime'])->toBe('image/png');
});

it('answers `has` for what it lists and refuses the rest', function (): void {
    // The two halves of the one rule: what the grid offers is what a stored id may point at.
    $mine = ($this->attach)($this->post);
    $theirs = ($this->attach)($this->other);
    $elsewhere = ($this->attach)($this->post, collection: 'somewhere-else');

    $source = ($this->source)();

    expect($source->has((string) $mine->uuid))->toBeTrue()
        ->and($source->has((string) $theirs->uuid))->toBeTrue()
        ->and($source->has((string) $elsewhere->uuid))->toBeFalse()
        ->and($source->has('not-a-uuid'))->toBeFalse()
        ->and($source->has(null))->toBeFalse();

    // And narrowed to the record, the neighbour's picture goes back out of reach.
    expect(($this->source)(scope: 'record')->has((string) $theirs->uuid))->toBeFalse();
});

it('lists across records once a pool query says so', function (): void {
    $shared = ($this->attach)($this->other, collection: 'library');

    $source = ($this->source)(fn (Builder $query): Builder => $query->where('collection_name', 'library'));

    expect(array_column($source->page()['items'], 'id'))->toBe([(string) $shared->uuid])
        ->and($source->has((string) $shared->uuid))->toBeTrue()
        ->and($source->isRecordScoped())->toBeFalse();
});

it('resolves a picture from the pool that the record does not own', function (): void {
    // The whole point of the shared library: an image uploaded on one article, shown on
    // another, with one file behind both.
    $shared = ($this->attach)($this->other, collection: 'library');

    $provider = ($this->field)(fn (Builder $query): Builder => $query->where('collection_name', 'library'))
        ->getFileAttachmentProvider();

    expect($provider->getFileAttachmentUrl((string) $shared->uuid))->toBeString()->not->toBeEmpty();
});

it('refuses to resolve a picture the pool does not hold', function (): void {
    // A hand-edited `data-id`. The pool is opened onto `library`, so media sitting in some
    // other collection on some other record stays unreachable.
    $hidden = ($this->attach)($this->other, collection: 'private-notes');

    $provider = ($this->field)(fn (Builder $query): Builder => $query->where('collection_name', 'library'))
        ->getFileAttachmentProvider();

    expect($provider->getFileAttachmentUrl((string) $hidden->uuid))->toBeNull();
});

it('resolves a neighbour\'s picture, because the browser offers it', function (): void {
    // Widening what is listed and widening what may be resolved is one act. A picture the grid
    // shows but the lookup refuses would be a dead thumbnail.
    $theirs = ($this->attach)($this->other);

    $provider = ($this->field)()->getFileAttachmentProvider();

    expect($provider->getFileAttachmentUrl((string) $theirs->uuid))->toBeString()->not->toBeEmpty()
        ->and(($this->source)()->isRecordScoped())->toBeFalse()
        ->and(($this->source)(scope: 'record')->isRecordScoped())->toBeTrue();
});

it('still resolves the record\'s own pictures while a pool is open', function (): void {
    // Widening the browser must not narrow anything: what this field uploaded has always
    // been resolvable and stays so.
    $mine = ($this->attach)($this->post);

    $provider = ($this->field)(fn (Builder $query): Builder => $query->where('collection_name', 'library'))
        ->getFileAttachmentProvider();

    expect($provider->getFileAttachmentUrl((string) $mine->uuid))->toBeString()->not->toBeEmpty();
});

it('has a library to show before the record exists', function (): void {
    // A create form has no record, and with the collection as the pool it does not need one -
    // which is the moment somebody most wants to reach for a picture they already have.
    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        visibility: 'public',
        getRecordUsing: fn (): ?MediaPost => null,
    );

    $existing = ($this->attach)($this->post);

    expect(array_column($source->page()['items'], 'id'))->toBe([(string) $existing->uuid])
        ->and($source->has((string) $existing->uuid))->toBeTrue()
        ->and($source->has('anything'))->toBeFalse();
});

it('has nothing to show before the record exists, once narrowed to it', function (): void {
    // Narrowed to the record there is nothing to scope to yet, and an unscoped list would be
    // every picture in the database.
    $source = SpatieMediaSource::make(
        collection: 'rich-editor',
        getRecordUsing: fn (): ?MediaPost => null,
        scope: 'record',
    );

    ($this->attach)($this->post);

    expect($source->page()['items'])->toBe([])
        ->and($source->has('anything'))->toBeFalse();
});
