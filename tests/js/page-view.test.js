import { afterEach, describe, expect, it, vi } from 'vitest'
import pageViewExtension, {
    effectiveZoom,
    fitZoom,
    percent,
    readMode,
    STORAGE_KEY,
    settingsOf,
    stepFrom,
    writeMode,
    zoomFor,
} from '../../resources/js/page-view.js'

/**
 * The page view's zoom: how large the sheet is drawn, where it starts, what the buttons step
 * through and what the browser remembers. The sheet itself is the stylesheet's and is
 * asserted from PHP - see `PageViewTest`.
 */

afterEach(() => {
    vi.unstubAllGlobals()
    delete window.FilamentRichEditor
    window.localStorage.clear()
})

const memory = () => {
    const values = new Map()

    return {
        getItem: (key) => (values.has(key) ? values.get(key) : null),
        setItem: (key, value) => values.set(key, String(value)),
    }
}

describe('the settings the field hands over', () => {
    it('are the width of the sheet and the words on the bar', () => {
        const element = document.createElement('div')
        element.dataset.artePage = JSON.stringify({ width: 210, labels: { fit: 'Fit width' } })

        expect(settingsOf(element)).toEqual({ width: 210, labels: { fit: 'Fit width' } })
    })

    it('are nothing where there is no sheet to read', () => {
        const element = document.createElement('div')

        expect(settingsOf(element)).toBeNull()

        element.dataset.artePage = '{broken'
        expect(settingsOf(element)).toBeNull()

        element.dataset.artePage = JSON.stringify({ width: 0 })
        expect(settingsOf(element)).toBeNull()
    })
})

describe('the size of the sheet', () => {
    it('steps up and down through the sizes on the bar, from anywhere between them', () => {
        expect(stepFrom(1, 1)).toBe(1.1)
        expect(stepFrom(1, -1)).toBe(0.9)
        expect(stepFrom(0.8, 1)).toBe(0.9)
        expect(stepFrom(0.8, -1)).toBe(0.75)
        expect(stepFrom(2, 1)).toBe(2)
        expect(stepFrom(0.5, -1)).toBe(0.5)
    })

    it('fills the room there is when fitted, within a quarter and twice the size', () => {
        // An A4 sheet is 793.7 pixels wide.
        expect(fitZoom(397, 210)).toBe(0.5)
        expect(fitZoom(1587, 210)).toBe(1.99)
        expect(fitZoom(100, 210)).toBe(0.25)
        expect(fitZoom(0, 210)).toBe(1)
    })

    it('starts at the width that fits, but never larger than the sheet itself', () => {
        expect(zoomFor('auto', 400, 210)).toBe(0.5)
        expect(zoomFor('auto', 1600, 210)).toBe(1)
        expect(zoomFor('fit', 1600, 210)).toBe(2)
        expect(zoomFor(1.25, 400, 210)).toBe(1.25)
        expect(zoomFor(9, 400, 210)).toBe(2)
    })

    it('writes the size as a percentage', () => {
        expect(percent(1)).toBe('100%')
        expect(percent(0.666)).toBe('67%')
    })

    it('multiplies every zoom on the way up, as the browser draws it', () => {
        const outer = document.createElement('div')
        const inner = document.createElement('div')

        outer.append(inner)

        expect(effectiveZoom(inner, (node) => ({ zoom: node === outer ? '2' : '0.5' }))).toBe(1)
        expect(effectiveZoom(inner, () => ({ zoom: 'normal' }))).toBe(1)
    })
})

describe('what the browser remembers', () => {
    it('is the size somebody chose, or fitting, for every field', () => {
        const storage = memory()

        writeMode(storage, 1.5)
        expect(readMode(storage)).toBe(1.5)

        writeMode(storage, 'fit')
        expect(readMode(storage)).toBe('fit')
    })

    it('starts fresh from anything it cannot read, or cannot reach', () => {
        const storage = memory()

        storage.setItem(STORAGE_KEY, 'huge')
        expect(readMode(storage)).toBe('auto')

        storage.setItem(STORAGE_KEY, '40')
        expect(readMode(storage)).toBe('auto')

        const refusing = {
            getItem: () => {
                throw new Error('denied')
            },
            setItem: () => {
                throw new Error('denied')
            },
        }

        expect(readMode(refusing)).toBe('auto')
        expect(() => writeMode(refusing, 2)).not.toThrow()
        expect(readMode(null)).toBe('auto')
    })
})

describe('the bar', () => {
    const start = ({ settings = { width: 210, labels: { sheet: 'A4 · Portrait', zoomIn: 'Zoom in', fit: 'Fit width' } }, room = 1000 } = {}) => {
        window.FilamentRichEditor = { tiptap: { core: { Extension: { create: (definition) => definition } } } }

        const desk = document.createElement('div')
        const sheet = document.createElement('div')

        sheet.className = 'tiptap'
        desk.append(sheet)

        if (settings) {
            desk.dataset.artePage = JSON.stringify(settings)
        }

        Object.defineProperty(desk, 'clientWidth', { configurable: true, value: room })
        document.body.append(desk)

        const extension = pageViewExtension()

        extension.editor = { options: { element: desk } }
        extension.storage = extension.addStorage()
        extension.onCreate.call(extension)

        return { extension, desk, bar: desk.querySelector('.fi-arte-page-bar') }
    }

    it('names the sheet and starts at its own size where it fits', () => {
        const { desk, bar } = start()

        expect(bar).not.toBeNull()
        expect(desk.firstElementChild).toBe(bar)
        expect(bar.contentEditable).toBe('false')
        expect(bar.querySelector('.fi-arte-page-bar-sheet').textContent).toBe('A4 · Portrait')
        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('1')
        expect(desk.classList.contains('fi-arte-page-overflowing')).toBe(false)
    })

    it('starts smaller on a narrow desk, so it does not have to scroll', () => {
        const { desk } = start({ room: 397 })

        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('0.5')
    })

    it('zooms in step by step, scrolls sideways once the sheet is wider than the desk, and remembers', () => {
        const { desk, bar } = start({ room: 800 })
        const [smaller, size, larger, fit] = bar.querySelectorAll('button')

        larger.click()

        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('1.1')
        expect(size.textContent).toBe('110%')
        expect(desk.classList.contains('fi-arte-page-overflowing')).toBe(true)
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('1.1')

        smaller.click()
        smaller.click()
        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('0.9')

        fit.click()
        expect(fit.getAttribute('aria-pressed')).toBe('true')
        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('1')

        size.click()
        expect(fit.getAttribute('aria-pressed')).toBe('false')
        expect(window.localStorage.getItem(STORAGE_KEY)).toBe('1')
    })

    it('buttons that never submit the form around the field', () => {
        const { bar } = start()

        for (const button of bar.querySelectorAll('button')) {
            expect(button.type).toBe('button')
        }
    })

    it('takes itself away again with the editor', () => {
        const { extension, desk } = start()

        extension.onDestroy.call(extension)

        expect(desk.querySelector('.fi-arte-page-bar')).toBeNull()
        expect(desk.style.getPropertyValue('--fi-arte-page-zoom')).toBe('')
    })

    it('draws nothing on a field without a sheet', () => {
        const { desk } = start({ settings: null })

        expect(desk.querySelector('.fi-arte-page-bar')).toBeNull()
    })

    it('says what is missing instead of breaking the editor without TipTap', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(pageViewExtension()).toBeNull()
        expect(error).toHaveBeenCalledOnce()
    })
})
