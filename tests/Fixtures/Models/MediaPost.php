<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A record that can hold media, for the one thing that cannot be asserted without it: which
 * attachments a save is allowed to delete.
 *
 * Kept apart from the plain `Post` on purpose - that model exists to prove the package works
 * with `spatie/laravel-medialibrary` absent, and implementing `HasMedia` there would make it
 * prove the opposite.
 */
class MediaPost extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
