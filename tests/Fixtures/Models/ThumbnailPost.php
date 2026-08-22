<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A record whose media has the small conversion the browser's grid draws from.
 *
 * Kept apart from `MediaPost`, which deliberately declares none: the fallback - a library
 * whose pictures have no thumbnail yet - is the common case and needs a model that proves it.
 */
class ThumbnailPost extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 64, 64);
    }
}
