/**
 * The two panels of the image toolbar: the alt text and caption, and the width and height.
 *
 * Both are popovers anchored to their button rather than a second level replacing the bar.
 * The bar is composed of independent items - Filament renders each toolbar entry on its own -
 * so a level swap would need state shared between siblings that have no common wrapper, and
 * the bubble menu destroys and re-initialises that markup on every hide. A popover keeps each
 * control self contained and keeps the bar configurable.
 *
 * This used to be JavaScript inside every panel's `x-data`, five kilobytes a copy. It is an
 * Alpine component now, loaded through `x-load-src` the way the media browser is, and the
 * markup only says which panel it is. The editor is read through `$getEditor()` from the field
 * around the panel, which is the scope Alpine evaluates the panel's expressions in.
 *
 * This package ships no bundler, so the file must stay free of `import` statements: Filament
 * loads it verbatim. The default export is the factory the `x-data` expression calls.
 */

// The panel for the two pieces of text a picture carries.
const textPanel = () => ({
    alt: '',
    caption: '',

    read() {
        const image = this.image()

        this.alt = image.alt ?? ''
        this.caption = image.caption ?? ''
    },

    // Neither can be stored empty - the renderer drops falsy attributes - so clearing a field
    // removes it rather than pretending otherwise.
    commit() {
        this.update({
            alt: this.alt.trim() === '' ? null : this.alt,
            caption: this.caption.trim() === '' ? null : this.caption,
        })
    },
})

// The panel for the width and height, linked through the picture's ratio while it is locked.
const sizePanel = () => ({
    width: null,
    height: null,
    ratio: null,
    locked: true,

    read() {
        const attributes = this.image()

        // An image that has never been sized carries no width or height of its own, so the
        // rendered element answers for it.
        const element = this.$getEditor()?.view?.nodeDOM?.(this.position() ?? 0)
        const image = element?.querySelector?.('img') ?? element ?? null

        this.width = Number.parseInt(attributes.width, 10) || image?.offsetWidth || null
        this.height = Number.parseInt(attributes.height, 10) || image?.offsetHeight || null
        this.ratio = this.width > 0 && this.height > 0 ? this.width / this.height : null
        this.locked = !(this.$getEditor()?.storage?.arteImageResize?.unlocked ?? false)
    },

    toggleLock() {
        const storage = this.$getEditor()?.storage?.arteImageResize

        if (!storage) {
            return
        }

        storage.unlocked = !storage.unlocked
        this.locked = !storage.unlocked

        // The same switch also sits in the toolbar itself, where it is visible during a drag.
        // One state, two places to reach it.
        window.dispatchEvent(new CustomEvent('arte-image-lock', { detail: { unlocked: storage.unlocked } }))
    },

    // The single writer for both fields. While the ratio is locked the other field follows
    // along as somebody types, so the pair that will be applied is visible before it is.
    // `x-model` would be a writer too, and it updates after this handler, so a bound model
    // would keep overwriting the linked value with the previous one.
    link(changed, raw) {
        const value = Number.parseInt(raw, 10)
        const valid = Number.isFinite(value) && value > 0

        if (changed === 'height') {
            this.height = valid ? value : null
        } else {
            this.width = valid ? value : null
        }

        if (!valid || !this.locked || !this.ratio) {
            return
        }

        if (changed === 'height') {
            this.width = Math.round(value * this.ratio)
        } else {
            this.height = Math.round(value / this.ratio)
        }
    },

    isDirty() {
        const attributes = this.image()
        const width = Number.parseInt(this.width, 10)
        const height = Number.parseInt(this.height, 10)

        if (!Number.isFinite(width) || !Number.isFinite(height) || width < 1 || height < 1) {
            return false
        }

        return width !== Number.parseInt(attributes.width, 10) || height !== Number.parseInt(attributes.height, 10)
    },

    // Applied on demand rather than on every change: with the ratio locked, writing each field
    // on its own would undo the other one before both numbers had been entered.
    apply() {
        if (!this.isDirty()) {
            return
        }

        const width = Number.parseInt(this.width, 10)
        const height = Number.parseInt(this.height, 10)

        this.update({ width, height })
        this.ratio = width / height
        this.open = false
    },

    reset() {
        this.update({ width: null, height: null })
        this.$nextTick(() => this.read())
    },
})

export default ({ mode = 'alt', menuPosition = null, menuUpClass = 'fi-arte-menu-up' } = {}) => ({
    ...(mode === 'size' ? sizePanel() : textPanel()),

    open: false,
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

    // Where the picture is, frozen while the panel is open.
    //
    // `commit()` reads this to decide where to write, and by the time it runs the live
    // selection is no longer the picture: focusing a field collapses the node selection to a
    // caret. Asking the selection at that point writes the alt text at wherever the caret
    // ended up - and the picture, no longer selected, takes the floating toolbar and this
    // panel down with it. Which is what clicking from the alt field into the caption did.
    anchored: null,

    position() {
        return this.anchored ?? this.$getEditor()?.state?.selection?.from
    },

    // Re-taken on every tick while the panel is closed, so it holds the picture the toolbar is
    // currently on. Opening the panel freezes it.
    anchor() {
        const from = this.$getEditor()?.state?.selection?.from

        this.anchored = this.$getEditor()?.state?.doc?.nodeAt(from)?.type.name === 'image' ? from : null
    },

    node() {
        const editor = this.$getEditor()
        const position = this.position()

        if (!editor || position === undefined || position === null) {
            return null
        }

        const node = editor.state.doc.nodeAt(position)

        return node?.type.name === 'image' ? node : null
    },

    // Read off the node rather than through `getAttributes()`: that one answers for the
    // selection, and the selection is a plain caret again as soon as anything focuses away
    // from the image.
    image() {
        return this.node()?.attrs ?? {}
    },

    // Writing to the document is what closes this panel: the transaction makes the floating
    // toolbar re-evaluate, the toolbar is rebuilt, and the panel inside it comes back closed.
    // Fine when somebody is finished, and wrong the moment they move from one field to the
    // next. `relatedTarget` on a blur is the element about to take focus, so this asks the
    // only question that matters: is focus still inside this panel? Moving between fields
    // writes nothing; leaving writes once. The size panel writes on its own button instead.
    commitOnLeaving(event) {
        if (this.$root.contains(event.relatedTarget)) {
            return
        }

        if (typeof this.commit === 'function') {
            this.commit()
        }
    },

    update(attributes) {
        const editor = this.$getEditor()
        const position = this.position()

        if (!editor || position === undefined || position === null) {
            return
        }

        // Nothing to write to. The picture was deleted, or the document moved under the open
        // panel; writing anyway would put an alt text on whatever node sits there now.
        if (editor.state.doc.nodeAt(position)?.type.name !== 'image') {
            return
        }

        // Mirrors how a resize drag commits: the node selection is restored first, because
        // focusing the editor would collapse it to a caret.
        editor.chain().setNodeSelection(position).updateAttributes('image', attributes).run()
    },
})
