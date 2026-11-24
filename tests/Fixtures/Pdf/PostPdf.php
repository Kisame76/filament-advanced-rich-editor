<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Pdf;

use Kisame76\FilamentAdvancedRichEditor\Pdf\PdfExport;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Models\RichPost;

/**
 * An export the way a project writes one: a record in, a PDF out, nothing in between that
 * a queue could not carry.
 */
class PostPdf extends PdfExport
{
    public function __construct(
        public RichPost $post,
        public ?string $link = null,
    ) {}

    public function pdf(): RichEditorPdf
    {
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
