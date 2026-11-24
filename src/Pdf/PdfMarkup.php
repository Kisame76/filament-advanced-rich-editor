<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;

/**
 * Rewrites rendered content into markup a PDF engine draws the way the page meant it.
 *
 * The renderer's output is written for a browser, and a few of its elements mean nothing on
 * paper or nothing to an engine without one: a video player, a YouTube frame, a disclosure
 * triangle, a checkbox drawn by a stylesheet, a CSS grid. Each becomes the nearest thing a
 * printed page has - a link to the video, a heading over the hidden text, a ballot box
 * character, a table. What has no printed form at all is replaced by its description rather
 * than left out, so a reader can see that something stood there.
 *
 * The document itself is not touched: this runs on a copy of the markup, after the
 * sanitiser, on the way to the engine.
 */
class PdfMarkup
{
    public const CHECKED = "\u{2611}";

    public const UNCHECKED = "\u{2610}";

    protected DOMDocument $document;

    protected DOMXPath $xpath;

    public function __construct(
        protected PdfImages $images,
        protected ?string $baseUrl = null,
    ) {}

    public function apply(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $this->document = new DOMDocument;

        $loaded = @$this->document->loadHTML(
            '<?xml encoding="UTF-8"><div id="arte-pdf-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $this->xpath = new DOMXPath($this->document);

        $this->dropHeadingMarkers();
        $this->framesToLinks();
        $this->playersToLinks();
        $this->tickTasks();
        $this->openDetails();
        $this->gridsToTables();
        $this->sizeTableColumns();
        $this->embedPictures();
        $this->absoluteLinks();
        $this->reduceStyles();
        $this->centreAutoMargins();

        $root = $this->document->getElementById('arte-pdf-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $rendered = '';

        foreach ($root->childNodes as $child) {
            $rendered .= $this->document->saveHTML($child);
        }

        return $rendered;
    }

    /**
     * The `#` a heading carries so a reader can copy a link to it. On paper there is nothing
     * to copy, and the symbol is one more character in every heading. Where the heading's own
     * text is the link, the link goes and the text stays; the heading keeps its id either way,
     * so a link elsewhere in the document still lands on it.
     */
    protected function dropHeadingMarkers(): void
    {
        foreach ($this->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]//a[starts-with(@href, "#")]') as $link) {
            $text = trim($link->textContent);

            if (($text !== '') && (preg_match('/[\p{L}\p{N}]/u', $text) !== 1) && (mb_strlen($text) <= 2)) {
                $link->parentNode?->removeChild($link);

                continue;
            }

            $this->unwrap($link);
        }
    }

    /**
     * An embedded frame - a video, a map, a pen - as a link to what it showed.
     */
    protected function framesToLinks(): void
    {
        foreach ($this->query('//iframe') as $frame) {
            $src = trim($frame->getAttribute('src'));
            $wrapper = $frame->parentNode;

            // The page the service shares rather than its player: a frame's own address opens
            // a bare player filling the window, where the reader expects the video's page.
            $embed = EmbedUrl::parse($src);
            $href = ($embed === null) ? $src : EmbedUrl::share($embed['provider'], $embed['id'], $embed['start']);

            $replacement = ($src === '')
                ? $this->document->createTextNode('')
                : $this->linkParagraph($href, $frame->getAttribute('title') ?: $href, 'fi-arte-pdf-embed');

            $target = (($wrapper instanceof DOMElement) && ($wrapper->getAttribute('data-type') === 'embed'))
                ? $wrapper
                : $frame;

            $target->parentNode?->replaceChild($replacement, $target);
        }
    }

    /**
     * A video or a sound as a link to the file, with the video's still above it where it
     * has one.
     */
    protected function playersToLinks(): void
    {
        foreach ($this->query('//video | //audio') as $player) {
            $src = trim($player->getAttribute('src'));

            if ($src === '') {
                foreach ($player->getElementsByTagName('source') as $source) {
                    $src = trim($source->getAttribute('src'));

                    break;
                }
            }

            $name = $player->getAttribute('title') ?: rawurldecode(basename((string) parse_url($src, PHP_URL_PATH)));
            $wrapper = $this->document->createElement('div');
            $wrapper->setAttribute('class', 'fi-arte-pdf-media');

            if (($poster = trim($player->getAttribute('poster'))) !== '') {
                $still = $this->document->createElement('img');
                $still->setAttribute('src', $poster);
                $still->setAttribute('alt', $name);
                $wrapper->appendChild($still);
            }

            if ($src !== '') {
                $wrapper->appendChild($this->linkParagraph($src, "\u{25B6} ".($name ?: $src)));
            }

            $player->parentNode?->replaceChild($wrapper, $player);
        }
    }

    /**
     * A task's box as a ballot box character: a stylesheet draws the box on screen, and the
     * label holding it is empty markup to anything that is not a browser.
     */
    protected function tickTasks(): void
    {
        foreach ($this->query('//li[@data-type="taskItem"]') as $item) {
            $isChecked = in_array('fi-arte-task-item-checked', explode(' ', $item->getAttribute('class')), strict: true)
                || ($item->getAttribute('data-checked') === 'true');

            foreach ($this->query('./label', $item) as $control) {
                $item->removeChild($control);
            }

            $box = $this->document->createElement('span', $isChecked ? self::CHECKED : self::UNCHECKED);
            $box->setAttribute('class', 'fi-arte-pdf-task-box');

            $first = $this->query('.//p', $item)[0] ?? $item;
            $first->insertBefore($this->document->createTextNode(' '), $first->firstChild);
            $first->insertBefore($box, $first->firstChild);
        }
    }

    /**
     * A disclosure as a titled block, open: paper cannot be clicked, and what an author
     * hid behind a summary is still part of the document.
     */
    protected function openDetails(): void
    {
        foreach ($this->query('//details') as $details) {
            $block = $this->document->createElement('div');
            $block->setAttribute('class', 'fi-arte-pdf-details');

            foreach (iterator_to_array($details->childNodes) as $child) {
                if (($child instanceof DOMElement) && (strtolower($child->nodeName) === 'summary')) {
                    $summary = $this->document->createElement('p');
                    $summary->setAttribute('class', 'fi-arte-pdf-details-summary');

                    foreach (iterator_to_array($child->childNodes) as $grandchild) {
                        $summary->appendChild($grandchild);
                    }

                    $block->appendChild($summary);

                    continue;
                }

                $block->appendChild($child);
            }

            $details->parentNode?->replaceChild($block, $details);
        }
    }

    /**
     * Filament's grid as a table. The grid is drawn with CSS grid and two custom properties,
     * neither of which a PDF engine reads, so without this its columns print one under the
     * other at full width. A column spanning more than one track keeps its share, and a row
     * that does not fill the grid is padded out rather than stretched.
     */
    protected function gridsToTables(): void
    {
        foreach (array_reverse($this->query('//div[contains(concat(" ", normalize-space(@class), " "), " grid-layout ")]')) as $grid) {
            $tracks = max(1, (int) $grid->getAttribute('data-cols'));

            $table = $this->document->createElement('table');
            $table->setAttribute('class', 'fi-arte-pdf-grid');

            $row = null;
            $used = 0;

            foreach (iterator_to_array($grid->childNodes) as $column) {
                if (! $column instanceof DOMElement) {
                    continue;
                }

                $span = min($tracks, max(1, (int) $column->getAttribute('data-col-span')));

                if (($row === null) || ($used + $span > $tracks)) {
                    if ($row !== null) {
                        $this->padRow($row, $tracks - $used, $tracks);
                    }

                    $row = $this->document->createElement('tr');
                    $table->appendChild($row);
                    $used = 0;
                }

                $cell = $this->document->createElement('td');
                $cell->setAttribute('style', 'width: '.PdfPage::number($span / $tracks * 100).'%;');

                if ($span > 1) {
                    $cell->setAttribute('colspan', (string) $span);
                }

                foreach (iterator_to_array($column->childNodes) as $child) {
                    $cell->appendChild($child);
                }

                $row->appendChild($cell);
                $used += $span;
            }

            if ($row !== null) {
                $this->padRow($row, $tracks - $used, $tracks);
            }

            $grid->parentNode?->replaceChild($table, $grid);
        }
    }

    protected function padRow(DOMElement $row, int $missing, int $tracks): void
    {
        if ($missing <= 0) {
            return;
        }

        $cell = $this->document->createElement('td');
        $cell->setAttribute('style', 'width: '.PdfPage::number($missing / $tracks * 100).'%;');

        if ($missing > 1) {
            $cell->setAttribute('colspan', (string) $missing);
        }

        $row->appendChild($cell);
    }

    /**
     * The widths a table's `<colgroup>` sets, onto the cells of its first row as well.
     * DomPDF lays a table out from its cells and does not read `<col>`; a browser reads both
     * and comes to the same widths.
     */
    protected function sizeTableColumns(): void
    {
        foreach ($this->query('//table[colgroup]') as $table) {
            $widths = [];

            foreach ($this->query('./colgroup/col', $table) as $col) {
                preg_match('/width:\s*([^;]+)/i', $col->getAttribute('style'), $matches);
                $widths[] = isset($matches[1]) ? trim($matches[1]) : null;
            }

            $row = $this->query('./tbody/tr | ./thead/tr | ./tr', $table)[0] ?? null;

            if ($row === null) {
                continue;
            }

            $index = 0;

            foreach ($this->query('./td | ./th', $row) as $cell) {
                $width = $widths[$index] ?? null;
                $index += max(1, (int) $cell->getAttribute('colspan'));

                if ($width === null) {
                    continue;
                }

                $style = rtrim(trim($cell->getAttribute('style')), ';');
                $cell->setAttribute('style', ltrim($style.'; width: '.$width.';', '; '));
            }
        }
    }

    /**
     * Every picture embedded, or its description where it cannot be. A picture that is only
     * decoration - a site's icon beside its name - goes without a trace.
     */
    protected function embedPictures(): void
    {
        foreach ($this->query('//img') as $image) {
            $embedded = $this->images->embed($image->getAttribute('src'), $image->getAttribute('data-id') ?: null);

            $image->removeAttribute('srcset');
            $image->removeAttribute('sizes');
            $image->removeAttribute('loading');

            if ($embedded !== null) {
                $image->setAttribute('src', $embedded);

                continue;
            }

            $description = trim($image->getAttribute('alt'));

            if (($description === '') || ($image->getAttribute('role') === 'presentation')) {
                $image->parentNode?->removeChild($image);

                continue;
            }

            $placeholder = $this->document->createElement('span');
            $placeholder->setAttribute('class', 'fi-arte-pdf-missing-image');
            $placeholder->appendChild($this->document->createTextNode("[{$description}]"));

            $image->parentNode?->replaceChild($placeholder, $image);
        }
    }

    /**
     * Links that lead somewhere from a file as well. A path relative to the site means
     * nothing in a PDF, which has no site to be relative to.
     */
    protected function absoluteLinks(): void
    {
        foreach ($this->query('//a[@href]') as $link) {
            $href = trim($link->getAttribute('href'));

            if (($href === '') || str_starts_with($href, '#') || (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) === 1)) {
                continue;
            }

            $absolute = $this->images->absolute($href);

            if ($absolute !== null) {
                $link->setAttribute('href', $absolute);
            }
        }
    }

