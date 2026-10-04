import { afterEach, describe, expect, it } from 'vitest'
import stickyToolbarExtension, { isStuck, STUCK_CLASS, scrollParent, watch } from '../../resources/js/sticky-toolbar.js'

/**
 * Telling a pinned toolbar from one that is resting at the top of its field.
 *
 * A sticky bar keeps the rounded top corners of the field it sits in, which is right while
 * it is at the top of that field and wrong once it is pinned under the page header: the
 * field's top edge has scrolled away, and the corners cut two notches out of a bar that now
 * spans the window. CSS has no way to ask whether a sticky box is stuck in every browser,
 * so an IntersectionObserver asks instead and the bar gets a class while it is.
 *
 * jsdom has no IntersectionObserver, and none is needed for what is tested here: which
 * entries count as stuck, how the observer is set up, and that the class follows it.
 */

class FakeObserver {
    static last = null

    constructor(callback, options) {
        this.callback = callback
        this.options = options
        this.observed = []
        this.disconnected = false
        FakeObserver.last = this
    }

    observe(element) {
        this.observed.push(element)
    }

    disconnect() {
        this.disconnected = true
    }

    report(entry) {
        this.callback([entry])
    }
}

// An entry as the observer reports one: where the bar is, and where the shrunken root starts.
const entry = ({ top, ratio, rootTop = 65 }) => ({
    intersectionRatio: ratio,
    boundingClientRect: { top },
    rootBounds: { top: rootTop },
})

afterEach(() => {
    document.body.innerHTML = ''
    FakeObserver.last = null
    delete window.FilamentRichEditor
    delete window.IntersectionObserver
})

describe('what counts as stuck', () => {
    it('is a bar held at its offset, its top edge just above the shrunken root', () => {
        // Offset 64px; the root is shrunk by one pixel more, so a bar held at 64 pokes out.
        expect(isStuck(entry({ top: 64, ratio: 0.98 }))).toBe(true)
    })

    it('is not a bar resting in its field further down', () => {
        expect(isStuck(entry({ top: 300, ratio: 1 }))).toBe(false)
    })

    it('is not a bar cut off by the bottom of the window on its way in', () => {
        // Partly hidden too, but at the other edge - the field has not reached the top yet.
        expect(isStuck(entry({ top: 880, ratio: 0.4 }))).toBe(false)
    })

    it('is nothing the observer could not measure', () => {
        expect(isStuck({ intersectionRatio: 0.5, boundingClientRect: { top: 0 }, rootBounds: null })).toBe(false)
    })
})

describe('the scrolling box a bar is pinned in', () => {
    it('is the nearest ancestor that scrolls, such as a modal', () => {
        document.body.innerHTML = '<div id="modal" style="overflow-y: auto"><div><div id="bar"></div></div></div>'

        expect(scrollParent(document.getElementById('bar'))).toBe(document.getElementById('modal'))
    })

    it('is the window when nothing in between scrolls', () => {
        document.body.innerHTML = '<div><div id="bar"></div></div>'

        expect(scrollParent(document.getElementById('bar'))).toBeNull()
    })
})

describe('watching a bar', () => {
    const toolbar = () => {
        document.body.innerHTML = '<div class="fi-fo-rich-editor-toolbar fi-arte-sticky" style="position: sticky; top: 64px"></div>'

        return document.querySelector('.fi-fo-rich-editor-toolbar')
    }

    it('shrinks the root by the offset the bar sticks at, plus one pixel', () => {
        const bar = toolbar()

        watch(bar, FakeObserver)

        expect(FakeObserver.last.observed).toEqual([bar])
        expect(FakeObserver.last.options).toMatchObject({ root: null, rootMargin: '-65px 0px 0px 0px', threshold: [1] })
    })

    it('marks the bar while it is stuck, and only then', () => {
        const bar = toolbar()

        watch(bar, FakeObserver)
        FakeObserver.last.report(entry({ top: 64, ratio: 0.98 }))

        expect(bar.classList.contains(STUCK_CLASS)).toBe(true)

        FakeObserver.last.report(entry({ top: 300, ratio: 1 }))

        expect(bar.classList.contains(STUCK_CLASS)).toBe(false)
    })

    it('lets go of the bar when it is stopped', () => {
        const bar = toolbar()
        const stop = watch(bar, FakeObserver)

        FakeObserver.last.report(entry({ top: 64, ratio: 0.98 }))
        stop()

        expect(FakeObserver.last.disconnected).toBe(true)
        expect(bar.classList.contains(STUCK_CLASS)).toBe(false)
    })
})

describe('the extension', () => {
    const field = () => {
        document.body.innerHTML = `
            <div class="fi-fo-rich-editor fi-arte">
                <div>
                    <div class="fi-fo-rich-editor-toolbar fi-arte-sticky" style="top: 64px"></div>
                    <div class="fi-fo-rich-editor-main"><div x-ref="editor" id="mount"></div></div>
                </div>
            </div>`

        window.IntersectionObserver = FakeObserver
        window.FilamentRichEditor = { tiptap: { core: { Extension: { create: (definition) => definition } } } }

        return stickyToolbarExtension()
    }

    it('carries a name of its own', () => {
        expect(field().name).toBe('arteStickyToolbar')
    })

    it('watches the bar of the field its editor is mounted in, and stops with the editor', () => {
        const extension = field()
        const context = { editor: { options: { element: document.getElementById('mount') } }, storage: extension.addStorage() }

        extension.onCreate.call(context)

        expect(FakeObserver.last.observed).toEqual([document.querySelector('.fi-fo-rich-editor-toolbar')])

        extension.onDestroy.call(context)

        expect(FakeObserver.last.disconnected).toBe(true)
    })

    it('does nothing on a field whose bar is not pinned', () => {
        const extension = field()

        document.querySelector('.fi-fo-rich-editor-toolbar').classList.remove('fi-arte-sticky')

        const context = { editor: { options: { element: document.getElementById('mount') } }, storage: extension.addStorage() }

        extension.onCreate.call(context)

        expect(FakeObserver.last).toBeNull()
    })
})
