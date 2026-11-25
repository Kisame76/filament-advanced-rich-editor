<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Docx;

use Kisame76\FilamentAdvancedRichEditor\Export\RichEditorExport;
use PhpOffice\PhpWord\PhpWord;

/**
 * Stored rich content as a Word file, configured exactly as a PDF is:
 *
 *     RichEditorDocx::forRecord($article, 'content')->title($article->title)->pageNumbers()->download();
 *
 * The sheet, the margins, the cover, the header and the footer with `{page}` and `{pages}`,
 * a combined document with Word's own table of contents - everything `RichEditorExport`
 * holds - and `DocxWriter` writes it. A project's `stylesheet()` has no place in a Word file
 * and is a PDF's only.
 *
 * PHPWord is not a dependency: `composer require phpoffice/phpword` where Word files are
 * wanted, and `MissingDocxLibrary` says so where they are asked for without it.
 */
class RichEditorDocx extends RichEditorExport
{
    public function toString(): string
    {
        if (! static::isInstalled()) {
            throw MissingDocxLibrary::make();
        }

        $contents = ($this->tableOfContentsEntries() !== null) ? $this->tableOfContentsTitle() : null;

        return (new DocxWriter($this->getPage(), $this->getLanguage(), $this->title))->write(
            body: $this->toBodyHtml(),
            cover: $this->coverHtml(),
            contents: $contents,
            header: $this->runningElement($this->header),
            footer: $this->runningElement($this->footer),
        );
    }

    public static function isInstalled(): bool
    {
        return class_exists(PhpWord::class);
    }

    protected static function extension(): string
    {
        return 'docx';
    }

    protected static function contentType(): string
    {
        return DocxWriter::CONTENT_TYPE;
    }
}
