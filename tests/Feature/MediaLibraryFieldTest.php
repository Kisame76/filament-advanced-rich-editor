<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\DiskMediaSource;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\SpatieMediaSource;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * How the field decides whether the image button opens the browser, and what pool it opens on.
 */
function actionNamed(AdvancedRichEditor $editor, string $name): ?Action
{
    foreach ($editor->getDefaultActions() as $action) {
        if ($action->getName() === $name) {
            return $action;
        }
    }

    return null;
}

/** The browser, which is registered only where there is a pool to browse. */
function browserAction(AdvancedRichEditor $editor): ?Action
{
    return actionNamed($editor, 'mediaBrowser');
}

/** Filament's own upload dialog, which this package no longer takes the name of. */
function stockAttachAction(AdvancedRichEditor $editor): ?Action
{
    return actionNamed($editor, 'attachFiles');
}

function ourImageDialogHeading(): string
{
    return (string) __('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.heading');
}

/**
 * A rich content plugin carrying a file attachment provider that is not this package's own -
 * an S3 integration, a DAM, anything a project writes for itself.
 */
function foreignAttachmentPlugin(): RichContentPlugin
{
    $provider = new class implements FileAttachmentProvider
    {
        public function attribute(RichContentAttribute $attribute): static
        {
            return $this;
        }

        public function getFileAttachmentUrl(mixed $file): ?string
        {
            return 'https://dam.example/'.$file;
        }

        public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
        {
            return 'dam-id';
        }

        public function getDefaultFileAttachmentVisibility(): ?string
        {
            return 'public';
        }

        public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
        {
            return false;
        }

        public function cleanUpFileAttachments(array $exceptIds): void {}
    };

    return new class($provider) implements HasFileAttachmentProvider, RichContentPlugin
    {
        public function __construct(protected FileAttachmentProvider $provider) {}

        public function getFileAttachmentProvider(): ?FileAttachmentProvider
        {
            return $this->provider;
        }

        public function getTipTapPhpExtensions(): array
        {
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
    };
}

it('opens the browser by default', function (): void {
    // Default meaning nothing was said about the library - the field only has to have a pool
    // for it to be browsable, which for a plain disk field means a directory of its own.
    $editor = editor()->fileAttachmentsDirectory('article-attachments');

    expect($editor->hasMediaLibrary())->toBeTrue()
        ->and(browserAction($editor)?->getModalHeading())->toBe(ourImageDialogHeading());
});

it('leaves Filament its own dialog beside the browser rather than taking its name', function (): void {
    // The browser used to be registered AS `attachFiles`, which meant a project that wanted
    // the plain upload dialog could not have it on any field: the name it answers to was
    // taken. Both are registered now, and Filament's is untouched.
    $editor = editor()->fileAttachmentsDirectory('article-attachments');

    expect(stockAttachAction($editor))->not->toBeNull()
        ->and(stockAttachAction($editor)?->getModalHeading())->not->toBe(ourImageDialogHeading())
        ->and(browserAction($editor))->not->toBeNull();
});

it('registers no browser at all where the browser is switched off', function (): void {
    // And the buttons then name Filament's dialog, which is what they did before any of
    // this existed - see `MediaLibraryAction::nameFor()`.
    $editor = editor()->mediaLibrary(false);

    expect($editor->hasMediaLibrary())->toBeFalse()
        ->and(browserAction($editor))->toBeNull()
        ->and(stockAttachAction($editor))->not->toBeNull()
        ->and(stockAttachAction($editor)?->getModalHeading())->not->toBe(ourImageDialogHeading());
});

it('points both media buttons at the dialog the field actually has', function (): void {
    $withPool = editor()->fileAttachmentsDirectory('article-attachments');
    $withoutPool = editor()->mediaLibrary(false);

    foreach (['mediaBrowser', 'image'] as $name) {
        expect($withPool->getTools()[$name]->getJsHandler())->toContain("mountAction('mediaBrowser'")
            ->and($withoutPool->getTools()[$name]->getJsHandler())->toContain("mountAction('attachFiles'");
    }
});

it('can be switched off for the whole project', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.enabled', false);

    expect(editor()->hasMediaLibrary())->toBeFalse();

    // And a field still wins over the project.
    expect(editor()->mediaLibrary()->hasMediaLibrary())->toBeTrue();
});

