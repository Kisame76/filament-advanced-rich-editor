<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\ByteSize;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\MediaKinds;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A document uploaded the plain way, as a card rather than as a broken picture.
 *
 * Filament's upload knows one kind of file. Its dialog and its drop handler both insert an
 * `image` node for whatever arrives, so a field that accepts a pdf - `->fileAttachmentsAcceptedFileTypes()`
 * with `application/pdf` in it - stored `<img src="….pdf">`. The media browser has always
 * drawn a document as a card; a field without one (`->mediaLibrary(false)`) had no way to.
 *
 * This is the one answer both ways in ask: which node an upload becomes. A picture stays the
 * picture Filament makes, a film or a recording becomes a player where the field has them,
 * and everything else becomes a card. The upload itself is Filament's - same temporary
 * storage, same accepted types, same size limit, same save - so a document joins the
 * attachment lifecycle the moment it is in the document, the way the browser's do.
 */
trait UploadsDocuments
{
    /**
     * What the drop and paste handler needs, or null where it should stay out of the way.
     *
     * Null on a field that takes nothing but pictures, because then Filament's own handler is
     * right for every file - including telling somebody a pdf is not accepted. And null on a
     * field with a media browser: there, a document goes through the browser, which measures
     * it against its own list.
     *
     * @return array<string, mixed>|null
     */
    public function getDocumentUploadSettingsForJs(): ?array
    {
        if (! $this->hasFileAttachments()) {
            return null;
        }

        if ($this->getMediaSource() !== null) {
            return null;
        }

        $accepted = $this->getFileAttachmentsAcceptedFileTypes();

        if (is_array($accepted) && ! array_filter($accepted, static fn (mixed $type): bool => ! str_starts_with((string) $type, 'image/'))) {
            return null;
        }

        return [
            'key' => $this->getKey(),
            'statePath' => $this->getStatePath(),
            'acceptedTypes' => is_array($accepted) ? array_values($accepted) : null,
            'maxSize' => $this->getFileAttachmentsMaxSize(),
            'uploadingMessage' => $this->getUploadingFileMessage(),
        ];
    }

    /**
     * The node an upload the drop handler just finished becomes, or null where the upload is
     * not one this field accepts.
     *
     * Measured by `getUploadedFileAttachment()`, which is Filament's gate: the accepted types
     * and the size limit the field was given. The browser's own uploads are not answered
     * here - they carry the browser's prefix and are measured against the browser's list.
     *
     * @return array{type: string, attrs: array<string, mixed>}|null
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getUploadedFileAttachmentNodeForJs(string $attachment): ?array
    {
        if (FileAttachments::pending($attachment)) {
            return null;
        }

        $file = $this->getUploadedFileAttachment($attachment);

        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        return $this->uploadedFileAttachmentNode($attachment, $file);
    }

    /**
     * The node an upload becomes, whichever way it came in.
     *
     * @return array{type: string, attrs: array<string, mixed>}|null
     */
    public function uploadedFileAttachmentNode(string $id, TemporaryUploadedFile $file, ?string $alt = null): ?array
    {
        $kind = MediaKinds::of($file->getMimeType());

        if ($kind === MediaKinds::IMAGE) {
            $src = static::temporaryUrlOf($file);

            return filled($src) ? ['type' => 'image', 'attrs' => ['alt' => $alt, 'id' => $id, 'src' => $src]] : null;
        }

        if (in_array($kind, [MediaKinds::VIDEO, MediaKinds::AUDIO], strict: true) && $this->hasMedia()) {
            $src = static::temporaryUrlOf($file);

            return filled($src) ? ['type' => 'media', 'attrs' => [
                'kind' => $kind,
                'src' => $src,
                'id' => $id,
                'title' => $alt,
                'preload' => 'metadata',
                'loop' => false,
            ]] : null;
        }

        // Livewire hands out a preview address only for the endings it lists, and a pdf is
        // not among them - which is why Filament's handler inserted nothing at all for one
        // on a local disk. Widened for this one file, the way the browser does it. Without
        // an address the card is still the card: the save writes the stored file's address
        // over this one either way.
        return ['type' => 'file', 'attrs' => [
            'src' => static::temporaryUrlOf($file, document: true),
            'id' => $id,
            'name' => (string) $file->getClientOriginalName(),
            'size' => ByteSize::format($file->getSize()),
        ]];
    }
}
