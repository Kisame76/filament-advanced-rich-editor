<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;

/**
 * A record that declares its rich content attribute, the way Filament asks it to.
 *
 * The entry and the column read that declaration rather than being told the same things a
 * second time, so the suite needs a model that makes one.
 */
class RichPost extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;

    protected function setUpRichContent(): void
    {
        // A mention provider, because what it answers is visible in the rendered markup:
        // the label in the document is a copy from the day it was typed, and the provider
        // is the only thing that knows the name now.
        $this->registerRichContent('content')
            ->mentions([
                MentionProvider::make('@')->items(['2' => 'Ada Lovelace']),
            ]);
    }
}
