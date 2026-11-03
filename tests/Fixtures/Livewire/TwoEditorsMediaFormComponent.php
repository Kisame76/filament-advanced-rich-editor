<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Schemas\Schema;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\MediaPost;

/**
 * An edit page with two editors on one record, both storing their pictures in the record's
 * media collection - and saving the way `EditRecord` does: the form's state first, which is
 * where each editor sweeps its removed pictures, and the record after it.
 */
class TwoEditorsMediaFormComponent extends EditorFormComponent
{
    public MediaPost $record;

    /**
     * `record` for a browser that lists this record's collection, `off` for no browser at all.
     */
    public string $browser = 'record';

    public function mount(MediaPost $record, string $browser = 'record'): void
    {
        $this->record = $record;
        $this->browser = $browser;

        $this->form->fill($record->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->editor('content'),
                $this->editor('summary'),
            ])
            ->record($this->record)
            ->operation('edit')
            ->statePath('data');
    }

    public function save(): void
    {
        $this->record->update($this->form->getState());
    }

    protected function editor(string $name): AdvancedRichEditor
    {
        $editor = AdvancedRichEditor::make($name)
            ->nestingCheck(false)
            ->spatieMediaLibrary('rich-editor');

        return ($this->browser === 'off')
            ? $editor->mediaLibrary(false)
            : $editor->mediaLibraryScope('record');
    }
}
