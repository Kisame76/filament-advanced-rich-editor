<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Events\RichContentSaved;

/**
 * Announces that this record's rich content was written.
 *
 * On the model rather than on the field, and that is the whole design rather than a
 * preference. Filament holds exactly one `saveRelationshipsUsing()` closure, so a field
 * registering a second one silently replaces the file attachment save; and on an edit page
 * that hook runs inside `getState()`, before `handleRecordUpdate()` writes anything - which
 * is why Filament's own use of it bails out unless the record `wasRecentlyCreated`. A field
 * cannot answer "was this saved". A model can, and it answers for an import, a queued job
 * and tinker as well.
 *
 * A trait rather than a listener the package installs for every model in the application:
 * this is behaviour a project opts into, and a package has no business hooking every save
 * an application makes on the chance that one of them is a document.
 *
 * Which columns are documents is not asked twice. `HasRichContent::getRichContentAttributes()`
 * is what the model already says about itself, and a second list beside it would be a second
 * place to be wrong.
 */
// The ignore is Filament's own on `InteractsWithRichContent`, for the same reason: a trait
// meant for a project's models is used nowhere inside the package, and the fixture that does
// use it lives in the tests, which this configuration does not analyse.
trait FiresRichContentEvents /** @phpstan-ignore trait.unused */
{
    public static function bootFiresRichContentEvents(): void
    {
        // Two hooks rather than one on `saved`, and the difference is not cosmetic:
        // `wasRecentlyCreated` is set on insert and never cleared again, so on a record
        // created earlier in the same request every later save still answers true to it. A
        // save that only touched the title would announce a document nobody wrote.
        //
        // `created` and `updated` each know which kind of save they are, and both run before
        // `finishSave()` calls `syncOriginal()`, so the original is still the value being
        // replaced. `updated` fires only where something was actually dirty, and after
        // `syncChanges()` - which is what makes `wasChanged()` answerable there and not on an
        // insert, where nothing was synced.
        static::created(static function (Model $record): void {
            foreach ($record->richContentEventAttributes() as $attribute) {
                $content = $record->getAttribute($attribute);

                // A record created with no document in it wrote no document. Clearing one
                // later is news; never having had one is not.
                //
                // `isBlank()` and pointedly not `blank()`, which is the trap this package
                // documents in two other places and would have walked into here: an editor
                // nobody typed into stores `<p></p>`, or a `doc` holding one empty
                // paragraph, and both are present, non-blank and empty. Asked with `blank()`
                // every create through a form announces a document, and every listener the
                // event exists for - optimising pictures, checking links, clearing a render
                // cache - runs over nothing.
                if (DocumentContent::isBlank($content)) {
                    continue;
                }

                event(new RichContentSaved(
                    record: $record,
                    attribute: $attribute,
                    content: $content,
                    previousContent: null,
                    wasRecentlyCreated: true,
                ));
            }
        });

        static::updated(static function (Model $record): void {
            foreach ($record->richContentEventAttributes() as $attribute) {
                if (! $record->wasChanged($attribute)) {
                    continue;
                }

                event(new RichContentSaved(
                    record: $record,
                    attribute: $attribute,
                    content: $record->getAttribute($attribute),
                    previousContent: $record->getOriginal($attribute),
                    wasRecentlyCreated: false,
                ));
            }
        });
    }

    /**
     * The columns this record announces. Whatever it registered as rich content, unless it
     * says otherwise - a model storing a document in a column it never declared can name it
     * by overriding this.
     *
     * @return array<int, string>
     */
    public function richContentEventAttributes(): array
    {
        return method_exists($this, 'getRichContentAttributes')
            ? array_keys($this->getRichContentAttributes())
            : [];
    }
}
