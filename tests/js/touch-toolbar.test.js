import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import touchToolbarExtension, {
    capturesFixed,
    DOCKED_CLASS,
    DOCKING_CLASS,
    dockedTop,
    KEYBOARD,
    keepsFocus,
    keyboardIsUp,
    watch,
} from '../../resources/js/touch-toolbar.js'

/**
 * The toolbar on a touch screen while the keyboard is up. jsdom has no keyboard and no visual
 * viewport, so the viewport is stated - how tall the visible part of the page is and where it
 * starts - and the rest is the class, the property and the placeholder the script leaves.
 */

const viewportOf = ({ height = 800, offsetTop = 0, scale = 1 } = {}) =>
    Object.assign(new EventTarget(), { height, offsetTop, scale })

afterEach(() => {
    document.body.innerHTML = ''
    vi.unstubAllGlobals()
})

describe('the keyboard', () => {
    it('is up when the visible part of the page is a keyboard shorter than the window', () => {
        expect(keyboardIsUp(viewportOf({ height: 420 }), 800)).toBe(true)
        // A browser's own bar sliding away is not a keyboard.
        expect(keyboardIsUp(viewportOf({ height: 800 - KEYBOARD }), 800)).toBe(false)
        expect(keyboardIsUp(viewportOf({ height: 744 }), 800)).toBe(false)
        expect(keyboardIsUp(undefined, 800)).toBe(false)
    })

    it('is not up on a page that is zoomed in, which is as short for another reason', () => {
        expect(keyboardIsUp(viewportOf({ height: 420, scale: 1.8 }), 800)).toBe(false)
    })

    it('has the bar sit on it, wherever the visible part has been scrolled to', () => {
        expect(dockedTop(viewportOf({ height: 420 }), 52)).toBe(368)
        expect(dockedTop(viewportOf({ height: 420, offsetTop: 300 }), 52)).toBe(668)
    })
})

describe('what takes a fixed bar away from the window', () => {
    it('is an ancestor that moves, and not the field itself', () => {
        document.body.innerHTML = `<div id="modal"><div class="fi-fo-rich-editor" id="field"><div id="bar"></div></div></div>`

        const bar = document.getElementById('bar')
        const field = document.getElementById('field')

        expect(capturesFixed(bar, field)).toBe(false)

        field.style.transform = 'translateY(0)'
        expect(capturesFixed(bar, field)).toBe(false)

        document.getElementById('modal').style.transform = 'scale(1)'
        expect(capturesFixed(bar, field)).toBe(true)
    })
})

describe('keeping the keyboard', () => {
    beforeEach(() => {
        document.body.innerHTML = `<div><button id="bold"><svg id="icon"></svg></button><input id="size"></div>`
    })

    it('keeps it through a tap on a button while the editor is written in', () => {
        expect(keepsFocus(document.getElementById('bold'), { touch: true, writing: true })).toBe(true)
        expect(keepsFocus(document.getElementById('icon'), { touch: true, writing: true })).toBe(true)
    })

    it('leaves a mouse, an editor nobody is writing in, and a text box alone', () => {
        expect(keepsFocus(document.getElementById('bold'), { touch: false, writing: true })).toBe(false)
        expect(keepsFocus(document.getElementById('bold'), { touch: true, writing: false })).toBe(false)
        expect(keepsFocus(document.getElementById('size'), { touch: true, writing: true })).toBe(false)
    })
})

