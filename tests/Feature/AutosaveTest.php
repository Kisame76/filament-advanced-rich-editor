<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AutosavePlugin;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * A field on a form that is editing one particular record, which is what a draft has to be
 * told apart by.
 */
function editorFor(?int $id, string $name = 'content'): AdvancedRichEditor
{
    $schema = Schema::make(new TestSchemaComponent)->operation('edit');

    if ($id !== null) {
        $schema->record(Post::make(['id' => $id]));
    }

    return AdvancedRichEditor::make($name)->container($schema);
}

/**
 * @return array<int, string>
 */
function autosavePlugins(mixed $editor): array
{
    return array_map(static fn (object $plugin): string => $plugin::class, $editor->getPlugins());
}

it('keeps a draft unless a project says otherwise', function (): void {
    expect(autosavePlugins(editor()))->toContain(AutosavePlugin::class);
});

it('stores nothing on the server and puts nothing on the bar', function (): void {
    // A draft never reaches the application, and a document restored from one is an
    // ordinary document by the time it is submitted. The whole feature lives in the browser.
    $plugin = AutosavePlugin::make();

    expect($plugin->getTipTapPhpExtensions())->toBe([])
        ->and($plugin->getEditorTools())->toBe([])
        ->and($plugin->getEditorActions())->toBe([])
        ->and($plugin->getTipTapJsExtensions())->toHaveCount(1)
        ->and($plugin->getTipTapJsExtensions()[0])->toContain('autosave');
});

it('hands over the key and the numbers behind it', function (): void {
    $settings = editor()->getAutosaveSettingsForJs();

    expect($settings)->toHaveKeys(['key', 'debounce', 'ttl', 'warnOnLeave', 'labels'])
        ->and($settings['debounce'])->toBe(1500)
        // Seconds in the config file, because that is how a person writes a day -
        // milliseconds by the time the browser compares one to a timestamp.
        ->and($settings['ttl'])->toBe(86400 * 1000)
        ->and($settings['warnOnLeave'])->toBeTrue()
        // One sentence with the time in it: where the time sits in it is a question about
        // the language, and answering it in JavaScript would be a second translation layer.
        ->and($settings['labels']['found'])->toContain(':time')
        ->and($settings['labels'])->toHaveKeys(['found', 'restore', 'discard']);
});

it('carries them into the markup, which is the only way the extension has to them', function (): void {
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    expect($compiled)->toContain('data-arte-autosave')
        ->and($compiled)->toContain('getAutosaveSettingsForJs');
});

it('gives two fields on one form two different drafts', function (): void {
    // Same page, same record, different field: a draft found by the wrong one would put the
    // summary into the body.
    expect(editor('content')->getAutosaveKey())->not->toBe(editor('summary')->getAutosaveKey());
});

it('gives two records two different drafts', function (): void {
    expect(editorFor(1)->getAutosaveKey())->not->toBe(editorFor(2)->getAutosaveKey())
        // And a record that does not exist yet is not the same as the first one that does.
        ->and(editorFor(null)->getAutosaveKey())->not->toBe(editorFor(1)->getAutosaveKey());
});

it('says nothing in the key about what it is a draft of', function (): void {
    // It is a key in a browser's storage that anything on the origin can read. What it says
    // is that two drafts are different, not what either of them is about.
    $key = editorFor(7)->getAutosaveKey();

    expect($key)->toMatch('/^[0-9a-f]{16}$/')
        ->and($key)->not->toContain('Post')
        ->and($key)->not->toContain('content');
});

it('finds the same draft again on the same field and record', function (): void {
    // The whole feature rests on this: a key that changed between two renders of one form
    // would write drafts nobody is ever offered.
    expect(editorFor(3)->getAutosaveKey())->toBe(editorFor(3)->getAutosaveKey());
});

it('takes the extension away with the setting', function (): void {
    $editor = editor()->autosave(false);

    expect($editor->getAutosaveSettingsForJs())->toBeNull()
        ->and(autosavePlugins($editor))->not->toContain(AutosavePlugin::class);
});

it('keeps the draft when a field only wants no question on the way out', function (): void {
    $editor = editor()->autosaveWarnOnLeave(false);

    expect($editor->getAutosaveSettingsForJs()['warnOnLeave'])->toBeFalse()
        ->and(autosavePlugins($editor))->toContain(AutosavePlugin::class);
});

it('reads all four settings out of the config file', function (): void {
    config()->set('filament-advanced-rich-editor.autosave.debounce', 400);
    config()->set('filament-advanced-rich-editor.autosave.ttl', 60);
    config()->set('filament-advanced-rich-editor.autosave.warn_on_leave', false);

    $settings = editor()->getAutosaveSettingsForJs();

    expect($settings['debounce'])->toBe(400)
        ->and($settings['ttl'])->toBe(60_000)
        ->and($settings['warnOnLeave'])->toBeFalse();

    config()->set('filament-advanced-rich-editor.autosave.enabled', false);

    expect(editor()->hasAutosave())->toBeFalse();
});

it('lets a field decide for itself', function (): void {
    config()->set('filament-advanced-rich-editor.autosave.enabled', false);

    expect(editor()->autosave()->hasAutosave())->toBeTrue();
});
