<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\RecordingSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Everything that is taken away rather than drawn: a pdf, a spreadsheet, an archive.
 *
 * The browser used to stop at pictures, films and sounds, and the document card had no way in
 * at all. What is pinned here is the whole road - which files the pool lists, which uploads it
 * takes, what picking one writes into the document, and how a card already in it goes back to
 * the browser - on a disk, where most of the questions are answered by a file's name.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Storage::fake('tmp-for-tests');

    $this->livewire = new RecordingSchemaComponent;

    $this->editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDisk('public')
        ->fileAttachmentsDirectory('article-attachments')
        ->mediaLibraryDirectory('library')
        ->container(Schema::make($this->livewire)->operation('edit')->record(Post::create(['title' => 'Post'])));

    $this->pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

    // An upload the way Livewire holds one, with real bytes - see `MediaLibraryPendingTest`.
    $this->hold = function (string $name, string $bytes): TemporaryUploadedFile {
        $fake = new UploadedFile((string) tempnam(sys_get_temp_dir(), 'arte'), $name, null, null, true);

        file_put_contents($fake->getPathname(), $bytes);

        $stored = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($fake);

        Storage::disk('tmp-for-tests')->put('livewire-tmp/'.$stored, $bytes);

        return TemporaryUploadedFile::createFromLivewire($stored);
    };

    $this->pend = function (string $id, TemporaryUploadedFile $file): void {
        data_set($this->livewire, "componentFileAttachments.{$this->editor->getStatePath()}.{$id}", $file);
    };

    $this->submit = fn (array $data, array $arguments = []) => $this->editor->getAction('mediaBrowser')?->call([
        'data' => ['src' => null, ...$data],
        'arguments' => ['editorSelection' => null, ...$arguments],
    ]);

    $this->command = fn (): array => $this->livewire->commands()[0] ?? [];
});

// What the field offers.

it('offers documents beside pictures, films and sounds', function (): void {
    expect(editor()->getMediaLibraryTypes()->kinds())->toBe(['image', 'video', 'audio', 'file']);
});

it('keeps the picture rule Filament was given', function (): void {
    // A project that narrowed its pictures to PNGs meant that, and the browser has no
    // business overruling it.
    $types = editor()->fileAttachmentsAcceptedFileTypes(['image/png'])->getMediaLibraryTypes();

    expect($types->kindOfPath('a.png'))->toBe('image')
        ->and($types->kindOfPath('a.jpg'))->toBeNull();
});

it('changes one family per field and leaves the others alone', function (): void {
    $types = editor()->mediaLibraryTypes(['file' => ['pdf']])->getMediaLibraryTypes();

    expect($types->kindOfPath('a.pdf'))->toBe('file')
        ->and($types->kindOfPath('a.docx'))->toBeNull()
        ->and($types->kindOfPath('a.mp4'))->toBe('video');

    expect(editor()->mediaLibraryTypes(['video' => [], 'audio' => []])->getMediaLibraryTypes()->kinds())
        ->toBe(['image', 'file']);
});

it('reads the families from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.media_library.types.file', []);

    expect(editor()->getMediaLibraryTypes()->offers('file'))->toBeFalse();
});

it('offers every family where a published config predates the key', function (): void {
    // `mergeConfigFrom()` is shallow, so a project's own copy replaces the whole block.
    config()->set('filament-advanced-rich-editor.media_library', ['enabled' => true]);

    expect(editor()->getMediaLibraryTypes()->kinds())->toBe(['image', 'video', 'audio', 'file']);
});

it('takes films and sounds away where the player is off', function (): void {
    expect(editor()->media(false)->getMediaLibraryTypes()->kinds())->toBe(['image', 'file']);
});

// What a disk lists.

