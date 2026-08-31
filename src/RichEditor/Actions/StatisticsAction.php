<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Html;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Numbers;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StatisticsTable;

/**
 * How long the document is, in a dialog.
 *
 * The numbers are the field's own, from `measureDocument()` - the same measurement the
 * counter under the editor shows two of, and the same one `maxLength()` refuses a save
 * over. Counting again here would give a friendlier number and a reader with two answers.
 *
 * Read at the moment the dialog opens rather than kept in step with the typing. Mounting
 * the action is a Livewire request, and the editor's state travels with it even on a field
 * that is not live, so what is counted is what is on screen.
 */
class StatisticsAction
{
    public static function make(): Action
    {
        return Action::make('statistics')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.statistics.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.statistics.heading'))
            ->modalWidth(Width::Medium)
            // Nothing to submit and nothing to cancel: a footer holding one button called
            // Close is furniture beside the cross that already closes the dialog. The ways
            // out that matter stay, and are stated rather than inherited - a project may
            // have changed Filament's own defaults for modals that do have something to
            // answer, and this one still has to be closable without a mouse.
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalCloseButton()
            ->closeModalByClickingAway()
            // Nothing to type into either. Letting the focus trap grab something inside the
            // dialog scrolls the page to wherever the modal markup sits, which is the very
            // bottom.
            ->modalAutofocus(false)
            ->schema(fn (AdvancedRichEditor $component): array => [
                Html::make(StatisticsTable::make()->rows(static::rowsFor($component))),
            ]);
    }

    /**
     * The five rows, formatted the way the line under the field formats its two.
     *
     * Through `number()` below, which reaches `Numbers` - where this package decided what a
     * number looks like: the app's locale, the same one the counter's browser half is
     * handed. A dialog formatting its own way would read 1,234 one screen away from a line
     * reading 1.234.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected static function rowsFor(AdvancedRichEditor $component): array
    {
        $counted = $component->measureDocument($component->getState());

        $line = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.statistics.{$key}");

        return [
            ['label' => $line('words'), 'value' => static::number($counted['words'])],
            ['label' => $line('characters'), 'value' => static::number($counted['characters'])],
            [
                'label' => $line('characters_without_spaces'),
                'value' => static::number($counted['charactersWithoutSpaces']),
            ],
            ['label' => $line('paragraphs'), 'value' => static::number($counted['paragraphs'])],
            ['label' => $line('reading_time'), 'value' => static::readingTime($counted['readingMinutes'])],
        ];
    }

    /**
     * A number the way this dialog writes one, which is the way the line under the field
     * writes one. The seam a project overrides to decide otherwise; `Numbers` is the answer
     * until it does.
     */
    protected static function number(int $value): string
    {
        return Numbers::format($value);
    }

    /**
     * An empty document takes no time, and anything shorter than a minute is not worth a
     * number - the reader would only wonder what "1" was rounded from.
     */
    protected static function readingTime(int $minutes): string
    {
        return match (true) {
            $minutes === 0 => __('filament-advanced-rich-editor::advanced-rich-editor.statistics.reading_time_none'),
            $minutes === 1 => __('filament-advanced-rich-editor::advanced-rich-editor.statistics.reading_time_under'),
            default => __('filament-advanced-rich-editor::advanced-rich-editor.statistics.reading_time_minutes', ['minutes' => $minutes]),
        };
    }
}
