<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * The grid's markup.
 *
 * Nothing else in the suite renders it, and it is the one file where a mistake is invisible
 * until somebody opens the dialog: an unbalanced directive, a label that never crossed into
 * JavaScript, or the editor key going missing - which would leave the grid asking the wrong
 * component for its pages and showing an empty library for ever.
 */
function renderPicker(bool $hasFolders = true, bool $isRecordScoped = true, bool $isListView = false, bool $canDescribe = true): string
{
    View::share('errors', new ViewErrorBag);

    return MediaPicker::make('media')
        ->editorKey('editor-key')
        ->folders($hasFolders)
        ->recordScoped($isRecordScoped)
        ->listView($isListView)
        ->canDescribe($canDescribe)
        ->container(Schema::make(new TestSchemaComponent)->operation('edit'))
        ->render()
        ->render();
}

it('compiles', function (): void {
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/media-picker.blade.php'));

    $file = tempnam(sys_get_temp_dir(), 'arte-media-view-').'.php';
    file_put_contents($file, $compiled);

    exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);

    unlink($file);

    expect($status)->toBe(0, implode(PHP_EOL, $output));
});

it('asks the editor it belongs to for its pages', function (): void {
    $html = renderPicker();

    expect($html)
        ->toContain('callSchemaComponentMethod')
        ->toContain('getMediaLibraryPageForJs')
        // Without the key the grid would address no component at all, and the library would
        // simply look empty rather than look broken.
        ->toContain("'editor-key'");
});

it('binds what was picked to the field it lives in', function (): void {
    // The picked id has to reach the action's `$data`, which is the whole reason this is a
    // field and not a bare view.
    expect(renderPicker())->toContain('entangle');
});

it('carries its strings into the browser', function (): void {
    // The grid is built in JavaScript, so anything it draws has to be handed over from PHP -
    // this is the only place that knows the locale.
    $html = renderPicker();

    foreach (['Search files', 'Upload', 'Up one folder', 'Dimensions', 'Newest first'] as $label) {
        expect($html)->toContain($label);
    }
});

it('uses Filament\'s own controls rather than lookalikes', function (): void {
    // A panel with its own theme, its own dark mode or its own input styling should get a
    // dialog that belongs to it - which a hand-rolled input never does.
    $html = renderPicker();

    expect($html)
        ->toContain('fi-input')
        ->toContain('fi-select-input')
        ->toContain('fi-btn')
        ->toContain('fi-icon-btn')
        ->toContain('fi-dropdown');
});

it('loads the component the grid is made of', function (): void {
    // The behaviour lives in `resources/js/media-picker.js` and reaches the page the way
    // Filament's own fields do. A view that named the wrong component, or forgot to load it
    // at all, would render an element Alpine cannot start - an empty dialog and no error.
    expect(renderPicker())
        ->toContain('x-load-src')
        ->toContain('components/media-picker.js')
        ->toContain('arteMediaPicker(');
});

it('makes the library itself the dropzone', function (): void {
    // A separate upload target would be a second place to look, sitting exactly where the
    // pictures being compared want to be.
    $html = renderPicker();

    expect($html)
        ->toContain('onDrop($event)')
        ->toContain('onDragOver($event)');

    // What happens to the dropped files afterwards is asserted in
    // `tests/js/media-picker.test.js`, and the upload field they are handed to belongs to the
    // dialog rather than to this view - `MediaLibraryFieldTest` watches that it keeps the
    // class the grid finds it by.
});

it('opens in the layout it was told to', function (): void {
    // Tiles by default, because picking a picture is done by looking at pictures.
    expect(renderPicker())->toContain('listView: false')
        ->and(renderPicker(isListView: true))->toContain('listView: true');
});

it('asks for the details of one picture rather than of the grid', function (): void {
    // Measuring a picture means opening it, so the panel fetches its own - a grid that
    // measured every tile would be a file read per thumbnail.
    expect(renderPicker())->toContain('getMediaDetailsForJs');
});

it('says something different about an empty record and an empty library', function (): void {
    // "No pictures yet" means "upload one" on a record and "the library is bare" in a shared
    // pool. One message for both would be wrong in one of the two places.
    expect(renderPicker(isRecordScoped: true))->toContain('No pictures on this record yet')
        ->and(renderPicker(isRecordScoped: false))->toContain('The library is empty');
});

