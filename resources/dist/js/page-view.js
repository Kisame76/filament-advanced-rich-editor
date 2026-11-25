/**
 * The page view's zoom: a bar over the sheet with its format, smaller, the size it is at,
 * larger, and fit to the width.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * The sheet itself is the stylesheet's, from what the field writes on its wrapper; this only
 * sets how large it is drawn. CSS `zoom` rather than a transform: a scaled box keeps its old
 * size in the layout and leaves the space around it empty, a zoomed one takes the size it is
 * drawn at. A picture's resize handles still measure the pointer in screen pixels - see
 * `effectiveZoom()` and `image-resize.js`, which divides them back.
 *
 * Where it starts: the width that fits, and never larger than the sheet itself - the desk
 * does not scroll unless somebody zooms in past it, because a box that scrolls clips the
 * toolbars inside it. A size somebody chose is remembered in the browser, for every field.
 */

export const NAME = 'artePageView'

/**
 * The sizes the buttons step through.
 */
export const STEPS = [0.5, 0.67, 0.75, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2]

export const STORAGE_KEY = 'filament-advanced-rich-editor.page-zoom'

const PIXELS_PER_MILLIMETRE = 96 / 25.4

const MINIMUM = 0.25

const MAXIMUM = 2

/**
 * What the field handed over: the sheet in millimetres and the words on the bar. Null where
 * there is nothing readable - the field then simply has no zoom.
 */
export function settingsOf(element) {
    try {
        const settings = JSON.parse(element?.dataset?.artePage ?? 'null')
        const width = Number(settings?.width)

        if (!Number.isFinite(width) || width <= 0) {
            return null
        }

        return { width, labels: { ...(settings.labels ?? {}) } }
    } catch {
        return null
    }
}

/**
 * The next size from the one it is at, up or down, among the steps - from a size between two
 * of them, the nearer one in that direction.
 */
export function stepFrom(zoom, direction) {
    if (direction > 0) {
        return STEPS.find((step) => step > zoom + 0.001) ?? STEPS[STEPS.length - 1]
    }

    return [...STEPS].reverse().find((step) => step < zoom - 0.001) ?? STEPS[0]
}

/**
 * The size at which a sheet this wide fills the room there is.
 */
export function fitZoom(available, widthInMillimetres) {
    const sheet = widthInMillimetres * PIXELS_PER_MILLIMETRE

    if (!(available > 0) || !(sheet > 0)) {
        return 1
    }

    return Math.min(MAXIMUM, Math.max(MINIMUM, Math.floor((available / sheet) * 100) / 100))
}

/**
 * The size a mode stands for: `auto` is the width that fits but never larger than the sheet,
 * `fit` the width that fits, and a number itself.
 */
export function zoomFor(mode, available, widthInMillimetres) {
    if (mode === 'fit') {
        return fitZoom(available, widthInMillimetres)
    }

    if (typeof mode === 'number') {
        return Math.min(MAXIMUM, Math.max(MINIMUM, mode))
    }

    return Math.min(1, fitZoom(available, widthInMillimetres))
}

/**
 * The mode remembered in this browser, or `auto`. Storage can be missing or refuse to be
 * read, in a private window or a sandbox, and the zoom then simply starts fresh.
 */
export function readMode(storage) {
    try {
        const value = storage?.getItem(STORAGE_KEY)

        if (value === 'fit' || value === 'auto') {
            return value
        }

        const number = Number.parseFloat(value)

        return Number.isFinite(number) && number >= MINIMUM && number <= MAXIMUM ? number : 'auto'
    } catch {
        return 'auto'
    }
}

export function writeMode(storage, mode) {
    try {
        storage?.setItem(STORAGE_KEY, String(mode))
    } catch {
        // Nothing to do: the next page starts at the width that fits.
    }
}

export function percent(zoom) {
    return `${Math.round(zoom * 100)}%`
}

/**
 * How much larger an element is drawn than it is laid out: every `zoom` on the way up
 * multiplied together, as the browser does.
 */
export function effectiveZoom(element, style = (node) => window.getComputedStyle(node)) {
    let zoom = 1

    for (let node = element; node && node.nodeType === 1; node = node.parentElement) {
        const own = Number.parseFloat(style(node)?.zoom)

        if (Number.isFinite(own) && own > 0) {
            zoom *= own
        }
    }

    return zoom
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor page view needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    return tiptap.Extension.create({
        name: NAME,

        addStorage() {
            return { teardown: null }
        },

        onCreate() {
            const desk = this.editor.options.element
            const settings = settingsOf(desk)

            if (!settings || !desk || typeof document === 'undefined') {
                return
            }

            const storage = (() => {
                try {
                    return window.localStorage
                } catch {
                    return null
                }
            })()

            let mode = readMode(storage)
            let zoom = 1

            const bar = document.createElement('div')
            bar.className = 'fi-arte-page-bar'
            bar.contentEditable = 'false'
            bar.setAttribute('role', 'toolbar')
            bar.setAttribute('aria-label', settings.labels.zoom ?? 'Zoom')

            const sheet = document.createElement('span')
            sheet.className = 'fi-arte-page-bar-sheet'
            sheet.textContent = settings.labels.sheet ?? ''

            const button = (text, label) => {
                const element = document.createElement('button')
                element.type = 'button'
                element.className = 'fi-arte-page-bar-button'
                element.textContent = text
                element.title = label ?? ''
                element.setAttribute('aria-label', label ?? text)

                return element
            }

            const smaller = button('−', settings.labels.zoomOut)
            const size = button('100%', settings.labels.reset)
            const larger = button('+', settings.labels.zoomIn)
            const fit = button('↔', settings.labels.fit)

            bar.append(sheet, smaller, size, larger, fit)
            desk.prepend(bar)

            const available = () => {
                const style = window.getComputedStyle(desk)

                return desk.clientWidth - Number.parseFloat(style.paddingLeft || '0') - Number.parseFloat(style.paddingRight || '0')
            }

            const apply = () => {
                const room = available()
                zoom = zoomFor(mode, room, settings.width)

                desk.style.setProperty('--fi-arte-page-zoom', String(zoom))
                desk.classList.toggle('fi-arte-page-overflowing', zoom * settings.width * PIXELS_PER_MILLIMETRE > room + 1)

                size.textContent = percent(zoom)
                fit.setAttribute('aria-pressed', mode === 'fit' ? 'true' : 'false')
            }

            const choose = (next) => {
                mode = next
                writeMode(storage, mode)
                apply()
            }

            smaller.addEventListener('click', () => choose(stepFrom(zoom, -1)))
            larger.addEventListener('click', () => choose(stepFrom(zoom, 1)))
            size.addEventListener('click', () => choose(1))
            fit.addEventListener('click', () => choose(mode === 'fit' ? 'auto' : 'fit'))

            const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(() => apply()) : null
            observer?.observe(desk)

            apply()

            this.storage.teardown = () => {
                observer?.disconnect()
                bar.remove()
                desk.style.removeProperty('--fi-arte-page-zoom')
                desk.classList.remove('fi-arte-page-overflowing')
            }
        },

        onDestroy() {
            this.storage.teardown?.()
            this.storage.teardown = null
        },
    })
}