it('lists a document beside a picture, as a file with its own tile', function (): void {
    Storage::disk('public')->put('library/report.pdf', $this->pdf);
    Storage::disk('public')->put('library/sunset.png', 'x');

    $items = collect($this->editor->getMediaSource()->page()['items'])->keyBy('name');

    expect($items['report.pdf']['kind'])->toBe('file')
        ->and($items['report.pdf']['mime'])->toBe('application/pdf')
        // Nothing to draw, and saying so is what makes the grid put the tile there instead.
        ->and($items['report.pdf']['thumbnail'])->toBeNull()
        ->and($items['report.pdf']['badge'])->toBe('PDF')
        ->and($items['report.pdf']['tint'])->toBe('#dc2626')
        ->and($items['sunset.png']['kind'])->toBe('image');
});

it('keeps what it writes beside a file out of the listing', function (): void {
    Storage::disk('public')->put('library/report.pdf', 'x');
    Storage::disk('public')->put('library/report.pdf.json', '{"title":"Q3"}');
    Storage::disk('public')->put('library/talk.mp4', 'x');
    Storage::disk('public')->put('library/talk.mp4.cover.jpg', 'x');

    // Even where JSON is a document the project wants: a description is not a file.
    $names = array_column(
        $this->editor->mediaLibraryTypes(['file' => ['pdf', 'json']])->getMediaSource()->page()['items'],
        'name',
    );

    expect($names)->toEqualCanonicalizing(['report.pdf', 'talk.mp4']);
});

it('narrows the grid to documents on their tab', function (): void {
    Storage::disk('public')->put('library/report.pdf', 'x');
    Storage::disk('public')->put('library/sunset.png', 'x');

    $page = $this->editor->getMediaSource()->page(filters: ['kind' => 'file']);

    expect(array_column($page['items'], 'name'))->toBe(['report.pdf'])
        ->and($page['kinds'])->toBe(['image', 'file']);
});

it('resolves a stored document, and refuses one the list does not take', function (): void {
    Storage::disk('public')->put('library/report.pdf', 'x');
    Storage::disk('public')->put('library/setup.exe', 'x');

    $source = $this->editor->getMediaSource();

    expect($source->has('library/report.pdf'))->toBeTrue()
        ->and($source->has('library/setup.exe'))->toBeFalse();
});

it('makes no cover for a document', function (): void {
    // A film and a sound are copied somewhere local and read for a picture; a pdf has none,
    // and must not be copied - or marked as tried - on every listing.
    config()->set('filament-advanced-rich-editor.media_library.covers.enabled', true);

    Storage::disk('public')->put('library/report.pdf', $this->pdf);

    $this->editor->getMediaSource()->page();

    expect(Storage::disk('public')->exists('library/report.pdf.json'))->toBeFalse()
        ->and(Storage::disk('public')->exists('library/report.pdf.cover.jpg'))->toBeFalse();
});

it('shows a stored name without the part that keeps it unguessable', function (): void {
    Storage::disk('public')->put('library/quartalsbericht-q3--7kq2xm.pdf', $this->pdf);

    $item = $this->editor->getMediaSource()->page()['items'][0];

    expect($item['id'])->toBe('library/quartalsbericht-q3--7kq2xm.pdf')
        ->and($item['name'])->toBe('quartalsbericht-q3.pdf')
        ->and($item['fileName'])->toBe('quartalsbericht-q3.pdf');
});

// What an upload becomes.

it('shows a document that has not been saved yet', function (): void {
    ($this->pend)(FileAttachments::PENDING_PREFIX.'report', ($this->hold)('Quartalsbericht Q3.pdf', $this->pdf));

    $item = $this->editor->getPendingMediaItems()[0] ?? [];

    expect($item['kind'] ?? null)->toBe('file')
        ->and($item['name'])->toBe('Quartalsbericht Q3.pdf')
        ->and($item['badge'])->toBe('PDF')
        ->and($item['thumbnail'])->toBeNull()
        ->and($item['url'])->toBeString()->not->toBeEmpty();
});

