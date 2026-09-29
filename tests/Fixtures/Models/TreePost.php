<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record that keeps its document as a tree in a JSON column, cast to the array the editor
 * hands out - the way a field with `->json()` is stored.
 */
class TreePost extends Model
{
    protected $table = 'tree_posts';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
