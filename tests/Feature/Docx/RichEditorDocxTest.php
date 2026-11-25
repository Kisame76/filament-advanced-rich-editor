<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Kisame76\FilamentAdvancedRichEditor\Docx\DocxWriter;
use Kisame76\FilamentAdvancedRichEditor\Docx\MissingDocxLibrary;
use Kisame76\FilamentAdvancedRichEditor\Docx\RichEditorDocx;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use PhpOffice\PhpWord\Settings;

/**
 * Stored content as a Word file: the same document a PDF is printed from, walked element by
 * element into PHPWord. Read back here as the XML Word reads - `word/document.xml` and the
 * parts beside it - since a byte comparison of a zip says nothing.
 */
beforeEach(function (): void {
    requires('phpoffice/phpword');

    Carbon::setTestNow('2026-11-25 10:00:00');

    config()->set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => 'https://site.test/storage', 'visibility' => 'public']);
    Storage::fake('public');
    Storage::disk('public')->put('pictures/harbour.png', (string) file_get_contents(__DIR__.'/../../Fixtures/media/harbour.png'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * One part of the Word file, as text.
 */
function docxPart(string $docx, string $part): ?string
{
    $path = tempnam(sys_get_temp_dir(), 'arte-docx-test-');
    file_put_contents($path, $docx);

    $zip = new ZipArchive;
    $zip->open($path);
    $content = $zip->getFromName($part);
    $zip->close();
    unlink($path);

    return ($content === false) ? null : $content;
}

/**
 * @return array<int, string>
 */
function docxParts(string $docx): array
{
    $path = tempnam(sys_get_temp_dir(), 'arte-docx-test-');
    file_put_contents($path, $docx);

    $zip = new ZipArchive;
    $zip->open($path);
    $names = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $names[] = (string) $zip->getNameIndex($index);
    }

    $zip->close();
    unlink($path);

    return $names;
}

/**
 * A part of the Word file, ready to be asked with the `w:` and `r:` prefixes Word uses.
 */
function docxXml(string $docx, string $part = 'word/document.xml'): DOMXPath
{
    $document = new DOMDocument;
    $document->loadXML((string) docxPart($docx, $part));

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');

    return $xpath;
}

/**
 * The paragraph a piece of text stands in.
 */
function docxParagraph(DOMXPath $xml, string $text): ?DOMElement
{
    $node = $xml->query("//w:p[.//w:t[contains(., '{$text}')]]")->item(0);

    return ($node instanceof DOMElement) ? $node : null;
}

/**
 * The run a piece of text stands in.
 */
function docxRun(DOMXPath $xml, string $text): ?DOMElement
{
    $node = $xml->query("//w:r[w:t[contains(., '{$text}')]]")->item(0);

    return ($node instanceof DOMElement) ? $node : null;
}

function docxValue(DOMXPath $xml, string $expression, DOMNode $context): ?string
{
    $node = $xml->query($expression, $context)->item(0);

    return ($node instanceof DOMElement) ? $node->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') : null;
}

describe('the file', function (): void {
    it('is a Word document with the title, the language and the sheet it was given', function (): void {
        $docx = RichEditorDocx::make('<p>Hello</p>')->title('Quartalsbericht')->language('de')->toString();
        $xml = docxXml($docx);
        $size = $xml->query('//w:sectPr/w:pgSz')->item(0);
        $margins = $xml->query('//w:sectPr/w:pgMar')->item(0);

        assert($size instanceof DOMElement && $margins instanceof DOMElement);

        expect(substr($docx, 0, 2))->toBe('PK')
            ->and(docxPart($docx, 'docProps/core.xml'))->toContain('<dc:title>Quartalsbericht</dc:title>')
            ->and(docxPart($docx, 'word/settings.xml'))->toContain('w:val="de-DE"')
            // A4 with the 20 mm margins of the configuration, in twentieths of a point.
            ->and($size->getAttribute('w:w'))->toBe('11906')
            ->and($size->getAttribute('w:h'))->toBe('16838')
            ->and($margins->getAttribute('w:top'))->toBe('1134')
            ->and($margins->getAttribute('w:left'))->toBe('1134');
    });

    it('turns the sheet for landscape', function (): void {
        $size = docxXml(RichEditorDocx::make('<p>x</p>')->page('letter')->landscape()->toString())->query('//w:sectPr/w:pgSz')->item(0);

        assert($size instanceof DOMElement);

        expect($size->getAttribute('w:orient'))->toBe('landscape')
            ->and((int) $size->getAttribute('w:w'))->toBeGreaterThan((int) $size->getAttribute('w:h'));
    });

    it('downloads as a .docx under the title', function (): void {
        $response = RichEditorDocx::make('<p>x</p>')->title('Bericht Q3')->download();

        expect($response->headers->get('Content-Type'))->toBe(DocxWriter::CONTENT_TYPE)
            ->and($response->headers->get('Content-Disposition'))->toContain('Bericht Q3.docx');
    });

    it('escapes the text, which is whatever somebody typed, and leaves PHPWord as it found it', function (): void {
        $escaping = Settings::isOutputEscapingEnabled();
        $font = Settings::getDefaultFontName();

        $docx = RichEditorDocx::make('<p>Tom &amp; Jerry &lt;w:p&gt;</p>')->title('A & B')->toString();

        expect(docxPart($docx, 'word/document.xml'))->toContain('Tom &amp; Jerry &lt;w:p&gt;')
            ->and(docxXml($docx)->query('//w:t[contains(., "Tom & Jerry <w:p>")]')->length)->toBe(1)
            ->and(Settings::isOutputEscapingEnabled())->toBe($escaping)
            ->and(Settings::getDefaultFontName())->toBe($font);
    });

    it('says what to install where PHPWord is missing', function (): void {
        $docx = new class(AdvancedRichContentRendererForDocx()) extends RichEditorDocx
        {
            public static function isInstalled(): bool
            {
                return false;
            }
        };

        $docx->toString();
    })->throws(MissingDocxLibrary::class, 'composer require phpoffice/phpword');
});

function AdvancedRichContentRendererForDocx(): AdvancedRichContentRenderer
{
    return AdvancedRichContentRenderer::make('<p>x</p>');
}

describe('text', function (): void {
    it('keeps the marks, the colours, the sizes and the typefaces', function (): void {
        $xml = docxXml(RichEditorDocx::make(
            '<p><strong>bold</strong> <em>italic</em> <u>under</u> <s>struck</s> H<sub>2</sub>O mc<sup>2</sup> <code>code</code> '
            .'<span class="color" data-color="#ef4444">red</span> <mark data-color="#fef08a" style="background-color: #fef08a;">marked</mark> '
            .'<span style="font-size: 24px;">large</span> <span style="font-family: Georgia;">serif</span></p>',
        )->toString());

        expect($xml->query('w:rPr/w:b', docxRun($xml, 'bold'))->length)->toBe(1)
            ->and($xml->query('w:rPr/w:i', docxRun($xml, 'italic'))->length)->toBe(1)
            ->and(docxValue($xml, 'w:rPr/w:u', docxRun($xml, 'under')))->toBe('single')
            ->and($xml->query('w:rPr/w:strike', docxRun($xml, 'struck'))->length)->toBe(1)
            ->and(docxValue($xml, 'w:rPr/w:vertAlign', docxRun($xml, '2')))->toBe('subscript')
            ->and(docxValue($xml, 'w:rPr/w:rFonts/@w:ascii/..', docxRun($xml, 'code')) ?? '')->toBe('')
            ->and($xml->query('w:rPr/w:rFonts[@w:ascii="Consolas"]', docxRun($xml, 'code'))->length)->toBe(1)
            // Filament's colour rides in a custom property no file format reads.
            ->and(docxValue($xml, 'w:rPr/w:color', docxRun($xml, 'red')))->toBe('EF4444')
            ->and($xml->query('w:rPr/w:shd[@w:fill="FEF08A"]', docxRun($xml, 'marked'))->length)->toBe(1)
            // 24 px are 18 pt, in half points.
            ->and(docxValue($xml, 'w:rPr/w:sz', docxRun($xml, 'large')))->toBe('36')
            ->and($xml->query('w:rPr/w:rFonts[@w:ascii="Georgia"]', docxRun($xml, 'serif'))->length)->toBe(1);
    });

    it('names one typeface Word can look up for a CSS font stack', function (): void {
        $xml = docxXml(RichEditorDocx::make(
            '<p><span style=\'font-family: ui-monospace, "SF Mono", monospace;\'>mono</span> '
            .'<span style=\'font-family: -apple-system, "Helvetica Neue", sans-serif;\'>sans</span> '
            .'<span style="font-family: ui-serif, serif;">plain serif</span></p>',
        )->toString());

        expect($xml->query('w:rPr/w:rFonts[@w:ascii="Consolas"]', docxRun($xml, 'mono'))->length)->toBe(1)
            ->and($xml->query('w:rPr/w:rFonts[@w:ascii="Helvetica Neue"]', docxRun($xml, 'sans'))->length)->toBe(1)
            ->and($xml->query('w:rPr/w:rFonts[@w:ascii="Times New Roman"]', docxRun($xml, 'plain serif'))->length)->toBe(1);
    });

    it('aligns, spaces and turns paragraphs as they were', function (): void {
        $xml = docxXml(RichEditorDocx::make(
            '<p style="text-align: center">Centred</p><p style="text-align: right">Right</p>'
            .'<p style="text-align: justify; line-height: 2">Justified</p><p dir="rtl">مرحبا</p>',
        )->toString());

        expect(docxValue($xml, 'w:pPr/w:jc', docxParagraph($xml, 'Centred')))->toBe('center')
            ->and(docxValue($xml, 'w:pPr/w:jc', docxParagraph($xml, 'Right')))->toBe('right')
            ->and(docxValue($xml, 'w:pPr/w:jc', docxParagraph($xml, 'Justified')))->toBe('both')
            ->and($xml->query('w:pPr/w:spacing[@w:line="480"]', docxParagraph($xml, 'Justified'))->length)->toBe(1)
            ->and($xml->query('w:pPr/w:bidi', docxParagraph($xml, 'مرحبا'))->length)->toBe(1);
    });

    it('links out, and to a heading inside the document', function (): void {
        $docx = RichEditorDocx::make('<h2>Introduction</h2><p>See <a href="https://example.com/terms">the terms</a> and the <a href="#introduction">introduction</a>.</p>')
            ->renderer(fn ($renderer) => $renderer->anchorHeadings([2]))
            ->toString();
        $xml = docxXml($docx);

        $external = $xml->query('//w:hyperlink[.//w:t[contains(., "the terms")]]')->item(0);
        $internal = $xml->query('//w:hyperlink[.//w:t[contains(., "introduction")]]')->item(0);

        assert($external instanceof DOMElement && $internal instanceof DOMElement);

        $bookmark = $internal->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'anchor');

        expect(docxPart($docx, 'word/_rels/document.xml.rels'))->toContain('Target="https://example.com/terms"')
            ->and($bookmark)->toStartWith('arte_')
            ->and($xml->query("//w:bookmarkStart[@w:name='{$bookmark}']")->length)->toBe(1);
    });
});