it('has nothing to browse where the field takes no attachments', function (): void {
    // The browser is the image dialog. A field without an image button has no dialog to open,
    // and offering a library there would be offering a picture nothing can insert.
    expect(editor()->toolbarButtons([['bold', 'italic']])->hasMediaLibrary())->toBeFalse();
});

it('browses the disk for a field that stores plain files', function (): void {
    $source = editor()->fileAttachmentsDirectory('article-attachments')->getMediaSource();

    expect($source)->toBeInstanceOf(DiskMediaSource::class)
        ->and($source->hasFolders())->toBeTrue()
        ->and($source->getRoot())->toBe('article-attachments')
        // The pool is the directory this field already uploads into, so nothing has been
        // widened by opening a grid over it.
        ->and($source->isRecordScoped())->toBeTrue();
});

it('browses a shared directory once it is given one', function (): void {
    $source = editor()->mediaLibraryDirectory('library')->getMediaSource();

    expect($source->isRecordScoped())->toBeFalse();

    Storage::fake('public');
    Storage::disk('public')->put('library/one.png', 'x');
    Storage::disk('public')->put('elsewhere/two.png', 'x');

    expect($source->has('library/one.png'))->toBeTrue()
        ->and($source->has('elsewhere/two.png'))->toBeFalse();
});

it('browses the media collection for a field that stores media', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $source = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post))
        ->getMediaSource();

    expect($source)->toBeInstanceOf(SpatieMediaSource::class)
        ->and($source->hasFolders())->toBeFalse()
        // Every record of this model, which is what the browser exists for.
        ->and($source->isRecordScoped())->toBeFalse();
});

it('scopes the media collection to the collection, and narrows on request', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $editor = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    expect($editor->getMediaLibraryScope())->toBe('collection')
        ->and($editor->mediaLibraryScope('model')->getMediaLibraryScope())->toBe('model')
        ->and($editor->mediaLibraryScope('record')->getMediaLibraryScope())->toBe('record')
        ->and($editor->mediaLibraryScope('record')->getMediaSource()->isRecordScoped())->toBeTrue()
        // An unknown setting falls back rather than quietly listing nothing.
        ->and($editor->mediaLibraryScope('nonsense')->getMediaLibraryScope())->toBe('collection');

    config()->set('filament-advanced-rich-editor.media_library.scope', 'record');

    expect(AdvancedRichEditor::make('summary')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post))
        ->getMediaLibraryScope())->toBe('record');
});

it('guards a disk field against a path the browser never offered', function (): void {
    // Filament ships this guard switched off. The browser switches it on, because the pool it
    // lists is exactly the answer the guard needs - and without it the "one rule" would only
    // hold on the media library side.
    $post = Post::create(['title' => 'Post', 'content' => '']);

    $editor = AdvancedRichEditor::make('content')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    Storage::fake('public');
    Storage::disk('public')->put('library/one.png', 'x');

    expect($editor->shouldPreventFileAttachmentPathTampering())->toBeTrue()
        ->and($editor->isFileAttachmentPathAuthorized('library/one.png'))->toBeTrue()
        ->and($editor->isFileAttachmentPathAuthorized('../../.env'))->toBeFalse()
        ->and($editor->isFileAttachmentPathAuthorized('elsewhere/two.png'))->toBeFalse();
});

it('leaves the guard alone where the media library provider enforces the rule', function (): void {
    if (! class_exists(Media::class)) {
        $this->markTestSkipped('spatie/laravel-medialibrary is not installed.');
    }

    // Every media lookup goes through the provider, which is scoped to the pool already.
    // Turning a path guard on there would be a second answer to a question that has one.
    $post = MediaPost::create(['title' => 'Post', 'content' => '']);

    $editor = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary('rich-editor')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    expect($editor->shouldPreventFileAttachmentPathTampering())->toBeFalse();
});

