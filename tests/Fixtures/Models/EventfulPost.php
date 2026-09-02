<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Models\Concerns\FiresRichContentEvents;

/**
 * A record that asks to be told when its document was written.
 *
 * Beside `RichPost` rather than instead of it: the two differ by the one trait, which is what
 * lets a test say that a model without it stays silent.
 */
class EventfulPost extends Model implements HasRichContent
{
    use FiresRichContentEvents;
    use InteractsWithRichContent;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('content');
    }
}
