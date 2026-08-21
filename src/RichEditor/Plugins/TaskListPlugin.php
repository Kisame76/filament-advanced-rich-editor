<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\TaskItem;
use Tiptap\Core\Extension;
use Tiptap\Nodes\TaskList;

class TaskListPlugin implements RichContentPlugin
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
        // The styling hooks are baked into the rendered markup rather than added
        // by a stylesheet selector, because saved task lists are also rendered
        // outside of a form - by `RichContentRenderer`, a text entry, or a front
        // end that has never heard of this package. Both nodes therefore emit the
        // same classes the JS extensions emit, so one stylesheet covers every
        // place the content ends up.
        return [
            app(TaskList::class, ['options' => ['HTMLAttributes' => ['class' => 'fi-arte-task-list']]]),
            app(TaskItem::class, ['options' => ['HTMLAttributes' => ['class' => 'fi-arte-task-item']]]),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        // The second argument is required: `AssetManager::getScriptSrc()` falls
        // back to the `app` package and would throw for assets this package
        // registered under its own name.
        return [
            FilamentAsset::getScriptSrc('advanced-rich-editor/task-list', 'kisame76/filament-advanced-rich-editor'),
            FilamentAsset::getScriptSrc('advanced-rich-editor/task-item', 'kisame76/filament-advanced-rich-editor'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('taskList')
                ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.task_list'))
                ->jsHandler('$getEditor()?.chain().focus().toggleTaskList().run()')
                ->icon(Icons::get('task_list')),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
