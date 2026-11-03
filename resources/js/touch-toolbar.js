/*
 * The toolbar on a touch screen, while the keyboard is up.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Two things a phone needs that a mouse does not:
 *
 * - The bar sits on the keyboard while the field is written in. At the top of the field - pinned
 *   or not - it is out of a thumb's reach, and often out of sight: the browser scrolls the page
 *   inside the window to keep the caret visible, and what sticks to the top of the window sticks
 *   to a part of it nobody can see.
 * - A tap on one of the field's buttons keeps the keyboard. The tap moves the focus to the
 *   button, the keyboard folds away, and the button's handler focuses the editor again and
 *   unfolds it - every press would bounce the screen.
 *
 * Nothing at all on a mouse, or where the browser has no visual viewport to measure: the
 * extension starts, finds nothing to do, and stops.
 */

export const DOCKED_CLASS = 'fi-arte-docked'

/** The class the field carries while its bar is docked. */
export const DOCKING_CLASS = 'fi-arte-docking'

/**
 * How much shorter than the window the visible part of the page has to be before it is a
 * keyboard rather than a browser's own bar sliding away, which is around sixty pixels.
 */
export const KEYBOARD = 150

/**
 * Whether the keyboard is covering part of the window. Never while the page is zoomed in: the
 * visible part is smaller then too, and a bar docked to a zoomed view would float over the text.
 */
export function keyboardIsUp(viewport, windowHeight) {
    return Boolean(viewport) && Math.abs(viewport.scale - 1) < 0.01 && windowHeight - viewport.height > KEYBOARD
}

/**
 * Where a docked bar's top edge goes: on the keyboard, in the coordinates `position: fixed`
 * is measured in - the window's, of which the visible part is a window of its own.
 */
export function dockedTop(viewport, barHeight) {
    return Math.round(viewport.offsetTop + viewport.height - barHeight)
}

/**
 * Whether an ancestor would take `position: fixed` away from the window - a transform, a
 * filter, a containment - and with it any hope of the bar landing on the keyboard. A field in a
 * modal is the usual case, since a modal moves in with a transform. The field's own query
 * container is not asked about: it is switched off while the bar is docked.
 */
export function capturesFixed(element, field) {
    for (let node = element?.parentElement; node && node !== document.documentElement; node = node.parentElement) {
        if (node === field) {
            continue
        }

        const style = window.getComputedStyle(node)

        const moved = ['transform', 'translate', 'scale', 'rotate', 'perspective', 'filter', 'backdropFilter'].some(
            (property) => style[property] && style[property] !== 'none',
        )

        if (
            moved ||
            /layout|paint|strict|content/.test(style.contain ?? '') ||
            (style.containerType && style.containerType !== 'normal') ||
            /transform|filter|perspective/.test(style.willChange ?? '')
        ) {
            return true
        }
    }

    return false
}

/**
 * Whether a press should leave the focus where it is: a button of the field, pressed with a
 * finger while the editor is being written in. A text box in the bar - the font size - takes
 * the focus as it should, and so does the text itself.
 */
export function keepsFocus(target, { touch, writing }) {
    if (!touch || !writing || !target?.closest) {
        return false
    }

    return Boolean(target.closest('button')) && !target.closest('input, textarea, select, [contenteditable="true"]')
}

/**
 * Docks a field's bar on the keyboard while its editor is written in, and keeps the keyboard
 * through a tap on its buttons. Returns what stops both.
 */
export function watch(toolbar, editor, viewport = window.visualViewport) {
    const field = toolbar?.closest('.fi-fo-rich-editor')

    if (!field || !viewport) {
        return () => {}
    }

    let spacer = null
    let frame = null
    let touch = false

    const undock = () => {
        if (spacer === null) {
            return
        }

        spacer.remove()
        spacer = null
        toolbar.classList.remove(DOCKED_CLASS)
        toolbar.style.removeProperty('--fi-arte-docked-top')
        field.classList.remove(DOCKING_CLASS)
    }

    const update = () => {
        frame = null

        // Anywhere in the field, not only the text: a menu of the bar takes the focus while it
        // is open, and the bar must not jump back to the top of the field under the finger.
        const writing = keyboardIsUp(viewport, window.innerHeight) && field.contains(document.activeElement)

        if (!writing || (spacer === null && capturesFixed(toolbar, field))) {
            undock()

            return
        }

        if (spacer === null) {
            // Holds the bar's place, so the text does not move up by its height the moment
            // it leaves.
            spacer = document.createElement('div')
            spacer.setAttribute('aria-hidden', 'true')
            spacer.style.blockSize = `${toolbar.offsetHeight}px`
            toolbar.before(spacer)
            field.classList.add(DOCKING_CLASS)
            toolbar.classList.add(DOCKED_CLASS)
        }

        toolbar.style.setProperty('--fi-arte-docked-top', `${dockedTop(viewport, toolbar.offsetHeight)}px`)
    }

    // Once a frame however many events arrive: the visible part of the page reports every
    // pixel of a scroll.
    const schedule = () => {
        frame ??= requestAnimationFrame(update)
    }

    const onPointerDown = (event) => {
        touch = event.pointerType === 'touch' || event.pointerType === 'pen'
    }

    const onMouseDown = (event) => {
        if (keepsFocus(event.target, { touch, writing: editor.view?.hasFocus?.() === true })) {
            event.preventDefault()
        }
    }

    viewport.addEventListener('resize', schedule)
    viewport.addEventListener('scroll', schedule)
    field.addEventListener('focusin', schedule)
    field.addEventListener('focusout', schedule)
    field.addEventListener('pointerdown', onPointerDown, true)
    field.addEventListener('mousedown', onMouseDown, true)

    return () => {
        viewport.removeEventListener('resize', schedule)
        viewport.removeEventListener('scroll', schedule)
        field.removeEventListener('focusin', schedule)
        field.removeEventListener('focusout', schedule)
        field.removeEventListener('pointerdown', onPointerDown, true)
        field.removeEventListener('mousedown', onMouseDown, true)

        if (frame !== null) {
            cancelAnimationFrame(frame)
        }

        undock()
    }
}

/**
 * The bar of the field an editor is mounted in, looked for upwards from the editor so a field
 * never finds a bar that belongs to another. See `sticky-toolbar.js`.
 */
function toolbarOf(element) {
    for (let node = element?.parentElement; node; node = node.parentElement) {
        const bar = [...node.children].find((child) => child.matches('.fi-fo-rich-editor-toolbar'))

        if (bar || node.matches('.fi-fo-rich-editor')) {
            return bar ?? null
        }
    }

    return null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor touch toolbar extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteTouchToolbar',

        addStorage() {
            return { stop: null }
        },

        onCreate() {
            if (!window.matchMedia?.('(pointer: coarse)').matches) {
                return
            }

            const toolbar = toolbarOf(this.editor.options.element)

            if (toolbar) {
                this.storage.stop = watch(toolbar, this.editor)
            }
        },

        onDestroy() {
            this.storage.stop?.()
            this.storage.stop = null
        },
    })
}
