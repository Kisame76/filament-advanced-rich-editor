<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Pdf;

use Kisame76\FilamentAdvancedRichEditor\Docx\RichEditorDocx;
use Kisame76\FilamentAdvancedRichEditor\Export\DocumentExport;
use Kisame76\FilamentAdvancedRichEditor\Export\RichEditorExport;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

/**
 * An export the way a project writes one: a record in, a document out, nothing in between
 * that a queue could not carry - as a PDF, or as a Word file where it is asked for one.
 */
class PostExport extends DocumentExport
{
    public function __construct(
        public RichPost $post,
        public ?string $link = null,
        public string $format = 'pdf',
    ) {}

    public function document(): RichEditorExport
    {
        if ($this->format === 'docx') {
            return RichEditorDocx::forRecord($this->post, 'content')->title($this->post->title);
        }

        return RichEditorPdf::forRecord($this->post, 'content')
            ->title($this->post->title)
            ->cover()
            ->driver(new HtmlPdfDriver);
    }

    public function downloadUrl(string $path, string $disk): ?string
    {
        return $this->link ?? parent::downloadUrl($path, $disk);
    }
}
