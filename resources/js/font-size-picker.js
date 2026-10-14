/**
 * The toolbar's font size: a number to type into, and a menu of the sizes anyone picks.
 *
 * `size` mirrors the size at the caret; `apply()` is the only writer, so a value typed into
 * the field goes through the same clamping as the menu.
 *
 * Text without an explicit size still has one - the theme's. Showing a guess instead would
 * make the first change go the wrong way: with prose at 14px, a field claiming 16px turns
 * "a little smaller" into 15px, which is larger than what the reader was looking at. So the
 * size is measured off the rendered text whenever the mark has nothing to say, which also
 * makes the field report a heading's size while the caret sits inside one.
 *
 * This used to be JavaScript inside the field's `x-data`. It is an Alpine component now,
 * loaded through `x-load-src`; the editor is read through `$getEditor()` and the selection is
 * put back through `setEditorSelection()`, both from the field the picker sits in. The name
 * is not `font-size.js`, which is the TipTap extension behind the mark.
 *
 * This package ships no bundler, so the file must stay free of `import` statements: Filament
 * loads it verbatim. The default export is the factory the `x-data` expression calls.
 */
export default ({
    min = 8,
    max = 96,
    step = 1,
    fallback = 16,
    unit = 'px',
    menuPosition = null,
    menuUpClass = 'fi-arte-menu-up',
} = {}) => {
    // A measurement waiting for the next frame, so a burst of ticks asks for one. Out here
    // rather than on the component, and that is the whole point: Alpine makes the
    // component's properties reactive and re-runs `follow()` when one it read changes - a
    // flag it reads and the frame resets set it off again every frame, for good.
    let measuring = false

    return {
        size: fallback,
        open: false,
        // Whether a size was chosen, as opposed to inherited. The menu marks what was picked, and
        // picking `Default` is a choice too - one that a number cannot represent, since the
        // inherited size is a number as well.
        isMarked: false,
        // What was selected when the field was reached for. Typing a size and then clicking back
        // into the text applies on the way out - by which time the click has already moved the
        // caret, and the size would land on nothing. So the range is remembered here and put back
        // before it is used.
        selection: null,
        // Whether something was typed since the field was reached, and whether the menu, `Default`
        // or Escape has already settled it. Leaving the field applies what was typed - and picking
        // from the menu leaves it too, since writing focuses the editor. Without these, leaving
        // wrote the number on show straight back: `Default` ended as a mark of the theme's own
        // size, and clicking into the field and out again marked text with the size it had.
        dirty: false,
        settled: false,
        min,
        max,
        step,
        fallback,
        unit,
        dropUp: false,
        menuUpClass,

        // Turned upwards where there is no room below; the measuring is `menu-position.js`, whose
        // URL the field hands over so it carries the version the field was rendered with.
        positionMenu() {
            this.dropUp = false

            if (menuPosition) {
                import(menuPosition).then(({ positionMenu }) => positionMenu(this))
            }
        },

        measure() {
            const editor = this.$getEditor()

            if (!editor?.view) {
                return null
            }

            try {
                const { node, offset } = editor.view.domAtPos(editor.state.selection.from)
                let element = node

                if (element?.nodeType === Node.TEXT_NODE) {
                    element = element.parentElement
                } else if (element?.childNodes?.length) {
                    element = element.childNodes[Math.min(offset, element.childNodes.length - 1)] ?? element
                }

                while (element && element.nodeType !== Node.ELEMENT_NODE) {
                    element = element.parentElement
                }

                const measured = element ? Number.parseFloat(window.getComputedStyle(element).fontSize) : Number.NaN

                return Number.isFinite(measured) ? Math.round(measured) : null
            } catch {
                return null
            }
        },

        /**
         * What the editor's tick calls: the size at the caret, measured a frame later.
         *
         * The tick is every transaction - and on a page of editors, every one of them starting
         * up. `measure()` asks the browser for a computed style, which makes it work out every
         * change any field has made since it last drew, there and then. Measured on a page of
         * twenty editors in a repeater: 35 of those, 1.3 of the 3.5 seconds the page spent on
         * styles before anybody could type. So the mark is read at once - that costs nothing -
         * and the rendered size in the next frame, by when the browser has worked the styles out
         * for its own drawing anyway. A burst of ticks measures once.
         */
        follow() {
            if (this.dirty) {
                return
            }

            if (this.readMark()) {
                return
            }

            if (measuring) {
                return
            }

            measuring = true

            requestAnimationFrame(() => {
                measuring = false

                // Typed into, or given a mark, while the frame was coming.
                if (!this.dirty && !this.isMarked) {
                    this.size = this.measure() ?? this.fallback
                }
            })
        },

        // Whether the text at the caret was given a size, which then is the number on show.
        readMark() {
            const marked = Number.parseFloat(this.$getEditor()?.getAttributes('fontSize')?.size)

            this.isMarked = Number.isFinite(marked)

            if (this.isMarked) {
                this.size = Math.round(marked)
            }

            return this.isMarked
        },

        // The same, at once - for when somebody acted on the field and the number has to be
        // right before the next thing they do.
        sync() {
            // Somebody is typing a size: the number in the field is theirs until they leave it.
            if (this.dirty) {
                return
            }

            if (!this.readMark()) {
                this.size = this.measure() ?? this.fallback
            }
        },

        capture() {
            this.selection = this.$getEditor()?.state?.selection?.toJSON() ?? null
        },

        // The field was reached: remember where from, and start with nothing typed.
        enter() {
            this.capture()
            this.dirty = false
            this.settled = false
            this.open = true
        },

        edited() {
            this.dirty = true
        },

        // Applied on the way out only when something was typed and nothing has settled it since.
        leave() {
            if (this.dirty && !this.settled) {
                this.apply(this.size)
            }

            this.dirty = false
            this.settled = false
        },

        // Escape throws away what was typed and shows the size in force again.
        cancel() {
            this.dirty = false
            this.settled = true
            this.sync()
        },

        restoreSelection() {
            if (this.selection) {
                this.setEditorSelection(this.selection)
                this.selection = null
            }
        },

        apply(value) {
            const parsed = Number.parseFloat(value)
            const current = Number.isFinite(parsed) ? parsed : (this.measure() ?? this.fallback)
            const next = Math.min(this.max, Math.max(this.min, Math.round(current)))

            this.size = next
            this.open = false

            this.settled = true

            this.restoreSelection()

            this.$getEditor()?.chain().focus().setFontSize(next + this.unit).run()
        },

        // Back to whatever the theme says, which is not the same as picking the number the theme
        // happens to use: one leaves a mark behind, the other does not, and only the second one
        // follows a restyled theme afterwards. The field keeps showing the size in force, so it is
        // never blank and never asks anyone to retype what they can already see.
        clear() {
            this.open = false
            this.settled = true

            this.restoreSelection()

            this.$getEditor()?.chain().focus().unsetFontSize().run()
        },
    }
}
