<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts;

use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast as BaseRichEditorStateCast;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Filament's rich editor cast, with the one shape it does not survive taken off the table.
 *
 * The cast falls back to an empty document when the state is null, and hands everything else
 * to the parser. An empty string is everything else: `text NOT NULL DEFAULT ''` is an
 * ordinary column, and a record nobody has edited yet holds exactly that. TipTap's DOM
 * parser then reaches for a `<body>` that was never built and dies on the null it finds -
 *
 *     Tiptap\Core\DOMParser::getDocumentBody(): Return value must be of type DOMElement,
 *     null returned
 *
 * - a TypeError out of a form that was only being rendered, naming a class the application
 * has never heard of. Nothing above it can recover, because hydration is what failed.
 *
 * Blank rather than empty, so a column holding nothing but spaces goes the same way: it
 * parses to the same empty document, and saying so here keeps the parser out of it.
 */
class RichEditorStateCast extends BaseRichEditorStateCast
{
    /**
     * @return array<string, mixed>
     */
    public function set(mixed $state): array
    {
        if ($state instanceof Htmlable) {
            $state = $state->toHtml();
        }

        if (is_string($state) && blank($state)) {
            $state = null;
        }

        return parent::set($state);
    }
}
