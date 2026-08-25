<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;

use function Filament\Support\generate_icon_html;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Tiptap\Core\Extension;

/**
 * The grip in the margin, and the plus beside it.
 *
 * No PHP extension, no mark and no tool. Rearranging a document changes the order of what
 * is in it and nothing else: by the time it is saved there is no trace of how it got that
 * way, so there is nothing to parse, nothing to sanitise and nothing to render on the way
 * back out.
 *
 * What crosses over is what the browser cannot produce: the two icons, drawn through the
 * icon registry so a project can swap them, and the two labels, which are what a screen
 * reader is given. They travel on the editor element rather than through a button's
 * handler, because there is no button - the controls are built in the browser, for a block
 * that only exists once somebody hovers it.
 */
class DragHandlePlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSettings(bool $insert): array
    {
        $line = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.drag_handle.{$key}");

        return [
            'insert' => $insert,
            'labels' => [
                'drag' => $line('drag'),
                'insert' => $line('insert'),
            ],
            'icons' => [
                'grip' => generate_icon_html(Icons::get('drag_handle'))?->toHtml() ?? '',
                'insert' => generate_icon_html(Icons::get('drag_handle_insert'))?->toHtml() ?? '',
            ],
        ];
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/drag-handle', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
