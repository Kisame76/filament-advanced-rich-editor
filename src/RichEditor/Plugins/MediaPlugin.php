<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\MediaLibraryAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MediaUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Media;
use Tiptap\Core\Extension;

/**
 * A video or a sound from this server, on both sides of the round trip.
 *
 * Not the media library. `SpatieMediaLibraryPlugin` and the media browser are about the
 * pictures already uploaded; this is a block that points at a file, the way the embed
 * points at a video somebody else hosts.
 *
 * The renderer declares the node unconditionally - stored content should keep a video
 * whether or not this render was told about them - so what this adds is the editor half:
 * the script, the two buttons and the dialog behind them.
 *
 * Two tools and one action. `video` and `audio` are the two things a person actually
 * reaches for, and both open the same dialog with the answer already filled in; a single
 * "Media" button would have opened a dialog whose first question is one the person had
 * already decided before they clicked.
 */
class MediaPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(Media::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/media', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return array_map(
            static fn (string $kind): RichEditorTool => RichEditorTool::make($kind)
                ->label(__("filament-advanced-rich-editor::advanced-rich-editor.tools.media.kinds.{$kind}"))
                ->icon(Icons::get("media_{$kind}"))
                // The media browser, opened on the tab this button is about. Not a dialog of
                // its own: a second door for video was the whole complaint, and a person
                // reaching for a film should not have to decide between "the library" and
                // "an address" before knowing which of them holds it.
                ->action(
                    static fn (RichEditorTool $tool): string => MediaLibraryAction::nameFor($tool),
                    arguments: "{ kind: '{$kind}' }",
                )
                ->activeKey('media'),
            MediaUrl::KINDS,
        );
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        // None. The dialog behind both buttons is the media browser, which the field
        // registers for itself - see `AdvancedRichEditor::getDefaultActions()`.
        return [];
    }
}
