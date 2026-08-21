<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Schemas\Components\Concerns\HasName;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;
use Illuminate\Support\Js;

/**
 * The typeface dropdown.
 *
 * Not a `RichEditorTool`, for the same reason the colour pickers are not: a tool is one
 * button with one handler, and this is a list. Living in the editor's own Alpine scope is
 * what lets it read `$getEditor()` and follow the caret.
 *
 * Each entry is drawn in the typeface it offers, which is the only preview worth having,
 * and the `@font-face` rules for the project's own files ride along once per page.
 */
class ToolbarFontPicker extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;
    use HasName;

    /**
     * A font stack is written into a `style` attribute, and Filament's sanitiser passes
     * `style` through untouched - so this is the only thing standing between the editor and
     * a stylesheet smuggled in as a family name. Letters, digits, spaces, quotes, commas and
     * hyphens are all a font stack has ever needed.
     */
    protected const PATTERN = '/^[\p{L}\p{N} ,\'"\-_]+$/u';

    /**
     * How many characters of a name the trigger shows before it gives up and elides. The
     * word `Default` is exempt: it is a state, not somebody's typeface.
     */
    protected const TRIGGER_LENGTH = 6;

    /**
     * @var array<int, array{label: string, stack: string, verify: bool}>
     */
    protected array $fonts = [];

    protected string $styleSheet = '';

    protected string $evaluationIdentifier = 'toolbarFontPicker';

    protected string $viewIdentifier = 'toolbarFontPicker';

    /**
     * Emitted by the first picker on the page and by no other: the faces are the same for
     * every field, and a page with three editors does not need them three times.
     */
    protected static bool $hasWrittenStyleSheet = false;

    final public function __construct(string $name = 'fontFamily')
    {
        $this->name($name);
    }

    public static function make(string $name = 'fontFamily'): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, array{label: string, stack: string, verify: bool}>  $fonts
     */
    public function fonts(array $fonts): static
    {
        $this->fonts = $fonts;

        return $this;
    }

    /**
     * @return array<int, array{label: string, stack: string, verify: bool}>
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    public function styleSheet(string $css): static
    {
        $this->styleSheet = $css;

        return $this;
    }

    /**
     * A font stack, or null when it is something else pretending to be one.
     */
    public static function sanitise(?string $stack): ?string
    {
        $stack = trim((string) $stack);

        if ($stack === '' || mb_strlen($stack) > 200) {
            return null;
        }

        return preg_match(static::PATTERN, $stack) === 1 ? $stack : null;
    }

    public function toEmbeddedHtml(): string
    {
        $fonts = $this->getFonts();
        $label = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_family');
        $clear = __('filament-advanced-rich-editor::advanced-rich-editor.tools.font_family_clear');
        $search = __('filament-advanced-rich-editor::advanced-rich-editor.fonts.search');

        $xData = <<<JS
            {
                open: false,
                current: null,
                defaultFamily: '',
                fonts: {$this->js($fonts)},
                query: '',
                init() {
                    // Only the families a project says it loads elsewhere are checked: the
                    // ones found on disk carry a face written a few lines above this.
                    this.fonts = this.fonts.filter((font) => ! font.verify || this.isAvailable(font.stack))
                },
                get matching() {
                    const query = this.query.trim().toLowerCase()

                    const listed = this.fonts.filter((font) => ! this.isTheDefault(font))

                    return query
                        ? listed.filter((font) => font.label.toLowerCase().includes(query))
                        : listed
                },
                // A project that ships the same typeface its theme already uses would
                // otherwise be offered it twice: once as Default, once by name. The first
                // row names what it resolves to, so the second row is the one to drop.
                //
                // Matched on the name rather than on the rendering. Comparing pixels would
                // also catch typefaces drawn to be metrically identical to another - Arimo
                // to Arial, Tinos to Times - and those are different typefaces that a
                // reader chose on purpose.
                isTheDefault(font) {
                    const family = font.stack.split(',')[0].trim().replace(/^[\x22\x27]|[\x22\x27]$/g, '')

                    return this.defaultFamily !== '' && this.simplify(family) === this.simplify(this.defaultFamily)
                },
                // `Inter` and `Inter Variable` are one typeface written down two ways: the
                // first came from a folder name, the second from a stylesheet. Nothing else
                // is stripped - `Playfair` and `Playfair Display` really are two families.
                simplify(name) {
                    return name
                        .toLowerCase()
                        .replace(/\b(variable|vf)\b/g, '')
                        .replace(/[^a-z0-9]/g, '')
                },
                // What the default actually looks like, read off the editor rather than
                // guessed: it is whatever the theme sets, and naming it is the difference
                // between an option and a shrug. Read on the editor's tick rather than as a
                // getter, because a getter with nothing reactive in it is evaluated once -
                // and at that moment the editor does not exist yet.
                measureDefault() {
                    const dom = \$getEditor()?.view?.dom

                    if (! dom) {
                        return ''
                    }

                    const first = window.getComputedStyle(dom).fontFamily.split(',')[0] ?? ''

                    // Written with escapes rather than with the quotes themselves, which
                    // are exactly what this is here to strip.
                    return first.trim().replace(/^[\x22\x27]|[\x22\x27]$/g, '')
                },
                isAvailable(stack) {
                    const first = stack.split(',')[0].trim()

                    try {
                        return document.fonts.check(`16px \${first}`)
                    } catch {
                        return true
                    }
                },
                sync() {
                    this.current = \$getEditor()?.getAttributes('fontFamily')?.family ?? null
                    this.defaultFamily = this.measureDefault()
                },
                // Cut to a handful of characters: a toolbar that grows by the length of
                // somebody's font name is a toolbar that wraps. The full name is one click
                // away, in the menu, set in the typeface it belongs to.
                label() {
                    const font = this.fonts.find((entry) => entry.stack === this.current)

                    if (! font) {
                        return {$this->js($clear)}
                    }

                    return font.label.length > {$this->trigger()}
                        ? font.label.slice(0, {$this->trigger()}).trimEnd() + '…'
                        : font.label
                },
                apply(stack) {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    let chain = editor.chain().focus()

                    // Without a selection the run under the caret is meant, which is what a
                    // reader expects when restyling the word they clicked into.
                    if (editor.state.selection.empty) {
                        chain = chain.extendMarkRange('fontFamily')
                    }

                    chain.setFontFamily(stack).run()

                    this.current = stack
                    this.open = false
                },
                clear() {
                    const editor = \$getEditor()

                    if (! editor) {
                        return
                    }

                    let chain = editor.chain().focus()

                    if (editor.state.selection.empty) {
                        chain = chain.extendMarkRange('fontFamily')
                    }

                    chain.unsetFontFamily().run()

                    this.current = null
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
            ->class(['fi-fo-rich-editor-dropdown-tool', 'fi-fo-rich-editor-dropdown-tool-textual', 'fi-arte-font-picker']);

        ob_start(); ?>

        <?php if ($this->styleSheet !== '' && ! static::$hasWrittenStyleSheet) {
            static::$hasWrittenStyleSheet = true; ?>

            <style><?= $this->styleSheet ?></style>
        <?php } ?>

        <div <?= $attributes->toHtml() ?>>
            <button
                type="button"
                tabindex="-1"
                aria-haspopup="true"
                x-bind:aria-expanded="open"
                aria-label="<?= e($label) ?>"
                x-tooltip="{ content: <?= Js::from($label)->toHtml() ?>, theme: $store.theme }"
                x-on:click="open = ! open"
                class="fi-fo-rich-editor-tool fi-fo-rich-editor-dropdown-tool-trigger fi-arte-font-picker-trigger"
            >
                <span class="fi-arte-font-picker-label" x-text="label()" x-bind:style="current ? { fontFamily: current } : {}"><?= e($clear) ?></span>

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
                role="menu"
                class="fi-fo-rich-editor-dropdown-tool-menu fi-arte-font-menu"
            >
                <?php if (count($fonts) > 5) { ?>
                    <input
                        type="search"
                        placeholder="<?= e($search) ?>"
                        aria-label="<?= e($search) ?>"
                        x-model="query"
                        x-on:keydown.stop
                        class="fi-arte-font-search"
                    />
                <?php } ?>

                <button
                    type="button"
                    tabindex="-1"
                    role="menuitem"
                    x-on:click="clear()"
                    x-bind:class="{ 'fi-active': ! current }"
                    class="fi-fo-rich-editor-dropdown-tool-option fi-arte-font-option"
                >
                    <span><?= e($clear) ?></span>

                    <span class="fi-arte-font-default" x-text="defaultFamily"></span>
                </button>

                <template x-for="font in matching" :key="font.label">
                    <button
                        type="button"
                        tabindex="-1"
                        role="menuitem"
                        class="fi-fo-rich-editor-dropdown-tool-option fi-arte-font-option"
                        x-bind:class="{ 'fi-active': current === font.stack }"
                        x-bind:style="{ fontFamily: font.stack }"
                        x-on:click="apply(font.stack)"
                    >
                        <span x-text="font.label"></span>
                    </button>
                </template>
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
