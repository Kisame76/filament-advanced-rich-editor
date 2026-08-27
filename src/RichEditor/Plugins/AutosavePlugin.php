<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * A draft in the browser, for the moment the server loses one.
 *
 * No PHP extension and no tool, because none of this is content: a draft never reaches the
 * application, and a document that was restored from one is an ordinary document by the
 * time it is submitted. The whole feature lives and dies in the browser.
 *
 * What crosses over is what the browser cannot work out. The key is the important one: a
 * draft has to be found again by the same field, on the same record, and by no other -
 * and to Livewire every request looks like the same endpoint, so the browser cannot tell
 * one form from another on its own. PHP knows the record and the field; the browser adds
 * the path of the page it is on, which PHP does not reliably know. Neither half is enough.
 */
class AutosavePlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function getSettings(array $settings): array
    {
        $line = static fn (string $key): string => __("filament-advanced-rich-editor::advanced-rich-editor.autosave.{$key}");

        return [
            ...$settings,
            'labels' => [
                // One sentence with the time in it, rather than a phrase and a date stuck
                // together: where the time sits in it is a question about the language.
                'found' => $line('found'),
                'restore' => $line('restore'),
                'discard' => $line('discard'),
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
            FilamentAsset::getScriptSrc('advanced-rich-editor/autosave', 'kisame76/filament-advanced-rich-editor'),
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
