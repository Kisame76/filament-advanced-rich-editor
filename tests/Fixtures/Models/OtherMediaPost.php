<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A second model that keeps media, for the one thing a single model cannot show: that the
 * browser's pool is the collection rather than the model.
 *
 * Deliberately without conversions - `ThumbnailPost` is where those are exercised, and dragging
 * them in here would test the conversion machinery instead of the boundary.
 */
class OtherMediaPost extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
