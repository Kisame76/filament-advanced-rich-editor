<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * The field, counting how often it measures a document for the counter under it.
 */
class MeasuringEditor extends AdvancedRichEditor
{
    public static int $measured = 0;

    public function measureCharacterCount(mixed $content): array
    {
        static::$measured++;

        return parent::measureCharacterCount($content);
    }
}
