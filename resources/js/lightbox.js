/**
 * A picture in a rendered document, opened large over the page when it is clicked.
 *
 * For the page a document is published on, where nothing else of this package runs: no
 * Alpine, no Filament, no bundler - one module the page loads with one tag, which
 * `<x-arte-lightbox />` writes, and `<x-arte-content lightbox>` for it. What it works on is the
 * link the renderer put around each picture, to the picture's own address. Without this file,
 * or with scripts off, that link simply opens the picture: the fallback is a page of its own,
 * not a dead click.
 *
 * The box is the browser's own `<dialog>`. It keeps focus inside, closes on Escape and hands
 * focus back to the link it was opened from - none of which this file has to get right.
 *
 * The large picture is the page's picture again with `sizes="100vw"`: the browser picks the
 * file for a screen-filling picture out of the sizes it was already offered, rather than being
 * sent the largest one there is.
 */

const LINK = 'a.fi-arte-lightbox'

const installed = new WeakSet()

let box = null

/**
 * The box, built the first time a picture is opened and kept for the next one. Put back on
 * the page where it was taken off - a page whose body was swapped by a client-side navigation
 * has the box in memory and not in the document.
 */
function dialog() {
    if (! box) {
        box = document.createElement('dialog')
        box.className = 'fi-arte-lightbox-dialog'

        const close = document.createElement('button')
        close.type = 'button'
        close.className = 'fi-arte-lightbox-close'
        close.append(cross())

        const figure = document.createElement('figure')
        figure.append(document.createElement('img'), document.createElement('figcaption'))

        box.append(close, figure)

        // Anywhere: on the picture, on the dark around it, on the button. A box that holds
        // nothing to interact with has nothing a click could have meant instead.
        box.addEventListener('click', () => box.close())

        box.addEventListener('close', () => {
            const picture = box.querySelector('img')

            picture.removeAttribute('src')
            picture.removeAttribute('srcset')
        })
    }

    if (! box.isConnected) {
        document.body.append(box)
    }

    return box
}

/**
 * The close button's cross, drawn rather than typed: a `×` sits wherever the page's font puts
 * it, which is rarely the middle of a round button. Stroked in `currentColor`, so it follows
 * the button's colour - and the system's, in forced-colours mode.
 */
function cross() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
    svg.setAttribute('viewBox', '0 0 24 24')
    svg.setAttribute('aria-hidden', 'true')
    svg.setAttribute('focusable', 'false')

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path')
    path.setAttribute('d', 'M6 6l12 12M18 6L6 18')
    path.setAttribute('fill', 'none')
    path.setAttribute('stroke', 'currentColor')
    path.setAttribute('stroke-width', '2')
    path.setAttribute('stroke-linecap', 'round')

    svg.append(path)

    return svg
}

/**
 * Opens the picture inside a link the renderer wrote.
 */
export function open(link) {
    const source = link.querySelector('img')
    const shown = dialog()
    const picture = shown.querySelector('img')
    const caption = shown.querySelector('figcaption')
    const words = link.closest('figure')?.querySelector('figcaption')?.textContent?.trim() ?? ''

    // A fresh copy of what the page was given, and not of how the page draws it: the width,
    // the height and the inline style belong to the column, not to the box.
    const alt = source.getAttribute('alt') ?? ''

    picture.setAttribute('alt', alt)
    picture.setAttribute('sizes', '100vw')

    if (source.hasAttribute('srcset')) {
        picture.setAttribute('srcset', source.getAttribute('srcset'))
    } else {
        picture.removeAttribute('srcset')
    }

    // What a screen reader announces the box as, the way it would announce the picture.
    shown.setAttribute('aria-label', alt)

    picture.setAttribute('src', source.currentSrc || source.getAttribute('src') || link.getAttribute('href'))

    caption.textContent = words
    caption.hidden = words === ''

    // Read as it opens rather than once: the tag that names it may be written after this
    // module has already run, by a component further down the page.
    shown.querySelector('button').setAttribute(
        'aria-label',
        document.querySelector('script[data-arte-lightbox]')?.dataset.close || 'Close',
    )

    if (! shown.open) {
        shown.showModal()
    }
}

function onClick(event) {
    // A click that means something else is the browser's: a new tab, a window, a download.
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return
    }

    const link = event.target?.closest?.(LINK)

    // The class is one a stored document could carry on any link, so only one around a
    // picture opens - and only where the browser has the box to open it in.
    if (! link?.querySelector('img') || typeof HTMLDialogElement === 'undefined') {
        return
    }

    event.preventDefault()

    open(link)
}

/**
 * Listens on the page for clicks on those links, once however often it is asked: the module
 * runs once a page, but a page may call this for a root of its own too.
 */
export function install(root) {
    if (installed.has(root)) {
        return
    }

    installed.add(root)
    root.addEventListener('click', onClick)
}

if (typeof document !== 'undefined') {
    install(document)
}