it('does not guard a field that is not browsing anything', function (): void {
    expect(editor()->mediaLibrary(false)->shouldPreventFileAttachmentPathTampering())->toBeFalse();
});

it('hands the browser an empty page while it is switched off', function (): void {
    expect(editor()->mediaLibrary(false)->getMediaLibraryPageForJs())
        ->toBe(['items' => [], 'folders' => [], 'parent' => null, 'hasMore' => false, 'total' => 0, 'types' => [], 'kinds' => [], 'perPage' => 40]);
});

it('keeps the page size within reach', function (): void {
    expect(editor()->getMediaLibraryPageSize())->toBe(40)
        ->and(editor()->mediaLibraryPageSize(12)->getMediaLibraryPageSize())->toBe(12)
        // A page of nothing would never finish loading, and a page of everything is not a page.
        ->and(editor()->mediaLibraryPageSize(0)->getMediaLibraryPageSize())->toBe(1)
        ->and(editor()->mediaLibraryPageSize(5000)->getMediaLibraryPageSize())->toBe(200);
});

it('opens no browser over a disk it cannot tell apart', function (): void {
    // Filament's `fileAttachmentsDirectory()` is null by default, and uploads then land at the
    // root of the disk among everything else on it - avatars, exports, another feature's
    // uploads. There is no pool to browse there, only a disk; opening a grid over it would
    // list all of that and, because the pool is also the lookup, let a stored path resolve to
    // any of it.
    $editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->getMediaSource())->toBeNull()
        // ...and the media buttons go back to naming Filament's own dialog.
        ->and(browserAction($editor))->toBeNull()
        ->and(stockAttachAction($editor)?->getModalHeading())->not->toBe(ourImageDialogHeading());
});

it('opens the browser as soon as the attachments have a directory of their own', function (): void {
    $editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->getMediaSource())->toBeInstanceOf(DiskMediaSource::class)
        ->and($editor->getMediaSource()->getRoot())->toBe('article-attachments');
});

it('opens no browser over a file attachment provider it does not know', function (): void {
    // Another provider defines both where an attachment lives and what its id means, and
    // neither is anything this package can enumerate. Guessing that it is a plain disk field
    // would list the wrong pool and - worse - switch Filament's tamper guard on with that
    // wrong pool as the authoriser, so ids the provider itself issued would be refused.
    $editor = AdvancedRichEditor::make('content')
        ->plugins([foreignAttachmentPlugin()])
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->getFileAttachmentProvider())->not->toBeNull()
        ->and($editor->getMediaSource())->toBeNull()
        ->and(browserAction($editor))->toBeNull()
        ->and(stockAttachAction($editor)?->getModalHeading())->not->toBe(ourImageDialogHeading());
});

it('marks the upload field the browser drops onto', function (): void {
    // The grid finds Filament's upload widget by this class and hands it every dropped file.
    // Rename it and both ways in - the Upload button and the dropzone - stop doing anything
    // at all, silently: `whenPond()` waits a minute for a field that will never be found.
    $editor = editor()->fileAttachmentsDirectory('article-attachments');

    // Bound to the field the way the schema binds it, because the dialog is built out of the
    // editor it was opened from.
    $schema = browserAction($editor)?->schemaComponent($editor)->getSchema(testSchema());

    $upload = collect($schema?->getComponents() ?? [])
        ->first(fn (Component $component): bool => $component instanceof FileUpload);

    expect($upload)->not->toBeNull()
        ->and($upload?->getExtraAttributes())->toHaveKey('class', 'fi-arte-media-uploader');
});

it('asks for no alt text under the grid any more', function (): void {
    // It moved into the panel, where it describes the file being looked at and is saved
    // against that file rather than against this one insert.
    $editor = editor()->fileAttachmentsDirectory('article-attachments');

    $schema = browserAction($editor)?->schemaComponent($editor)->getSchema(testSchema());

    $names = array_map(
        static fn (Component $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema?->getComponents() ?? [],
    );

    // And no address input either, since `+ Add → From an address` replaced it. `src` is
    // still a field - a hidden one that the nested dialog fills and Submit reads.
    expect($names)->not->toContain('alt')
        ->and($names)->toContain('src');
});
