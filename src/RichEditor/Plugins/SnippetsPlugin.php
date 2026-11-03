<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Snippets;
use Tiptap\Core\Extension;

/**
 * One tool per snippet, which the toolbar's dropdown and the slash menu both offer.
 *
 * No script and no node: a snippet is content the editor already knows how to hold, put in
 * with TipTap's own `insertContent`. What a tool carries is the snippet's key, and the content
 * is asked of the field when the tool is pressed - see `InsertsSnippets::getSnippetForJs()`.
 */
class SnippetsPlugin implements RichContentPlugin
{
    /**
     * @param  array<string, string>  $labels  key => label
     */
    public function __construct(protected array $labels = []) {}

    /**
     * @param  array<string, string>  $labels
     */
    public static function make(array $labels = []): static
    {
        return app(static::class, ['labels' => $labels]);
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
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        $tools = [];

        foreach ($this->labels as $key => $label) {
            $tools[] = RichEditorTool::make(Snippets::toolName($key))
                ->label($label)
                ->icon(Icons::get('snippets'))
                ->jsHandler(static fn (RichEditorTool $tool): string => static::getJsHandler($tool, $key));
        }

        return $tools;
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }

    /**
     * Ask the field for the content, and put it where the caret is if an answer came back -
     * the arrangement the date buttons use, for the same reasons.
     */
    protected static function getJsHandler(RichEditorTool $tool, string $key): string
    {
        $component = Js::from($tool->getEditor()->getKey())->toHtml();
        $argument = Js::from($key)->toHtml();

        return "\$wire.callSchemaComponentMethod({$component}, 'getSnippetForJs', { key: {$argument} })".
            '.then((content) => content != null && content !== \'\' && '.
            '$getEditor()?.chain().focus().insertContent(content).run())';
    }
}
