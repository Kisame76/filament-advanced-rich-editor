<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A model that exists only to own pictures.
 *
 * The point of it is what it is *not*: nothing an editor deletes. A media row belongs to a
 * model and its file lives at a path built from the row's id, so a picture owned by an article
 * disappears with that article - which is fine until a second article reuses it.
 */
class MediaLibraryItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