describe('docking', () => {
    let viewport
    let stop
    let frames

    // What the browser does on its next frame, run on demand.
    const settle = () => {
        for (const callback of frames.splice(0)) {
            callback()
        }
    }

    const field = () => {
        document.body.innerHTML = `
            <div class="fi-fo-rich-editor">
                <div class="fi-fo-rich-editor-toolbar"><button id="bold">B</button></div>
                <div id="text" tabindex="0" contenteditable="true"></div>
            </div>
            <input id="elsewhere">`

        return {
            field: document.querySelector('.fi-fo-rich-editor'),
            toolbar: document.querySelector('.fi-fo-rich-editor-toolbar'),
            text: document.getElementById('text'),
        }
    }

    beforeEach(() => {
        viewport = viewportOf({ height: 420 })
        vi.stubGlobal('innerHeight', 800)
        frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
        vi.stubGlobal('cancelAnimationFrame', () => {})
    })

    afterEach(() => {
        stop?.()
        stop = null
    })

    it('puts the bar on the keyboard while the field is written in, holding its place', () => {
        const { field: wrapper, toolbar, text } = field()
        stop = watch(toolbar, { view: { hasFocus: () => true } }, viewport)

        text.focus()
        settle()

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(true)
        expect(wrapper.classList.contains(DOCKING_CLASS)).toBe(true)
        expect(toolbar.style.getPropertyValue('--fi-arte-docked-top')).toBe('420px')
        expect(toolbar.previousElementSibling?.getAttribute('aria-hidden')).toBe('true')

        // And follows the keyboard as the visible part moves.
        viewport.offsetTop = 120
        viewport.dispatchEvent(new Event('scroll'))
        settle()

        expect(toolbar.style.getPropertyValue('--fi-arte-docked-top')).toBe('540px')
    })

    it('gives the bar back to the field once the focus leaves it, or the keyboard goes', () => {
        const { field: wrapper, toolbar, text } = field()
        stop = watch(toolbar, { view: { hasFocus: () => true } }, viewport)

        text.focus()
        settle()
        document.getElementById('elsewhere').focus()
        settle()

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(false)
        expect(wrapper.classList.contains(DOCKING_CLASS)).toBe(false)
        expect(toolbar.previousElementSibling).toBeNull()

        text.focus()
        settle()
        viewport.height = 800
        viewport.dispatchEvent(new Event('resize'))
        settle()

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(false)
    })

    it('stays where it is in a container that would carry a fixed bar off with it', () => {
        const { toolbar, text } = field()
        document.body.style.transform = 'scale(1)'
        stop = watch(toolbar, { view: { hasFocus: () => true } }, viewport)

        text.focus()
        settle()

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(false)
        document.body.style.transform = ''
    })

    it('keeps the focus in the text through a tap on a button, and only for a finger', () => {
        const { toolbar } = field()
        stop = watch(toolbar, { view: { hasFocus: () => true } }, viewport)

        const press = (pointerType) => {
            const down = new Event('pointerdown', { bubbles: true })
            down.pointerType = pointerType
            document.getElementById('bold').dispatchEvent(down)

            const mouse = new MouseEvent('mousedown', { bubbles: true, cancelable: true })
            document.getElementById('bold').dispatchEvent(mouse)

            return mouse.defaultPrevented
        }

        expect(press('touch')).toBe(true)
        expect(press('mouse')).toBe(false)
    })

    it('lets go of everything when it is stopped', () => {
        const { toolbar, text } = field()
        stop = watch(toolbar, { view: { hasFocus: () => true } }, viewport)

        text.focus()
        settle()
        stop()
        stop = null

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(false)

        text.focus()
        viewport.dispatchEvent(new Event('resize'))
        settle()

        expect(toolbar.classList.contains(DOCKED_CLASS)).toBe(false)
    })
})

describe('the extension', () => {
    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('does nothing at all on a mouse', () => {
        window.FilamentRichEditor = { tiptap: { core: { Extension: { create: (definition) => definition } } } }
        vi.stubGlobal('matchMedia', () => ({ matches: false }))

        const extension = touchToolbarExtension()
        extension.storage = extension.addStorage()
        extension.editor = { options: { element: document.createElement('div') } }
        extension.onCreate.call(extension)

        expect(extension.storage.stop).toBeNull()
    })

    it('says so when Filament did not expose TipTap', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(touchToolbarExtension()).toBeNull()
    })
})
