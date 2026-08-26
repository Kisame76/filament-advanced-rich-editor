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
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;

/**
 * What a list is told about itself, as a panel that only exists while the caret is in one.
 *
 * It lives in a floating toolbar rather than on the bar, and that is the whole reason the
 * feature is affordable: these are three controls that mean nothing anywhere else in a
 * document, and a bar already carrying five dropdowns has no room to say so permanently.
 * Filament shows a floating toolbar while `editor.isActive(<its key>)`, so a bubble keyed
 * `bulletList` appears when the caret enters one and takes itself away again on the way
 * out.
 *
 * One instance per kind of list rather than one that asks which it is in. The two kinds
 * genuinely differ - an ordered list counts and a bullet list does not - so a panel that
 * covered both would spend half its markup hiding the half that did not apply, and the
 * bubble it sits in already knows which one it was opened for.
 */
class ToolbarListPanel extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasLabel;
    use HasName;
    use OpensAwayFromTheEdge;

    protected string $listType = 'bulletList';

    protected string $evaluationIdentifier = 'toolbarListPanel';

    protected string $viewIdentifier = 'toolbarListPanel';

    final public function __construct(string $name, string $listType)
    {
        $this->name($name);
        $this->listType = $listType;
    }

    public static function make(string $name = 'bulletListProperties', string $listType = 'bulletList'): static
    {
        $static = app(static::class, ['name' => $name, 'listType' => $listType]);
        $static->configure();

        return $static;
    }

    public static function bullet(): static
    {
        return static::make('bulletListProperties', 'bulletList')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.bullet'));
    }

    public static function ordered(): static
    {
        return static::make('orderedListProperties', 'orderedList')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.ordered'));
    }

    public function getListType(): string
    {
        return $this->listType;
    }

    public function isOrdered(): bool
    {
        return $this->listType === 'orderedList';
    }

    /**
     * The markers this kind of list is offered, each with the label the button reads.
     *
     * Offered rather than valid: the one a browser already draws unasked is left out, or
     * the panel would carry a button beside Default that draws exactly what Default draws.
     *
     * The ordered ones are labelled by an example rather than by a name: "a, b, c" says
     * what the choice does and "Lower alpha" says what somebody called it.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getMarkers(): array
    {
        $values = ListProperties::offered($this->getListType());
        $group = $this->isOrdered() ? 'ordered' : 'bullet';

        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'label' => (string) __(
                    "filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.markers.{$group}.{$value}",
                ),
            ],
            $values,
        );
    }

    public function toEmbeddedHtml(): string
    {
        $label = (string) $this->getLabel();
        $markerLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.marker');
        $defaultLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.marker_default');
        $startLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.start');
        $reversedLabel = __('filament-advanced-rich-editor::advanced-rich-editor.tools.list_properties.reversed');

        $listType = Js::from($this->getListType())->toHtml();
        $implied = Js::from(ListProperties::IMPLIED[$this->getListType()] ?? null)->toHtml();
        $maxStart = ListProperties::MAX_START;

        // Counting is the ordered list's half of the panel, and a bullet list gets neither
        // the markup for it nor the state behind it: a component carrying a `commitStart()`
        // it can never call is one that says it does something it does not.
        $counting = $this->isOrdered()
            ? <<<'JS'
                start: 1,
                reversed: false,
                commitStart() {
                    $getEditor()?.chain().focus().setListStart(this.start).run()

                    this.read()
                },
                toggleReversed() {
                    $getEditor()?.chain().focus().toggleListReversed().run()

                    this.read()
                },
                JS
            : '';

        $readCounting = $this->isOrdered()
            ? 'this.start = attributes.start ?? 1
                    this.reversed = attributes.reversed === true'
            : '';

        // `read()` runs when the panel opens rather than on every tick, for the reason the
        // image panel gives: the tick fires on every transaction, and re-reading while
        // somebody is typing a start number would overwrite it mid-keystroke.
        $xData = <<<JS
            {
                {$this->menuPositioning()}
                open: false,
                marker: null,
                {$counting}
                attributes() {
                    return \$getEditor()?.getAttributes({$listType}) ?? {}
                },
                read() {
                    const attributes = this.attributes()
                    const type = attributes.type ?? null

                    // A list already carrying the marker a browser draws unasked is a list
                    // sitting on Default, and that is the button that should light up - the
                    // panel does not offer a second one that draws the same thing.
                    this.marker = type === {$implied} ? null : type
                    {$readCounting}
                },
                setMarker(value) {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    // Picking the marker a list already draws puts it back to the one a
                    // browser draws unasked, which is the same rule the headings dropdown
                    // follows for a heading level.
                    const next = this.marker === value ? null : value

                    editor.chain().focus()[next === null ? 'unsetListType' : 'setListType'](next).run()

                    this.marker = next
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-data' => $xData,
                'x-effect' => 'editorUpdatedAt && ! open && read()',
                'x-on:click.outside' => 'open = false',
                'x-on:keydown.escape.prevent.stop' => 'open = false',
            ], escape: false)
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-arte-list-panel']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <button
                type="button"
                tabindex="-1"
                aria-haspopup="true"
                x-bind:aria-expanded="open"
                aria-label="<?= e($label) ?>"
                x-tooltip="{ content: <?= Js::from($label)->toHtml() ?>, theme: $store.theme }"
                x-ref="trigger"
                x-on:click="open = ! open; open && (read(), positionMenu())"
                class="fi-fo-rich-editor-tool"
            >
                <?= generate_icon_html(Icons::get($this->isOrdered() ? 'list_ordered' : 'list_bullet'))->toHtml() ?>
            </button>

            <div
                x-show="open"
                x-cloak
                x-ref="menu"
                x-bind:class="{ [menuUpClass]: dropUp }"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-list-panel-menu"
            >
                <p class="fi-arte-list-panel-label"><?= e($markerLabel) ?></p>

                <div class="fi-arte-list-panel-markers" role="group" aria-label="<?= e($markerLabel) ?>">
                    <button
                        type="button"
                        tabindex="-1"
                        x-on:click="setMarker(null)"
                        x-bind:aria-pressed="marker === null"
                        x-bind:class="{ 'fi-active': marker === null }"
                        title="<?= e($defaultLabel) ?>"
                        class="fi-arte-list-panel-marker"
                    ><?= e($defaultLabel) ?></button>

                    <?php foreach ($this->getMarkers() as $marker) { ?>
                        <?php $value = Js::from($marker['value'])->toHtml(); ?>

                        <button
                            type="button"
                            tabindex="-1"
                            x-on:click="setMarker(<?= $value ?>)"
                            x-bind:aria-pressed="marker === <?= $value ?>"
                            x-bind:class="{ 'fi-active': marker === <?= $value ?> }"
                            title="<?= e($marker['label']) ?>"
                            class="fi-arte-list-panel-marker"
                        ><?= e($marker['label']) ?></button>
                    <?php } ?>
                </div>

                <?php if ($this->isOrdered()) { ?>
                    <label class="fi-arte-list-panel-field">
                        <span class="fi-arte-list-panel-label"><?= e($startLabel) ?></span>

                        <input
                            type="number"
                            min="1"
                            max="<?= $maxStart ?>"
                            inputmode="numeric"
                            x-model.number="start"
                            x-on:change="commitStart()"
                            x-on:blur="commitStart()"
                            x-on:keydown.enter.prevent.stop="commitStart(); open = false"
                            class="fi-arte-list-panel-input"
                        />
                    </label>

                    <label class="fi-arte-list-panel-check">
                        <input
                            type="checkbox"
                            x-bind:checked="reversed"
                            x-on:change="toggleReversed()"
                        />

                        <span><?= e($reversedLabel) ?></span>
                    </label>
                <?php } ?>
            </div>
        </div>

        <?php return ob_get_clean();
    }
}
