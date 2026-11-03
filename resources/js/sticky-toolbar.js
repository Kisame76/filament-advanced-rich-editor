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
 * The colour a field shows behind its bar, as one opaque colour - or null where it cannot be
 * told.
 *
 * A pinned bar has to be opaque, or the text slides visibly through it, and opaque in the
 * colour of the field or it is a stripe of another grey. That colour is not one value: in dark
 * mode Filament draws a field as a translucent white over whatever it sits on, so it is one
 * grey in a section and a darker one straight on the page. The stylesheet can only guess which.
 * So every background from the bar outwards is laid over the next, down to the first opaque
 * one, on a one-pixel canvas - which does the compositing, and reads every colour notation the
 * browser does, `oklab()` included.
 */
export function colourBehind(element, context = canvasContext()) {
    if (!element || !context) {
        return null
    }

    const alphaOf = (colour) => {
        context.clearRect(0, 0, 1, 1)
        // Reset first: a colour the canvas cannot read leaves the last one in place, and
        // would be counted twice.
        context.fillStyle = 'transparent'
        context.fillStyle = colour
        context.fillRect(0, 0, 1, 1)

        return context.getImageData(0, 0, 1, 1).data[3]
    }

    const layers = []

    for (let node = element.parentElement; node; node = node.parentElement) {
        const colour = window.getComputedStyle(node).backgroundColor
        const alpha = alphaOf(colour)

        if (alpha === 0) {
            continue
        }

        layers.unshift(colour)

        if (alpha === 255) {
            context.clearRect(0, 0, 1, 1)

            for (const layer of layers) {
                context.fillStyle = layer
                context.fillRect(0, 0, 1, 1)
            }

            const [red, green, blue] = context.getImageData(0, 0, 1, 1).data

            return `rgb(${red} ${green} ${blue})`
        }
    }

    return null
}

let canvas = null

function canvasContext() {
    canvas ??= document.createElement('canvas')
    canvas.width = 1
    canvas.height = 1

    return canvas.getContext?.('2d', { willReadFrequently: true }) ?? null
}

/**
 * Keeps `fi-arte-stuck` on a bar while it is stuck, and the colour behind it on the bar for the
 * stylesheet to paint it with. Returns what stops both.
 */
export function watch(toolbar, Observer = window.IntersectionObserver, Mutations = window.MutationObserver) {
    if (!toolbar || !Observer) {
        return () => {}
    }

    // Resolved to pixels whatever the field was configured with - `4rem`, a calc().
    const top = Number.parseFloat(window.getComputedStyle(toolbar).top) || 0

    const paint = () => {
        const colour = colourBehind(toolbar)

        if (colour) {
            toolbar.style.setProperty('--fi-arte-behind', colour)
        } else {
            toolbar.style.removeProperty('--fi-arte-behind')
        }
    }

    const observer = new Observer(
        ([entry]) => {
            toolbar.classList.toggle(STUCK_CLASS, isStuck(entry))
        },
        { root: scrollParent(toolbar), rootMargin: `${-(top + 1)}px 0px 0px 0px`, threshold: [1] },
    )

    observer.observe(toolbar)
    paint()

    // Again whenever the page changes between light and dark, which Filament does with a class
    // on the root - the colour measured for one is wrong for the other. Filament fades a field's
    // background from one to the other, so what is read at the switch is a colour on its way:
    // it is read again once every fade behind the bar has arrived.
    const repaint = () => {
        paint()

        const fading = []

        for (let node = toolbar.parentElement; node; node = node.parentElement) {
            fading.push(...(node.getAnimations?.() ?? []))
        }

        if (fading.length > 0) {
            Promise.allSettled(fading.map((animation) => animation.finished)).then(paint)
        }
    }

    const theme = Mutations ? new Mutations(repaint) : null
    theme?.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

    return () => {
        observer.disconnect()
        theme?.disconnect()
        toolbar.classList.remove(STUCK_CLASS)
        toolbar.style.removeProperty('--fi-arte-behind')
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
            return { stop: null, frame: null }
        },

        // From the next frame rather than now. Starting reads the bar's computed `top` and
        // walks up for a box that scrolls, and inside the editor's own start that asks the
        // browser for styles there and then - which makes it work out every change every other
        // field has made since it last drew. Measured on a page of twenty editors: half a
        // second. In the next frame the browser has those styles anyway, and nothing is stuck
        // while a page is still loading.
        onCreate() {
            const toolbar = toolbarOf(this.editor.options.element)

            if (!toolbar) {
                return
            }

            this.storage.frame = requestAnimationFrame(() => {
                this.storage.frame = null
                this.storage.stop = watch(toolbar)
            })
        },

        onDestroy() {
            if (this.storage.frame !== null) {
                cancelAnimationFrame(this.storage.frame)
                this.storage.frame = null
            }

            this.storage.stop?.()
            this.storage.stop = null
        },
    })
}
