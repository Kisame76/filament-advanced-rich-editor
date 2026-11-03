<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A record whose collection asks Spatie for responsive images, the way a project turns them on.
 *
 * The wide conversion makes its own; the small one makes none, so it can prove that a picture
 * shown as the conversion is not handed the original's sizes.
 */
class ResponsivePost extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('rich-editor')->withResponsiveImages();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('arte-wide')->width(800)->withResponsiveImages();
        $this->addMediaConversion('arte-thumb')->fit(Fit::Contain, 64, 64);
    }
}
