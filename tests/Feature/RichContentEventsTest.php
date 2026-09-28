<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Events\RichContentSaved;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\EventfulPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

/**
 * Telling an application that a document was written.
 *
 * Optimising the pictures in it, checking its links, clearing a cache that renders it - all
 * three want the same moment, and none of them is the editor's business. The moment is the
 * model's: a field hook cannot answer "was it saved" at all, because on an edit page
 * `saveRelationships()` runs inside `getState()` and `handleRecordUpdate()` comes after it.
 * A record written by an import, a queued job or tinker never sees a field either.
 */
it('fires once the record is written, with the content that was written', function (): void {
    Event::fake([RichContentSaved::class]);

    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::assertDispatched(
        RichContentSaved::class,
        fn (RichContentSaved $event): bool => $event->record->is($post)
            && $event->attribute === 'content'
            && $event->content === '<p>eins</p>'
            && $event->previousContent === null
            && $event->wasRecentlyCreated,
    );
});

it('carries what stood there before, which is the half a cache needs', function (): void {
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::fake([RichContentSaved::class]);

    $post->update(['content' => '<p>zwei</p>']);

    Event::assertDispatched(
        RichContentSaved::class,
        fn (RichContentSaved $event): bool => $event->content === '<p>zwei</p>'
            && $event->previousContent === '<p>eins</p>'
            && ! $event->wasRecentlyCreated,
    );
});

it('says nothing where the document did not change', function (): void {
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::fake([RichContentSaved::class]);

    // A save that touched another column is not a save of the document, and a listener that
    // re-optimises every picture whenever somebody fixes a typo in the title is a listener
    // nobody keeps.
    $post->update(['title' => 'Ein anderer Titel']);

    Event::assertNotDispatched(RichContentSaved::class);
});

it('stays silent on a later save of a record it created a moment ago', function (): void {
    // The trap this design walks around: `wasRecentlyCreated` is set on insert and never
    // cleared, so on the instance that created the record every later save answers true to
    // it. Hung on `saved`, a typo fix in the title would announce a document nobody wrote.
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::fake([RichContentSaved::class]);

    $post->update(['title' => 'Titel']);

    expect($post->wasRecentlyCreated)->toBeTrue();

    Event::assertNotDispatched(RichContentSaved::class);
});

it('says nothing for a record created without a document in it', function (): void {
    // Clearing a document later is news. Never having had one is not.
    Event::fake([RichContentSaved::class]);

    EventfulPost::create(['title' => 'Nur ein Titel']);

    Event::assertNotDispatched(RichContentSaved::class);
});

it('says nothing for a record created through an editor nobody typed into', function (string $markup): void {
    // The shape a create page actually writes, which is the reason this asks `isBlank()`
    // rather than `blank()`: an untouched editor stores a paragraph, and a paragraph is
    // present, not blank, and empty. Asked the other way every create through a form would
    // announce a document, and every listener the event exists for would run over nothing.
    Event::fake([RichContentSaved::class]);

    EventfulPost::create(['content' => $markup]);

    Event::assertNotDispatched(RichContentSaved::class);
})->with([
    'the paragraph TipTap always keeps' => '<p></p>',
    'the second one a stray return leaves behind' => '<p></p><p></p>',
    'the line break inside it' => '<p><br></p>',
    'the space a paste from Word leaves' => '<p>&nbsp;</p>',
]);

it('still announces a document whose only content is a node', function (): void {
    // The other direction, and the one that matters more: a document holding one picture and
    // no words at all is a document somebody wrote.
    Event::fake([RichContentSaved::class]);

    EventfulPost::create(['content' => '<p><img src="https://example.test/a.png"></p>']);

    Event::assertDispatched(RichContentSaved::class);
});

it('announces a document that was cleared, because that is news', function (): void {
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::fake([RichContentSaved::class]);

    $post->update(['content' => null]);

    Event::assertDispatched(
        RichContentSaved::class,
        fn (RichContentSaved $event): bool => $event->content === null
            && $event->previousContent === '<p>eins</p>',
    );
});

it('says nothing for a column the model never declared as rich content', function (): void {
    // Which columns are documents is what the model already says through
    // `registerRichContent()`. A second list beside it would be a second place to be wrong.
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    Event::fake([RichContentSaved::class]);

    $post->update(['title' => 'Titel']);

    Event::assertNotDispatched(RichContentSaved::class);
});

it('leaves a model alone that did not ask for the events', function (): void {
    Event::fake([RichContentSaved::class]);

    RichPost::create(['content' => '<p>eins</p>']);

    Event::assertNotDispatched(RichContentSaved::class);
});

/**
 * The two Eloquent facts the trait stands on. Both are pinned here rather than believed,
 * because each of them is one line of framework behaviour that the whole thing rests on and
 * neither is visible in the trait itself.
 */
it('still sees the old value inside the event, because saved runs before syncOriginal', function (): void {
    $post = EventfulPost::create(['content' => '<p>eins</p>']);

    $seen = null;

    EventfulPost::saved(function (EventfulPost $model) use (&$seen): void {
        $seen = $model->getOriginal('content');
    });

    $post->update(['content' => '<p>zwei</p>']);

    // `Model::finishSave()` fires `saved` and calls `syncOriginal()` afterwards. If that
    // order ever changed, `previousContent` would silently become a copy of `content`.
    expect($seen)->toBe('<p>eins</p>');
});

it('cannot ask wasChanged on a record that was just inserted', function (): void {
    $seen = null;

    EventfulPost::saved(function (EventfulPost $model) use (&$seen): void {
        $seen = ['changed' => $model->wasChanged('content'), 'created' => $model->wasRecentlyCreated];
    });

    EventfulPost::create(['content' => '<p>eins</p>']);

    // An insert syncs no changes, so `wasChanged()` is false for a column that was very much
    // written. This is why the condition is `wasRecentlyCreated || wasChanged()` and not the
    // obvious half of it.
    expect($seen)->toBe(['changed' => false, 'created' => true]);
});