it('gives a held document a preview address, whatever finfo calls it', function (): void {
    // Livewire hands one out only for the endings on its own list, read off the content: a
    // spreadsheet written as text is a `txt` to it. Without an address there is no tile.
    ($this->pend)(FileAttachments::PENDING_PREFIX.'prices', ($this->hold)('prices.csv', "name,price\nTea,3\n"));

    // An ending one field adds, and no configuration anywhere knows about.
    $editor = $this->editor->mediaLibraryTypes(['file' => ['csv', 'dwg']]);

    ($this->pend)(FileAttachments::PENDING_PREFIX.'drawing', ($this->hold)('drawing.dwg', "AC1032\0\0\0\0binary"));

    $items = collect($editor->getPendingMediaItems())->keyBy('name');

    expect($items['prices.csv']['url'] ?? null)->toBeString()->not->toBeEmpty()
        ->and($items['drawing.dwg']['url'] ?? null)->toBeString()->not->toBeEmpty();
});

it('says which uploads it refused, and lets go of them', function (): void {
    data_set($this->livewire, 'mountedActions.0.data.file', [
        'one' => ($this->hold)('page.html', '<!DOCTYPE html><html><body>x</body></html>'),
        'two' => ($this->hold)('report.pdf', $this->pdf),
    ]);

    $page = $this->editor->getMediaLibraryPageForJs();

    expect($page['rejected'])->toBe(['page.html'])
        ->and(array_column($page['items'], 'name'))->toBe(['report.pdf'])
        // Out of the dialog's own field too, or the submit would validate a file nobody
        // can see and refuse to insert anything.
        ->and(array_keys(data_get($this->livewire, 'mountedActions.0.data.file')))->toBe(['two']);
});

// What picking one writes.

it('inserts a document as a card', function (): void {
    Storage::disk('public')->put('library/report.pdf', $this->pdf);

    ($this->submit)(['media' => 'library/report.pdf']);

    $command = ($this->command)();

    expect($command['name'] ?? null)->toBe('setFile')
        ->and($command['arguments'][0])->toBe([
            'src' => Storage::disk('public')->url('library/report.pdf'),
            'id' => 'library/report.pdf',
            'name' => 'report.pdf',
            'size' => ByteSize::format(strlen($this->pdf)),
        ]);
});

it('inserts an upload that is not saved yet under the name it came with', function (): void {
    $id = FileAttachments::PENDING_PREFIX.'report';

    ($this->pend)($id, ($this->hold)('Quartalsbericht Q3.pdf', $this->pdf));

    ($this->submit)(['media' => $id]);

    expect(($this->command)()['arguments'][0]['id'] ?? null)->toBe($id)
        ->and(($this->command)()['arguments'][0]['name'] ?? null)->toBe('Quartalsbericht Q3.pdf');
});

it('replaces a picture the caret is standing on rather than rewriting it', function (): void {
    // An `<img>` cannot become a card by writing attributes at it.
    Storage::disk('public')->put('library/report.pdf', $this->pdf);

    ($this->submit)(['media' => 'library/report.pdf'], ['src' => '/storage/old.png', 'id' => 'old-picture']);

    expect(($this->command)()['name'] ?? null)->toBe('setFile');
});

it('inserts a document somebody else hosts as a card', function (): void {
    ($this->submit)(['src' => 'https://cdn.test/files/report.pdf?v=2']);

    expect(($this->command)()['name'] ?? null)->toBe('setFile')
        ->and(($this->command)()['arguments'][0])->toBe([
            'src' => 'https://cdn.test/files/report.pdf?v=2',
            'id' => null,
            'name' => null,
            'size' => null,
        ]);
});

it('replaces the card it was opened from', function (): void {
    // The card's own bar opens the browser with the card selected. Where that selection
    // arrives described as a caret beside the card, the insert would land next to it.
    Storage::disk('public')->put('library/report.pdf', $this->pdf);

    ($this->submit)(['media' => 'library/report.pdf'], [
        'replace' => 'file',
        'id' => 'library/old.pdf',
        'editorSelection' => ['type' => 'text', 'anchor' => 5, 'head' => 5],
    ]);

    $dispatched = collect($this->livewire->dispatched)->last();

    expect(($this->command)()['name'] ?? null)->toBe('setFile')
        ->and($dispatched['params']['editorSelection'] ?? null)->toBe(['type' => 'node', 'anchor' => 4]);
});

