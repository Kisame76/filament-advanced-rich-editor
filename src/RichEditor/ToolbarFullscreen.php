<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasLabel;
use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

use function Filament\Support\generate_icon_html;

use Illuminate\Support\Js;

/**
 * Expands the editor to fill the window.
 *
 * Deliberately not the browser's own Fullscreen API: that promotes one element to the top
 * layer, and Filament renders its modals - the file upload among them - at the end of the
 * body, so they would be invisible while the editor was fullscreen. A fixed overlay keeps
 * everything in the same stacking context, one level below Filament's modals.
 *
 * Not a `RichEditorTool` either, because a tool's active state can only ask the document
 * what it contains, and being fullscreen is a property of the view.
 */
class ToolbarFullscreen extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasLabel;
    use HasName;

    protected string $evaluationIdentifier = 'toolbarFullscreen';

    protected string $viewIdentifier = 'toolbarFullscreen';

    final public function __construct(string $name = 'fullscreen')
    {
        $this->name($name);
    }

    public static function make(string $name = 'fullscreen'): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function toEmbeddedHtml(): string
    {
        $enterLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.fullscreen.enter');
        $exitLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.fullscreen.exit');

        $enterIcon = generate_icon_html(Icons::get('fullscreen_enter'))->toHtml();
        $exitIcon = generate_icon_html(Icons::get('fullscreen_exit'))->toHtml();

        // `$root` is the editor's own Alpine component, so the class lands on the input
        // wrapper that frames the whole field rather than on some ancestor of the page.
        $xData = <<<'JS'
            {
                fullscreen: false,
                field() {
                    return $root.closest('.fi-fo-rich-editor')
                },
                apply() {
                    this.field()?.classList.toggle('fi-arte-fullscreen', this.fullscreen)
                    document.body.classList.toggle('fi-arte-fullscreen-lock', this.fullscreen)
                },
                toggle() {
                    this.fullscreen = ! this.fullscreen
                    this.apply()

                    $getEditor()?.commands.focus()
                },
                exit() {
                    if (! this.fullscreen) {
                        return
                    }

                    this.fullscreen = false
                    this.apply()
                },
                destroy() {
                    // A Livewire re-render must not leave the page scroll locked.
                    this.exit()
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'type' => 'button',
                'tabindex' => -1,
                'x-data' => $xData,
                'x-on:click' => 'toggle()',
                'x-on:keydown.escape.window' => 'exit()',
                'x-bind:aria-pressed' => 'fullscreen',
                'x-bind:aria-label' => 'fullscreen ? '.Js::from($exitLabel)->toHtml().' : '.Js::from($enterLabel)->toHtml(),
                'x-tooltip' => '{ content: fullscreen ? '.Js::from($exitLabel)->toHtml().' : '.Js::from($enterLabel)->toHtml().', theme: $store.theme }',
                'x-bind:class' => '{ \'fi-active\': fullscreen }',
            ], escape: false)
            ->class(['fi-fo-rich-editor-tool', 'fi-arte-fullscreen-toggle']);

        ob_start(); ?>

        <button <?= $attributes->toHtml() ?>>
            <span x-show="! fullscreen"><?= $enterIcon ?></span>
            <span x-show="fullscreen" x-cloak><?= $exitIcon ?></span>
        </button>

        <?php return ob_get_clean();
    }
}
