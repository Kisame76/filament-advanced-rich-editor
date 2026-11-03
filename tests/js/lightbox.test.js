import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { install, open } from '../../resources/js/lightbox.js'

/**
 * The box a picture on a rendered page opens in.
 *
 * What the renderer writes - a link from the picture to its own address - is asserted from
 * PHP. This is the other half: the click on that link, turned into the browser's own dialog.
 */

beforeAll(() => {
    // jsdom draws no dialogs; the two calls only have to flip `open`.
    HTMLDialogElement.prototype.showModal ??= function () {
        this.open = true
    }
    HTMLDialogElement.prototype.close ??= function () {
        this.open = false
        this.dispatchEvent(new Event('close'))
    }

    install(document)
})

afterEach(() => {
    document.body.innerHTML = ''
    document.head.innerHTML = ''
})

const page = (html) => {
    document.body.innerHTML = html

    return document.body.querySelector('a.fi-arte-lightbox')
}

const click = (target, options = {}) => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options })

    target.dispatchEvent(event)

    return event
}

const dialog = () => document.querySelector('dialog.fi-arte-lightbox-dialog')

describe('a click on a picture', () => {
    it('opens it large, as the page already knows it', () => {
        const link = page(`
            <p><a href="/storage/harbour.png" class="fi-arte-lightbox"><img src="/storage/harbour.png"
                srcset="/storage/harbour-640.png 640w, /storage/harbour-1280.png 1280w" sizes="(max-width: 480px) 100vw, 480px"
                alt="The harbour" width="480" height="270" style="width: 480px"></a></p>
        `)

        const event = click(link.querySelector('img'))
        const large = dialog()?.querySelector('img')

        expect(event.defaultPrevented).toBe(true)
        expect(dialog().open).toBe(true)
        expect(large.getAttribute('src')).toBe('/storage/harbour.png')
        // The same files, picked for a screen-filling picture this time.
        expect(large.getAttribute('srcset')).toBe('/storage/harbour-640.png 640w, /storage/harbour-1280.png 1280w')
        expect(large.getAttribute('sizes')).toBe('100vw')
        expect(large.getAttribute('alt')).toBe('The harbour')
        expect(dialog().getAttribute('aria-label')).toBe('The harbour')
        // Not the size it is drawn at on the page.
        expect(large.hasAttribute('width')).toBe(false)
        expect(large.hasAttribute('style')).toBe(false)
    })

    it('shows the caption beneath it', () => {
        const link = page(`
            <figure><a href="/storage/harbour.png" class="fi-arte-lightbox"><img src="/storage/harbour.png" alt="The harbour"></a>
            <figcaption>The harbour at dawn</figcaption></figure>
        `)

        click(link)

        expect(dialog().querySelector('figcaption').textContent).toBe('The harbour at dawn')
        expect(dialog().querySelector('figcaption').hidden).toBe(false)
    })

    it('shows no caption for a picture that has none', () => {
        click(page('<figure><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" alt="A"></a><figcaption>Old</figcaption></figure>'))
        dialog().close()

        click(page('<p><a href="/b.png" class="fi-arte-lightbox"><img src="/b.png" alt="B"></a></p>'))

        expect(dialog().querySelector('figcaption').hidden).toBe(true)
    })

    it('leaves a click that means something else to the browser', () => {
        // A new tab, a download, a window: what the link is for without this script.
        const link = page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" alt="A"></a></p>')

        for (const options of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
            expect(click(link, options).defaultPrevented).toBe(false)
        }

        expect(dialog()?.open ?? false).toBe(false)
    })

    it('leaves a link with no picture in it alone', () => {
        // The class is one a document could carry on any link; only a picture opens.
        document.body.innerHTML = '<p><a href="/elsewhere" class="fi-arte-lightbox">Elsewhere</a></p>'

        expect(click(document.querySelector('a')).defaultPrevented).toBe(false)
    })

    it('leaves other links alone', () => {
        document.body.innerHTML = '<p><a href="/a.png"><img src="/a.png" alt="A"></a></p>'

        expect(click(document.querySelector('img')).defaultPrevented).toBe(false)
    })
})

describe('the box', () => {
    it('closes on a click anywhere in it', () => {
        click(page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" alt="A"></a></p>'))

        click(dialog().querySelector('img'))

        expect(dialog().open).toBe(false)
    })

    it('lets go of the picture once it is closed', () => {
        // Or a large file would stay decoded behind a closed box for the rest of the visit.
        click(page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" srcset="/a-640.png 640w" alt="A"></a></p>'))

        dialog().close()

        expect(dialog().querySelector('img').hasAttribute('src')).toBe(false)
        expect(dialog().querySelector('img').hasAttribute('srcset')).toBe(false)
    })

    it('has a close button named in the language the page loaded it in', () => {
        document.head.innerHTML = '<script type="module" data-arte-lightbox data-close="Schließen"></script>'

        click(page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" alt="A"></a></p>'))

        const button = dialog().querySelector('button')

        expect(button.getAttribute('aria-label')).toBe('Schließen')
        expect(button.type).toBe('button')
        // A drawn cross rather than a typed one, and silent: the label is the button's name.
        expect(button.textContent).toBe('')
        expect(button.querySelector('svg').getAttribute('aria-hidden')).toBe('true')
    })

    it('is built once, however many pictures are opened', () => {
        click(page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" alt="A"></a></p>'))
        dialog().close()
        click(page('<p><a href="/b.png" class="fi-arte-lightbox"><img src="/b.png" alt="B"></a></p>'))

        expect(document.querySelectorAll('dialog.fi-arte-lightbox-dialog')).toHaveLength(1)
        expect(dialog().querySelector('img').getAttribute('src')).toBe('/b.png')
    })

    it('does not carry one picture\'s sizes over to the next', () => {
        // Opened straight from one to the other, as a page script might: nothing closed the
        // box in between to clear it.
        open(page('<p><a href="/a.png" class="fi-arte-lightbox"><img src="/a.png" srcset="/a-640.png 640w" alt="A"></a></p>'))
        open(page('<p><a href="/b.png" class="fi-arte-lightbox"><img src="/b.png" alt="B"></a></p>'))

        expect(dialog().querySelector('img').hasAttribute('srcset')).toBe(false)
    })
})

describe('installing', () => {
    it('listens once, however often it is asked', () => {
        const root = { addEventListener: vi.fn() }

        install(root)
        install(root)

        expect(root.addEventListener).toHaveBeenCalledOnce()
    })
})
