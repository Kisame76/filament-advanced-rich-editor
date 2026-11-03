<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * Another picture out of the library, put in the place of the one the caret stands on.
 *
 * Filament draws a picture's width and height as an inline style, so a height kept from the old
 * file pressed a picture of another shape into the old one's box. The picture keeps the width
 * it was drawn at and takes the new file's shape, the way replacing a file under its own id
 * already does in the open editor - see `media-replace.js`.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->livewire = new RecordingSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->fileAttachmentsDirectory('article-attachments')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    Storage::disk('public')->put('library/wide.png', UploadedFile::fake()->image('wide.png', 640, 360)->get());
    Storage::disk('public')->put('library/tall.png', UploadedFile::fake()->image('tall.png', 400, 800)->get());

    // The picture the caret stands on: the wide file, drawn 320 wide.
    $this->pick = fn (?string $media, array $picture = []) => $this->editor->getAction('mediaBrowser')?->call([
        'data' => ['src' => null, 'media' => $media],
        'arguments' => [
            'id' => 'library/wide.png',
            'src' => '/storage/library/wide.png',
            'alt' => 'Hafen',
            'width' => 320,
            'height' => 180,
            'editorSelection' => ['type' => 'node', 'anchor' => 4],
            ...$picture,
        ],
    ]);

    $this->written = fn (): array => $this->livewire->commands()[0]['arguments'][1] ?? [];
});

it('keeps the width the picture was drawn at and takes the new file\'s shape', function (): void {
    ($this->pick)('library/tall.png');

    expect($this->livewire->commands()[0]['name'] ?? null)->toBe('updateAttributes')
        ->and(($this->written)())->toMatchArray([
            'id' => 'library/tall.png',
            'src' => '/storage/library/tall.png',
            'width' => 320,
            'height' => 640,
        ]);
});

it('gives a picture without a size none', function (): void {
    ($this->pick)('library/tall.png', ['width' => null, 'height' => null]);

    expect(($this->written)())->toMatchArray(['id' => 'library/tall.png'])
        ->not->toHaveKeys(['width', 'height']);
});

it('leaves the size alone where nothing was picked', function (): void {
    // Opened to correct the description: the picture stays the file it was, at its size.
    ($this->pick)(null, ['height' => 200]);

    expect(($this->written)())->toMatchArray(['id' => 'library/wide.png'])
        ->not->toHaveKeys(['width', 'height']);
});

it('leaves the size alone where the same file was picked again', function (): void {
    // A height somebody set on purpose is not the file's to undo.
    ($this->pick)('library/wide.png', ['height' => 200]);

    expect(($this->written)())->toMatchArray(['id' => 'library/wide.png'])
        ->not->toHaveKeys(['width', 'height']);
});

it('leaves the size alone where the new file\'s shape is unknown', function (): void {
    // A picture somebody else hosts is never measured. Without a shape there is nothing to
    // follow, and the replace in the open editor leaves the size as it was too.
    $this->editor->getAction('mediaBrowser')?->call([
        'data' => ['src' => 'https://example.com/photo.jpg', 'media' => null],
        'arguments' => ['id' => 'library/wide.png', 'src' => '/storage/library/wide.png', 'width' => 320, 'height' => 180, 'editorSelection' => null],
    ]);

    expect(($this->written)())->toMatchArray(['src' => 'https://example.com/photo.jpg'])
        ->not->toHaveKeys(['width', 'height']);
});
