<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

it('has no file attachment provider until the field opts in', function (): void {
    expect(editor()->getFileAttachmentProvider())->toBeNull();
});

it('hands out a media library provider once the field opts in', function (): void {
    $provider = editor()->spatieMediaLibrary()->getFileAttachmentProvider();

    expect($provider)->toBeInstanceOf(SpatieMediaLibraryFileAttachmentProvider::class)
        ->and($provider->getCollection())->toBe('rich-editor')
        ->and($provider->getConversion())->toBeNull()
        ->and($provider->getDisk())->toBeNull()
        ->and($provider->getDefaultFileAttachmentVisibility())->toBe('public')
        // Media rows need a persisted model, so the save is deferred on a create form.
        ->and($provider->isExistingRecordRequiredToSaveNewFileAttachments())->toBeTrue();
});

it('takes the media library defaults from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.spatie', [
        'collection' => 'editor-images',
        'conversion' => 'preview',
        'disk' => 'media',
        'visibility' => 'private',
    ]);

    $provider = editor()->spatieMediaLibrary()->getFileAttachmentProvider();

    expect($provider->getCollection())->toBe('editor-images')
        ->and($provider->getConversion())->toBe('preview')
        ->and($provider->getDisk())->toBe('media')
        ->and($provider->getDefaultFileAttachmentVisibility())->toBe('private');
});

it('lets the field override every media library default', function (): void {
    $provider = editor()
        ->spatieMediaLibrary('gallery', 'thumb', 's3', 'private')
        ->getFileAttachmentProvider();

    expect($provider->getCollection())->toBe('gallery')
        ->and($provider->getConversion())->toBe('thumb')
        ->and($provider->getDisk())->toBe('s3')
        ->and($provider->getDefaultFileAttachmentVisibility())->toBe('private');
});

it('binds the provider to the record of the field', function (): void {
    $post = Post::create(['title' => 'A post']);

    $provider = editor()->model($post)->spatieMediaLibrary()->getFileAttachmentProvider();

    expect($provider->getRecord())->toBe($post);
});

it('resolves no record while the field has none', function (): void {
    expect(editor()->spatieMediaLibrary()->getFileAttachmentProvider()->getRecord())->toBeNull();
});

it('registers exactly one media library plugin however often the field opts in', function (): void {
    $editor = editor()->spatieMediaLibrary('one')->spatieMediaLibrary('two');

    $mediaLibraryPlugins = array_filter(
        $editor->getPlugins(),
        fn (object $plugin): bool => $plugin instanceof SpatieMediaLibraryPlugin,
    );

    expect($mediaLibraryPlugins)->toHaveCount(1)
        // The last call wins, rather than the first one being stuck in the plugin list.
        ->and($editor->getFileAttachmentProvider()->getCollection())->toBe('two');
});

it('discovers a media library provider registered as a plugin', function (): void {
    $editor = editor()->plugins([SpatieMediaLibraryPlugin::make('attachments')]);

    expect($editor->getFileAttachmentProvider())
        ->toBeInstanceOf(SpatieMediaLibraryFileAttachmentProvider::class)
        ->and($editor->getFileAttachmentProvider()->getCollection())->toBe('attachments');
});

it('prefers the field level provider over a plugin level one', function (): void {
    $editor = editor()
        ->plugins([SpatieMediaLibraryPlugin::make('from-plugin')])
        ->spatieMediaLibrary('from-field');

    expect($editor->getFileAttachmentProvider()->getCollection())->toBe('from-field');
});

it('leaves the editor itself untouched when the media library is opted into', function (): void {
    // The media library plugin only carries the provider - no tool, no toolbar button.
    expect(array_keys(editor()->spatieMediaLibrary()->getTools()))
        ->toBe(array_keys(editor()->getTools()))
        ->and(toolbarShape(editor()->spatieMediaLibrary()))->toBe(toolbarShape(editor()));
});
