<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * The browser after it stopped being about pictures: which families it knows, which files it
 * lists, and what it will accept an upload of.
 */
it('knows three families and files a mime under the right one', function (): void {
    expect(MediaKinds::all())->toBe(['image', 'video', 'audio'])
        ->and(MediaKinds::of('image/jpeg'))->toBe('image')
        ->and(MediaKinds::of('video/mp4'))->toBe('video')
        ->and(MediaKinds::of('audio/mpeg'))->toBe('audio')
        // A family it does not draw, and a string that is not a mime type at all.
        ->and(MediaKinds::of('application/pdf'))->toBeNull()
        ->and(MediaKinds::of('nonsense'))->toBeNull()
        ->and(MediaKinds::of(null))->toBeNull();
});

it('files a row it would never have uploaded under the family it belongs to', function (): void {
    // Matched on the prefix rather than against the table, which is what the old
    // `like 'image/%'` did for pictures and has to keep doing: a collection holding an
    // `image/heic` is holding a picture.
    expect(MediaKinds::of('image/heic'))->toBe('image')
        ->and(MediaKinds::of('video/x-matroska'))->toBe('video');
});

it('reads a family and a mime off a file name', function (): void {
    expect(MediaKinds::ofPath('/library/sunset.JPG'))->toBe('image')
        ->and(MediaKinds::ofPath('talk.mp4'))->toBe('video')
        ->and(MediaKinds::ofPath('talk.mp3'))->toBe('audio')
        ->and(MediaKinds::ofPath('notes.pdf'))->toBeNull()
        ->and(MediaKinds::ofPath('README'))->toBeNull()
        ->and(MediaKinds::mimeOf('talk.webm'))->toBe('video/webm')
        ->and(MediaKinds::mimeOf('notes.pdf'))->toBe('');
});

it('spells the families as patterns, which is what an accepted-types list takes', function (): void {
    expect(MediaKinds::patterns())->toBe(['image/*', 'video/*', 'audio/*'])
        ->and(MediaKinds::patterns(['audio']))->toBe(['audio/*'])
        // Order is the table's, not the caller's, so two fields asking differently get the
        // same answer.
        ->and(MediaKinds::patterns(['audio', 'image']))->toBe(['image/*', 'audio/*']);
});

it('lists a video beside a picture, and leaves alone what it cannot draw', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('library/sunset.png', 'x');
    Storage::disk('public')->put('library/talk.mp4', 'x');
    Storage::disk('public')->put('library/talk.mp3', 'x');
    Storage::disk('public')->put('library/notes.pdf', 'x');

    $page = editor()->mediaLibraryDirectory('library')->getMediaSource()->page();

    $names = array_column($page['items'], 'name');

    expect($names)->toContain('sunset.png', 'talk.mp4', 'talk.mp3')
        // A player nothing can start is worse than a file that is not offered.
        ->and($names)->not->toContain('notes.pdf')
        ->and($page['kinds'])->toBe(['image', 'video', 'audio']);
});

it('says which family each row is, and gives a thumbnail only to a picture', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('library/sunset.png', 'x');
    Storage::disk('public')->put('library/talk.mp4', 'x');

    $items = collect(editor()->mediaLibraryDirectory('library')->getMediaSource()->page()['items'])
        ->keyBy('name');

    expect($items['sunset.png']['kind'])->toBe('image')
        ->and($items['sunset.png']['thumbnail'])->not->toBeNull()
        ->and($items['talk.mp4']['kind'])->toBe('video')
        // A film drawn in an `<img>` is a broken-image icon, which reads as a broken library.
        ->and($items['talk.mp4']['thumbnail'])->toBeNull()
        ->and($items['talk.mp4']['width'])->toBeNull();
});

it('narrows the grid to one family when a tab is chosen', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('library/sunset.png', 'x');
    Storage::disk('public')->put('library/talk.mp4', 'x');

    $source = editor()->mediaLibraryDirectory('library')->getMediaSource();

    expect(array_column($source->page(filters: ['kind' => 'video'])['items'], 'name'))
        ->toBe(['talk.mp4'])
        // The tabs still name both, because a tab list that shrank to the tab you are
        // standing on would be a list you cannot get out of.
        ->and($source->page(filters: ['kind' => 'video'])['kinds'])->toBe(['image', 'video']);
});

