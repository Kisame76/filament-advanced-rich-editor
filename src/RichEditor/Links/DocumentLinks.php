<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Links;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentContent;

/**
 * Every address a stored document links to.
 *
 * Three kinds of link, in either shape a column holds: a link on text, a link on a picture, and a
 * link card. A download card is left out although it is an `<a>` - it points at a file of the library,
 * and a file that went missing is a question about the library rather than about where the
 * text sends its readers. So is an embed, which a reader never follows anywhere.
 */
final class DocumentLinks
{
    /**
     * In document order, each address once, as it was written.
     *
     * @param  string|array<string, mixed>|null  $document
     * @return array<int, string>
     */
    public static function of(string|array|null $document): array
    {
        $links = is_array($document) ? self::ofTree($document) : self::ofMarkup($document);

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $href): string => is_string($href) ? trim($href) : '', $links),
            static fn (string $href): bool => $href !== '',
        )));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, mixed>
     */
    private static function ofTree(array $document): array
    {
        $links = [];

        foreach (DocumentContent::nodes($document) as $node) {
            if (in_array($node['type'] ?? null, ['image', 'linkPreview'], strict: true)) {
                $links[] = $node['attrs']['href'] ?? null;
            }

            foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
                if (is_array($mark) && (($mark['type'] ?? null) === 'link')) {
                    $links[] = $mark['attrs']['href'] ?? null;
                }
            }
        }

        return $links;
    }

    /**
     * @return array<int, mixed>
     */
    private static function ofMarkup(?string $markup): array
    {
        // Most documents link nowhere, and a page of them is not worth a parser each.
        if (blank($markup) || (! str_contains($markup, 'href'))) {
            return [];
        }

        $document = new DOMDocument;

        // The processing instruction keeps DOMDocument from reading an encoded fragment as
        // Latin-1. See `DocumentTasks`.
        if (! @$document->loadHTML('<?xml encoding="UTF-8">'.$markup, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [];
        }

        $links = [];

        foreach ((new DOMXPath($document))->query('//a[@href] | //img[@data-href]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if ($element->tagName === 'img') {
                $links[] = $element->getAttribute('data-href');

                continue;
            }

            // A card says what it is by the type its node writes, or by the `download` a
            // hand-written one is recognised by - `DocumentAttachments` reads it the same way.
            if (($element->getAttribute('data-type') !== 'file') && (! $element->hasAttribute('download'))) {
                $links[] = $element->getAttribute('href');
            }
        }

        return $links;
    }
}
