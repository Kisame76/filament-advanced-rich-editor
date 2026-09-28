<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\StatisticsAction;

/**
 * A project that decided its own answer to what a number looks like.
 *
 * `Numbers` is the package's answer and not the last word, so the dialog reaches it through
 * an overridable `number()` rather than naming it at each of the four places it writes one.
 * This is the subclass that proves the seam is there, and the only caller in the suite that
 * may reach `rowsFor()` directly - being a subclass is exactly who that method is for.
 */
final class RomanStatisticsAction extends StatisticsAction
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function rows(AdvancedRichEditor $component): array
    {
        return self::rowsFor($component);
    }

    protected static function number(int $value): string
    {
        return "[{$value}]";
    }
}
