<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Schemas\Components\Html;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\PreviewFrame;

/**
 * What the document looks like on the site, in a dialog.
 *
 * The markup is the front end's own: `getRichContentRenderer()` is the same assembly
 * `AdvancedRichEntry` and `AdvancedRichColumn` render through, and `toHtml()` is the same
 * sanitised exit. Rendering a second way here would give an author a preview that agrees with
 * nothing, and a preview that disagrees with the page is worse than no preview, because it is
 * believed.
 *
 * The document comes out of the browser rather than off the server's copy of the state, which
 * is the source view's channel and its reasoning: the last keystrokes may not have been synced
 * yet, and an author who just wrote a sentence is previewing to see that sentence. It goes back
 * through the field's own schema on the way in, so what is drawn is what would be stored -
 * anything the schema cannot hold is already gone at that point, visibly, rather than silently
 * on the next save.
 */
class PreviewAction
{
    public static function make(): Action
    {
        return Action::make('preview')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.preview.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.preview.heading'))
            ->modalDescription(__('filament-advanced-rich-editor::advanced-rich-editor.preview.description'))
            // Wide, because the thing being judged is a page and a page judged in a column is
            // not the same page. The same width the source view opens at.
            ->modalWidth(Width::FiveExtraLarge)
            // Nothing to submit and nothing to cancel, the same as the statistics dialog: a
            // footer holding one button called Close is furniture beside the cross that closes
            // it already. The ways out are stated rather than inherited, because a project may
            // have changed Filament's defaults for modals that do have something to answer.
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalCloseButton()
            ->closeModalByClickingAway()
            // Nothing to type into. Letting the focus trap grab something scrolls the page to
            // wherever the modal markup sits, which is the very bottom.
            ->modalAutofocus(false)
            ->schema(fn (array $arguments, AdvancedRichEditor $component): array => [
                Html::make(static::frameFor($component, $arguments['html'] ?? null)),
            ]);
    }

    /**
     * The frame, built from what the field says about its own front end.
     *
     * Kept apart from the action so the two decisions that matter - which markup is drawn and
     * which stylesheets reach it - can be read and tested without mounting a modal.
     */
    public static function frameFor(AdvancedRichEditor $component, ?string $html): PreviewFrame
    {
        // The browser's copy where there is one, and the server's where there is not.
        //
        // The fallback is the whole of what stops a bad answer from looking like a true one.
        // The argument is an expression evaluated in the browser, so it can simply fail to
        // arrive - an editor not yet ready, a stale bundle after an upgrade where the assets
        // were not republished, a dialog opened from somewhere that is not that button. With
        // no fallback the document normalises to an empty string and the frame opens on a
        // blank page under the word "preview", which reads as "this document is empty"
        // rather than as "the document did not reach me". A slightly stale preview is a
        // worse answer than a fresh one and a far better one than a wrong one.
        $document = filled($html)
            ? $component->normaliseSourceHtml($html)
            : $component->getState();

        return PreviewFrame::make()
            ->document($component->getRichContentRenderer($document)->toHtml())
            ->stylesheets($component->getPreviewStylesheets())
            ->wrapperClass($component->getPreviewWrapperClass())
            ->title(__('filament-advanced-rich-editor::advanced-rich-editor.preview.frame'))
            // The application's locale, not the browser's. This package takes the browser's
            // opinion nowhere else, and a frame is not the place to start.
            ->language(app()->getLocale());
    }
}
