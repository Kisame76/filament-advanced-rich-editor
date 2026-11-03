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

    /**
     * What the last measurement was handed, by type: the document the field holds is an
     * array, the markup a save writes is a string.
     */
    public static ?string $measuredFrom = null;

    public function measureCharacterCount(mixed $content): array
    {
        static::$measured++;
        static::$measuredFrom = get_debug_type($content);

        return parent::measureCharacterCount($content);
    }
}
