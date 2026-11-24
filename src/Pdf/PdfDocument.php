<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Pdf;

/**
 * What a driver is handed: the finished HTML document and the sheet it goes on - and, for a
 * driver whose engine draws headers and footers itself, those two apart from the document.
 *
 * DomPDF prints a header and a footer as elements of the document, fixed to every page; a
 * Chromium engine prints them from templates of its own, and the same markup in the document
 * would print twice. Which of the two a driver gets is decided by `TakesHeaderAndFooter` on
 * the driver, before the document is built - so `$header` and `$footer` are null here for a
 * driver that finds them in `$html`.
 */
final readonly class PdfDocument
{
    /**
     * Where the page number stands in a header or a footer. DomPDF fills it from a CSS
     * counter, Chromium from its own `pageNumber` class.
     */
    public const PAGE_NUMBER = '<span class="fi-arte-pdf-page-number pageNumber"></span>';

    /**
     * Where the number of pages stands. Neither CSS nor DomPDF knows it while laying out, so
     * DomPDF prints twice where it is asked for and writes it in between; Chromium fills its
     * own `totalPages` class.
     */
    public const PAGE_COUNT = '<span class="fi-arte-pdf-page-count totalPages"></span>';

    /**
     * Where the number of the page an element starts on stands - the element named by its
     * `id`. DomPDF writes it in after a first pass, as it does the number of pages; Chromium
     * has no way to tell, and leaves it empty.
     */
    public static function pageOf(string $id): string
    {
        return '<span class="fi-arte-pdf-page-of" data-page-of="'.e($id).'"></span>';
    }

    public function __construct(
        public string $html,
        public PdfPage $page,
        public ?string $header = null,
        public ?string $footer = null,
    ) {}

    public function countsPages(): bool
    {
        return str_contains($this->html, self::PAGE_COUNT);
    }

    /**
     * The document with the number of pages written in.
     */
    public function withPageCount(int $pages): self
    {
        return new self(
            str_replace(self::PAGE_COUNT, str_replace('></span>', '>'.$pages.'</span>', self::PAGE_COUNT), $this->html),
            $this->page,
            $this->header,
            $this->footer,
        );
    }

    /**
     * The ids whose page the document asks for, through `pageOf()`.
     *
     * @return list<string>
     */
    public function pageReferences(): array
    {
        preg_match_all('/<span class="fi-arte-pdf-page-of" data-page-of="([^"]*)"><\/span>/', $this->html, $matches);

        return array_values(array_unique(array_map(
            static fn (string $id): string => html_entity_decode($id, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $matches[1],
        )));
    }

    /**
     * The document with the pages of the elements it asks for written in. An id missing
     * from `$pages` stays empty.
     *
     * @param  array<string, int>  $pages
     */
    public function withPageNumbers(array $pages): self
    {
        $replacements = [];

        foreach ($pages as $id => $page) {
            $replacements[self::pageOf((string) $id)] = str_replace('></span>', '>'.$page.'</span>', self::pageOf((string) $id));
        }

        return new self(strtr($this->html, $replacements), $this->page, $this->header, $this->footer);
    }

    /**
     * The header as a Chromium header template: a page of its own, drawn in the top margin,
     * which reads none of the document's stylesheets - hence the styles inline, and the
     * margins as padding so it lines up with the text.
     */
    public function headerTemplate(): string
    {
        return $this->template($this->header);
    }

    public function footerTemplate(): string
    {
        return $this->template($this->footer);
    }

    protected function template(?string $markup): string
    {
        // Chromium draws its own date and title where a template is left empty; an empty
        // element is how a side says it has nothing.
        if ($markup === null) {
            return '<span></span>';
        }

        $padding = '0 '.PdfPage::number($this->page->marginRight).'mm 0 '.PdfPage::number($this->page->marginLeft).'mm';

        return '<style>'.file_get_contents(__DIR__.'/../../resources/pdf/running.css').'</style>'
            .'<div class="fi-arte-pdf-running" style="padding: '.$padding.';">'.$markup.'</div>';
    }
}
