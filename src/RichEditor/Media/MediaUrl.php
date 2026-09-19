<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Media;

use Illuminate\Support\Str;
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
    public static function for(Media $media, ?string $conversion = null, ?string $visibility = null, ?string $kind = null): ?string
    {
        // A conversion is a picture made from the file. For a picture that is exactly what a
        // document should point at; for anything else it is a JPEG of page one where the
        // reader asked for the report, or a still where they asked for the film.
        //
        // The family the row is listed under where the caller knows it, and the type only
        // where nobody does. `finfo` files a CAD drawing under `image/vnd.dwg` and a
        // Photoshop file under `image/vnd.adobe.photoshop`, so a type test alone kept a
        // conversion for two documents that never had one - and took it away from a picture
        // whose type was never recorded, which is the full-size file on every page it is on.
        $isPicture = ($kind !== null)
            ? ($kind === MediaKinds::IMAGE)
            : str_starts_with(Str::lower((string) $media->getAttributeValue('mime_type')), 'image/');

        if (! $isPicture) {
            $conversion = null;
        }

        return static::address($media, $conversion, $visibility);
    }

    /**
     * The picture a conversion made from a file, whatever the file is.
     *
     * The one place that wants a picture of a document rather than the document: a tile in
     * the browser, where the first page of a pdf says more than the letters `PDF` do.
     */
    public static function picture(Media $media, string $conversion, ?string $visibility = null): ?string
    {
        return static::address($media, $conversion, $visibility);
    }

    protected static function address(Media $media, ?string $conversion, ?string $visibility): ?string
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
    public static function forWithFallback(Media $media, ?string $conversion = null, ?string $visibility = null, ?string $kind = null): ?string
    {
        if (filled($conversion) && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = null;
        }

        return static::for($media, $conversion, $visibility, $kind);
    }
}
