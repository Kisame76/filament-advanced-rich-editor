<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Shortcuts;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ShortcutTable;

/**
 * What this editor can do, in a dialog.
 *
 * Two things at most: the keys it answers to, and whatever the project wants to tell the
 * people writing in it. The second one only appears when there is something to say - a tab
 * bar over a single tab is furniture, not navigation.
 */
class HelpAction
{
    public static function make(): Action
    {
        return Action::make('help')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.help.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.help.heading'))
            ->modalWidth(Width::TwoExtraLarge)
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
            ->schema(function (AdvancedRichEditor $component): array {
                $table = ShortcutTable::make()->rows(Shortcuts::for($component));

                $more = $component->getHelpMore();

                if ($more === null) {
                    return [Html::make($table)];
                }

                return [
                    Tabs::make()->tabs([
                        Tab::make(__('filament-advanced-rich-editor::advanced-rich-editor.help.shortcuts'))
                            ->schema([Html::make($table)]),
                        Tab::make($component->getHelpMoreLabel())
                            ->schema([Html::make($more)]),
                    ]),
                ];
            });
    }
}
