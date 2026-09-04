<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\MediaPicker;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\Embeds;
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

    /**
     * A link, whatever kind of link it is.
     *
     * One door rather than two, because from the outside they were one question - "I have
     * an address, put it in" - and asking somebody to decide which of two dialogs their
     * link belongs in is asking them to know the answer before they have it.
     *
     * What follows from the link is not the same, though, and the helper text says so: a
     * YouTube or Vimeo address becomes an entry in the library, there to be picked again
     * tomorrow; anything else is a file on somebody else's server, which is inserted and
     * stored nowhere, because it is not ours to keep.
     */
    public static function fromUrl(): Action
    {
        return Action::make('mediaBrowserUrl')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.from_url'))
            ->icon('heroicon-m-link')
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.from_url_heading'))
            ->modalWidth(Width::Large)
            ->schema(fn (AdvancedRichEditor $component): array => [
                TextInput::make('url')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.from_url_label'))
                    ->helperText(__(
                        'filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.'
                        .($component->hasEmbeds() ? 'from_url_hint' : 'from_url_hint_files'),
                    ))
                    ->required()
                    ->maxLength(2000)
                    // One rule for both, refusing only what neither half will take. The
                    // dialog is the last moment anybody can paste something else.
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                        if (blank($value)) {
                            return;
                        }

                        if (EmbedUrl::parse(is_string($value) ? $value : null) !== null) {
                            // A video this package would frame, on a field that has no node
                            // to frame it in. Refused rather than passed to the file half,
                            // which would happily insert a player pointing at a watch page
                            // and play nothing at all.
                            if (! $component->hasEmbeds()) {
                                $fail(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.from_url_no_embeds'));
                            }

                            return;
                        }

                        if (MediaUrl::src($value) === null) {
                            $fail(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media.unsupported'));
                        }
                    }),

                TextInput::make('title')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media_library.title'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.media.title_hint'))
                    ->maxLength(1000),
            ])
            ->action(function (array $data, AdvancedRichEditor $component): void {
                // Held in a variable because `data_set()` takes its target by reference,
                // which a method call cannot be.
                $livewire = $component->getLivewire();

                $embed = $component->hasEmbeds() ? EmbedAction::parse($data) : null;

                if ($embed !== null) {
                    $id = $component->getMediaSource()?->saveEmbed($embed);

                    if (blank($id)) {
                        return;
                    }

                    // The browser's own selection, written straight into the form
                    // underneath - index 0 is the browser, this dialog being the one on top
                    // of it. The grid reloads on the event below and finds it picked.
                    data_set($livewire, 'mountedActions.0.data.media', $id);

                    $livewire->dispatch('arte-media-added', id: $id);

                    return;
                }

                $src = MediaUrl::src($data['url'] ?? null);

                if ($src === null) {
                    return;
                }

                // This one does not insert. It writes into the browser's own form and
                // closes, so the one thing that inserts is still the browser's Submit -
                // two insert paths would be two places for the family rules to disagree.
                data_set($livewire, 'mountedActions.0.data.src', $src);
                data_set($livewire, 'mountedActions.0.data.alt', filled($data['title'] ?? null) ? $data['title'] : null);
                // Nothing is picked from the grid any more, or the selection would win over
                // the address that was just typed.
                data_set($livewire, 'mountedActions.0.data.media', null);

                $livewire->dispatch('arte-media-added', id: null);
            });
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
                    // Nowhere to write a description where there is no pool behind the
                    // field, and an input that saves nowhere looks like one that worked.
                    ->canDescribe($component->getMediaSource() !== null)
                    // Drawn beside Upload: the two ways of putting something in belong
                    // together, and this one is registered on the editor rather than here.
                    //
                    // Through a closure, so it is asked for when the dialog draws rather
                    // than while its schema is being built - at that moment the editor's
                    // own actions are still being cached and it answers null, which is a
                    // button that silently never appears.
                    ->fromUrlAction(fn (): ?Action => $component->getAction('mediaBrowserUrl'))
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

                // A file somebody else hosts, filled in by the nested address dialog rather
                // than typed here. It stays a field because it is what Submit reads - the
                // insert path is one path, whichever door the address came through.
                Hidden::make('src'),

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

                // The description the medium carries, rather than one asked for again here.
                // Global is the default and never a lock: the image bubble toolbar still
                // edits this one insert, which is what it did before.
                //
                // Only for something that was picked. Reopening a picture to correct its
                // position must not overwrite the alt text already on that insert with the
                // library's - the two are allowed to differ, and the one in the document is
                // the more specific of them.
                $described = filled($data['media'] ?? null)
                    ? $component->getMediaMetadata($data['media'])
                    : ['alt' => null, 'title' => null];

                $alt = $described['alt'] ?? ($arguments['alt'] ?? null);
                $title = $described['title'] ?? null;

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

                // A video somebody else hosts, picked out of the library rather than pasted
                // into a dialog. What is written is what the embed dialog writes - the same
                // command, the same five attributes - because a library entry is a shortcut
                // to that dialog and not a second way of storing a video.
                //
                // Before the reopen branch, for the reason the family check is: an embed
                // chosen while the caret is on a picture replaces that picture, and an
                // `<img>` cannot be turned into an iframe by writing attributes at it.
                if ($kind === MediaKinds::EMBED) {
                    $embed = Embeds::describes((array) ($item['embed'] ?? []));

                    if ($embed === null) {
                        return;
                    }

                    $component->runCommands(
                        [EditorCommand::make('setEmbed', arguments: [$embed])],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

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
                                // On a player the description is a title, which is what a
                                // screen reader reads out instead of the file name.
                                'title' => $title,
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