    protected function reduceStyles(): void
    {
        foreach ($this->query('//*[@style]') as $element) {
            $style = PdfStyles::reduce($element->getAttribute('style'));

            if ($style === '') {
                $element->removeAttribute('style');

                continue;
            }

            $element->setAttribute('style', $style);
        }
    }

    /**
     * A picture or a figure centred with `margin: auto`, centred the way DomPDF can: by the
     * alignment of the line it stands on. DomPDF leaves a block with automatic side margins
     * on the left. The picture becomes inline for it, inside a line of its own.
     */
    protected function centreAutoMargins(): void
    {
        foreach ($this->query('//img[@style] | //figure[@style]') as $element) {
            $style = $element->getAttribute('style');

            if ((preg_match('/margin-left:\s*auto/i', $style) !== 1) || (preg_match('/margin-right:\s*auto/i', $style) !== 1)) {
                continue;
            }

            if (strtolower($element->nodeName) === 'figure') {
                $element->setAttribute('style', rtrim($style, '; ').'; text-align: center;');

                continue;
            }

            $element->setAttribute('style', trim((string) preg_replace('/(?:^|\s)(?:display|margin-left|margin-right):[^;]*;/i', '', $style)));

            if ($element->getAttribute('style') === '') {
                $element->removeAttribute('style');
            }

            $line = $this->document->createElement('div');
            $line->setAttribute('style', 'text-align: center;');

            $element->parentNode?->replaceChild($line, $element);
            $line->appendChild($element);
        }
    }

    protected function linkParagraph(string $href, string $text, ?string $class = null): DOMElement
    {
        $paragraph = $this->document->createElement('p');

        if ($class !== null) {
            $paragraph->setAttribute('class', $class);
        }

        $link = $this->document->createElement('a');
        $link->setAttribute('href', $href);
        $link->appendChild($this->document->createTextNode($text));

        $paragraph->appendChild($link);

        return $paragraph;
    }

    protected function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * @return array<int, DOMElement>
     */
    protected function query(string $expression, ?DOMNode $context = null): array
    {
        $found = $this->xpath->query($expression, $context);

        if ($found === false) {
            return [];
        }

        return array_values(array_filter(
            iterator_to_array($found),
            static fn (mixed $node): bool => $node instanceof DOMElement,
        ));
    }
}
