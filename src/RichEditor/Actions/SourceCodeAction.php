<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SourceCodeFormatter;

/**
 * The source view: the document as HTML, editable.
 *
 * Built on the same mechanism Filament's own link tool uses - the button hands the editor's
 * current HTML to a modal, the modal hands an answer back through `runCommands()` - so there
 * is no JavaScript of this package's own in the way.
 *
 * Both directions go through the field's own TipTap editor rather than being passed along
 * untouched. Opening that way means the markup shown is the one that gets stored, not the
 * browser's serialisation of it; saving that way means what comes back has already been read
 * by the schema that has to hold it, which is the same thing that happens to pasted markup.
 * Anything the editor cannot represent is gone at that point - honestly, and before the
 * record is written, rather than silently on the next save.
 */
class SourceCodeAction
{
    public static function make(): Action
    {
        return Action::make('sourceCode')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.heading'))
            ->modalDescription(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.description'))
            ->modalSubmitActionLabel(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.apply'))
            ->modalWidth(Width::FiveExtraLarge)
            // Laid out for reading on the way in, compacted again on the way out: the
            // parser drops whitespace between blocks, so the indentation costs nothing.
            ->fillForm(fn (array $arguments, RichEditor $component): array => [
                'html' => SourceCodeFormatter::format(static::normalise($component, $arguments['html'] ?? null)),
            ])
            ->schema([
                CodeEditor::make('html')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.source_code.label'))
                    ->hiddenLabel()
                    ->language(Language::Html)
                    // A document is one long line of HTML more often than not, and reading
                    // it sideways is not reading it.
                    ->wrap(),
            ])
            ->action(function (array $data, RichEditor $component): void {
                $component->runCommands([
                    EditorCommand::make('setContent', arguments: [
                        static::normalise($component, $data['html'] ?? null),
                    ]),
                ]);
            });
    }

    /**
     * The markup as the field's own schema writes it - every plugin the field carries
     * included, so a rotation, a background colour or a direction survives the trip.
     */
    public static function normalise(RichEditor $component, ?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return $component->getTipTapEditor()->setContent($html)->getHtml();
    }
}
