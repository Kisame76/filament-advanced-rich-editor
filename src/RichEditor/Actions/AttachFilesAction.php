<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\Actions\AttachFilesAction as BaseAttachFilesAction;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Filament's upload dialog, which no longer turns a document into a picture.
 *
 * Its action inserts an `image` node for whatever was uploaded, so a pdf the field accepts
 * was stored as `<img src="….pdf">`. Everything about the dialog stays Filament's - the form,
 * the accepted types, the size limit, the alt text, the picture it inserts - and only the
 * answer to "which node" is asked first. A picture goes on to Filament's own action
 * untouched; anything else becomes the node `UploadsDocuments` names for it.
 *
 * Built on top of Filament's action rather than copied from it, so a fix to the dialog in
 * Filament is a fix here too.
 */
class AttachFilesAction
{
    public static function make(): Action
    {
        $action = BaseAttachFilesAction::make();
        $upstream = $action->getActionFunction();

        return $action->action(function (Action $action, array $arguments, array $data, RichEditor $component, Component $livewire) use ($upstream): void {
            $file = $data['file'] ?? null;

            // Not while a picture is being edited: the dialog opened on one changes it in
            // place, and that stays Filament's business.
            if ($component instanceof AdvancedRichEditor && $file instanceof TemporaryUploadedFile && blank($arguments['src'] ?? null)) {
                $id = (string) Str::orderedUuid();
                $node = $component->uploadedFileAttachmentNode($id, $file, alt: $data['alt'] ?? null);

                if ($node !== null && $node['type'] !== 'image') {
                    // Filed where Filament files its own uploads, so the save finds it.
                    data_set($livewire, "componentFileAttachments.{$component->getStatePath()}.{$id}", $file);

                    $component->runCommands(
                        [EditorCommand::make('insertContent', arguments: [$node])],
                        editorSelection: $arguments['editorSelection'] ?? null,
                    );

                    return;
                }
            }

            $action->evaluate($upstream, ['arguments' => $arguments, 'data' => $data]);
        });
    }
}
