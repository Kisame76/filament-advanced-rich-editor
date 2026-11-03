<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\SpatieMediaLibraryPlugin;

/**
 * A record whose rich content says its files are in a media collection - the way a project
 * tells every page rendering it, and `arte:diagnose`, where to look.
 */
class RichMediaPost extends MediaPost implements HasRichContent
{
    use InteractsWithRichContent;

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('content')
            ->plugins([SpatieMediaLibraryPlugin::make('rich-editor')]);
    }
}
