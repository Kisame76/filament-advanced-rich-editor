<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Docx;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfPage;
use PhpOffice\PhpWord\ComplexType\TblWidth;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;
use RuntimeException;
use Throwable;

/**
 * Writes the document a `RichEditorDocx` describes as a Word file.
 *
 * It reads the same markup the PDF is printed from - the renderer's, after `PdfMarkup` has
 * embedded every picture, turned embeds and players into links, ticked the task boxes and
 * spelled logical sides as physical ones - and walks it element by element into PHPWord.
 * Not through PHPWord's own HTML reader, which drops exactly what this package adds:
 * callouts, task lists, definition lists, file cards, the widths of table columns. Not from
 * the stored JSON either: there every attribute is still the editor's own notation - a
 * colour's name, a size in pixels, an indent as a step - and all of that has already been
 * resolved once, in the markup, by the extensions that own it.
 *
 * What Word has no equivalent for falls away visibly: a picture it cannot hold (an SVG)
 * leaves its description in brackets, as in the PDF; a video is already a link to its file.
 */
final class DocxWriter
{
    public const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const TWIPS_PER_MILLIMETRE = 56.6929;

    private const TWIPS_PER_PIXEL = 15;

    private const POINTS_PER_PIXEL = 0.75;

    private const FONT = 'Calibri';

    private const SIZE = 10.5;

    private const MONOSPACE = 'Consolas';

    private const LINK = ['color' => '2563EB', 'underline' => 'single'];

    /**
     * The four kinds of callout, as the PDF draws them: a tint, and the colour of the bar.
     */
    private const CALLOUTS = [
        'note' => ['EFF6FF', '2563EB'],
        'tip' => ['F0FDF4', '16A34A'],
        'warning' => ['FEFCE8', 'CA8A04'],
        'danger' => ['FEF2F2', 'DC2626'],
    ];

