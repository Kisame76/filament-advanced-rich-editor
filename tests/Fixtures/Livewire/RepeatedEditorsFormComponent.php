<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor\MeasuringEditor;

/**
 * A repeater of three editors, each holding a document - the page that showed what a field
 * costs once there are several of it.
 */
class RepeatedEditorsFormComponent extends EditorFormComponent
{
    public function mount(): void
    {
        $this->form->fill([
            'rows' => array_map(
                static fn (int $row): array => ['body' => "<p>Abschnitt {$row} mit ein paar Wörtern.</p>"],
                range(1, 3),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('rows')->schema([
                    MeasuringEditor::make('body')->nestingCheck(false),
                ]),
            ])
            ->statePath('data');
    }
}
