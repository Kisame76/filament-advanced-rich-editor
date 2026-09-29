<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaUsages;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\TreePost;

/**
 * Where a file of the library is used, across the documents a project keeps.
 *
 * A package cannot know which tables of an application hold rich content, so it is told: the
 * field's own column by default, and whatever else a project names. What is asserted here is
 * that every entry pointing at the file is found, rewritten and saved - and that an entry
 * pointing at another file, or a column nobody named, is left exactly as it was.
 */
beforeEach(function (): void {
    $this->picture = fn (string $id): string => '<p>Text <img src="/storage/'.$id.'.png" data-id="'.$id.'"></p>';

    $this->usages = MediaUsages::make([Post::class => ['content']]);
});

it('counts and names the entries using a file', function (): void {
    // The keys are read off the rows rather than written out: on a server that keeps its
    // sequences between tests they are whatever the tests before this one left behind.
    $prices = Post::create(['title' => 'Preise', 'content' => ($this->picture)('a')]);
    Post::create(['title' => 'Anderes', 'content' => ($this->picture)('b')]);
    $contact = Post::create(['title' => 'Kontakt', 'content' => ($this->picture)('a')]);

    $usage = $this->usages->describe('a');

    expect($usage['count'])->toBe(2)
        ->and($usage['entries'])->toBe(["Post #{$prices->getKey()} “Preise”", "Post #{$contact->getKey()} “Kontakt”"]);
});

it('names only a few, and counts all of them', function (): void {
    foreach (range(1, 5) as $number) {
        Post::create(['title' => "Seite {$number}", 'content' => ($this->picture)('a')]);
    }

    $usage = $this->usages->describe('a', named: 2);

    expect($usage['count'])->toBe(5)
        ->and($usage['entries'])->toHaveCount(2);
});

it('names an entry without a title by its key', function (): void {
    $post = Post::create(['title' => null, 'content' => ($this->picture)('a')]);

    expect($this->usages->describe('a')['entries'])->toBe(["Post #{$post->getKey()}"]);
});

it('takes the file out of every entry using it, and saves them', function (): void {
    $first = Post::create(['title' => 'Preise', 'content' => ($this->picture)('a')]);
    $other = Post::create(['title' => 'Anderes', 'content' => ($this->picture)('b')]);

    expect($this->usages->remove('a'))->toBe(1)
        ->and($first->fresh()->content)->toBe('<p>Text </p>')
        ->and($other->fresh()->content)->toBe(($this->picture)('b'));
});

it('points every entry using the file at its replacement', function (): void {
    $first = Post::create(['title' => 'Preise', 'content' => ($this->picture)('a')]);

    expect($this->usages->update('a', ['src' => '/storage/new.jpg']))->toBe(1)
        ->and($first->fresh()->content)->toBe('<p>Text <img src="/storage/new.jpg" data-id="a"></p>');
});

it('finds a path whose slashes a stored document tree escaped', function (): void {
    // A path is an id on a disk, and `json_encode()` writes its slashes as `\/` - a search for
    // the path as it is would walk straight past the document.
    $id = 'article-attachments/sunset.png';
    $document = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => $id, 'src' => '/storage/'.$id]]]],
    ]];

    $post = Post::create(['title' => 'Tree', 'content' => json_encode($document)]);

    expect($this->usages->describe($id)['count'])->toBe(1)
        ->and($this->usages->update($id, ['src' => '/storage/new.png']))->toBe(1)
        ->and(json_decode((string) $post->fresh()->content, true)['content'][0]['content'][0]['attrs']['src'])->toBe('/storage/new.png');
});

it('finds a path whose letters a stored document tree escaped', function (): void {
    // `json_encode()` writes a letter outside ASCII as `\u00dc` - so a file somebody put in a
    // library folder by hand, "Übersicht.png", hid behind its own spelling in every tree.
    $id = 'library/Übersicht.png';
    $document = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => $id, 'src' => '/storage/'.$id]]]],
    ]];

    $post = Post::create(['title' => 'Tree', 'content' => json_encode($document)]);

    expect($this->usages->describe($id)['count'])->toBe(1)
        ->and($this->usages->remove($id))->toBe(1)
        ->and(json_decode((string) $post->fresh()->content, true)['content'][0]['content'] ?? [])->toBe([]);
});

it('finds a document tree kept in a json column', function (): void {
    // The column type a field with `->json()` is usually given. Postgres has no `LIKE` for
    // `json` or `jsonb` - Laravel's grammar reads the column as `::text` for one, which is what
    // this pins: a pattern written by hand as raw SQL would fail there, and every entry kept
    // this way would be passed over as a place that cannot be read.
    $id = 'article-attachments/sunset.png';
    $post = TreePost::create(['title' => 'Tree', 'content' => ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['id' => $id, 'src' => '/storage/'.$id]]]],
    ]]]);

    $usages = MediaUsages::make([TreePost::class => ['content']]);

    expect($usages->describe($id)['count'])->toBe(1)
        ->and($usages->update($id, ['src' => '/storage/new.png']))->toBe(1)
        ->and($post->fresh()->content['content'][0]['content'][0]['attrs']['src'])->toBe('/storage/new.png');
});

it('is not fooled by an id that is only part of another', function (): void {
    // The database is asked for a pattern, so `a` matches `ab` there; the document is then
    // read, and only a node pointing at `a` itself counts.
    $post = Post::create(['title' => 'Nah dran', 'content' => ($this->picture)('ab')]);

    expect($this->usages->describe('a')['count'])->toBe(0)
        ->and($this->usages->remove('a'))->toBe(0)
        ->and($post->fresh()->content)->toBe(($this->picture)('ab'));
});

it('reads the columns a model declared when it is named without any', function (): void {
    // `registerRichContent('content')` is what the model already says about itself, and a
    // second list beside it would be a second place to be wrong.
    RichPost::create(['title' => 'Rich', 'content' => ($this->picture)('a')]);

    expect(MediaUsages::make([RichPost::class => []])->describe('a')['count'])->toBe(1);
});

it('looks nowhere it was not told to', function (): void {
    Post::create(['title' => 'Preise', 'content' => ($this->picture)('a')]);

    expect(MediaUsages::make([])->describe('a')['count'])->toBe(0)
        ->and(MediaUsages::make([Post::class => ['title']])->remove('a'))->toBe(0);
});

it('passes over a place it cannot read rather than failing the delete', function (): void {
    // A configuration naming a table that was since dropped must not stop a file from being
    // deleted - the entries it can still read are what it can clean. A table rather than a
    // column, because SQLite reads an unknown quoted column as a string and never complains.
    Post::create(['title' => 'Preise', 'content' => ($this->picture)('a')]);

    $gone = new class extends Model
    {
        protected $table = 'dropped_long_ago';
    };

    $usages = MediaUsages::make([$gone::class => ['content'], RichPost::class => ['content']]);

    expect($usages->describe('a')['count'])->toBe(1)
        ->and($usages->remove('a'))->toBe(1);
});
