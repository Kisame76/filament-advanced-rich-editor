/**
 * A dropdown of colour swatches for the selection: the text colour, or the background.
 *
 * One component serves both because only three things differ between them: which mark is
 * written, which command writes it, and the argument that command takes. The text colour rides
 * on Filament's own `textColor` mark - it ships the mark, the commands and a configurable
 * palette - and keeps its value in `data-color`; the background uses this package's
 * `textBackground` mark, since Filament registers TipTap's highlight without colour support,
 * and keeps it in a plain `color`.
 *
 * This used to be JavaScript inside every picker's `x-data`, four copies per editor. It is an
 * Alpine component now, loaded through `x-load-src`, and the markup hands over which picker it
 * is and its palette. The editor is read through `$getEditor()` from the field around it.
 *
 * This package ships no bundler, so the file must stay free of `import` statements: Filament
 * loads it verbatim. The default export is the factory the `x-data` expression calls.
 */

const MARKS = {
    text: {
        mark: 'textColor',
        attribute: 'data-color',
        set: (chain, color) => chain.setTextColor({ color }),
        unset: (chain) => chain.unsetTextColor(),
    },
    background: {
        mark: 'textBackground',
        attribute: 'color',
        set: (chain, color) => chain.setTextBackground(color),
        unset: (chain) => chain.unsetTextBackground(),
    },
}

export default ({ mode = 'text', colors = [], menuPosition = null, menuUpClass = 'fi-arte-menu-up' } = {}) => {
    const marks = MARKS[mode] ?? MARKS.text

    // Without a selection the whole run under the caret is meant, which is what a reader
    // expects when recolouring a word they clicked into.
    const chainFor = (editor) => {
        const chain = editor.chain().focus()

        return editor.state.selection.empty ? chain.extendMarkRange(marks.mark) : chain
    }

    return {
        open: false,
        current: null,
        colors,
        dropUp: false,
        menuUpClass,

        // Turned upwards where there is no room below; the measuring is `menu-position.js`,
        // whose URL the field hands over so it carries the version the field was rendered with.
        positionMenu() {
            this.dropUp = false

            if (menuPosition) {
                import(menuPosition).then(({ positionMenu }) => positionMenu(this))
            }
        },

        sync() {
            this.current = this.$getEditor()?.getAttributes(marks.mark)?.[marks.attribute] ?? null
        },

        // The colour a value is painted in on the trigger: a palette entry's own, or the value
        // itself for one picked freely.
        swatch(value) {
            return this.colors.find((color) => color.value === value)?.color ?? value
        },

        apply(color) {
            const editor = this.$getEditor()

            if (!editor) {
                return
            }

            marks.set(chainFor(editor), color).run()

            this.current = color
            this.open = false
        },

        clear() {
            const editor = this.$getEditor()

            if (!editor) {
                return
            }

            marks.unset(chainFor(editor)).run()

            this.current = null
            this.open = false
        },
    }
}