describe('blocks', function (): void {
    it('writes headings as Word headings, for the navigation pane', function (): void {
        $xml = docxXml(RichEditorDocx::make('<h2>Chapter</h2><h3>Section</h3><p>Text</p>')->toString());

        expect(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Chapter')))->toBe('Heading2')
            ->and(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Section')))->toBe('Heading3');
    });

    it('numbers each list on its own, nested lists a level deeper, in the marker and from the number given', function (): void {
        $docx = RichEditorDocx::make(
            '<ul><li><p>Bullet</p><ul><li><p>Nested</p></li></ul></li></ul>'
            .'<ol><li><p>First</p></li><li><p>Second</p></li></ol>'
            .'<ol start="3" style="list-style-type: lower-alpha;"><li><p>Third letter</p></li></ol>',
        )->renderer(fn ($renderer) => $renderer)->toString();
        $xml = docxXml($docx);
        $numbering = docxXml($docx, 'word/numbering.xml');

        $format = static function (string $text) use ($xml, $numbering): array {
            $paragraph = docxParagraph($xml, $text);
            $id = docxValue($xml, 'w:pPr/w:numPr/w:numId', $paragraph);
            $level = docxValue($xml, 'w:pPr/w:numPr/w:ilvl', $paragraph);
            $abstract = docxValue($numbering, "//w:num[@w:numId='{$id}']/w:abstractNumId", $numbering->document);
            $definition = $numbering->query("//w:abstractNum[@w:abstractNumId='{$abstract}']/w:lvl[@w:ilvl='{$level}']")->item(0);

            assert($definition instanceof DOMElement);

            return [
                'id' => $id,
                'level' => $level,
                'format' => docxValue($numbering, 'w:numFmt', $definition),
                'start' => docxValue($numbering, 'w:start', $definition),
            ];
        };

        expect($format('Bullet'))->toMatchArray(['level' => '0', 'format' => 'bullet'])
            ->and($format('Nested'))->toMatchArray(['level' => '1', 'format' => 'bullet'])
            ->and($format('First'))->toMatchArray(['level' => '0', 'format' => 'decimal', 'start' => '1'])
            ->and($format('Second')['id'])->toBe($format('First')['id'])
            ->and($format('Third letter'))->toMatchArray(['format' => 'lowerLetter', 'start' => '3'])
            ->and($format('Third letter')['id'])->not->toBe($format('First')['id']);
    });

    it('writes a task list as its boxes, without a bullet in front of them as well', function (): void {
        $xml = docxXml(RichEditorDocx::make('<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>Done</p></li><li data-type="taskItem" data-checked="false"><p>Open</p></li></ul>')->toString());

        expect($xml->query('//w:t[contains(., "☑")]')->length)->toBe(1)
            ->and($xml->query('//w:t[contains(., "☐")]')->length)->toBe(1)
            ->and($xml->query('w:pPr/w:numPr', docxParagraph($xml, 'Done'))->length)->toBe(0);
    });

    it('merges cells, sizes the columns, and repeats the header row on every page', function (): void {
        $xml = docxXml(RichEditorDocx::make(
            '<table><tbody>'
            .'<tr><th colspan="2" data-colwidth="200,0"><p>Head</p></th></tr>'
            .'<tr><td rowspan="2" data-colwidth="200"><p>Merged</p></td><td><p>B</p></td></tr>'
            .'<tr><td><p>C</p></td></tr>'
            .'</tbody></table>',
        )->toString());

        $rows = $xml->query('//w:tbl/w:tr');
        $head = $xml->query('//w:tc[.//w:t[.="Head"]]')->item(0);
        $merged = $xml->query('//w:tc[.//w:t[.="Merged"]]')->item(0);
        $grid = $xml->query('//w:tbl/w:tblGrid/w:gridCol');

        assert($head instanceof DOMElement && $merged instanceof DOMElement);

        expect($rows->length)->toBe(3)
            ->and($xml->query('w:trPr/w:tblHeader', $rows->item(0))->length)->toBe(1)
            ->and(docxValue($xml, 'w:tcPr/w:gridSpan', $head))->toBe('2')
            ->and($xml->query('w:tcPr/w:shd[@w:fill="F4F4F5"]', $head)->length)->toBe(1)
            ->and(docxValue($xml, 'w:tcPr/w:vMerge', $merged))->toBe('restart')
            // The third row begins with the merged cell's continuation, then its own.
            ->and($xml->query('w:tc', $rows->item(2))->length)->toBe(2)
            ->and(docxValue($xml, 'w:tc[1]/w:tcPr/w:vMerge', $rows->item(2)))->toBe('continue')
            // 200 px are 3000 twips; the other column takes what is left of the line.
            ->and($grid->item(0)?->attributes?->getNamedItemNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w')?->nodeValue)->toBe('3000');
    });

    it('sets a callout, a quotation and code apart in a box, every space of the code kept', function (): void {
        $xml = docxXml(RichEditorDocx::make(
            '<div class="fi-arte-callout fi-arte-callout-warning" data-type="callout"><p>Careful</p></div>'
            ."<blockquote><p>Quoted</p></blockquote><pre><code>if (x) {\n    y();\n}</code></pre>",
        )->toString());

        $callout = $xml->query('//w:tc[.//w:t[.="Careful"]]/w:tcPr')->item(0);

        assert($callout instanceof DOMElement);

        expect($xml->query('w:shd[@w:fill="FEFCE8"]', $callout)->length)->toBe(1)
            ->and($xml->query('w:tcBorders/w:left[@w:color="CA8A04"]', $callout)->length)->toBe(1)
            ->and($xml->query('//w:tc[.//w:t[.="Quoted"]]/w:tcPr/w:tcBorders/w:left')->length)->toBe(1)
            ->and($xml->query('//w:t[.="    y();"]')->length)->toBe(1)
            ->and($xml->query('w:rPr/w:rFonts[@w:ascii="Consolas"]', docxRun($xml, 'y();'))->length)->toBe(1);
    });

    it('embeds a picture from the bytes the export already read, and leaves an SVG\'s description', function (): void {
        $docx = RichEditorDocx::make(
            '<p><img src="https://site.test/storage/pictures/harbour.png" alt="Harbour"></p>'
            .'<p><img src="https://elsewhere.test/logo.svg" alt="Logo"></p>',
        )->toString();
        $xml = docxXml($docx);

        // PHPWord writes a picture as VML, which Word and LibreOffice both read.
        expect($xml->query('//w:pict | //w:drawing')->length)->toBe(1)
            ->and(array_filter(docxParts($docx), static fn (string $part): bool => str_starts_with($part, 'word/media/')))->toHaveCount(1)
            ->and($xml->query('//w:t[.="[Logo]"]')->length)->toBe(1);
    });

    it('starts a new page at a page break', function (): void {
        $xml = docxXml(RichEditorDocx::make('<p>One</p><div data-type="pageBreak"></div><p>Two</p>')->toString());

        expect($xml->query('//w:br[@w:type="page"]')->length)->toBe(1);
    });

    it('writes a file card as its name and size, linked to the file', function (): void {
        $docx = RichEditorDocx::make('<p><a data-type="file" href="https://site.test/storage/report.pdf" download="report.pdf"><span class="fi-arte-file-kind">PDF</span><span class="fi-arte-file-name">report.pdf</span><span class="fi-arte-file-size">88 KB</span></a></p>')->toString();

        expect(docxXml($docx)->query('//w:hyperlink//w:t[.="report.pdf (88 KB)"]')->length)->toBe(1);
    });
});

describe('around the content', function (): void {
    it('prints the header and the footer with Word\'s own page fields, and leaves them off the cover', function (): void {
        $docx = RichEditorDocx::make('<p>Body</p>')
            ->title('Report')
            ->cover()
            ->header('<p>{title}<span style="float: right;">{date}</span></p>')
            ->pageNumbers('Page {page} of {pages}')
            ->toString();

        // The cover's own, empty header and footer come first; the running ones after them.
        $running = static fn (string $kind): string => implode("\n", array_map(
            static fn (string $part): string => (string) docxPart($docx, $part),
            array_filter(docxParts($docx), static fn (string $part): bool => str_starts_with($part, "word/{$kind}")),
        ));
        $footer = $running('footer');
        $header = $running('header');
        $xml = docxXml($docx);

        expect($header)->toContain('Report')->toContain('November 25, 2026')
            ->and($footer)->toContain(' PAGE ')->toContain(' NUMPAGES ')->toContain('Page ')
            ->and($xml->query('//w:sectPr/w:titlePg')->length)->toBe(1)
            ->and($xml->query('//w:sectPr/w:headerReference[@w:type="first"]')->length)->toBe(1)
            // The cover's title is large type, not a heading the table of contents would list.
            ->and(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Report')))->toBeNull()
            ->and($xml->query('//w:br[@w:type="page"]')->length)->toBe(1);
    });

    it('combines documents under their titles, with Word\'s table of contents over the parts', function (): void {
        $docx = RichEditorDocx::combine([
            RichEditorDocx::make('<h2>Inside one</h2><p>One</p>')->title('Part one'),
            RichEditorDocx::make('<p>Two</p>')->title('Part two'),
        ])->tableOfContents('Contents')->toString();
        $xml = docxXml($docx);

        expect(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Part one')))->toBe('Heading1')
            ->and(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Part two')))->toBe('Heading1')
            // A part's own headings sit one level under its title.
            ->and(docxValue($xml, 'w:pPr/w:pStyle', docxParagraph($xml, 'Inside one')))->toBe('Heading3')
            ->and(docxPart($docx, 'word/document.xml'))->toContain('TOC \o 1-1 \h \z \u')
            ->and(docxPart($docx, 'word/settings.xml'))->toContain('w:updateFields')
            // After the table of contents, and before the second part.
            ->and($xml->query('//w:br[@w:type="page"]')->length)->toBe(2);
    });
});
