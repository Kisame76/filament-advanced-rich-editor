<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns\OpensAwayFromTheEdge;

/**
 * The dropdown that puts a project's own design system in front of an editor.
 *
 * Not a `RichEditorTool`, for the same reason the font and colour pickers are not: a tool
 * is one button with one handler, and this is a list. Living in the editor's own Alpine
 * scope is what lets it read `$getEditor()` and follow the caret.
 *
 * The two scopes are drawn under headings rather than mixed, because a block style and an
 * inline style do different things to different parts of the document, and a reader should
 * be able to tell which is which before clicking rather than after. With only one kind in
 * the list there is nothing to tell apart, and the headings stay away.
 *
 * A block style that cannot apply where the caret is - a style for headings while the caret
 * sits in a paragraph - is drawn dimmed rather than removed. A list that changes length as
 * the caret moves is a list nobody can aim at.
 */
class ToolbarStylePicker extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasName;
    use OpensAwayFromTheEdge;

    /**
     * How many characters of a label the trigger shows before it elides. Same reason as the
     * font picker: a toolbar that grows by the length of somebody's style name wraps.
     */
    protected const TRIGGER_LENGTH = 10;

    /**
     * @var array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    protected array $styles = [];

    protected string $evaluationIdentifier = 'toolbarStylePicker';

    protected string $viewIdentifier = 'toolbarStylePicker';

    final public function __construct(string $name = 'styles')
    {
        $this->name($name);
    }

    public static function make(string $name = 'styles'): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>  $styles
     */
    public function styles(array $styles): static
    {
        $this->styles = array_values($styles);

        return $this;
    }

    /**
     * @return array<int, array{key: string, label: string, class: string, scope: string, types: array<int, string>}>
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * Whether both kinds are in the list, which is the only case where saying which is
     * which is worth the two extra rows.
     */
    protected function hasGroups(): bool
    {
        $scopes = array_unique(array_column($this->getStyles(), 'scope'));

        return count($scopes) > 1;
    }

    public function toEmbeddedHtml(): string
    {
        $styles = $this->getStyles();
        $label = __('filament-advanced-rich-editor::advanced-rich-editor.tools.styles');
        $clear = __('filament-advanced-rich-editor::advanced-rich-editor.tools.styles_clear');
        $groups = [
            'block' => __('filament-advanced-rich-editor::advanced-rich-editor.styles.block'),
            'inline' => __('filament-advanced-rich-editor::advanced-rich-editor.styles.inline'),
        ];

        $xData = <<<JS
            {
                {$this->menuPositioning()}
                open: false,
                block: null,
                inline: null,
                activeTypes: [],
                styles: {$this->js($styles)},
                sync() {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    this.block = editor.getAttributes('paragraph')?.arteStyle
                        ?? editor.getAttributes('heading')?.arteStyle
                        ?? editor.getAttributes('blockquote')?.arteStyle
                        ?? editor.getAttributes('listItem')?.arteStyle
                        ?? editor.getAttributes('codeBlock')?.arteStyle
                        ?? null
                    this.inline = editor.getAttributes('styleClass')?.name ?? null

                    // Which block the caret is in, so that a style meant for headings can
                    // say so while the caret is somewhere else.
                    this.activeTypes = {$this->js(Styles::BLOCK_TYPES)}.filter((type) => editor.isActive(type))
                },
                find(key) {
                    return this.styles.find((entry) => entry.key === key) ?? null
                },
                isActive(key) {
                    const style = this.find(key)

                    if (! style) {
                        return false
                    }

                    return style.scope === 'block' ? this.block === key : this.inline === key
                },
                applies(key) {
                    const style = this.find(key)

                    if (! style) {
                        return false
                    }

                    if (style.scope === 'inline') {
                        return true
                    }

                    return style.types.some((type) => this.activeTypes.includes(type))
                },
                // The inline style wins the trigger where there is one: it is the narrower
                // of the two, so it is the one a reader just did something to.
                //
                // With neither set the trigger names the feature rather than the empty
                // state. A button reading "None" at rest says nothing about what it does,
                // and this one has no icon to fall back on.
                label() {
                    const key = this.inline ?? this.block
                    const style = this.styles.find((entry) => entry.key === key)

                    if (! style) {
                        return {$this->js($label)}
                    }

                    return style.label.length > {$this->trigger()}
                        ? style.label.slice(0, {$this->trigger()}).trimEnd() + '…'
                        : style.label
                },
                apply(key) {
                    const editor = \$getEditor()
                    const style = this.find(key)

                    if (! editor || ! style || ! this.applies(key)) {
                        return
                    }

                    if (style.scope === 'block') {
                        editor.chain().focus().setBlockStyle(key).run()
                    } else {
                        let chain = editor.chain().focus()

                        // Without a selection the run under the caret is meant, which is
                        // what somebody expects when restyling the word they clicked into.
                        if (editor.state.selection.empty) {
                            chain = chain.extendMarkRange('styleClass')
                        }

                        chain.setStyleClass(key).run()
                    }

                    this.sync()
                    this.open = false
                },
                clear() {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    let chain = editor.chain().focus()

                    if (editor.state.selection.empty) {
                        chain = chain.extendMarkRange('styleClass')
                    }

                    chain.unsetStyleClass().unsetBlockStyle().run()

                    this.sync()
                    this.open = false
                },
            }
            JS;

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                // Escaped for the attribute it lands in: a stray quote in here would end
                // the attribute early and take everything after it with it.
                'x-data' => e($xData),
                'x-effect' => 'editorUpdatedAt && sync()',
                'x-on:click.outside' => 'open = false',
                'x-on:keydown.escape.prevent' => 'open = false',
            ], escape: false)
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-fo-rich-editor-dropdown-tool-textual', 'fi-arte-style-picker']);

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
                x-on:click="open = ! open; open && positionMenu()"
                class="fi-fo-rich-editor-tool fi-fo-rich-editor-dropdown-tool-trigger fi-arte-style-picker-trigger"
            >
                <span class="fi-arte-style-picker-label" x-text="label()"><?= e($label) ?></span>

                <svg
                    class="fi-fo-rich-editor-dropdown-tool-chevron"
                    viewBox="0 0 12 12"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="M3 4.5 6 7.5l3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>

            <div
                x-show="open"
                x-cloak
                x-ref="menu"
                role="menu"
                x-bind:class="{ [menuUpClass]: dropUp }"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-style-menu"
            >
                <button
                    type="button"
                    tabindex="-1"
                    role="menuitem"
                    x-on:click="clear()"
                    x-bind:class="{ 'fi-active': ! block && ! inline }"
                    class="fi-fo-rich-editor-dropdown-tool-option fi-arte-style-option"
                >
                    <span><?= e($clear) ?></span>
                </button>

                <?php foreach (['block', 'inline'] as $scope) { ?>
                    <?php $scoped = Styles::ofScope($styles, $scope); ?>

                    <?php if ($scoped === []) {
                        continue;
                    } ?>

                    <?php if ($this->hasGroups()) { ?>
                        <p class="fi-arte-style-group"><?= e($groups[$scope]) ?></p>
                    <?php } ?>

                    <?php foreach ($scoped as $style) { ?>
                        <button
                            type="button"
                            tabindex="-1"
                            role="menuitem"
                            class="fi-fo-rich-editor-dropdown-tool-option fi-arte-style-option"
                            <?php $key = $this->js($style['key']); ?>
                            x-bind:class="{ 'fi-active': isActive(<?= $key ?>), 'fi-disabled': ! applies(<?= $key ?>) }"
                            x-bind:aria-disabled="! applies(<?= $key ?>)"
                            x-on:click="apply(<?= $key ?>)"
                        >
                            <span><?= e($style['label']) ?></span>
                        </button>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <?php return ob_get_clean();
    }

    protected function trigger(): int
    {
        return static::TRIGGER_LENGTH;
    }

    /**
     * @param  array<mixed>|string  $value
     */
    protected function js(array|string $value): string
    {
        return Js::from($value)->toHtml();
    }
}
