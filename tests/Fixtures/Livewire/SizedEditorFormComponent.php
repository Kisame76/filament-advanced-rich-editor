<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * One editor with both heights set, for what the field's view writes for each of them.
 *
 * `minHeight()` is Filament's from 5.9 on, so only a test that has checked for it renders this.
 */
class SizedEditorFormComponent extends TestSchemaComponent
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AdvancedRichEditor::make('content')
                    ->nestingCheck(false)
                    ->minHeight('12rem')
                    ->maxHeight('32rem'),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
