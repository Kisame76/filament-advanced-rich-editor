<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EditorSelection;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ImageAttributes;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Cropping, turning and flipping a picture that is already in the document.
 *
 * Filament's own image editor does the editing - the one `FileUpload::imageEditor()` opens,
 * with its crop box, its ratios and its buttons, translated and kept up by Filament. The
 * dialog is a file upload holding the picture as it is, with that editor switched on.
 *
 * What it saves is a new file, held as a pending upload exactly like one dropped into the
 * editor, and only this picture is pointed at it: the old file stays where it was, for every
 * other document using it and for an undo that brings this one back. A pixel edit rather than
 * a style the way a rotation from the bar is one, because a crop has to reach everywhere the
 * document goes - a feed, a mail, an export - and a style does not.
 */
class EditImageAction
{
    public const NAME = 'editImage';

    /**
     * What the button hands the dialog: the picture's address and size, and how wide its file
     * is, which only the browser can tell - see `ImageAttributes::afterEditing()`.
     */
    public const ARGUMENTS = <<<'JS'
        (() => {
            const editor = $getEditor()
            const attributes = editor?.getAttributes('image') ?? {}
            const drawn = editor?.view.nodeDOM(editor.state.selection.from)
            const picture = drawn?.querySelector?.('img') ?? drawn

            return {
                src: attributes.src,
                width: attributes.width,
                height: attributes.height,
                naturalWidth: picture?.naturalWidth,
            }
        })()
        JS;

    /**
     * Straight into the editor, and straight back out of it.
     *
     * The dialog is only the way to Filament's editor, so it is not shown on the way: it waits
     * unseen while the picture loads, the editor opens on the picture the moment it has, Save
     * applies the result as soon as it is uploaded, and Cancel closes the lot. Run on the
     * upload component Filament draws, through the three names its editor has always had -
     * `loadEditor()`, `isEditorOpen` and `pond`.
     *
     * Unseen rather than hidden: the upload refuses to start inside anything `visibility:
     * hidden`. Shown once the editor covers it, so that what is left on screen after Save is
     * the upload's progress, and shown wherever the editor cannot open on its own: the
     * picture failed to load - another host refusing to be read - or what came back is not a
     * picture at all, since Filament takes a page answering 404 for the file; or it is a
     * drawing, which Filament asks about first, and a refusal would leave a dialog nobody can
     * see.
     *
     * FilePond tells the two pictures apart: the one the dialog opened on is a local file
     * (origin 3), the edited one comes back as an input (origin 1).
     */
    public const OPENS_DIRECTLY = <<<'JS'
        (() => {
            const reveal = () => $el.closest('.fi-arte-image-editor-pending')?.classList.remove('fi-arte-image-editor-pending')

            $el.addEventListener('FilePond:addfile', ({ detail }) => {
                if (detail?.error) return reveal()
                if (detail?.file?.origin !== 3) return
                const type = detail.file.file?.type ?? ''
                if (! type.startsWith('image/')) return reveal()
                if (type === 'image/svg+xml') reveal()
                $data.loadEditor(detail.file.file)
            })

            $el.addEventListener('FilePond:processfile', ({ detail }) => detail?.error ? reveal() : $wire.callMountedAction())

            $watch('isEditorOpen', (isOpen) => {
                if (isOpen) return reveal()
                if (! $data.pond?.getFiles().some((file) => file.origin === 1)) $wire.unmountAction()
            })
        })()
        JS;

    public static function make(): Action
    {
        return Action::make(static::NAME)
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_edit.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_edit.label'))
            ->modalSubmitActionLabel(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_edit.apply'))
            ->modalWidth(Width::Large)
            // Unseen until the editor covers it - see `OPENS_DIRECTLY`.
            ->extraModalWindowAttributes(['class' => 'fi-arte-image-editor-pending'])
            ->fillForm(fn (array $arguments): array => [
                'file' => $arguments['src'] ?? null,
            ])
            ->schema(fn (AdvancedRichEditor $component): array => [
                FileUpload::make('file')
                    ->hiddenLabel()
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_edit.hint'))
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions([null, '16:9', '4:3', '3:2', '1:1'])
                    // An edit is drawn on a canvas, so a drawing comes out of it as pixels.
                    // Asked rather than done, because a logo that scaled to any size before
                    // does not afterwards.
                    ->confirmSvgEditing()
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    // The picture as the document shows it. Its address stands in for a path on
                    // a disk, so there is nothing for Filament to look up - the browser loads
                    // it from where the editor already does. Nothing is ever stored or deleted
                    // under it, which is why a tampered address cannot reach anything.
                    ->fetchFileInformation(false)
                    ->preventFilePathTampering(false)
                    ->getUploadedFileUsing(static fn (string $file): array => [
                        'name' => basename((string) parse_url($file, PHP_URL_PATH)),
                        'size' => 0,
                        'type' => null,
                        'url' => $file,
                    ])
                    ->storeFiles(false)
                    // Filament writes these as they are, so the script is escaped here and
                    // read back by the browser.
                    ->extraAlpineAttributes(['x-init' => e(static::OPENS_DIRECTLY)]),
            ])
            ->action(function (array $arguments, array $data, AdvancedRichEditor $component): void {
                $file = $data['file'] ?? null;

                // Closed without editing: the field still holds the address it was opened with.
                if (! ($file instanceof TemporaryUploadedFile)) {
                    return;
                }

                // Resolved through the field like any upload, which checks it again and
                // measures it.
                $item = $component->findMediaItem($component->registerPendingUpload($file));

                if ($item === null) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('updateAttributes', arguments: [
                            'image',
                            ImageAttributes::afterEditing($item, $arguments),
                        ]),
                    ],
                    editorSelection: EditorSelection::onTheNode($arguments['editorSelection'] ?? null),
                );
            });
    }
}