it('keeps the picture rule the field already stated and widens the rest', function (): void {
    // Filament's answer governs pictures and a project may have narrowed it on purpose; it
    // never said anything about video, so those two families come from here.
    expect(editor()->getMediaLibraryAcceptedFileTypes())
        ->toBe(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/*', 'audio/*']);

    expect(editor()->fileAttachmentsAcceptedFileTypes(['image/png'])->getMediaLibraryAcceptedFileTypes())
        ->toBe(['image/png', 'video/*', 'audio/*']);
});

it('offers only pictures where the player is off', function (): void {
    // The two switches are one question asked twice: a field with no node to put a video in
    // has no business offering one in the grid, and what is left is exactly Filament's list.
    expect(editor()->media(false)->getMediaLibraryAcceptedFileTypes())
        ->toBe(['image/png', 'image/jpeg', 'image/gif', 'image/webp']);
});

it('lets the browser be given a list of its own', function (): void {
    expect(editor()->mediaLibraryAcceptedFileTypes(['image/png'])->getMediaLibraryAcceptedFileTypes())
        ->toBe(['image/png']);

    config()->set('filament-advanced-rich-editor.media_library.accepted_file_types', ['video/*']);

    expect(editor()->getMediaLibraryAcceptedFileTypes())->toBe(['video/*']);
});

it('keeps Filament its own narrow list, which the drop handler reads', function (): void {
    // Widening this one would make a film dropped into the editor an `<img>` pointing at an
    // mp4: Filament's compiled handler inserts an image node for anything it accepts.
    expect(editor()->getFileAttachmentsAcceptedFileTypes())
        ->not->toContain('video/*')
        ->and(editor()->getMediaLibraryAcceptedFileTypes())->toContain('video/*');
});

it('counts a player as something that carries a file', function (): void {
    // The whole of the data-loss guard: an id nothing walks is an id
    // `cleanUpFileAttachments()` does not spare, and the file goes while the document keeps
    // pointing at it.
    expect(FileAttachments::TYPES)->toBe(['image', 'media', 'file'])
        ->and(FileAttachments::carriedBy('image'))->toBeTrue()
        ->and(FileAttachments::carriedBy('media'))->toBeTrue()
        // And a document card, for the same reason: it carries the same `data-id`.
        ->and(FileAttachments::carriedBy('file'))->toBeTrue()
        ->and(FileAttachments::carriedBy('paragraph'))->toBeFalse()
        ->and(FileAttachments::carriedBy(null))->toBeFalse();
});

it('tells a browser upload from one Filament is holding', function (): void {
    expect(FileAttachments::pending('arte-abc123'))->toBeTrue()
        ->and(FileAttachments::pending('abc123'))->toBeFalse()
        ->and(FileAttachments::pending(null))->toBeFalse();
});

it('counts a player among the attachments a document still uses', function (): void {
    // The data-loss guard, measured. `getOriginalFileAttachmentPaths()` is what the tamper
    // check and the cleanup measure a save against - a media id it walks past is an id
    // `cleanUpFileAttachments()` does not spare, so the file goes while the document keeps
    // pointing at it.
    $post = Post::create([
        'title' => 'P',
        'content' => '<p>Text</p>'.
            '<img src="/storage/a.png" data-id="picture-1">'.
            '<video src="/storage/talk.mp4" data-id="film-1" controls></video>'.
            '<audio src="/storage/talk.mp3" data-id="sound-1" controls></audio>',
    ]);

    $editor = AdvancedRichEditor::make('content')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    expect($editor->getOriginalFileAttachmentPaths())
        ->toBe(['picture-1', 'film-1', 'sound-1']);
});

it('walks past a player that points at no attachment', function (): void {
    // A file named by a plain address has no id, which is the ordinary case for one typed
    // into the browser's address field. Nothing is holding it, so nothing has to spare it.
    $post = Post::create([
        'title' => 'P',
        'content' => '<video src="https://cdn.test/talk.mp4" controls></video>',
    ]);

    $editor = AdvancedRichEditor::make('content')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    expect($editor->getOriginalFileAttachmentPaths())->toBe([]);
});

it('reads the family off the row rather than deriving it again', function (): void {
    // Both sources and the pending-upload path already answer this, and two derivations are
    // two answers to keep in step.
    expect(MediaLibraryAction::kindOf(['kind' => 'video', 'mime' => 'image/png']))->toBe('video')
        ->and(MediaLibraryAction::kindOf(['mime' => 'audio/mpeg']))->toBe('audio')
        ->and(MediaLibraryAction::kindOf(['kind' => 'nonsense', 'mime' => 'image/png']))->toBe('image')
        ->and(MediaLibraryAction::kindOf(null))->toBeNull()
        ->and(MediaLibraryAction::kindOf([]))->toBeNull();
});

it('reads a typed address past its query string', function (): void {
    // A file somebody else hosts has no row and no mime type this side ever saw, so the
    // ending is all there is - and a signed CDN link carries a query after it.
    expect(MediaLibraryAction::kindOf(['url' => 'https://cdn.test/talk.mp4?token=abc']))->toBe('video')
        ->and(MediaLibraryAction::kindOf(['url' => 'https://cdn.test/talk.mp3#t=10']))->toBe('audio')
        ->and(MediaLibraryAction::kindOf(['url' => 'https://cdn.test/photo.png']))->toBe('image')
        // Nothing in the ending says anything, so nothing is claimed.
        ->and(MediaLibraryAction::kindOf(['url' => 'https://cdn.test/download']))->toBeNull();
});
