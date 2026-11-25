<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use InvalidArgumentException;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PageFormat;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;

/**
 * The page view: the editor draws a sheet of paper instead of a column of text - its width,
 * its margins, and where a page break ends it - with a zoom under the toolbar.
 *
 * The format belongs to the field, not to the document: what is stored stays flowing text,
 * which is what a web page needs, and the same document can be shown on any sheet. Asked for
 * without a format it is the sheet the PDF export prints on, from the same configuration, so
 * what the page view shows is where the PDF breaks its lines.
 */
trait ShowsPages
{
    protected string|PageFormat|bool|Closure|null $pageFormat = null;

    protected bool|Closure|null $isPageLandscape = null;

    /**
     * @var array{0: float, 1: float, 2: float, 3: float}|Closure|null
     */
    protected array|Closure|null $pageMargins = null;

    /**
     * Shows the content on a sheet: `a4`, `a3`, `a5`, `letter` or `legal`, or - with nothing
     * given - the PDF export's configured one. False switches it off again.
     */
    public function page(string|PageFormat|bool|Closure $format = true): static
    {
        if (is_string($format) && (PageFormat::tryFrom(strtolower($format)) === null)) {
            throw new InvalidArgumentException("Unknown page format [{$format}]. Use one of: ".implode(', ', array_column(PageFormat::cases(), 'value')).'.');
        }

        $this->pageFormat = $format;

        return $this;
    }

    public function pageLandscape(bool|Closure $condition = true): static
    {
        $this->isPageLandscape = $condition;

        return $this;
    }

    public function pagePortrait(): static
    {
        return $this->pageLandscape(false);
    }

    /**
     * The margins in millimetres, read the way CSS reads its shorthand - as the PDF export's
     * `margins()` does.
     */
    public function pageMargins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
    {
        $margins = array_values(array_filter([$top, $right, $bottom, $left], static fn (?float $margin): bool => $margin !== null));

        foreach ($margins as $margin) {
            if ($margin < 0) {
                throw new InvalidArgumentException('A margin cannot be negative.');
            }
        }

        $this->pageMargins = PdfPage::shorthand($margins);

        return $this;
    }

    public function hasPageView(): bool
    {
        $format = $this->evaluate($this->pageFormat) ?? config('filament-advanced-rich-editor.page_view', false);

        return ($format !== false) && ($format !== null) && ($format !== '');
    }

    /**
     * The sheet the editor is drawn on, or null where it is not.
     */
    public function getPageSheet(): ?PdfPage
    {
        if (! $this->hasPageView()) {
            return null;
        }

        $format = $this->evaluate($this->pageFormat) ?? config('filament-advanced-rich-editor.page_view');

        $format = match (true) {
            $format instanceof PageFormat => $format,
            is_string($format) => PageFormat::tryFrom(strtolower($format)),
            default => null,
        };

        $landscape = $this->evaluate($this->isPageLandscape);
        $margins = $this->evaluate($this->pageMargins);

        return PdfPage::configured($format, is_bool($landscape) ? $landscape : null, is_array($margins) ? $margins : null);
    }

    /**
     * What `page-view.js` reads off the editor: the sheet in millimetres, and the words on the
     * zoom, in the language of whoever looks at them.
     *
     * @return array{width: float, height: float, margins: array{0: float, 1: float, 2: float, 3: float}, labels: array<string, string>}|null
     */
    public function getPageViewSettingsForJs(): ?array
    {
        $page = $this->getPageSheet();

        if ($page === null) {
            return null;
        }

        $key = 'filament-advanced-rich-editor::advanced-rich-editor.page_view';

        return [
            'width' => $page->width,
            'height' => $page->height,
            'margins' => [$page->marginTop, $page->marginRight, $page->marginBottom, $page->marginLeft],
            'labels' => [
                'zoom' => (string) __("{$key}.zoom"),
                'zoomIn' => (string) __("{$key}.zoom_in"),
                'zoomOut' => (string) __("{$key}.zoom_out"),
                'reset' => (string) __("{$key}.reset"),
                'fit' => (string) __("{$key}.fit"),
                'sheet' => $this->pageSheetLabel($page),
            ],
        ];
    }

    /**
     * The sheet as the bar names it: `A4 · Portrait`, or its size where it has no name.
     */
    protected function pageSheetLabel(PdfPage $page): string
    {
        $short = min($page->width, $page->height);
        $long = max($page->width, $page->height);
        $name = null;

        foreach (PageFormat::cases() as $format) {
            [$width, $height] = $format->dimensions();

            if ((abs($width - $short) < 0.5) && (abs($height - $long) < 0.5)) {
                $name = ucfirst(in_array($format->value, ['letter', 'legal'], strict: true) ? $format->value : strtoupper($format->value));
            }
        }

        $orientation = (string) __('filament-advanced-rich-editor::advanced-rich-editor.page_view.'.($page->isLandscape() ? 'landscape' : 'portrait'));

        return ($name ?? PdfPage::number($page->width).' × '.PdfPage::number($page->height).' mm').' · '.$orientation;
    }

    /**
     * The sheet as CSS custom properties on the field, so the first paint is already a page.
     *
     * @return array<string, string>
     */
    public function getPageViewStyles(): array
    {
        $page = $this->getPageSheet();

        if ($page === null) {
            return [];
        }

        return [
            '--fi-arte-page-width' => PdfPage::number($page->width).'mm',
            '--fi-arte-page-height' => PdfPage::number($page->height).'mm',
            '--fi-arte-page-margin-top' => PdfPage::number($page->marginTop).'mm',
            '--fi-arte-page-margin-right' => PdfPage::number($page->marginRight).'mm',
            '--fi-arte-page-margin-bottom' => PdfPage::number($page->marginBottom).'mm',
            '--fi-arte-page-margin-left' => PdfPage::number($page->marginLeft).'mm',
        ];
    }
}
