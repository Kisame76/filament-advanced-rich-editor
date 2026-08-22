<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The URL a media item is shown and stored with.
 *
 * Lifted out of the file attachment provider so that the browser and the provider cannot
 * disagree: the picture in the grid and the `src` the content is saved with are produced
 * by the same three lines. A thumbnail that resolves through a different path than the
 * saved image is a grid that lies about what picking an item will do.
 */
class MediaUrl
{
    /**
     * @param  string|null  $conversion  the conversion to embed, or null for the original file
     * @param  string|null  $visibility  'private' hands out a short lived signed URL instead
     */
    public static function for(Media $media, ?string $conversion = null, ?string $visibility = null): ?string
    {
        $conversion ??= '';

        // A private disk has no permanent public URL, so mirror Filament's own behaviour for
        // private attachments and hand out a short-lived signed URL instead.
        if ($visibility === 'private') {
            try {
                return $media->getTemporaryUrl(
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                    $conversion,
                );
            } catch (Throwable $exception) {
                // This driver does not support creating temporary URLs.
            }
        }

        try {
            return $media->getUrl($conversion);
        } catch (Throwable $exception) {
            // The media, its file or the requested conversion is gone; a missing image is far
            // better than a 500 while rendering somebody's content.
            return null;
        }
    }

    /**
     * The still-being-generated conversion problem: asking for a conversion that has not been
     * made yet returns a URL to a file that is not there. Falling back to the original is the
     * only answer that shows a picture.
     */
    public static function forWithFallback(Media $media, ?string $conversion = null, ?string $visibility = null): ?string
    {
        if (filled($conversion) && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = null;
        }

        return static::for($media, $conversion, $visibility);
    }
}
