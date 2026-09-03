<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

/**
 * Which nodes carry a file this field is responsible for.
 *
 * Filament's whole attachment lifecycle asks one question in six places, and asks it the
 * same way every time: `$node->type !== 'image'`. That was the truth while a picture was
 * the only thing an editor could upload, and it stopped being the truth the moment a video
 * could be.
 *
 * The consequence is not cosmetic. An id nothing recognises is an id that never reaches
 * `resolveFileAttachmentIds()`, and what that method returns is the list
 * `cleanUpFileAttachments()` spares - so a video uploaded through the browser is a video
 * deleted by the next save of the same record. The file goes; the document keeps pointing
 * at it; nothing raises.
 *
 * So the question is asked here instead, once, and a new kind of file joins the lifecycle
 * by being named in this list rather than by six edits in five files.
 */
class FileAttachments
{
    /**
     * The node types that may carry a `data-id` pointing at an attachment.
     *
     * `image` is Filament's own and is here because everything below has to agree with it,
     * not because this package added it.
     *
     * @var array<int, string>
     */
    public const TYPES = ['image', 'media'];

    /**
     * What marks an attachment id as one the media browser is holding rather than one the
     * library issued.
     *
     * It decides which accepted-type list an upload is measured against: a file that came
     * through the browser is measured against the browser's, which is wider than the one
     * Filament's own dialog and its compiled drop handler use.
     */
    public const PENDING_PREFIX = 'arte-';

    public static function pending(mixed $id): bool
    {
        return is_string($id) && str_starts_with($id, static::PENDING_PREFIX);
    }

    public static function carriedBy(mixed $type): bool
    {
        return is_string($type) && in_array($type, static::TYPES, strict: true);
    }
}
