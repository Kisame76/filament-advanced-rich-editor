/*
 * A small window hanging off the body: the emoji picker and the find bar.
 *
 * Both wanted the same four things and neither of them is the interesting part of what it
 * does - stay on the screen, move when dragged, close on Escape, close on a click that is
 * not in the editor. Written twice they would drift, and the half that drifts silently is
 * the geometry: a window placed past the edge of the screen cannot be dragged back, and
 * nothing says so until somebody opens it on a narrow one.
 *
 * What is NOT here is where a panel goes and how big it is. The emoji picker measures the
 * room under the caret and shrinks to fit it; the find bar is one fixed row in a corner.
 * Those are the two things they genuinely do differently, so each keeps its own.
 *
 * The panel is `position: fixed` on the body rather than a child of the field, and that is
 * the whole reason it exists as a panel: inside the field it is a box in Filament's own
 * layout, which turns into a two-column row on a wide screen, and the bar would take half
 * the editor.
 */

/**
 * The gap kept between a panel and the edge of the window. Small enough to read as a panel
 * against the edge, large enough that the shadow is not clipped.
 */
export const MARGIN = 8

/**
 * A coordinate kept inside the window.
 *
 * The near edge wins where nothing fits - a panel wider than the screen has no position
 * satisfying both, and one whose left-hand end is off the screen cannot be reached at all,
 * while one whose right-hand end is off it can still be read and dragged.
 */
export function clampInto(value, extent, limit) {
    return Math.min(Math.max(MARGIN, value), Math.max(MARGIN, limit - extent - MARGIN))
}

/**
 * The top right corner inside a rectangle, brought back onto the screen. Where a panel
 * belongs to something on the page rather than to a point in the text.
 */
export function cornerOf(anchor, size, viewport) {
    return {
        left: clampInto(anchor.right - size.width - MARGIN, size.width, viewport.width),
        top: clampInto(anchor.top + MARGIN, size.height, viewport.height),
    }
}

/**
 * Hangs an element off the body and gives it the four behaviours.
 *
 * `handle` is what can be grabbed to move it, and it must not be the whole panel where the
 * panel holds a text field - dragging would start on every click into it. `keepOpenInside`
 * are the elements a click may land in without closing the panel: for both callers that is
 * the editor, because clicking into the text is how the next spot gets chosen.
 *
 * `onResize` fires only while the panel has not been dragged: once someone has put it
 * somewhere, a window resize is no reason to take that back.
 */
export function floatingPanel(element, { handle, keepOpenInside = [], onClose = null, onResize = null } = {}) {
    let drag = null
    let wasDragged = false
    let isOpen = true

    const onDrag = (event) => {
        if (!drag) {
            return
        }

        const box = element.getBoundingClientRect()

        element.style.left = `${clampInto(event.clientX - drag.x, box.width, window.innerWidth)}px`
        element.style.top = `${clampInto(event.clientY - drag.y, box.height, window.innerHeight)}px`
    }

    const endDrag = () => {
        drag = null

        document.removeEventListener('pointermove', onDrag)
        document.removeEventListener('pointerup', endDrag)
    }

    const startDrag = (event) => {
        const box = element.getBoundingClientRect()

        drag = { x: event.clientX - box.left, y: event.clientY - box.top }
        wasDragged = true

        document.addEventListener('pointermove', onDrag)
        document.addEventListener('pointerup', endDrag)
    }

    const onPointerDown = (event) => {
        if (element.contains(event.target)) {
            return
        }

        if (keepOpenInside.some((node) => node?.contains(event.target))) {
            return
        }

        close()
    }

    const onKeyDown = (event) => {
        if (event.key !== 'Escape') {
            return
        }

        event.preventDefault()
        close()
    }

    const onWindowResize = () => {
        if (!wasDragged) {
            onResize?.()
        }
    }

    function close() {
        if (!isOpen) {
            return
        }

        isOpen = false

        document.removeEventListener('pointerdown', onPointerDown, true)
        document.removeEventListener('keydown', onKeyDown, true)
        window.removeEventListener('resize', onWindowResize)

        handle?.removeEventListener('pointerdown', startDrag)

        endDrag()

        element.remove()

        onClose?.()
    }

    document.body.append(element)

    // Capturing, so a panel closes on a click the page would otherwise swallow first.
    document.addEventListener('pointerdown', onPointerDown, true)
    document.addEventListener('keydown', onKeyDown, true)
    window.addEventListener('resize', onWindowResize)

    handle?.addEventListener('pointerdown', startDrag)

    return {
        close,
        moveTo({ left, top }) {
            element.style.left = `${left}px`
            element.style.top = `${top}px`
        },
        get wasDragged() {
            return wasDragged
        },
        get isOpen() {
            return isOpen
        },
    }
}
