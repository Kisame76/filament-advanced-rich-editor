<?php

declare(strict_types=1);

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
function renderPicker(bool $hasFolders = true, bool $isRecordScoped = true, bool $isListView = false): string
{
    View::share('errors', new ViewErrorBag);

    return MediaPicker::make('media')
        ->editorKey('editor-key')
        ->folders($hasFolders)
        ->recordScoped($isRecordScoped)
        ->listView($isListView)
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

it('remembers which layout was last used', function (): void {
    // Browsing in tiles or in a list is a habit, not a setting, so it is remembered in the
    // browser rather than asked again every time the dialog opens.
    expect(renderPicker())
        ->toContain('arte-media-view')
        ->toContain('localStorage');
});

it('makes the library itself the dropzone', function (): void {
    // A separate upload target would be a second place to look, sitting exactly where the
    // pictures being compared want to be.
    $html = renderPicker();

    expect($html)
        ->toContain('onDrop($event)')
        ->toContain('onDragOver($event)')
        // And the drop is handed to Filament's own upload widget rather than uploaded
        // separately, so a dropped picture and a chosen one travel one path.
        ->toContain('addFiles(files)')
        ->toContain('fi-arte-media-uploader');
});

it('shows what was just uploaded, and selects it', function (): void {
    // An upload is handed to the editor as it arrives rather than kept in this dialog, so the
    // grid has nothing to mirror - it asks again and the new picture is in the answer, with a
    // selection following it because that is what somebody expects after dropping a file.
    expect(renderPicker())
        ->toContain("pond.on('processfile'")
        ->toContain('selectNewest');
});

it('opens in the layout it was told to', function (): void {
    // Tiles by default, because picking a picture is done by looking at pictures.
    expect(renderPicker())->toContain('list: false')
        ->and(renderPicker(isListView: true))->toContain('list: true');
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
