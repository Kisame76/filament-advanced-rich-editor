<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

use function Filament\Support\generate_icon_html;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * The switch in the image floating toolbar that frees a resize from the aspect ratio.
 *
 * Not a `RichEditorTool`: a tool's active state is hard-wired to
 * `$getEditor()?.isActive(name)`, and "the ratio is unlocked" is not something the
 * document knows about. The state belongs to the editor - it is a drag-time modifier, not
 * content - so it is kept in the resize extension's storage and only mirrored here.
 *
 * The mirror matters: the bubble menu removes its element from the DOM every time it
 * hides, which wipes any Alpine state declared inside it. Reading the editor's storage on
 * init makes the button come back in the state the user left it in.
 */
class ToolbarImageLock extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasName;

    protected string $evaluationIdentifier = 'toolbarImageLock';

    protected string $viewIdentifier = 'toolbarImageLock';

    final public function __construct(string $name = 'imageAspectRatioLock')
    {
        $this->name($name);
    }

    public static function make(string $name = 'imageAspectRatioLock'): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function toEmbeddedHtml(): string
    {
        $lockedLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_lock.locked');
        $unlockedLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.image_lock.unlocked');

        $lockedIcon = generate_icon_html(Heroicon::LockClosed)->toHtml();
        $unlockedIcon = generate_icon_html(Heroicon::LockOpen)->toHtml();

        $xData = <<<'JS'
            {
                storage() {
                    return $getEditor()?.storage?.arteImageResize
                },
                unlocked: false,
                init() {
                    this.unlocked = this.storage()?.unlocked ?? false
                },
                toggle() {
                    const storage = this.storage()

                    if (! storage) {
                        return
                    }

                    storage.unlocked = ! storage.unlocked
                    this.unlocked = storage.unlocked
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'type' => 'button',
                'tabindex' => -1,
                'x-data' => $xData,
                'x-on:click' => 'toggle()',
                'x-bind:aria-pressed' => 'unlocked ? \'false\' : \'true\'',
                'x-bind:aria-label' => 'unlocked ? '.Js::from($unlockedLabel)->toHtml().' : '.Js::from($lockedLabel)->toHtml(),
                'x-tooltip' => '{ content: unlocked ? '.Js::from($unlockedLabel)->toHtml().' : '.Js::from($lockedLabel)->toHtml().', theme: $store.theme }',
                'x-bind:class' => '{ \'fi-active\': unlocked }',
            ], escape: false)
            ->class(['fi-fo-rich-editor-tool', 'fi-arte-image-lock']);

        ob_start(); ?>

        <button <?= $attributes->toHtml() ?>>
            <span x-show="! unlocked"><?= $lockedIcon ?></span>
            <span x-show="unlocked" x-cloak><?= $unlockedIcon ?></span>
        </button>

        <?php return ob_get_clean();
    }
}
