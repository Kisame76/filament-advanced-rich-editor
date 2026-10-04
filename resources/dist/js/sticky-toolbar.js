/*
 * Telling a pinned toolbar from one that is resting at the top of its field.
 *
 * A sticky bar keeps the rounded top corners of the field it sits in - it has to, or its
 * opaque background would paint square corners over the field's round ones. That is right
 * while the bar is at the top of its field and wrong once it is pinned under the page
 * header: the field's top edge has scrolled away, and the corners cut two notches out of a
 * bar that now spans the field with text sliding past behind it.
 *
 * CSS cannot ask whether a sticky box is stuck in every browser this package supports, so
 * an IntersectionObserver asks instead, and the bar carries `fi-arte-stuck` while it is.
 * The root is shrunk at the top by the offset the bar sticks at plus one pixel: a bar held
 * at its offset then pokes one pixel out of the root, which no bar resting in its field
 * further down ever does. Filament loads this verbatim through a dynamic `import()`, so
 * there are no `import` statements.
 */

export const STUCK_CLASS = 'fi-arte-stuck'

const STICKY_BARS = '.fi-fo-rich-editor-toolbar.fi-arte-sticky, .fi-fo-rich-editor-toolbar.fi-fo-rich-editor-sticky-toolbar'

/**
 * Whether an observer entry describes a stuck bar: partly outside the root, and outside it
 * at the top. A bar cut off by the bottom of the window is on its way in, not stuck.
 */
export function isStuck(entry) {
    if (!entry?.rootBounds) {
        return false
    }

    return entry.intersectionRatio < 1 && entry.boundingClientRect.top < entry.rootBounds.top
}

/**
 * The box a bar is pinned in: the nearest ancestor that scrolls - a modal, a slide-over - or
 * the window, as `null`, which is what an observer takes for it.
 */
export function scrollParent(element) {
    for (let node = element?.parentElement; node && node !== document.body; node = node.parentElement) {
        if (/(auto|scroll|overlay)/.test(window.getComputedStyle(node).overflowY)) {
            return node
        }
    }

    return null
}

/**
 * Keeps `fi-arte-stuck` on a bar while it is stuck. Returns what stops it.
 */
export function watch(toolbar, Observer = window.IntersectionObserver) {
    if (!toolbar || !Observer) {
        return () => {}
    }

    // Resolved to pixels whatever the field was configured with - `4rem`, a calc().
    const top = Number.parseFloat(window.getComputedStyle(toolbar).top) || 0

    const observer = new Observer(
        ([entry]) => {
            toolbar.classList.toggle(STUCK_CLASS, isStuck(entry))
        },
        { root: scrollParent(toolbar), rootMargin: `${-(top + 1)}px 0px 0px 0px`, threshold: [1] },
    )

    observer.observe(toolbar)

    return () => {
        observer.disconnect()
        toolbar.classList.remove(STUCK_CLASS)
    }
}

/**
 * The bar of the field an editor is mounted in. Looked for upwards from the editor rather
 * than with one query on the field, so a field never finds a bar that belongs to another.
 */
function toolbarOf(element) {
    for (let node = element?.parentElement; node; node = node.parentElement) {
        const bar = [...node.children].find((child) => child.matches('.fi-fo-rich-editor-toolbar'))

        if (bar) {
            return bar.matches(STICKY_BARS) ? bar : null
        }

        if (node.matches('.fi-fo-rich-editor')) {
            return null
        }
    }

    return null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor sticky toolbar extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteStickyToolbar',

        addStorage() {
            return { stop: null }
        },

        onCreate() {
            const toolbar = toolbarOf(this.editor.options.element)

            if (toolbar) {
                this.storage.stop = watch(toolbar)
            }
        },

        onDestroy() {
            this.storage.stop?.()
            this.storage.stop = null
        },
    })
}
