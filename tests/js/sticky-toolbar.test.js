import { afterEach, describe, expect, it, vi } from 'vitest'
import stickyToolbarExtension, {
    colourBehind,
    isStuck,
    STUCK_CLASS,
    scrollParent,
    watch,
} from '../../resources/js/sticky-toolbar.js'

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

    it('measures the colour behind it again when the page turns light or dark', () => {
        let theme = null

        class FakeMutations {
            constructor(callback) {
                theme = this
                this.callback = callback
            }

            observe(target, options) {
                this.target = target
                this.options = options
            }

            disconnect() {
                this.disconnected = true
            }
        }

        const stop = watch(toolbar(), FakeObserver, FakeMutations)

        // Filament turns the page dark with a class on the root.
        expect(theme.target).toBe(document.documentElement)
        expect(theme.options).toEqual({ attributes: true, attributeFilter: ['class'] })
        expect(() => theme.callback()).not.toThrow()

        stop()

        expect(theme.disconnected).toBe(true)
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

/**
 * A one-pixel canvas that lays `rgb()` and `rgba()` over each other the way a browser's does -
 * jsdom has no canvas. A colour it cannot read leaves the last one in place, as a browser's does.
 */
function canvas() {
    let pixel = [0, 0, 0, 0]
    let fill = [0, 0, 0, 0]

    return {
        set fillStyle(colour) {
            const match = /^rgba?\(([^)]+)\)$/.exec(colour)

            if (colour === 'transparent') {
                fill = [0, 0, 0, 0]
            } else if (match) {
                const [red, green, blue, alpha = 1] = match[1].split(/[\s,/]+/).map(Number)
                fill = [red, green, blue, alpha]
            }
        },
        clearRect: () => {
            pixel = [0, 0, 0, 0]
        },
        fillRect: () => {
            const alpha = fill[3] + pixel[3] * (1 - fill[3])
            const over = (index) =>
                alpha === 0 ? 0 : (fill[index] * fill[3] + pixel[index] * pixel[3] * (1 - fill[3])) / alpha

            pixel = [over(0), over(1), over(2), alpha]
        },
        getImageData: () => ({ data: [...pixel.slice(0, 3).map(Math.round), Math.round(pixel[3] * 255)] }),
    }
}

describe('the colour behind a bar', () => {
    const stack = (...colours) => {
        document.body.innerHTML = ''
        let parent = document.body

        for (const colour of colours) {
            const layer = document.createElement('div')
            layer.style.backgroundColor = colour
            parent.append(layer)
            parent = layer
        }

        const bar = document.createElement('div')
        bar.style.backgroundColor = 'rgb(255, 0, 0)'
        parent.append(bar)

        return bar
    }

    it('lays every background from the bar outwards over the next, down to the first opaque one', () => {
        // A field straight on a dark page, and the same field in a section: one grey each,
        // which is the whole reason this is measured rather than written down.
        expect(colourBehind(stack('rgb(9, 9, 11)', 'rgba(255, 255, 255, 0.05)'), canvas())).toBe('rgb(21 21 23)')
        expect(
            colourBehind(stack('rgb(9, 9, 11)', 'rgb(24, 24, 27)', 'rgba(255, 255, 255, 0.05)', 'rgba(255, 255, 255, 0.05)'), canvas()),
        ).toBe('rgb(47 47 49)')
    })

    it('leaves out the bar itself, and whatever is transparent', () => {
        expect(colourBehind(stack('rgb(255, 255, 255)', 'rgba(0, 0, 0, 0)'), canvas())).toBe('rgb(255 255 255)')
    })

    it('cannot tell where nothing behind the bar is opaque, or there is no canvas', () => {
        expect(colourBehind(stack('rgba(255, 255, 255, 0.05)'), canvas())).toBeNull()
        expect(colourBehind(stack('rgb(9, 9, 11)'), null)).toBeNull()
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
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
        const extension = field()
        const context = { editor: { options: { element: document.getElementById('mount') } }, storage: extension.addStorage() }

        extension.onCreate.call(context)

        // From the next frame. Starting means reading the bar's computed `top` and walking up
        // for a box that scrolls, and inside the editor's own start that asks the browser for
        // styles at once - measured, half a second on a page of twenty editors, paying for
        // every other field's changes. Nothing is stuck while a page is still loading.
        expect(FakeObserver.last).toBeNull()

        frames.shift()()

        expect(FakeObserver.last.observed).toEqual([document.querySelector('.fi-fo-rich-editor-toolbar')])

        extension.onDestroy.call(context)

        expect(FakeObserver.last.disconnected).toBe(true)
        vi.unstubAllGlobals()
    })

    it('never starts for an editor that was gone before its frame came', () => {
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))
        vi.stubGlobal('cancelAnimationFrame', (id) => {
            frames[id - 1] = () => {}
        })
        const extension = field()
        const context = { editor: { options: { element: document.getElementById('mount') } }, storage: extension.addStorage() }

        extension.onCreate.call(context)
        extension.onDestroy.call(context)
        frames.shift()()

        expect(FakeObserver.last).toBeNull()
        vi.unstubAllGlobals()
    })

    it('does nothing on a field whose bar is not pinned', () => {
        const extension = field()

        document.querySelector('.fi-fo-rich-editor-toolbar').classList.remove('fi-arte-sticky')

        const context = { editor: { options: { element: document.getElementById('mount') } }, storage: extension.addStorage() }

        extension.onCreate.call(context)

        expect(FakeObserver.last).toBeNull()
    })
})