it('draws no folder bar where the pool has no folders', function (): void {
    expect(renderPicker(hasFolders: false))->toContain('hasFolders: false')
        ->and(renderPicker(hasFolders: true))->toContain('hasFolders: true');
});

it('takes the opening layout from the field', function (): void {
    // Every other per-field media option is a `mediaLibrary*()` call on the editor, and the
    // README documented this one as though it were too - while the only way to set it was the
    // config key, because the setter existed on the picker alone.
    $editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->mediaLibraryListView()
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    expect($editor->hasMediaLibraryListView())->toBeTrue()
        ->and(AdvancedRichEditor::make('content')->hasMediaLibraryListView())->toBeFalse();

    config()->set('filament-advanced-rich-editor.media_library.list_view', true);

    expect(AdvancedRichEditor::make('content')->hasMediaLibraryListView())->toBeTrue()
        // ...and the field still wins over the project.
        ->and(AdvancedRichEditor::make('content')->mediaLibraryListView(false)->hasMediaLibraryListView())->toBeFalse();
});

it('describes the selection in the panel rather than under the grid', function (): void {
    // The two inputs under the grid pushed it up and read as part of the picker, which they
    // were not: an alt text belongs to the medium being looked at, so it belongs beside it.
    $html = renderPicker();

    expect($html)
        ->toContain('saveMediaMetadataForJs')
        ->toContain('saveDescription()')
        ->toContain('descriptionLabel');
});

it('offers the selected file for download', function (): void {
    // A plain anchor with the `download` attribute: on a private disk `url` is already the
    // temporary signed one, so nothing else has to know about visibility.
    expect(renderPicker())->toContain('download');
});

it('writes no description field where the field has no library to write to', function (): void {
    // A picker rendered without a pool - which the address-only case is - has nowhere to
    // put a description, and an input that silently does nothing is worse than no input.
    expect(renderPicker(canDescribe: false))->not->toContain('saveDescription()');
});

it('offers no delete button in a library shared across records', function (): void {
    // The file may be in content this editor cannot see. A button that cannot know that is
    // a button that quietly breaks somebody else's page.
    expect(renderPicker(isRecordScoped: false))->toContain('canDelete: false')
        ->and(renderPicker(isRecordScoped: true))->toContain('canDelete: true');
});

it('draws the trigger it was handed, beside Upload', function (): void {
    // The one that actually renders it: the picker is handed somebody else's action and has
    // to put it on screen. Handing it nothing must leave the header alone.
    View::share('errors', new ViewErrorBag);

    $editor = AdvancedRichEditor::make('content')
        ->fileAttachmentsDirectory('article-attachments')
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record(Post::create(['title' => 'P'])));

    $html = MediaPicker::make('media')
        ->editorKey('editor-key')
        ->fromUrlAction(fn (): ?Action => $editor->getAction('mediaBrowserUrl'))
        ->container(Schema::make(new TestSchemaComponent)->operation('edit'))
        ->render()
        ->render();

    expect($html)->toContain('From a link')
        ->and($html)->toContain('mediaBrowserUrl')
        // As markup rather than as text. Blade escapes a plain string, so rendering
        // `->toHtml()` here put an escaped `<button>` on screen for a person to read.
        ->and($html)->not->toContain('&lt;button');
});

it('draws the add-from-a-link trigger beside Upload rather than under the grid', function (): void {
    // Under the grid it sat below the *tallest* column - the details panel - so a short
    // list left a field of nothing above it. And "add something" is not a thing anybody
    // looks for at the bottom of what they are looking through.
    $html = renderPicker();

    $header = substr($html, (int) strpos($html, 'fi-arte-media-header'), 1200);

    expect($header)->toContain('fi-arte-media-add')
        ->and($html)->not->toContain('labels.addEmbed');
});

it('plays an embed only once it is asked to', function (): void {
    // An iframe drawn on selection would call the video service from every editor that
    // opens the dialog, which is the tracking the cookie-free host exists to avoid.
    $html = renderPicker();

    expect($html)->toContain('playing = true')
        ->and($html)->toContain('fi-arte-media-play')
        ->and($html)->toContain('selected.frame');
});

it('offers no download for something that is not a file', function (): void {
    expect(renderPicker())->toContain('! isEmbed(selected)');
});
