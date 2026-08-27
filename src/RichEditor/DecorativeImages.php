<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * The empty description that belongs beside `role="presentation"`.
 *
 * Written here rather than by the extension that carries the mark, because an empty
 * attribute cannot be written from a schema: `tiptap-php` drops any attribute whose value is
 * blank on the way out, which is right for every other attribute and exactly wrong for this
 * one. An `alt` of nothing is not the absence of an `alt` - the first says "there is nothing
 * to tell you", the second says nothing at all, and HTML requires the attribute either way.
 *
 * The same pass is where a description left behind on a marked picture is dropped. A screen
 * reader that meets a role and a description together reads the description out, which is
 * the failure this whole feature exists to prevent - and the two can only disagree if the
 * mark was set in one place and the words in another.
 *
 * Unconditional, like the caption pass beside it: a picture that says it carries nothing is
 * one that should keep saying so on the page, and nothing has to ask for that.
 */
class DecorativeImages
{
    public function apply(string $html): string
    {
        // Cheap way out for the documents that have no such picture in them, which is most
        // of them. The same guard the caption pass opens with.
        if (! str_contains($html, 'role=')) {
            return $html;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-decorative-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $images = (new DOMXPath($document))->query('//img[@role]');

        if ($images === false) {
            return $html;
        }

        $touched = false;

        foreach (iterator_to_array($images) as $image) {
            if (! ($image instanceof DOMElement)) {
                continue;
            }

            if (! TipTapExtensions\ImageDecorative::isPresentational($image->getAttribute('role'))) {
                continue;
            }

            // One spelling on the way out, whichever of the two synonyms came in, so stored
            // markup does not depend on which editor a paragraph was written in.
            $image->setAttribute('role', 'presentation');
            $image->setAttribute('alt', '');

            $touched = true;
        }

        if (! $touched) {
            return $html;
        }

        $root = $document->getElementById('arte-decorative-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }
}
