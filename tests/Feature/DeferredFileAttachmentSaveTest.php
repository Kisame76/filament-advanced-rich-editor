<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\TestSchemaComponent;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\Post;

/**
 * Filament writes the editor's state back to a freshly created record through
 * `saveFileAttachmentsToRecord()`, resolving the attribute name with
 * `getContentAttribute()->getName()`. That attribute only exists when the model
 * registers rich content, so every plain model would fatal there as soon as a file
 * attachment provider asks for a persisted record - which the media library one does.
 */
it('writes the state back to a record that has just been created', function (): void {
    $post = Post::create(['title' => 'Deferred']);
    $post->wasRecentlyCreated = true;

    $field = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary()
        ->container(Schema::make(new TestSchemaComponent)->operation('create')->record($post));

    $field->state('<p>Saved after the record existed.</p>');

    $field->saveFileAttachmentsToRecord();

    expect($post->refresh()->content)->toContain('Saved after the record existed.');
});

it('leaves an existing record alone', function (): void {
    $post = Post::create(['title' => 'Existing', 'content' => 'untouched']);
    $post->wasRecentlyCreated = false;

    $field = AdvancedRichEditor::make('content')
        ->spatieMediaLibrary()
        ->container(Schema::make(new TestSchemaComponent)->operation('edit')->record($post));

    $field->state('<p>Not written.</p>');

    $field->saveFileAttachmentsToRecord();

    expect($post->refresh()->content)->toBe('untouched');
});

it('does nothing without a file attachment provider', function (): void {
    $post = Post::create(['title' => 'No provider', 'content' => 'untouched']);
    $post->wasRecentlyCreated = true;

    $field = AdvancedRichEditor::make('content')
        ->container(Schema::make(new TestSchemaComponent)->operation('create')->record($post));

    $field->state('<p>Not written.</p>');

    $field->saveFileAttachmentsToRecord();

    expect($post->refresh()->content)->toBe('untouched');
});
