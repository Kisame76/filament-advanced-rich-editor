import { afterEach, describe, expect, it } from 'vitest'
import { MARGIN, MIN_HEIGHT, positionMenu } from '../../resources/js/menu-position.js'

/**
 * Turning a dropdown upwards, and capping it, where there is no room for it below.
 *
 * Every menu in this package used to carry this as a few kilobytes of JavaScript inside its
 * own `x-data` - two dozen copies per editor, measured at a hundred kilobytes of every
 * editor's markup. It is one module now, and the menus call it.
 *
 * jsdom lays nothing out, so every box is stated: where the trigger is, how tall the menu is,
 * which ancestor clips. And it does not compute `overflow` the way a browser does - unset is
 * an empty string rather than `visible`, and the shorthand is not expanded - so each ancestor
 * says what a browser would report for it.
 */

const scenes = []

afterEach(() => {
    for (const element of scenes.splice(0)) {
        element.remove()
    }
})

function scene({ viewport = 800, clip = null, trigger, height, wrapper = { top: 0 } }) {
    Object.defineProperty(window, 'innerHeight', { value: viewport, configurable: true })

    const host = document.createElement('div')

    if (clip === null) {
        host.style.overflow = 'visible'
    } else {
        host.style.overflow = 'auto'
        host.style.overflowY = 'auto'
        host.getBoundingClientRect = () => clip
    }

    const triggerElement = document.createElement('button')
    triggerElement.getBoundingClientRect = () => trigger

    const menu = document.createElement('div')
    Object.defineProperty(menu, 'offsetHeight', { value: height })

    const wrapperElement = document.createElement('div')
    wrapperElement.getBoundingClientRect = () => wrapper
    Object.defineProperty(menu, 'offsetParent', { value: wrapper === null ? null : wrapperElement })

    host.append(triggerElement, menu)
    document.body.append(host)
    scenes.push(host)

    const ticks = []

    const component = {
        dropUp: true,
        $refs: { trigger: triggerElement, menu },
        $nextTick: (callback) => ticks.push(callback),
    }

    return {
        component,
        menu,
        // What Alpine does on its next tick, run on demand so a test can look in between.
        settle: () => {
            for (const callback of ticks.splice(0)) {
                callback()
            }
        },
    }
}

describe('turning a menu over', () => {
    it('measures from the downward position, so a turned menu does not measure where it was put', () => {
        const { component } = scene({ trigger: { top: 80, bottom: 100 }, height: 200 })

        positionMenu(component)

        // Reset straight away, before anything is measured.
        expect(component.dropUp).toBe(false)
    })

    it('leaves a menu that fits below where it is', () => {
        const { component, menu, settle } = scene({ trigger: { top: 80, bottom: 100 }, height: 200 })

        positionMenu(component)
        settle()

        expect(component.dropUp).toBe(false)
        expect(menu.style.maxHeight).toBe('')
    })

    it('turns a menu up where it does not fit below and there is more room above', () => {
        const { component, menu, settle } = scene({ trigger: { top: 700, bottom: 720 }, height: 300 })

        positionMenu(component)
        settle()

        expect(component.dropUp).toBe(true)
        expect(menu.style.maxHeight).toBe('')
    })

    it('measures against the nearest ancestor that clips, not only the window', () => {
        // The window has room below; the editor's own scrolling box does not.
        const { component, settle } = scene({
            clip: { top: 100, bottom: 400 },
            trigger: { top: 330, bottom: 350 },
            height: 200,
        })

        positionMenu(component)
        settle()

        expect(component.dropUp).toBe(true)
    })
})

describe('capping a menu neither side can hold', () => {
    it('places it against the box and caps it to the box, rather than squeezing it into one side', () => {
        // 182 pixels above the trigger and 182 below, in a box of 400 - and a menu of 500.
        const { component, menu, settle } = scene({
            clip: { top: 0, bottom: 400 },
            trigger: { top: 190, bottom: 210 },
            height: 500,
            wrapper: { top: 50 },
        })

        positionMenu(component)
        settle()

        const box = 400 - 0 - 2 * MARGIN

        expect(component.dropUp).toBe(false)
        expect(menu.style.maxHeight).toBe(`${box}px`)
        expect(menu.style.overflowY).toBe('auto')
        // Against the top of the box, measured from the wrapper the menu is positioned in.
        expect(menu.style.insetBlockStart).toBe(`${0 + MARGIN - 50}px`)
        expect(menu.style.insetBlockEnd).toBe('auto')
    })

    it('forgets the last cap before it measures, so a menu does not ratchet shorter', () => {
        const { component, menu, settle } = scene({ trigger: { top: 80, bottom: 100 }, height: 200 })

        menu.style.maxHeight = '50px'
        menu.style.overflowY = 'auto'

        positionMenu(component)
        settle()

        expect(menu.style.maxHeight).toBe('')
        expect(menu.style.overflowY).toBe('')
    })

    it('keeps a menu on the roomier side when it fits on neither', () => {
        // Too tall for the 272 pixels below, and there are only 92 above: turning it over
        // would trade a menu cut short for one cut shorter.
        const { component, menu, settle } = scene({
            viewport: 400,
            trigger: { top: 100, bottom: 120 },
            height: 300,
            wrapper: null,
        })

        positionMenu(component)
        settle()

        expect(component.dropUp).toBe(false)
        expect(menu.style.maxHeight).toBe(`${400 - 120 - MARGIN}px`)
    })

    it('never caps a menu below the height at which it stops being one', () => {
        // No positioned wrapper to place it against, and almost no room on the bigger side.
        const { component, menu, settle } = scene({
            viewport: 120,
            trigger: { top: 50, bottom: 70 },
            height: 300,
            wrapper: null,
        })

        positionMenu(component)
        settle()

        expect(menu.style.maxHeight).toBe(`${MIN_HEIGHT}px`)
        expect(menu.style.overflowY).toBe('auto')
    })

    it('does nothing where there is no trigger or no menu to measure', () => {
        const component = { dropUp: true, $refs: {}, $nextTick: (callback) => callback() }

        expect(() => positionMenu(component)).not.toThrow()
        expect(component.dropUp).toBe(false)
    })
})
