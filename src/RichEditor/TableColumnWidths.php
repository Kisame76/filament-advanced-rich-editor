<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Turns the width somebody dragged a column to into a width the page can use.
 *
 * Filament configures TipTap's table with `resizable: true`, so dragging works and the
 * width is kept in the document as `colwidth`. It never reaches the reader: `tiptap-php`
 * writes it as `data-colwidth` on the cell, which is neither on the sanitiser's allow list
 * nor anything a browser does something with - CSS cannot read an attribute value as a
 * width. The editor looks right, the page does not, and nobody is told.
 *
 * A `<colgroup>` is what ProseMirror itself draws while resizing, and `colgroup`, `col` and
 * `style` all come through the sanitiser. The widths are read off the first row and nowhere
 * else, which is also where ProseMirror reads them: a width sitting only on a later row is
 * not one the editor is showing either.
 *
 * `table-layout: fixed` comes with it, and only with it. Without a fixed layout a column
 * width is a suggestion the browser drops as soon as the text is wider, so the page stops
 * looking like the editor - and setting it on a table nobody ever resized would make every
 * column equally wide, which is a change to content that was fine.
 *
 * The styles are inline for the same reason `ImageCaptions` writes one: the page this lands
 * on has never loaded this package's stylesheet.
 */
class TableColumnWidths
{
    public function apply(string $html): string
    {
        if (! str_contains($html, 'data-colwidth')) {
            return $html;
        }

        $document = new DOMDocument;

        // The id is scratch inside a throwaway document; the processing instruction keeps
        // DOMDocument from reading an encoded fragment as Latin-1.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-column-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        foreach (iterator_to_array($document->getElementsByTagName('table')) as $table) {
            $this->size($document, $table);
        }

        $root = $document->getElementById('arte-column-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $document->saveHTML($child);
        }

        return $rendered;
    }

    protected function size(DOMDocument $document, DOMElement $table): void
    {
        $widths = $this->widths($table);

        // Whatever happens next, the attribute does not reach the page: it says something
        // the reader is either shown properly or not at all, and the sanitiser would take
        // it away a moment later regardless.
        foreach ($this->descendants($table, ['td', 'th']) as $cell) {
            $cell->removeAttribute('data-colwidth');
        }

        if (! array_filter($widths, static fn (?int $width): bool => $width !== null)) {
            return;
        }

        // A pasted table may bring its own, and two of them are one too many.
        foreach ($this->descendants($table, ['colgroup']) as $existing) {
            $existing->parentNode?->removeChild($existing);
        }

        $colgroup = $document->createElement('colgroup');

        foreach ($widths as $width) {
            $col = $document->createElement('col');

            if ($width !== null) {
                $col->setAttribute('style', "width: {$width}px;");
            }

            $colgroup->appendChild($col);
        }

        $table->insertBefore($colgroup, $table->firstChild);

        $style = trim($table->getAttribute('style'));
        $style = ($style === '') ? '' : rtrim($style, ';').'; ';

        $table->setAttribute('style', $style.'table-layout: fixed;');
    }

    /**
     * One entry per column, read off the first row - the row ProseMirror reads too.
     *
     * A cell carries one width per column it spans, so a merged cell decides the width of
     * every column beneath it. A column nobody dragged is null and is left to the browser.
     *
     * @return array<int, ?int>
     */
    protected function widths(DOMElement $table): array
    {
        $row = $this->descendants($table, ['tr'])[0] ?? null;

        if ($row === null) {
            return [];
        }

        $widths = [];

        foreach ($row->childNodes as $cell) {
            if (! ($cell instanceof DOMElement) || ! in_array(strtolower($cell->nodeName), ['td', 'th'], strict: true)) {
                continue;
            }

            $span = max(1, (int) $cell->getAttribute('colspan'));
            $declared = array_map(
                static fn (string $width): ?int => ((int) trim($width)) ?: null,
                array_filter(explode(',', $cell->getAttribute('data-colwidth')), static fn (string $width): bool => trim($width) !== ''),
            );

            for ($column = 0; $column < $span; $column++) {
                $widths[] = $declared[$column] ?? null;
            }
        }

        return $widths;
    }

    /**
     * The elements of the given names belonging to this table rather than to one nested
     * inside it. Grouped by name rather than in document order, which only the single-name
     * callers care about and which `getElementsByTagName()` already gives them.
     *
     * @param  array<int, string>  $names
     * @return array<int, DOMElement>
     */
    protected function descendants(DOMElement $table, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            foreach ($table->getElementsByTagName($name) as $element) {
                if ($this->table($element) === $table) {
                    $found[] = $element;
                }
            }
        }

        return $found;
    }

    protected function table(DOMElement $element): ?DOMElement
    {
        $parent = $element->parentNode;

        while ($parent instanceof DOMNode) {
            if ($parent instanceof DOMElement && strtolower($parent->nodeName) === 'table') {
                return $parent;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }
}
