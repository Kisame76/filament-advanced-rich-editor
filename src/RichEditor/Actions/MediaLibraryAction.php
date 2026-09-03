<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;

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
    /**
     * The action a media button should mount.
     *
     * Asked per field rather than decided once, because a field may have nothing browsable
     * behind it - a foreign attachment provider, or a disk with no directory to tell its
     * pictures apart. There the browser is not registered at all and the honest answer is
     * Filament's own dialog, which is also what the button did before any of this existed.
     *
     * Evaluated when the button draws itself rather than when it is defined: `action()`
     * resolves its argument through the tool, and the tool only knows its editor by then.
     */
    public static function nameFor(RichEditorTool $tool): string
    {
        $editor = $tool->getEditor();

        return ($editor instanceof AdvancedRichEditor) && $editor->getMediaSource() !== null
            ? 'mediaBrowser'
            : 'attachFiles';
    }

    /**
     * Which family a resolved item belongs to, or null where nothing says.
     *
     * The row first, because both sources and the pending-upload path already answer this
     * and a second derivation is a second answer to keep in step. The address only where
     * there is no row - a file somebody else hosts has no mime type this side ever saw, and
     * `MediaUrl::guess()` is the one that strips a query string before reading the ending.
     *
     * @param  array<string, mixed>|null  $item
     */
    public static function kindOf(?array $item): ?string
    {
        if ($item === null) {
            return null;
        }

        $kind = $item['kind'] ?? null;

        if (is_string($kind) && in_array($kind, MediaKinds::all(), strict: true)) {
            return $kind;
        }

        return MediaKinds::of(is_string($item['mime'] ?? null) ? $item['mime'] : null)
            ?? MediaUrl::guess(is_string($item['url'] ?? null) ? $item['url'] : null);
    }

    public static function make(): Action
    {
        // Registered under a name of its own rather than taking Filament's.
        //
        // It used to be `attachFiles`, replacing Filament's action by name - which meant a
        // project that wanted the plain upload dialog could not have it back at all, on any
        // field, because the browser had eaten the only name it answers to. Two different
        // dialogs sharing one name is one dialog.
        //
        // The cost of the rename is that nothing points here by accident any more, so both
        // buttons say where they are going: `image` and `mediaBrowser` pick this action when
        // there is a pool to browse and Filament's when there is not.
        return Action::make('mediaBrowser')
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
                'src' => null,
            ])
            ->schema(fn (array $arguments, AdvancedRichEditor $component): array => [
                MediaPicker::make('media')
                    ->hiddenLabel()
                    ->editorKey($component->getKey())
                    // Which button was pressed. The sound button opens on sounds rather than
                    // on a grid of everything, since whoever pressed it has already said what
                    // they were looking for.
                    ->kind(fn (): ?string => is_string($arguments['kind'] ?? null) ? $arguments['kind'] : null)
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
                    // The browser's own list, which is wider than Filament's. Filament's
                    // governs its dialog AND its compiled drop handler, and that handler
                    // inserts an `image` node for anything it accepts - so widening it would
                    // make a dropped film an `<img>` pointing at an mp4.
                    ->acceptedFileTypes($component->getMediaLibraryAcceptedFileTypes())
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    // Held as a temporary upload and handed to the provider on save, exactly as
                    // Filament's own dialog does it - the whole upload path is unchanged, it has
                    // just stopped being the only way in.
                    ->storeFiles(false),

                TextInput::make('alt')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.alt'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_alt.hint'))
                    ->maxLength(1000),

                // A file that is not on this server, in the one dialog rather than behind a
                // second door. That was the whole complaint about the way video used to work
                // - the library here, a separate address dialog there, and a person deciding
                // which of the two to open before knowing which one has the file.
                //
                // Nothing typed here reaches the library: it has no row, no thumbnail and no
                // attachment id, and it cannot be picked again tomorrow. It is a link, not a
                // file that belongs to this project, and the helper text says so.
                TextInput::make('src')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.address'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.address_hint'))
                    ->maxLength(2000)
                    // Refused here rather than quietly rendered as nothing later, and the
                    // whole of the check is the scheme: a path with none is the ordinary
                    // case, `http`/`https` are allowed, and `javascript:` is what turns a
                    // player into a script.
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && MediaUrl::src($value) === null) {
                            $fail(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media.unsupported'));
                        }
                    }),
            ])
            ->action(function (array $arguments, array $data, AdvancedRichEditor $component): void {
                // The whole item rather than its two identifying fields: what it was
                // measured at on upload rides along with it, and that is what keeps the
                // article below a picture from jumping when the picture arrives.
                $item = null;

                // One list and one selection. An upload became a pending attachment the moment
                // it arrived, so it is picked by id exactly like a picture from the library -
                // and resolved rather than trusted, because the id came from the browser and
                // the field is the only thing that decides what it may point at.
                if (filled($data['media'] ?? null)) {
                    $item = $component->findMediaItem($data['media']);
                }

                // Somebody uploaded a picture and nothing ended up selected. The grid normally
                // selects an arriving upload for them, so this is the case where it could not -
                // and inserting nothing after a deliberate upload is the one outcome that reads
                // as the dialog being broken. The newest pending attachment is what just
                // arrived, and it is resolved through the field like everything else.
                if ($item === null && filled($data['file'] ?? null)) {
                    $item = $component->getPendingMediaItems()[0] ?? null;
                }

                $id = $item['id'] ?? null;
                $src = $item['url'] ?? null;

                // A file somebody else hosts. It has no row, no id and no thumbnail - what
                // is known about it is its address, and the family is read off that. Only
                // where nothing was picked: a selection is a file that is actually here, and
                // it wins over a line of text left in a field.
                if (blank($id) && blank($src) && filled($typed = MediaUrl::src($data['src'] ?? null))) {
                    $src = $typed;
                    $item = ['url' => $typed];
                }

                $alt = filled($data['alt'] ?? null) ? $data['alt'] : null;

                // The family the file belongs to decides which node is written, and that is
                // the whole of the difference between the three. A picture stays Filament's
                // `image` node with everything that hangs off it - caption, float, size,
                // link, decorative - and a video or a sound becomes this package's `media`
                // node, which draws the element that can play it.
                //
                // One node per family rather than one node for all of them: unifying the way
                // IN is what this dialog is for, and unifying the storage would have meant
                // moving five image extensions onto a new node and migrating every document
                // that already exists, to say the same thing a different way.
                //
                // Decided BEFORE the branch below, and that ordering is the whole point: the
                // dialog opened on a picture used to answer every submission by rewriting
                // that picture, which was right while the library held nothing else. Picking
                // a film there wrote `<img src="...mp4">` - a broken picture on the page and
                // a video's id on an image node.
                $kind = static::kindOf($item);

                if (filled($arguments['src'] ?? null)) {
                    // Fixes an issue where the editor selection is sent as text instead of a node,
                    // which causes the image update to fail even though the image is selected.
                    if (($arguments['editorSelection']['type'] ?? null) !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor']--;

                        unset($arguments['editorSelection']['head']);
                    }

                    // Still a picture - either one was picked, or nothing was and the dialog
                    // was opened to correct the alt text, in which case the image it is
                    // standing on stays put.
                    if ($kind === null || $kind === MediaKinds::IMAGE) {
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

                    // A film chosen while standing on a picture. Falls through to the insert
                    // below, which replaces the selected node rather than updating it -
                    // an `<img>` cannot be turned into a `<video>` by writing attributes.
                }

                if (blank($src)) {
                    return;
                }

                if ($kind !== null && $kind !== MediaKinds::IMAGE) {
                    $component->runCommands(
                        [
                            EditorCommand::make('setMedia', arguments: [[
                                'kind' => $kind,
                                'src' => $src,
                                // Carried so the save can tell a file that is still in use
                                // from one nothing points at any more.
                                'id' => $id,
                                // The alt field is the one line of text this dialog collects,
                                // and on a player it is the title a screen reader reads out
                                // instead of the file name.
                                'title' => $alt,
                                'preload' => 'metadata',
                                'loop' => false,
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            // Only here, and deliberately not on the update above: that one
                            // runs when the dialog was opened to correct an alt text, and the
                            // size it would write is the file's rather than the one somebody
                            // dragged the picture to.
                            'attrs' => ImageAttributes::forInsert(
                                item: $item ?? [],
                                alt: $alt,
                                loading: $component->getImageLoading(),
                                withDimensions: $component->hasImageDimensions(),
                            ),
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
