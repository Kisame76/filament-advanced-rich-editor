<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Infolists\Components;

use Closure;
use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\RendersRichContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentTasks;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TickableTasks;

/**
 * A stored document on a view page, rendered rather than escaped.
 *
 * The alternative is `TextEntry::make('body')->html()`, and it is wrong in the way that
 * costs an afternoon: it prints the markup as it lies in the column, which means a picture
 * whose `src` is a file attachment id renders as a broken image, a mention renders as an
 * empty span, and a custom block renders as nothing at all. Those are resolved by the
 * renderer and by nothing else. This entry is that renderer, on an entry.
 *
 * `fi-prose` is Filament's own class for rich content, the same one the editor draws the
 * document in - so what the view page shows is what the form showed.
 */
class AdvancedRichEntry extends Entry implements HasEmbeddedView
{
    use RendersRichContent;

    protected bool|Closure $isTickable = false;

    public function toEmbeddedHtml(): string
    {
        $state = $this->getState();

        $html = blank($state) ? '' : $this->getRichContentRenderer($state)->toHtml();

        // After the sanitiser, deliberately: what this writes is a `wire:click`, and the
        // sanitiser's allow list would take it straight off again.
        if (filled($html) && $this->canTickTasks()) {
            // The count is handed over so the pass can refuse to draw anything where this
            // markup and the stored document disagree about how many task items there are.
            $html = (new TickableTasks)->apply(
                $html,
                $this->getKey(),
                expected: DocumentTasks::count($this->resolveRichContent($state)),
            );
        }

        $attributes = $this->getExtraAttributeBag()
            ->class(['fi-arte-entry', 'fi-prose']);

        if (blank($html)) {
            $placeholder = $this->getPlaceholder();

            ob_start(); ?>

            <div <?= $attributes->toHtml() ?>>
                <?php if (filled($placeholder)) { ?>
                    <p class="fi-in-placeholder"><?= e($placeholder) ?></p>
                <?php } ?>
            </div>

            <?php return $this->wrapEmbeddedHtml(ob_get_clean());
        }

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php // Sanitised by `toHtml()` before it got here, which is the whole reason
                  // that method is the one being called.?>
            <?= $html ?>
        </div>

        <?php return $this->wrapEmbeddedHtml(ob_get_clean());
    }

    /**
     * Lets somebody tick a checkbox here, on a page that is otherwise only for reading.
     *
     * Off unless asked for, and asked for with a question rather than a switch: a view page
     * is shown to people who may only look, and this package does not know your policies.
     * The callback is handed the record.
     *
     *     AdvancedRichEntry::make('content')
     *         ->tickableTasks(fn (Article $record): bool => auth()->user()->can('update', $record))
     */
    public function tickableTasks(bool|Closure $condition = true): static
    {
        $this->isTickable = $condition;

        return $this;
    }

    public function canTickTasks(): bool
    {
        $record = $this->getRecord();

        // Nothing to write to, so nothing to offer. A repeatable entry over an array has no
        // record of its own, and neither has an entry on a page that never loaded one.
        if (! $record instanceof Model) {
            return false;
        }

        return (bool) $this->evaluate($this->isTickable);
    }

    /**
     * Turns one tick over and writes it back.
     *
     * `ExposedLivewireMethod` is what makes this reachable from the browser at all -
     * Filament dispatches `callSchemaComponentMethod` only to methods carrying it - and the
     * permission is asked again here rather than trusted from the markup: the button was
     * drawn for somebody who may write, and a request is not a button.
     *
     * Only the one attribute is assigned, so the update Eloquent writes is the one column.
     * The document is edited where it lies rather than rebuilt through the editor: a round
     * trip through the schema would rewrite every node on the way past, and a click on a
     * checkbox has no business touching the paragraph beside it.
     */
    #[ExposedLivewireMethod]
    public function toggleTask(int $index): void
    {
        if (! $this->canTickTasks()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return;
        }

        $attribute = $this->getName();
        $content = $this->resolveRichContent($record->getAttribute($attribute));

        if ($content === null) {
            return;
        }

        $toggled = DocumentTasks::toggle($content, $index);

        // Null is an index that names no task item, which is a document that changed
        // between being drawn and being clicked. Writing anything then would be writing a
        // guess.
        if ($toggled === null) {
            return;
        }

        $record->setAttribute($attribute, $toggled);
        $record->save();
    }
}
