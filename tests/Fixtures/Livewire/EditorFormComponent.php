<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * A page with one editor on it, rendered the way a panel renders a form.
 *
 * The field's view reads the Livewire component off `$this`, so it can only be drawn from
 * inside a component's own render. This is the smallest component that does that.
 */
class EditorFormComponent extends TestSchemaComponent
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The suite runs on Livewire's shipped nesting limit, which the check would
                // refuse.
                AdvancedRichEditor::make('content')->nestingCheck(false),
            ])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
