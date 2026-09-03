<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts;

use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast as BaseRichEditorStateCast;
use Illuminate\Contracts\Support\Htmlable;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Media;

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

        return $this->resolveMediaSources(parent::set($state));
    }

    /**
     * The address of every player pointing at an attachment, asked for again.
     *
     * The parent does this for `image` and only for `image`, which is the same blind spot
     * the rest of the attachment lifecycle had - see `Media/FileAttachments.php`. It matters
     * most on a private disk, where the stored address is a signed URL that expires: a video
     * saved last week would come back into the editor pointing at a link that has run out,
     * and the player would sit there refusing to load a file that is perfectly fine.
     *
     * Written as a second pass rather than by copying the parent's walk. It costs one more
     * parse of a document that has just been parsed, which is a price worth paying to not
     * carry a copy of Filament's method that has to be kept in step with it.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function resolveMediaSources(array $document): array
    {
        $editor = $this->richEditor;

        if (! ($editor instanceof AdvancedRichEditor)) {
            return $document;
        }

        // Nothing to resolve, and the overwhelming majority of documents are this. The parse
        // below is a second full pass over something that has just been parsed, so it is
        // worth one cheap look at the serialised form to not pay for it on every save of
        // every field that has never held a player.
        if (! str_contains(json_encode($document) ?: '', Media::$name)) {
            return $document;
        }

        $tiptap = $editor->getTipTapEditor()
            ->setContent($document)
            ->descendants(function (object &$node) use ($editor): void {
                // The picture is the parent's job and is already done; this is the rest.
                if (($node->type ?? null) !== Media::$name) {
                    return;
                }

                if (blank($node->attrs->id ?? null)) {
                    return;
                }

                $url = $editor->getFileAttachmentUrl($node->attrs->id);

                if (filled($url)) {
                    $node->attrs->src = $url;
                }
            });

        return $tiptap->getDocument();
    }
}
