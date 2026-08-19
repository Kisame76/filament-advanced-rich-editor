<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

/**
 * A purely decorative separator that can be dropped anywhere into a toolbar
 * array. It extends `ViewComponent` so that Blade can render it with `{{ }}`
 * like every other toolbar item, and so that `extraAttributes()` gets the same
 * closure evaluation Filament components provide.
 */
final class ToolbarDivider extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;

    protected string $evaluationIdentifier = 'toolbarDivider';

    protected string $viewIdentifier = 'toolbarDivider';

    public static function make(): static
    {
        $static = app(self::class);
        $static->configure();

        return $static;
    }

    public function toEmbeddedHtml(): string
    {
        // The class is merged last on purpose: it keeps `class` as the first
        // rendered attribute, so the markup stays stable for snapshot tests
        // and for the `.fi-arte-toolbar-divider` stylesheet.
        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'aria-hidden' => 'true',
            ], escape: false)
            ->class(['fi-arte-toolbar-divider']);

        return '<span '.$attributes->toHtml().'></span>';
    }
}
