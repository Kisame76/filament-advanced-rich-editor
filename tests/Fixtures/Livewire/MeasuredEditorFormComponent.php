<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor\MeasuringEditor;

/**
 * The page with one editor on it, holding a document and drawing the counter that measures it.
 */
class MeasuredEditorFormComponent extends EditorFormComponent
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = ['content' => '<p>Ein Satz mit sechs Wörtern darin.</p>'];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MeasuringEditor::make('content')->nestingCheck(false),
            ])
            ->statePath('data');
    }
}
