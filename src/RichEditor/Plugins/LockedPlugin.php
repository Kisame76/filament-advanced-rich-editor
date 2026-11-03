<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Locked;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\Concerns\BuildsToggles;
use Tiptap\Core\Extension;

/**
 * Parts of a document the people writing in it cannot change.
 *
 * The node is declared on every field that has the feature, whoever is looking, because a
 * field that stopped declaring it would unwrap every locked part on the next save. The button
 * that locks and unlocks is only there for somebody who may - see
 * `AdvancedRichEditor::lockedRegionsEditable()`; for everybody else the script guards the
 * parts and the save is checked again on the server. See `locked.js` and `LockedRegions`.
 */
class LockedPlugin implements RichContentPlugin
{
    use BuildsToggles;

    protected bool $editable = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public function editable(bool $editable = true): static
    {
        $this->editable = $editable;

        return $this;
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [app(Locked::class)];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/locked', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return $this->editable
            ? [$this->toggleTool('lock', 'lock', 'toggleLock', "\$getEditor()?.isActive('locked') === true")]
            : [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
