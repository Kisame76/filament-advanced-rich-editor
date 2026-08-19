<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A deliberately plain record.
 *
 * It implements neither `HasMedia` nor `HasRichContent`, which is what keeps the suite
 * runnable without `spatie/laravel-medialibrary`: the field level media library wiring is
 * asserted through the provider it hands out, never by touching a media collection.
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
