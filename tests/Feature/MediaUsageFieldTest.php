<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Deleting and replacing a file reaches every entry that uses it.
 *
 * The field knows one place a document lives without being told - its own column - and is
 * told the rest. What is pinned here is that the question before deleting names the entries,
 * that deleting takes the file out of them, and that replacing points them at the new one.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new TestSchemaComponent;

    $this->picture = fn (string $id): string => '<p>Text <img src="/storage/'.$id.'" data-id="'.$id.'"></p>';

    $this->record = Post::create(['title' => 'Offen', 'content' => ($this->picture)('article-attachments/sunset.png')]);

    // A record's own directory, so the field may delete without being opened for it.
    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record($this->record));

    Storage::disk('public')->put('article-attachments/sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());
});

it('looks in its own column without being told', function (): void {
    expect($this->editor->getMediaLibraryDocuments())->toBe([Post::class => ['content']]);
});

it('adds the places the configuration and the field name', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.documents', [RichPost::class]);

    // A model named alone answers with the columns it registered as rich content itself.
    expect($this->editor->mediaLibraryDocuments([Post::class => 'title'])->getMediaLibraryDocuments())->toBe([
        Post::class => ['content', 'title'],
        RichPost::class => ['content'],
    ]);
});

it('has no column of its own where it is not bound to one', function (): void {
    $nested = AdvancedRichEditor::make('meta.body')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($nested->getMediaLibraryDocuments())->toBe([]);
});

it('names the entries using a file for the question before deleting it', function (): void {
    Post::create(['title' => 'Preise', 'content' => ($this->picture)('article-attachments/sunset.png')]);
    Post::create(['title' => 'Anderes', 'content' => ($this->picture)('article-attachments/other.png')]);

    expect($this->editor->getMediaUsageForJs('article-attachments/sunset.png'))->toBe([
        'count' => 2,
        'entries' => ['Post #1 “Offen”', 'Post #2 “Preise”'],
    ]);
});

it('says nothing about a file outside the pool', function (): void {
    Post::create(['title' => 'Fremd', 'content' => ($this->picture)('elsewhere/sunset.png')]);

    expect($this->editor->getMediaUsageForJs('elsewhere/sunset.png'))->toBe(['count' => 0, 'entries' => []]);
});

it('takes a deleted file out of every entry using it', function (): void {
    $other = Post::create(['title' => 'Preise', 'content' => ($this->picture)('article-attachments/sunset.png')]);
    $untouched = Post::create(['title' => 'Anderes', 'content' => ($this->picture)('article-attachments/other.png')]);

    expect($this->editor->deleteMediaForJs('article-attachments/sunset.png'))->toBe(['deleted' => true, 'documents' => 2])
        ->and($other->fresh()->content)->toBe('<p>Text </p>')
        ->and($this->record->fresh()->content)->toBe('<p>Text </p>')
        ->and($untouched->fresh()->content)->toBe(($this->picture)('article-attachments/other.png'));
});

it('leaves every entry alone when the file could not be deleted', function (): void {
    $other = Post::create(['title' => 'Preise', 'content' => ($this->picture)('article-attachments/sunset.png')]);

    expect($this->editor->mediaLibraryDeletable(false)->deleteMediaForJs('article-attachments/sunset.png'))->toBe(['deleted' => false, 'documents' => 0])
        ->and($other->fresh()->content)->toBe(($this->picture)('article-attachments/sunset.png'));
});

it('points every entry using a replaced file at the new one', function (): void {
    // A card writes down its name and its size, so those follow the file too.
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    Storage::disk('public')->put('article-attachments/report.pdf', $pdf);

    $card = fn (string $size): string => '<p><a class="fi-arte-file" data-type="file" data-id="article-attachments/report.pdf" href="/storage/article-attachments/report.pdf" download="report.pdf">'
        .'<span class="fi-arte-file-kind">PDF</span> <span class="fi-arte-file-text"><span class="fi-arte-file-name">report.pdf</span> '
        .'<span class="fi-arte-file-size">'.$size.'</span></span></a></p>';

    $other = Post::create(['title' => 'Preise', 'content' => $card('1 KB')]);

    $fake = new UploadedFile((string) tempnam(sys_get_temp_dir(), 'arte'), 'report-2026.pdf', null, null, true);
    file_put_contents($fake->getPathname(), $pdf.str_repeat('%', 2048));
    $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);
    Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, (string) file_get_contents($fake->getPathname()));

    data_set($this->livewire, 'mountedActions', [
        ['name' => 'mediaBrowser', 'data' => ['replacement' => ['k' => TemporaryUploadedFile::createFromLivewire($stored)]]],
    ]);

    $result = $this->editor->replaceMediaForJs('article-attachments/report.pdf');

    expect($result['replaced'])->toBeTrue()
        ->and($result['documents'])->toBe(1)
        ->and($other->fresh()->content)->toBe(str_replace(
            'href="/storage/article-attachments/report.pdf"',
            'href="'.Storage::disk('public')->url('article-attachments/report.pdf').'"',
            $card('2 KB'),
        ));
});
