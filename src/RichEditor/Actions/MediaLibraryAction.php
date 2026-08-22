<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;

/**
 * The image dialog, with the pictures that are already on the server in it.
 *
 * Filament's own dialog asks for a file and nothing else, which makes every use of a picture
 * a new upload - the same image lands on the disk once per article that shows it. This one
 * opens on the library instead and keeps the upload as the second tab, because uploading is
 * what you do when the picture is *not* there yet.
 *
 * Picking an existing item stores what an upload would have stored: the media UUID, or the
 * storage path for a field without a media library. Nothing is copied, so one file on the disk
 * can back any number of references - and nothing new is written into the document, so content
 * saved before this dialog existed and content saved through it are the same content.
 *
 * Size and rotation stay where they were: they are attributes of the image node, not of the
 * file. Reusing a picture at a different size cannot disturb the picture anywhere else.
 */
class MediaLibraryAction
{
    public static function make(): Action
    {
        // Registered under Filament's own name so that everything already pointing at the
        // image dialog keeps pointing at it: the toolbar button, the slash menu entry, and
        // re-opening an image that is already in the document. Switching the library off puts
        // Filament's action back, and nothing else has to know either happened.
        return Action::make('attachFiles')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.heading'))
            // Wide enough for a grid to be a grid. A library shown four thumbnails at a time
            // is a list, and a list is what this dialog exists to stop being.
            ->modalWidth(Width::FourExtraLarge)
            ->fillForm(fn (array $arguments): array => [
                'alt' => $arguments['alt'] ?? null,
                // An image being re-opened starts with its own file picked, so the grid shows
                // which one the caret is on rather than an empty selection.
                'media' => $arguments['id'] ?? null,
            ])
            ->schema(fn (array $arguments, AdvancedRichEditor $component): array => [
                MediaPicker::make('media')
                    ->hiddenLabel()
                    ->editorKey($component->getKey())
                    ->folders($component->getMediaSource()?->hasFolders() ?? false)
                    ->recordScoped($component->getMediaSource()?->isRecordScoped() ?? true)
                    ->pageSize($component->getMediaLibraryPageSize())
                    ->listView($component->hasMediaLibraryListView()),

                // Off screen, and deliberately so: the library itself is the dropzone, and a
                // second one under it would be a second place to look sitting exactly where
                // the pictures being compared want to be.
                //
                // Kept rather than replaced, because it is the whole upload path - Livewire's
                // protocol, the size and type validation, the progress events and the pending
                // attachment. The browser's Upload button clicks its file input and a dropped
                // picture is handed to it, so both ways in travel the same road.
                FileUpload::make('file')
                    ->hiddenLabel()
                    // Several at once: a set of pictures for one article arrives together, and
                    // queueing them one dialog at a time is the tedious way to do it.
                    ->multiple()
                    ->extraAttributes(['class' => 'fi-arte-media-uploader'])
                    ->acceptedFileTypes($component->getFileAttachmentsAcceptedFileTypes())
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    // Held as a temporary upload and handed to the provider on save, exactly as
                    // Filament's own dialog does it - the whole upload path is unchanged, it has
                    // just stopped being the only way in.
                    ->storeFiles(false),

                TextInput::make('alt')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.alt'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.hint'))
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, AdvancedRichEditor $component): void {
                $id = null;
                $src = null;

                // One list and one selection. An upload became a pending attachment the moment
                // it arrived, so it is picked by id exactly like a picture from the library -
                // and resolved rather than trusted, because the id came from the browser and
                // the field is the only thing that decides what it may point at.
                if (filled($data['media'] ?? null)) {
                    $item = $component->findMediaItem($data['media']);

                    if ($item !== null) {
                        $id = $item['id'];
                        $src = $item['url'];
                    }
                }

                // Somebody uploaded a picture and nothing ended up selected. The grid normally
                // selects an arriving upload for them, so this is the case where it could not -
                // and inserting nothing after a deliberate upload is the one outcome that reads
                // as the dialog being broken. The newest pending attachment is what just
                // arrived, and it is resolved through the field like everything else.
                if ($id === null && filled($data['file'] ?? null)) {
                    $arrived = $component->getPendingMediaItems()[0] ?? null;

                    if ($arrived !== null) {
                        $id = $arrived['id'];
                        $src = $arrived['url'];
                    }
                }

                $alt = filled($data['alt'] ?? null) ? $data['alt'] : null;

                if (filled($arguments['src'] ?? null)) {
                    // Fixes an issue where the editor selection is sent as text instead of a node,
                    // which causes the image update to fail even though the image is selected.
                    if (($arguments['editorSelection']['type'] ?? null) !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor']--;

                        unset($arguments['editorSelection']['head']);
                    }

                    // Nothing picked and nothing uploaded means the dialog was opened to
                    // correct the alt text, so the image it is standing on stays put.
                    $id ??= $arguments['id'] ?? null;
                    $src ??= $arguments['src'];

                    $component->runCommands(
                        [
                            EditorCommand::make('updateAttributes', arguments: [
                                'image',
                                ['alt' => $alt, 'id' => $id, 'src' => $src],
                            ]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                if (blank($id) || blank($src)) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => ['alt' => $alt, 'id' => $id, 'src' => $src],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