// How a card goes back to the browser.

it('opens the browser on the documents tab', function (): void {
    $tool = $this->editor->getTools()['file'] ?? null;

    expect($tool?->getJsHandler())->toContain("mountAction('mediaBrowser'")
        ->and($tool?->getJsHandler())->toContain("kind: 'file'")
        ->and($tool?->getLabel())->toBe('File');
});

it('offers the documents in the slash menu', function (): void {
    $names = array_merge(...array_map(
        static fn (array $group): array => array_column($group['items'], 'name'),
        SlashMenu::for($this->editor)['groups'],
    ));

    expect($names)->toContain('file');
});

it('puts a bar over a selected card that goes back to the browser', function (): void {
    $replace = $this->editor->getTools()['fileReplace'] ?? null;

    expect($this->editor->getFloatingToolbars()['file'] ?? null)->toBe(['fileReplace', 'fileDelete'])
        ->and($replace?->getJsHandler())->toContain("replace: 'file'")
        ->and($replace?->getJsHandler())->toContain("getAttributes('file')")
        ->and($this->editor->getTools()['fileDelete']?->getJsHandler())->toContain('deleteSelection()');
});

it('offers only removing a card where there is no browser to go back to', function (): void {
    // Without a pool the browser button falls back to Filament's own dialog, which takes
    // pictures only - a Replace that opened it would be a door onto the wrong room.
    expect(editor()->getFloatingToolbars()['file'] ?? null)->toBe(['fileDelete']);
});

it('names the documents tab in the browser\'s own words', function (): void {
    $labels = MediaPicker::make('media')->container(testSchema())->getLabels();

    expect($labels['kinds']['file'] ?? null)->toBe('Files')
        ->and($labels['rejected'] ?? null)->toBeString()->not->toBeEmpty();
});

// What a disk stores it as.

it('stores an upload under a name somebody can find again', function (): void {
    $path = $this->editor->saveUploadedFileAttachment(($this->hold)('Quartalsbericht Q3.pdf', $this->pdf));

    expect($path)->toMatch('#^article-attachments/quartalsbericht-q3--[a-z0-9]{6}\.pdf$#')
        ->and(Storage::disk('public')->exists((string) $path))->toBeTrue();
});

it('keeps the ending that was checked when it stores one', function (): void {
    $path = $this->editor->saveUploadedFileAttachment(($this->hold)('Preise.csv', "name,price\nTea,3\n"));

    expect($path)->toMatch('#^article-attachments/preise--[a-z0-9]{6}\.csv$#');
});

it('leaves the naming to Filament where there is no library', function (): void {
    $path = $this->editor->mediaLibrary(false)
        ->saveUploadedFileAttachment(($this->hold)('Quartalsbericht Q3.pdf', $this->pdf));

    expect($path)->not->toContain('quartalsbericht');
});

// What a save hands back.

it('reads a saved card back into the editor as a card', function (): void {
    // The save writes HTML, and the editor is handed that HTML parsed again. A card that came
    // back as a link with bold and a font size on its letters would be saved as exactly that
    // the next time, and the card would be gone for good.
    $card = ['type' => 'file', 'attrs' => [
        'src' => '/storage/library/report.pdf',
        'name' => 'report.pdf',
        'size' => '48 B',
        'id' => 'library/report.pdf',
    ]];

    $html = $this->editor->getTipTapEditor()
        ->setContent(documentOf([paragraphOf([$card])]))
        ->getHtml();

    $document = $this->editor->getTipTapEditor()->setContent($html)->getDocument();

    expect($document['content'][0]['content'][0]['type'] ?? null)->toBe('file')
        ->and($document['content'][0]['content'][0]['attrs'] ?? [])->toMatchArray($card['attrs']);
});
