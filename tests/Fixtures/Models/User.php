<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Somebody a queued export can tell that it is done.
 */
class User extends Model
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