    private const BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'dd', 'details', 'dialog', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hgroup', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table',
        'ul',
    ];

    private const NAMED_COLOURS = [
        'black' => '000000', 'white' => 'FFFFFF', 'red' => 'FF0000', 'green' => '008000',
        'blue' => '0000FF', 'yellow' => 'FFFF00', 'orange' => 'FFA500', 'purple' => '800080',
        'gray' => '808080', 'grey' => '808080', 'silver' => 'C0C0C0', 'maroon' => '800000',
        'navy' => '000080', 'teal' => '008080', 'olive' => '808000', 'lime' => '00FF00',
        'aqua' => '00FFFF', 'fuchsia' => 'FF00FF', 'pink' => 'FFC0CB', 'brown' => 'A52A2A',
    ];

    private PhpWord $word;

    private int $lists = 0;

    /**
     * The ids a link in the document points at, by the name of the bookmark each became.
     *
     * @var array<string, string>
     */
    private array $bookmarks = [];

    /**
     * Whether the text written last ended in a space - or nothing has been written in this
     * paragraph yet - so the next text's leading space is the same gap and goes.
     */
    private bool $spaced = true;

    public function __construct(
        private readonly PdfPage $page,
        private readonly string $language,
        private readonly ?string $title = null,
    ) {}

    /**
     * @param  string  $body  The content, as `RichEditorExport::toBodyHtml()` gives it.
     * @param  string|null  $cover  The cover's markup, on a page of its own without the header and the footer.
     * @param  string|null  $contents  The heading of a table of contents after the cover, or null for none.
     */
    public function write(string $body, ?string $cover = null, ?string $contents = null, ?string $header = null, ?string $footer = null): string
    {
        // PHPWord writes text into the XML as it is unless told to escape it - and the text is
        // whatever somebody typed. An `&` would break the file, a `<w:...>` would be read.
        // A global switch, so it is put back the way it was found.
        // The default font is just as global; all of it goes back the way it was found.
        $escaping = Settings::isOutputEscapingEnabled();
        $fontName = Settings::getDefaultFontName();
        $fontSize = Settings::getDefaultFontSize();
        Settings::setOutputEscapingEnabled(true);

        try {
            return $this->build($body, $cover, $contents, $header, $footer);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            Settings::setDefaultFontName($fontName);
            Settings::setDefaultFontSize($fontSize);
        }
    }

    private function build(string $body, ?string $cover, ?string $contents, ?string $header, ?string $footer): string
    {
        $this->word = new PhpWord;
        $this->lists = 0;
        $this->bookmarks = [];

        $this->word->setDefaultFontName(self::FONT);
        Settings::setDefaultFontSize(self::SIZE);
        $this->word->setDefaultParagraphStyle(['spaceAfter' => 160, 'lineHeight' => 1.15]);
        $this->word->getSettings()->setThemeFontLang(new Language(self::locale($this->language)));

        if (filled($this->title)) {
            $this->word->getDocInfo()->setTitle((string) $this->title);
        }

        foreach ([1 => 22, 2 => 16, 3 => 13, 4 => 11.5, 5 => 10.5, 6 => 10.5, 7 => 10.5, 8 => 10.5, 9 => 10.5] as $depth => $size) {
            $this->word->addTitleStyle(
                $depth,
                ['bold' => true, 'size' => $size, 'color' => '09090B'],
                ['spaceBefore' => ($depth === 1) ? 320 : 240, 'spaceAfter' => 120, 'keepNext' => true],
            );
        }

        $section = $this->word->addSection([
            'pageSizeW' => $this->twips($this->page->width),
            'pageSizeH' => $this->twips($this->page->height),
            'marginTop' => $this->twips($this->page->marginTop),
            'marginRight' => $this->twips($this->page->marginRight),
            'marginBottom' => $this->twips($this->page->marginBottom),
            'marginLeft' => $this->twips($this->page->marginLeft),
            // Where the header and the footer stand: two fifths of the margin in from the
            // edge, where the PDF puts them.
            'headerHeight' => $this->twips($this->page->marginTop * 0.4),
            'footerHeight' => $this->twips($this->page->marginBottom * 0.4),
            'orientation' => $this->page->isLandscape() ? 'landscape' : 'portrait',
        ]);

        $root = $this->parse($body);
        $this->collectBookmarks($root);

        $context = $this->context();

        if ($cover !== null) {
            // An empty header and footer of the first page's own: Word then prints neither
            // on the cover, which is what the PDF does.
            $section->addHeader(Header::FIRST);
            $section->addFooter(Header::FIRST);

            $this->blocks($this->parse($cover), $section, [...$context, 'chapters' => false]);
            $section->addPageBreak();
        }

        if ($contents !== null) {
            $section->addText($contents, ['bold' => true, 'size' => 22, 'color' => '09090B'], ['spaceAfter' => 240]);
            // Word's own table of contents, over the parts' titles: Word writes the page
            // numbers in, which it asks to do as the file opens.
            $section->addTOC(['size' => self::SIZE], ['tabLeader' => 'dot'], 1, 1);
            $section->addPageBreak();
            $this->word->getSettings()->setUpdateFields(true);
        }

        if ($header !== null) {
            $this->blocks($this->parse($header), $section->addHeader(), $this->runningContext());
        }

        if ($footer !== null) {
            $this->blocks($this->parse($footer), $section->addFooter(), $this->runningContext());
        }

        $this->blocks($root, $section, $context);

        return $this->save();
    }

    private function save(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'arte-docx-');

        if ($path === false) {
            throw new RuntimeException('There is no temporary directory to write the Word file in.');
        }

        try {
            IOFactory::createWriter($this->word, 'Word2007')->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * What every block is written with until something says otherwise.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'font' => [],
            'paragraph' => [],
            'indent' => 0,
            'width' => $this->twips($this->page->width - $this->page->marginLeft - $this->page->marginRight),
            'headings' => 0,
            // Whether a heading becomes one of Word's - in the navigation pane and the table of
            // contents. Not on the cover, and not in a header.
            'chapters' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runningContext(): array
    {
        return [
            ...$this->context(),
            'font' => ['size' => 8, 'color' => '52525B'],
            'paragraph' => ['spaceAfter' => 0],
            'chapters' => false,
        ];
    }

    private function parse(string $html): DOMElement
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"><div id="arte-docx-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $root = $document->getElementById('arte-docx-root');

        if (! $root instanceof DOMElement) {
            throw new RuntimeException('The document could not be read for the Word file.');
        }

        return $root;
    }

    /**
     * Every element a link in the document points at gets a bookmark of its own: Word links
     * inside a file only to bookmarks, whose names are a letter, then letters, digits and
     * underscores, forty of them at most.
     */
    private function collectBookmarks(DOMElement $root): void
    {
        $targets = [];

        foreach ((new DOMXPath($root->ownerDocument ?? new DOMDocument))->query('.//a[starts-with(@href, "#")]', $root) ?: [] as $link) {
            if ($link instanceof DOMElement) {
                $targets[substr($link->getAttribute('href'), 1)] = true;
            }
        }

        foreach (array_keys($targets) as $index => $id) {
            if ($id !== '') {
                $this->bookmarks[(string) $id] = 'arte_'.($index + 1).'_'.substr((string) preg_replace('/[^A-Za-z0-9_]/', '_', (string) $id), 0, 28);
            }
        }
    }

    /**
     * The children of an element, as paragraphs and tables: every run of inline content in
     * a paragraph of its own, every block as what it is.
     *
     * @param  array<string, mixed>  $context
     */
    private function blocks(DOMNode $parent, AbstractContainer $container, array $context): void
    {
        $inline = [];

        foreach ($parent->childNodes as $child) {
            if ($this->isBlock($child)) {
                $this->inlineParagraph($inline, $container, $context);
                $inline = [];

                assert($child instanceof DOMElement);
                $this->block($child, $container, $context);

                continue;
            }

            $inline[] = $child;
        }

        $this->inlineParagraph($inline, $container, $context);
    }

    private function isBlock(DOMNode $node): bool
    {
        if (! $node instanceof DOMElement) {
            return false;
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'a') {
            return $this->hasClass($node, 'fi-arte-link-preview');
        }

        return in_array($tag, self::BLOCKS, strict: true);
    }

    /**
     * @param  array<int, DOMNode>  $nodes
     * @param  array<string, mixed>  $context
     */
    private function inlineParagraph(array $nodes, AbstractContainer $container, array $context): void
    {
        $hasContent = false;

        foreach ($nodes as $node) {
            if (($node instanceof DOMElement) || (trim(str_replace("\u{00A0}", ' ', (string) $node->textContent)) !== '')) {
                $hasContent = true;

                break;
            }
        }

        if (! $hasContent) {
            return;
        }

        $run = $container->addTextRun($this->paragraphStyle(null, $context));
        $this->spaced = true;

        foreach ($nodes as $node) {
            $this->inline($node, $run, $context, $context['font'], null);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function block(DOMElement $element, AbstractContainer $container, array $context): void
    {
        $tag = strtolower($element->tagName);
        $id = $element->getAttribute('id');

        if (($id !== '') && isset($this->bookmarks[$id]) && (! $container instanceof Header) && (! $container instanceof Footer)) {
            $container->addBookmark($this->bookmarks[$id]);
        }

        if ($element->getAttribute('data-type') === 'pageBreak') {
            if ($container instanceof Section) {
                $container->addPageBreak();
            }

            return;
        }

        if ($this->hasClass($element, 'fi-arte-pdf-part')) {
            if ($this->hasClass($element, 'fi-arte-pdf-new-page') && ($container instanceof Section)) {
                $container->addPageBreak();
            }

            // Each part's own headings sit under its title, one level down, so the table of
            // contents lists the parts and nothing inside them.
            $this->blocks($element, $container, [...$this->within($element, $context), 'headings' => 1]);

            return;
        }

        if ($this->hasClass($element, 'fi-arte-callout')) {
            $variant = null;

            foreach (array_keys(self::CALLOUTS) as $kind) {
                if ($this->hasClass($element, "fi-arte-callout-{$kind}")) {
                    $variant = $kind;
                }
            }

            [$fill, $bar] = self::CALLOUTS[$variant] ?? ['F4F4F5', 'A1A1AA'];
            $this->box($element, $container, $context, $fill, ['left' => [$bar, 24]]);

            return;
        }

        if ($this->hasClass($element, 'lead')) {
            $this->blocks($element, $container, [...$context, 'font' => [...$context['font'], 'size' => 13, 'color' => '3F3F46']]);

            return;
        }

        if ($this->hasClass($element, 'fi-arte-task-item-checked')) {
            $context = [...$context, 'font' => [...$context['font'], 'color' => '71717A']];
        }

        match ($tag) {
            'p', 'summary' => $this->paragraph($element, $container, $context),
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->heading($element, $container, $context, (int) substr($tag, 1)),
            'ul', 'ol' => $this->list($element, $container, $context, 0),
            'table' => $this->table($element, $container, $context),
            'blockquote' => $this->box($element, $container, [...$context, 'font' => [...$context['font'], 'color' => '3F3F46']], null, ['left' => ['D4D4D8', 18]]),
            'pre' => $this->code($element, $container, $context),
            'hr' => $this->rule($container, $context),
            'dt' => $this->paragraph($element, $container, [...$context, 'font' => [...$context['font'], 'bold' => true]]),
            'dd' => $this->blocks($element, $container, [...$context, 'indent' => $context['indent'] + 360]),
            'figcaption' => $this->paragraph($element, $container, [...$context, 'font' => [...$context['font'], 'italic' => true, 'size' => 9, 'color' => '52525B']]),
            'a' => $this->linkCard($element, $container, $context),
            default => $this->blocks($element, $container, $this->within($element, $context)),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function paragraph(DOMElement $element, AbstractContainer $container, array $context): void
    {
        if ($this->hasClass($element, 'fi-arte-pdf-details-summary')) {
            $context = [...$context, 'font' => [...$context['font'], 'bold' => true]];
        }

        if ($this->hasClass($element, 'fi-arte-pdf-page-numbers')) {
            $context = [...$context, 'paragraph' => [...$context['paragraph'], 'alignment' => Jc::CENTER]];
        }

        // `float: right` beside other text, as a header writes its date beside the title:
        // Word has no floats, and two cells side by side are what keeps them on one line.
        foreach ($element->childNodes as $child) {
            if (($child instanceof DOMElement) && preg_match('/(?:^|;)\s*float\s*:\s*right/i', $child->getAttribute('style')) === 1) {
                $this->sideBySide($element, $child, $container, $context);

                return;
            }
        }

        $run = $container->addTextRun($this->paragraphStyle($element, $context));
        $this->spaced = true;

        foreach ($element->childNodes as $child) {
            $this->inline($child, $run, $context, $context['font'], null);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sideBySide(DOMElement $paragraph, DOMElement $right, AbstractContainer $container, array $context): void
    {
        $half = (int) ($context['width'] / 2);
        $table = $container->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0, 'width' => $half * 2, 'unit' => 'dxa', 'layout' => 'fixed']);
        $table->addRow();

        $leftRun = $table->addCell($half, $this->noBorders())->addTextRun([...$this->paragraphStyle($paragraph, $context), 'alignment' => Jc::LEFT]);
        $this->spaced = true;

        foreach ($paragraph->childNodes as $child) {
            if ($child !== $right) {
                $this->inline($child, $leftRun, $context, $context['font'], null);
            }
        }

        $rightRun = $table->addCell($half, $this->noBorders())->addTextRun([...$this->paragraphStyle($paragraph, $context), 'alignment' => Jc::RIGHT]);
        $this->spaced = true;
        $this->inline($right, $rightRun, $context, $context['font'], null);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function heading(DOMElement $element, AbstractContainer $container, array $context, int $level): void
    {
        // The cover's title is set large rather than as a chapter: it would otherwise be the
        // first entry of the table of contents.
        if ($this->hasClass($element, 'fi-arte-pdf-cover-title')) {
            $this->paragraph($element, $container, [
                ...$context,
                'font' => [...$context['font'], 'bold' => true, 'size' => 28, 'color' => '09090B'],
                'paragraph' => [...$context['paragraph'], 'spaceBefore' => $this->twips(70), 'spaceAfter' => $this->twips(8)],
            ]);

            return;
        }

        if ((! $context['chapters']) || (! ($container instanceof Section || $container instanceof Cell))) {
            $sizes = [1 => 22, 2 => 16, 3 => 13, 4 => 11.5, 5 => 10.5, 6 => 10.5];

            // Bold at its size - or, in a header, at the header's.
            $this->paragraph($element, $container, [
                ...$context,
                'font' => [...$context['font'], 'bold' => true, 'size' => $context['font']['size'] ?? $sizes[$level]],
            ]);

            return;
        }

        $depth = $this->hasClass($element, 'fi-arte-pdf-part-title')
            ? 1
            : min(9, $level + (int) $context['headings']);

        // A run of its own, so a heading keeps its marks and its links - and a Word heading,
        // so the navigation pane and the table of contents see it.
        $run = new TextRun;
        $this->spaced = true;

        foreach ($element->childNodes as $child) {
            $this->inline($child, $run, $context, [], null);
        }

        $container->addTitle($run, $depth);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function list(DOMElement $list, AbstractContainer $container, array $context, int $depth): void
    {
        $isTasks = ($list->getAttribute('data-type') === 'taskList') || $this->hasClass($list, 'fi-arte-task-list');
        $style = $isTasks ? null : $this->numbering($list, $depth);

        foreach ($list->childNodes as $item) {
            if (($item instanceof DOMElement) && (strtolower($item->tagName) === 'li')) {
                $this->listItem($item, $container, $context, $depth, $style);
            }
        }
    }

    /**
     * One item: its first paragraph carries the number or the bullet, every further block
     * stands indented beneath it, and a list inside it goes one level deeper.
     *
     * @param  array<string, mixed>  $context
     */
    private function listItem(DOMElement $item, AbstractContainer $container, array $context, int $depth, ?string $style): void
    {
        if ($this->hasClass($item, 'fi-arte-task-item-checked')) {
            $context = [...$context, 'font' => [...$context['font'], 'color' => '71717A']];
        }

        $indent = 360 * ($depth + 1) + (int) $context['indent'];
        $paragraph = [...$this->paragraphStyle(null, $context), 'spaceAfter' => 40];
        $run = null;
        $pending = [];

        foreach ($this->itemChildren($item) as $child) {
            $isList = ($child instanceof DOMElement) && in_array(strtolower($child->tagName), ['ul', 'ol'], strict: true);

            if (! ($isList || $this->isBlock($child))) {
                $pending[] = $child;

                continue;
            }

            if ($pending !== []) {
                $run ??= $this->openItem($container, $depth, $style, $paragraph, $indent);
                $this->inlineInto($pending, $run, $context);
                $pending = [];
            }

            assert($child instanceof DOMElement);

            if ($isList) {
                $run ??= $this->openItem($container, $depth, $style, $paragraph, $indent);
                $this->list($child, $container, $context, $depth + 1);

                continue;
            }

            if ($run === null) {
                $run = $this->openItem($container, $depth, $style, $paragraph, $indent);
                $this->inlineInto(iterator_to_array($child->childNodes), $run, $context);

                continue;
            }

            $this->block($child, $container, [...$context, 'indent' => $indent]);
        }

        if ($pending !== []) {
            $run ??= $this->openItem($container, $depth, $style, $paragraph, $indent);
            $this->inlineInto($pending, $run, $context);
        }

        if ($run === null) {
            $this->openItem($container, $depth, $style, $paragraph, $indent);
        }
    }

    /**
     * The paragraph an item's number or bullet stands in. A task item's box is its first
     * character: no bullet in front of that as well.
     *
     * @param  array<string, mixed>  $paragraph
     */
    private function openItem(AbstractContainer $container, int $depth, ?string $style, array $paragraph, int $indent): AbstractContainer
    {
        $this->spaced = true;

        return ($style === null)
            ? $container->addTextRun([...$paragraph, 'indentation' => ['left' => $indent - 360]])
            : $container->addListItemRun($depth, $style, $paragraph);
    }

    /**
     * @param  array<int, DOMNode>  $nodes
     * @param  array<string, mixed>  $context
     */
    private function inlineInto(array $nodes, AbstractContainer $run, array $context): void
    {
        foreach ($nodes as $node) {
            $this->inline($node, $run, $context, $context['font'], null);
        }
    }

    /**
     * The children of an item, with the wrappers a task item puts around its text opened up.
     *
     * @return array<int, DOMNode>
     */
    private function itemChildren(DOMElement $item): array
    {
        $children = [];

        foreach ($item->childNodes as $child) {
            if (($child instanceof DOMElement) && (strtolower($child->tagName) === 'div') && ($child->getAttribute('data-type') === '') && ! $this->hasClass($child, 'fi-arte-callout')) {
                array_push($children, ...$this->itemChildren($child));

                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * A numbering of its own for every list, so each one starts from its own first number,
     * in the marker the list was given.
     */
    private function numbering(DOMElement $list, int $depth): string
    {
        $isOrdered = strtolower($list->tagName) === 'ol';
        $type = strtolower(trim((string) ($this->declarations($list)['list-style-type'] ?? '')));

        if ($type === '') {
            $type = match ($list->getAttribute('type')) {
                'a' => 'lower-alpha',
                'A' => 'upper-alpha',
                'i' => 'lower-roman',
                'I' => 'upper-roman',
                default => $isOrdered ? 'decimal' : 'disc',
            };
        }

        $format = match ($type) {
            'lower-alpha', 'lower-latin' => 'lowerLetter',
            'upper-alpha', 'upper-latin' => 'upperLetter',
            'lower-roman' => 'lowerRoman',
            'upper-roman' => 'upperRoman',
            'decimal', 'decimal-leading-zero' => 'decimal',
            default => 'bullet',
        };

        $bullet = match ($type) {
            'circle' => '◦',
            'square' => '▪',
            'none' => '',
            default => '•',
        };

        $start = max(1, (int) ($list->getAttribute('start') ?: 1));
        $name = 'arte-list-'.(++$this->lists);
        $levels = [];

        for ($level = 0; $level < 9; $level++) {
            $levels[] = [
                'format' => $format,
                'text' => ($format === 'bullet') ? $bullet : '%'.($level + 1).'.',
                'left' => 360 * ($level + 1),
                'hanging' => 360,
                'tabPos' => 360 * ($level + 1),
                'start' => ($level === $depth) ? $start : 1,
            ];
        }

        $this->word->addNumberingStyle($name, ['type' => 'multilevel', 'levels' => $levels]);

        return $name;
    }

    /**
     * A table, cell by cell, with the widths the columns were given, merged cells merged,
     * header rows repeated on every page, and a grid's columns side by side without lines.
     *
     * @param  array<string, mixed>  $context
     */
    private function table(DOMElement $element, AbstractContainer $container, array $context): void
    {
        $rows = [];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'tr') {
                $rows[] = [$child, false];
            } elseif (in_array($tag, ['thead', 'tbody', 'tfoot'], strict: true)) {
                foreach ($child->childNodes as $row) {
                    if (($row instanceof DOMElement) && (strtolower($row->tagName) === 'tr')) {
                        $rows[] = [$row, $tag === 'thead'];
                    }
                }
            }
        }

        if ($rows === []) {
            return;
        }

        $isGrid = $this->hasClass($element, 'fi-arte-pdf-grid');
        $available = max(1000, (int) $context['width'] - (int) $context['indent']);
        $widths = $this->columnWidths($rows, $this->declaredWidths($element), $available);

        $table = $container->addTable([
            ...($isGrid ? ['borderSize' => 0, 'borderColor' => 'FFFFFF'] : ['borderSize' => 6, 'borderColor' => 'D4D4D8']),
            'cellMarginTop' => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => $isGrid ? 0 : 100,
            'cellMarginRight' => $isGrid ? 120 : 100,
            'width' => array_sum($widths),
            'unit' => 'dxa',
            'layout' => 'fixed',
            ...$this->tableIndent($context),
        ]);

        // Columns a cell above still covers, by where they start: how many rows it has left
        // and how many columns it spans.
        $merged = [];

        foreach ($rows as [$row, $isHead]) {
            $cells = array_values(array_filter(
                iterator_to_array($row->childNodes),
                static fn (DOMNode $node): bool => ($node instanceof DOMElement) && in_array(strtolower($node->tagName), ['td', 'th'], strict: true),
            ));

            $isHeader = $isHead || (($cells !== []) && (count(array_filter($cells, static fn (DOMElement $cell): bool => strtolower($cell->tagName) === 'th')) === count($cells)));

            $table->addRow(null, ['tblHeader' => $isHeader && ! $isGrid, 'cantSplit' => true]);
            $column = 0;

            $continue = function () use (&$column, &$merged, $table, $widths, $isGrid): void {
                while (isset($merged[$column]) && ($merged[$column][0] > 0)) {
                    [$left, $span] = $merged[$column];
                    $table->addCell($this->span($widths, $column, $span), [
                        'vMerge' => 'continue',
                        ...($span > 1 ? ['gridSpan' => $span] : []),
                        ...($isGrid ? $this->noBorders() : []),
                    ]);
                    $merged[$column][0] = $left - 1;
                    $column += $span;
                }
            };

            foreach ($cells as $cell) {
                $continue();

                $span = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                $rowspan = max(1, (int) ($cell->getAttribute('rowspan') ?: 1));
                $width = $this->span($widths, $column, $span);
                $isHeaderCell = strtolower($cell->tagName) === 'th';
                $background = $this->colour($this->declarations($cell)['background-color'] ?? null);

                $style = [
                    'valign' => 'top',
                    ...($span > 1 ? ['gridSpan' => $span] : []),
                    ...($rowspan > 1 ? ['vMerge' => 'restart'] : []),
                    ...(($background ?? ($isHeaderCell ? 'F4F4F5' : null)) !== null ? ['bgColor' => $background ?? 'F4F4F5'] : []),
                    ...($isGrid ? $this->noBorders() : []),
                ];

                if ($rowspan > 1) {
                    $merged[$column] = [$rowspan - 1, $span];
                }

                $target = $table->addCell($width, $style);

                $this->blocks($cell, $target, [
                    ...$context,
                    'width' => max(600, $width - 240),
                    'indent' => 0,
                    'font' => $isHeaderCell ? [...$context['font'], 'bold' => true] : $context['font'],
                    'paragraph' => ['spaceAfter' => 60],
                ]);

                $column += $span;
            }

            $continue();
        }

        $this->separator($container);
    }

    /**
     * The width of every column, in twips: what the first row's cells ask for - in pixels or
     * as a share - and the rest of the line shared out among the ones that do not, scaled
     * down where they ask for more than there is.
     *
     * @param  array<int, array{0: DOMElement, 1: bool}>  $rows
     * @param  array<int, string|null>  $declared  What a `colgroup` says, column by column.
     * @return array<int, int>
     */
    private function columnWidths(array $rows, array $declared, int $available): array
    {
        $widths = [];
        $columns = 0;

        foreach ($rows as [$row]) {
            $count = 0;

            foreach ($row->childNodes as $cell) {
                if (($cell instanceof DOMElement) && in_array(strtolower($cell->tagName), ['td', 'th'], strict: true)) {
                    $count += max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                }
            }

            $columns = max($columns, $count);
        }

        // The `colgroup` first - the renderer writes the widths there, and a first row may
        // open with a cell across several columns. Otherwise what the first row's cells say.
        if ($declared !== []) {
            foreach ($declared as $width) {
                $widths[] = $this->length($width, $available);
            }
        } else {
            foreach ($rows[0][0]->childNodes as $cell) {
                if (! (($cell instanceof DOMElement) && in_array(strtolower($cell->tagName), ['td', 'th'], strict: true))) {
                    continue;
                }

                $span = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                $width = $this->length($this->declarations($cell)['width'] ?? null, $available);

                for ($index = 0; $index < $span; $index++) {
                    $widths[] = ($width === null) ? null : (int) ($width / $span);
                }
            }
        }

        $widths = array_pad($widths, $columns, null);
        $known = array_sum(array_filter($widths, static fn (?int $width): bool => $width !== null));
        $unknown = count(array_filter($widths, static fn (?int $width): bool => $width === null));
        $share = ($unknown > 0) ? max(600, (int) (($available - $known) / $unknown)) : 0;

        $widths = array_map(static fn (?int $width): int => $width ?? $share, $widths);
        $total = array_sum($widths);

        if ($total > $available) {
            $widths = array_map(static fn (int $width): int => (int) floor($width * $available / $total), $widths);
        }

        return $widths;
    }

    /**
     * The width each `col` of a table's `colgroup` gives its columns, a `span` counted out.
     *
     * @return array<int, string|null>
     */
    private function declaredWidths(DOMElement $table): array
    {
        $widths = [];

        foreach ($table->childNodes as $group) {
            if (! (($group instanceof DOMElement) && (strtolower($group->tagName) === 'colgroup'))) {
                continue;
            }

            foreach ($group->childNodes as $column) {
                if (($column instanceof DOMElement) && (strtolower($column->tagName) === 'col')) {
                    $width = $this->declarations($column)['width'] ?? ($column->getAttribute('width') ?: null);

                    for ($index = 0; $index < max(1, (int) ($column->getAttribute('span') ?: 1)); $index++) {
                        $widths[] = $width;
                    }
                }
            }
        }

        return $widths;
    }

    /**
     * @param  array<int, int>  $widths
     */
    private function span(array $widths, int $from, int $span): int
    {
        return array_sum(array_slice($widths, $from, $span)) ?: 600;
    }

    /**
     * Content in a box of its own - a callout, a quotation, code: a table of one cell, the
     * one thing Word puts a background and a bar beside a run of paragraphs with.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, array{0: string, 1: int}>  $borders  By side: colour, width in eighths of a point.
     */
    private function box(DOMElement $element, AbstractContainer $container, array $context, ?string $fill, array $borders, bool $isCode = false): void
    {
        $width = max(1000, (int) $context['width'] - (int) $context['indent']);

        $table = $container->addTable([
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMarginTop' => 100,
            'cellMarginBottom' => 100,
            'cellMarginLeft' => 160,
            'cellMarginRight' => 160,
            'width' => $width,
            'unit' => 'dxa',
            'layout' => 'fixed',
            ...$this->tableIndent($context),
        ]);
        $table->addRow(null, ['cantSplit' => false]);

        $style = $this->noBorders();

        foreach ($borders as $side => [$colour, $size]) {
            $style['border'.ucfirst($side).'Size'] = $size;
            $style['border'.ucfirst($side).'Color'] = $colour;
        }

        if ($fill !== null) {
            $style['bgColor'] = $fill;
        }

        $cell = $table->addCell($width, $style);
        $inner = [...$context, 'width' => $width - 320, 'indent' => 0, 'paragraph' => ['spaceAfter' => 80]];

        if ($isCode) {
            $this->codeLines($element, $cell, $inner);
        } else {
            $this->blocks($element, $cell, $inner);
        }

        $this->separator($container);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function code(DOMElement $element, AbstractContainer $container, array $context): void
    {
        $all = ['top', 'right', 'bottom', 'left'];

        $this->box($element, $container, $context, 'F4F4F5', array_fill_keys($all, ['E4E4E7', 4]), isCode: true);
    }

    /**
     * Code a line at a time, every space kept - indentation is meaning in code, and PHPWord
     * writes text with `xml:space="preserve"`.
     *
     * @param  array<string, mixed>  $context
     */
    private function codeLines(DOMElement $element, AbstractContainer $container, array $context): void
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], (string) $element->textContent);
        $lines = explode("\n", trim($text, "\n"));

        foreach ($lines as $index => $line) {
            $run = $container->addTextRun(['spaceAfter' => 0, 'lineHeight' => 1.0]);

            if ($line !== '') {
                $run->addText($line, [...$context['font'], 'name' => self::MONOSPACE, 'size' => 9]);
            }
        }
    }

    /**
     * A horizontal rule: a line across the text, which Word draws as the top edge of an
     * empty cell.
     *
     * @param  array<string, mixed>  $context
     */
    private function rule(AbstractContainer $container, array $context): void
    {
        $width = max(1000, (int) $context['width'] - (int) $context['indent']);
        $table = $container->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0, 'width' => $width, 'unit' => 'dxa', 'layout' => 'fixed']);
        $table->addRow(40, ['exactHeight' => true]);
        $table->addCell($width, [...$this->noBorders(), 'borderTopSize' => 6, 'borderTopColor' => 'D4D4D8']);

        $this->separator($container);
    }

    /**
     * A link card: the title as the link, the description and the site under it, in a frame.
     *
     * @param  array<string, mixed>  $context
     */
    private function linkCard(DOMElement $element, AbstractContainer $container, array $context): void
    {
        $href = $element->getAttribute('href');
        $width = max(1000, (int) $context['width'] - (int) $context['indent']);

        $table = $container->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 140, 'width' => $width, 'unit' => 'dxa', 'layout' => 'fixed']);
        $table->addRow();
        $cell = $table->addCell($width, array_merge(...array_map(
            static fn (string $side): array => ["border{$side}Size" => 6, "border{$side}Color" => 'D4D4D8'],
            ['Top', 'Right', 'Bottom', 'Left'],
        )));

        $parts = [
            'fi-arte-link-preview-title' => ['bold' => true],
            'fi-arte-link-preview-description' => ['size' => 9, 'color' => '52525B'],
            'fi-arte-link-preview-site' => ['size' => 8, 'color' => '71717A'],
        ];

        $found = false;

        foreach ((new DOMXPath($element->ownerDocument ?? new DOMDocument))->query('.//span[@class]', $element) ?: [] as $span) {
            if (! $span instanceof DOMElement) {
                continue;
            }

            foreach ($parts as $class => $font) {
                if (! $this->hasClass($span, $class)) {
                    continue;
                }

                $text = trim((string) preg_replace('/\s+/u', ' ', $span->textContent));

                if ($text === '') {
                    continue;
                }

                $run = $cell->addTextRun(['spaceAfter' => 40]);
                $found = true;

                if ($class === 'fi-arte-link-preview-title') {
                    $this->link($run, $href, $text, [...$context['font'], ...$font]);
                } else {
                    $run->addText($text, [...$context['font'], ...$font]);
                }
            }
        }

        if (! $found) {
            $this->link($cell->addTextRun(), $href, $href, $context['font']);
        }

        $this->separator($container);
    }

    /**
     * Word joins two tables that touch into one; an empty paragraph between them keeps
     * them apart, and a cell has to end in a paragraph anyway.
     */
    private function separator(AbstractContainer $container): void
    {
        // A line one point high: the gap under a box is its spacing, not an empty line.
        $container->addTextRun(['spaceBefore' => 0, 'spaceAfter' => 120, 'spacing' => 20, 'spacingLineRule' => 'exact']);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, TblWidth>
     */
    private function tableIndent(array $context): array
    {
        return ((int) $context['indent'] > 0) ? ['indent' => new TblWidth((int) $context['indent'], 'dxa')] : [];
    }

    /**
     * @return array<string, int|string>
     */
    private function noBorders(): array
    {
        $style = [];

        foreach (['Top', 'Right', 'Bottom', 'Left'] as $side) {
            $style["border{$side}Size"] = 0;
            $style["border{$side}Color"] = 'FFFFFF';
        }

        return $style;
    }

    /**
     * Inline content into a run: text with its marks, links, line breaks, pictures, and the
     * page number and the number of pages where a header or a footer asks for them.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $font
     */
    private function inline(DOMNode $node, AbstractContainer $run, array $context, array $font, ?string $href): void
    {
        if ($node instanceof DOMText) {
            $this->text($run, (string) $node->data, $font, $href);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        if ($this->hasClass($node, 'fi-arte-pdf-page-number')) {
            $run->addField('PAGE')->setFontStyle($font ?: null);
            $this->spaced = false;

            return;
        }

        if ($this->hasClass($node, 'fi-arte-pdf-page-count')) {
            $run->addField('NUMPAGES')->setFontStyle($font ?: null);
            $this->spaced = false;

            return;
        }

        if ($tag === 'br') {
            $run->addTextBreak();
            $this->spaced = true;

            return;
        }

        if ($tag === 'img') {
            $this->image($node, $run, $context);

            return;
        }

        if (in_array($tag, ['script', 'style', 'template'], strict: true)) {
            return;
        }

        // A file card: its name and size, linked to the file. The coloured tile with the
        // extension in it is drawing, not text.
        if (($tag === 'a') && ($node->getAttribute('data-type') === 'file')) {
            $name = $this->textOfClass($node, 'fi-arte-file-name');
            $size = $this->textOfClass($node, 'fi-arte-file-size');
            $label = ($name ?? $node->getAttribute('download')) ?: $node->getAttribute('href');

            $this->link($run, $node->getAttribute('href'), ($size !== null) ? "{$label} ({$size})" : $label, $font);

            return;
        }

        if (($tag === 'a') && ($node->getAttribute('href') !== '')) {
            $href = $node->getAttribute('href');
        }

        $font = $this->fontOf($node, $font);

        foreach ($node->childNodes as $child) {
            $this->inline($child, $run, $context, $font, $href);
        }
    }

    /**
     * @param  array<string, mixed>  $font
     */
    private function text(AbstractContainer $run, string $text, array $font, ?string $href): void
    {
        // White space as HTML reads it: any run of it is one space, and none at the start of
        // a line or after a space already written.
        $text = (string) preg_replace('/[ \t\r\n\f]+/', ' ', $text);

        if ($this->spaced) {
            $text = ltrim($text, ' ');
        }

        if ($text === '') {
            return;
        }

        $this->spaced = str_ends_with($text, ' ');

        if ($href !== null) {
            $this->link($run, $href, $text, $font);

            return;
        }

        $run->addText($text, $font ?: null);
    }

    /**
     * @param  array<string, mixed>  $font
     */
    private function link(AbstractContainer $run, string $href, string $text, array $font): void
    {
        if (str_starts_with($href, '#')) {
            $bookmark = $this->bookmarks[substr($href, 1)] ?? null;

            if ($bookmark === null) {
                $run->addText($text, $font ?: null);

                return;
            }

            $run->addLink($bookmark, $text, [...$font, ...self::LINK], null, true);

            return;
        }

        $run->addLink($href, $text, [...$font, ...self::LINK]);
    }

    /**
     * A picture, from the bytes `PdfImages` already read into the markup and nowhere else.
     * PHPWord fetches an address it is handed and reads a path that exists, and the document
     * is text somebody typed - so it is only ever handed bytes.
     *
     * @param  array<string, mixed>  $context
     */
    private function image(DOMElement $image, AbstractContainer $run, array $context): void
    {
        $alt = trim($image->getAttribute('alt'));
        $bytes = $this->embeddedBytes($image->getAttribute('src'));
        $size = ($bytes !== null) ? @getimagesizefromstring($bytes) : false;

        // Word holds PNG, JPEG and GIF; WebP and BMP are turned into PNG, and what GD cannot
        // read - an SVG - leaves its description, as in the PDF.
        if (($bytes !== null) && ($size !== false) && ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/gif'], strict: true)) {
            $bytes = $this->asPng($bytes);
        }

        if (($bytes === null) || ($size === false) || ($size[0] < 1) || ($size[1] < 1)) {
            if ($alt !== '') {
                $this->text($run, "[{$alt}]", ['color' => '71717A'], null);
            }

            return;
        }

        [$naturalWidth, $naturalHeight] = $size;
        $maximum = (int) $context['width'] / self::TWIPS_PER_PIXEL;
        $width = $this->length($image->getAttribute('width') ?: ($this->declarations($image)['width'] ?? null), (int) $context['width']);
        $width = ($width !== null) ? $width / self::TWIPS_PER_PIXEL : $naturalWidth;
        $width = max(1, min($width, $maximum));
        $height = $width * $naturalHeight / $naturalWidth;

        $run->addImage($bytes, [
            'width' => round($width * self::POINTS_PER_PIXEL, 2),
            'height' => round($height * self::POINTS_PER_PIXEL, 2),
        ]);

        $this->spaced = false;
    }

    private function embeddedBytes(string $source): ?string
    {
        if (preg_match('#^data:image/[a-z0-9.+-]+;base64,(.+)$#is', trim($source), $matches) !== 1) {
            return null;
        }

        $bytes = base64_decode((string) preg_replace('/\s+/', '', $matches[1]), strict: true);

        if (($bytes === false) || ($bytes === '')) {
            return null;
        }

        // PHPWord reads a source ending in `.php` as a script that draws a picture.
        return str_ends_with(strtolower($bytes), '.php') ? $this->asPng($bytes) : $bytes;
    }

    private function asPng(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $image = @imagecreatefromstring($bytes);
        } catch (Throwable) {
            return null;
        }

        if ($image === false) {
            return null;
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * The font an element asks for, on top of the one around it: by what it is - `strong`,
     * `code`, `sub` - and by its inline style.
     *
     * @param  array<string, mixed>  $font
     * @return array<string, mixed>
     */
    private function fontOf(DOMElement $element, array $font): array
    {
        $size = (float) ($font['size'] ?? self::SIZE);

        $font = match (strtolower($element->tagName)) {
            'strong', 'b' => [...$font, 'bold' => true],
            'em', 'i', 'cite', 'var', 'dfn' => [...$font, 'italic' => true],
            'u', 'ins' => [...$font, 'underline' => 'single'],
            's', 'del', 'strike' => [...$font, 'strikethrough' => true],
            'sub' => [...$font, 'subScript' => true],
            'sup' => [...$font, 'superScript' => true],
            'code', 'kbd', 'samp', 'tt' => [...$font, 'name' => self::MONOSPACE, 'size' => round($size * 0.9, 1), 'bgColor' => 'F4F4F5'],
            'mark' => [...$font, 'bgColor' => 'FEF08A'],
            'small' => [...$font, 'size' => round($size * 0.85, 1)],
            default => $font,
        };

        $declarations = $this->declarations($element);

        if (($colour = $this->colour($declarations['color'] ?? null)) !== null) {
            $font['color'] = $colour;
        }

        if (($background = $this->colour($declarations['background-color'] ?? $declarations['background'] ?? null)) !== null) {
            $font['bgColor'] = $background;
        }

        if (($points = $this->points($declarations['font-size'] ?? null, $size)) !== null) {
            $font['size'] = $points;
        }

        if (isset($declarations['font-family']) && (($family = $this->fontName($declarations['font-family'])) !== null)) {
            $font['name'] = $family;
        }

        $weight = strtolower($declarations['font-weight'] ?? '');

        if (($weight === 'bold') || ($weight === 'bolder') || (is_numeric($weight) && (int) $weight >= 600)) {
            $font['bold'] = true;
        } elseif (($weight === 'normal') || (is_numeric($weight) && (int) $weight < 600)) {
            $font['bold'] = false;
        }

        $style = strtolower($declarations['font-style'] ?? '');

        if ($style === 'italic' || $style === 'oblique') {
            $font['italic'] = true;
        }

        $decoration = strtolower($declarations['text-decoration'] ?? $declarations['text-decoration-line'] ?? '');

        if (str_contains($decoration, 'underline')) {
            $font['underline'] = 'single';
        }

        if (str_contains($decoration, 'line-through')) {
            $font['strikethrough'] = true;
        }

        $alignment = strtolower($declarations['vertical-align'] ?? '');

        if ($alignment === 'super') {
            $font['superScript'] = true;
        } elseif ($alignment === 'sub') {
            $font['subScript'] = true;
        }

        if (($lang = trim($element->getAttribute('lang'))) !== '') {
            $font['lang'] = self::locale($lang);
        }

        if (strtolower($element->getAttribute('dir')) === 'rtl') {
            $font['rtl'] = true;
        }

        return $font;
    }

    /**
     * The typeface a CSS font stack names, as one name Word can look up - Word has no
     * fallback list. A monospaced stack is Consolas, which every Office has; any other is its
     * first face that is not a browser's generic or system name, and a serif stack with none
     * is Times New Roman.
     */
    private function fontName(string $stack): ?string
    {
        $generics = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'inherit', 'initial', 'blinkmacsystemfont'];
        $families = array_values(array_filter(array_map(
            static fn (string $family): string => trim($family, " \t\"'"),
            explode(',', $stack),
        )));
        $lower = array_map('strtolower', $families);

        if (in_array('monospace', $lower, strict: true) || in_array('ui-monospace', $lower, strict: true)) {
            return self::MONOSPACE;
        }

        foreach ($families as $index => $family) {
            if (! in_array($lower[$index], $generics, strict: true) && ! str_starts_with($family, '-')) {
                return $family;
            }
        }

        return (in_array('serif', $lower, strict: true) || in_array('ui-serif', $lower, strict: true)) ? 'Times New Roman' : null;
    }

    /**
     * The paragraph an element asks for, on top of the one around it: its alignment, its
     * line height, its indent and its direction.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function paragraphStyle(?DOMElement $element, array $context): array
    {
        [$style, $margin] = $this->ownParagraph($element, $context);
        $indent = (int) $context['indent'] + $margin;

        if ($indent > 0) {
            $style['indentation'] = ['left' => $indent];
        }

        return $style;
    }

    /**
     * What the blocks inside a container inherit from it: its alignment, line height and
     * direction for their paragraphs, its indent added to theirs.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function within(DOMElement $element, array $context): array
    {
        [$style, $margin] = $this->ownParagraph($element, $context);

        return [...$context, 'paragraph' => $style, 'indent' => (int) $context['indent'] + $margin];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function ownParagraph(?DOMElement $element, array $context): array
    {
        $style = (array) $context['paragraph'];
        $margin = 0;

        if ($element !== null) {
            $declarations = $this->declarations($element);
            $align = strtolower($declarations['text-align'] ?? $element->getAttribute('align'));

            $alignment = match ($align) {
                'center' => Jc::CENTER,
                'right', 'end' => Jc::RIGHT,
                'justify' => Jc::BOTH,
                'left', 'start' => Jc::LEFT,
                default => null,
            };

            if ($alignment !== null) {
                $style['alignment'] = $alignment;
            }

            $lineHeight = trim($declarations['line-height'] ?? '');

            if (is_numeric($lineHeight) && (float) $lineHeight > 0) {
                $style['lineHeight'] = (float) $lineHeight;
            } elseif (preg_match('/^([\d.]+)%$/', $lineHeight, $matches) === 1) {
                $style['lineHeight'] = (float) $matches[1] / 100;
            }

            $length = $this->length($declarations['margin-left'] ?? $declarations['padding-left'] ?? null, (int) $context['width']);

            if (($length !== null) && ($length > 0) && (strtolower($element->tagName) !== 'figure')) {
                $margin = $length;
            }

            if (strtolower($element->getAttribute('dir')) === 'rtl') {
                $style['bidi'] = true;
            }
        }

        return [$style, $margin];
    }

    /**
     * An element's inline style, property by property.
     *
     * @return array<string, string>
     */
    private function declarations(DOMElement $element): array
    {
        $declarations = [];

        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim((string) preg_replace('/\s*!important\s*$/i', '', $value));

            if (($property !== '') && ($value !== '')) {
                $declarations[$property] = $value;
            }
        }

        return $declarations;
    }

    /**
     * A CSS colour as Word writes one - six hex digits - or null for one it cannot hold:
     * a variable, a mix, transparency.
     */
    private function colour(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (($value === '') || ($value === 'transparent') || ($value === 'inherit') || ($value === 'currentcolor')) {
            return null;
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $value, $matches) === 1) {
            return strtoupper(implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($matches[1]))));
        }

        if (preg_match('/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:\s*[,\/]\s*([\d.]+%?))?\s*\)$/', $value, $matches) === 1) {
            $alpha = $matches[4] ?? '1';
            $alpha = str_ends_with($alpha, '%') ? (float) $alpha / 100 : (float) $alpha;

            if ($alpha < 0.5) {
                return null;
            }

            return sprintf('%02X%02X%02X', min(255, (int) $matches[1]), min(255, (int) $matches[2]), min(255, (int) $matches[3]));
        }

        return self::NAMED_COLOURS[$value] ?? null;
    }

    /**
     * A font size in points: from pixels, points, `em` against the size around it, or a
     * percentage of it.
     */
    private function points(?string $value, float $around): ?float
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^([\d.]+)(px|pt|em|rem|%)$/', $value, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];

        $points = match ($matches[2]) {
            'px' => $number * self::POINTS_PER_PIXEL,
            'pt' => $number,
            'em' => $number * $around,
            'rem' => $number * 12,
            '%' => $number * $around / 100,
        };

        return ($points > 0) ? round($points, 1) : null;
    }

    /**
     * A length in twips: from pixels, points, millimetres, centimetres, inches, or a
     * percentage of what there is.
     */
    private function length(?string $value, int $available): ?int
    {
        $value = strtolower(trim((string) $value));

        if (is_numeric($value)) {
            $value .= 'px';
        }

        if (preg_match('/^([\d.]+)(px|pt|mm|cm|in|%|em|rem)$/', $value, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];

        $twips = match ($matches[2]) {
            'px' => $number * self::TWIPS_PER_PIXEL,
            'pt' => $number * 20,
            'mm' => $number * self::TWIPS_PER_MILLIMETRE,
            'cm' => $number * self::TWIPS_PER_MILLIMETRE * 10,
            'in' => $number * 1440,
            '%' => $available * $number / 100,
            'em', 'rem' => $number * 16 * self::TWIPS_PER_PIXEL,
        };

        return (int) round($twips);
    }

    private function hasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], strict: true);
    }

    private function textOfClass(DOMElement $element, string $class): ?string
    {
        foreach ((new DOMXPath($element->ownerDocument ?? new DOMDocument))->query('.//*[@class]', $element) ?: [] as $child) {
            if (($child instanceof DOMElement) && $this->hasClass($child, $class)) {
                $text = trim((string) preg_replace('/\s+/u', ' ', $child->textContent));

                return ($text === '') ? null : $text;
            }
        }

        return null;
    }

    private function twips(float $millimetres): int
    {
        return (int) round($millimetres * self::TWIPS_PER_MILLIMETRE);
    }

    /**
     * A language as Word names one: `de-DE`, `pt-BR`. A bare `en` is American English, the
     * spelling Word itself defaults to; any other bare code is read as its own country's.
     */
    public static function locale(string $language): string
    {
        $language = str_replace('_', '-', trim($language));

        if ($language === '') {
            return 'en-US';
        }

        if (strtolower($language) === 'en') {
            return 'en-US';
        }

        if (preg_match('/^([a-z]{2,3})-([a-z]{2})$/i', $language, $matches) === 1) {
            return strtolower($matches[1]).'-'.strtoupper($matches[2]);
        }

        if (preg_match('/^[a-z]{2}$/i', $language) === 1) {
            return strtolower($language).'-'.strtoupper($language);
        }

        return 'en-US';
    }
}
