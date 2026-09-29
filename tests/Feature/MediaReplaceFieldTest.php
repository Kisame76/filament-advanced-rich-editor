<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The browser's Replace button, from the field's side.
 *
 * The upload travels the way every upload in the dialog does - through a Filament upload field
 * kept off screen - and lands in a field of its own, apart from the uploads waiting to be
 * inserted. What the field does with it is take it out again, whatever the answer: an upload
 * left in a dialog's form is validated on Submit, and a file nobody can see then stops the
 * dialog from inserting anything.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('tmp-for-tests');

    $this->livewire = new TestSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    // Built the way Livewire builds one - see `MediaLibraryPendingTest` for why a fake will
    // not do: everything downstream reads the bytes.
    $this->hold = function (string $name, string $bytes): TemporaryUploadedFile {
        $fake = new UploadedFile(tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    // The browser's dialog, holding one replacement the way Filament's upload field holds
    // it: keyed by the id the widget made up for it.
    $this->offer = function (TemporaryUploadedFile $file, string $action = 'mediaBrowser'): void {
        data_set($this->livewire, 'mountedActions', [
            ['name' => $action, 'data' => ['media' => null, 'replacement' => ['a1b2' => $file]]],
        ]);
    };

    Storage::disk('public')->put('article-attachments/sunset.png', UploadedFile::fake()->image('sunset.png', 4, 4)->get());
});

it('swaps the file the panel is showing for the upload the dialog holds', function (): void {
    $bytes = UploadedFile::fake()->image('dawn.png', 12, 5)->get();

    ($this->offer)(($this->hold)('dawn.png', $bytes));

    $result = $this->editor->replaceMediaForJs('article-attachments/sunset.png');

    expect($result['replaced'])->toBeTrue()
        ->and($result['item'])->toMatchArray(['id' => 'article-attachments/sunset.png', 'width' => 12, 'height' => 5])
        ->and(Storage::disk('public')->get('article-attachments/sunset.png'))->toBe($bytes)
        ->and(data_get($this->livewire, 'mountedActions.0.data.replacement'))->toBe([]);
});

it('lets go of the upload when it is refused, and says what would have been taken', function (): void {
    ($this->offer)(($this->hold)('dawn.jpg', UploadedFile::fake()->image('dawn.jpg', 12, 5)->get()));

    $result = $this->editor->replaceMediaForJs('article-attachments/sunset.png');

    expect($result)->toBe(['replaced' => false, 'accept' => ['.png']])
        ->and(data_get($this->livewire, 'mountedActions.0.data.replacement'))->toBe([])
        ->and(Storage::disk('public')->exists('article-attachments/dawn.jpg'))->toBeFalse();
});

it('refuses where the field says no', function (): void {
    ($this->offer)(($this->hold)('dawn.png', UploadedFile::fake()->image('dawn.png', 12, 5)->get()));

    $before = Storage::disk('public')->get('article-attachments/sunset.png');

    expect($this->editor->mediaLibraryReplaceable(false)->replaceMediaForJs('article-attachments/sunset.png')['replaced'])->toBeFalse()
        ->and(Storage::disk('public')->get('article-attachments/sunset.png'))->toBe($before);
});

it('refuses an upload that is not saved yet, and a dialog holding nothing', function (): void {
    expect($this->editor->replaceMediaForJs('article-attachments/sunset.png')['replaced'])->toBeFalse();

    ($this->offer)(($this->hold)('dawn.png', UploadedFile::fake()->image('dawn.png', 12, 5)->get()));

    expect($this->editor->replaceMediaForJs(FileAttachments::PENDING_PREFIX.'held')['replaced'])->toBeFalse();
});

it('leaves an upload held by somebody else\'s dialog alone', function (): void {
    $file = ($this->hold)('dawn.png', UploadedFile::fake()->image('dawn.png', 12, 5)->get());

    ($this->offer)($file, action: 'someoneElses');

    expect($this->editor->replaceMediaForJs('article-attachments/sunset.png')['replaced'])->toBeFalse()
        ->and(data_get($this->livewire, 'mountedActions.0.data.replacement'))->toBe(['a1b2' => $file]);
});

it('says what may replace the selected file in its details', function (): void {
    expect($this->editor->getMediaDetailsForJs('article-attachments/sunset.png')['replace'])->toBe(['.png'])
        ->and($this->editor->mediaLibraryReplaceable(false)->getMediaDetailsForJs('article-attachments/sunset.png')['replace'])->toBeNull();
});

it('describes a replaced document the way its card is written', function (): void {
    // What the open editor writes into the cards pointing at it: the size especially, which
    // a card carries as the label it was inserted with.
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    Storage::disk('public')->put('article-attachments/report.pdf', $pdf);

    ($this->offer)(($this->hold)('report-2026.pdf', $pdf.str_repeat('%', 2048)));

    $result = $this->editor->replaceMediaForJs('article-attachments/report.pdf');

    expect($result['replaced'])->toBeTrue()
        ->and($result['card'])->toBe(['name' => 'report.pdf', 'size' => '2 KB']);
});
