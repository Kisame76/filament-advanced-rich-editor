import { describe, expect, it, vi } from 'vitest'
import colorPicker from '../../resources/js/color-picker.js'

/**
 * The two colour pickers: the text colour and the background behind it.
 *
 * One component serves both because only three things differ between them: which mark is
 * written, which command writes it, and the argument that command takes. The text colour rides
 * on Filament's own `textColor` mark, which keeps the value in `data-color` and is set with an
 * object; the background is this package's `textBackground` mark, a plain `color` attribute set
 * with a bare string. Getting either half of that wrong writes nothing, silently.
 *
 * These used to be checked as strings in the PHP that assembled the script. They are run here,
 * against an editor that records the chain it is asked for.
 */

const PALETTE = [
    { value: 'red', label: 'Red', color: '#dc2626', darkColor: '#f87171' },
    { value: 'ink', label: 'Ink', color: '#18181b', darkColor: '#f4f4f5' },
]

function editorWith({ empty = false, attributes = {} } = {}) {
    const calls = []

    const chain = new Proxy(
        {},
        {
            get: (_, name) =>
                name === 'run'
                    ? () => {
                          calls.push(['run'])

                          return true
                      }
                    : (...args) => {
                          calls.push([name, ...args])

                          return chain
                      },
        },
    )

    return {
        calls,
        state: { selection: { empty } },
        getAttributes: (mark) => attributes[mark] ?? {},
        chain: () => chain,
    }
}

function mount(mode, editor) {
    return Object.assign(colorPicker({ mode, colors: PALETTE, menuPosition: null, menuUpClass: 'fi-arte-menu-up' }), {
        $getEditor: () => editor,
    })
}

describe('the text colour', () => {
    it('reads the colour at the caret off Filament’s data attribute', () => {
        const picker = mount('text', editorWith({ attributes: { textColor: { 'data-color': 'red' } } }))

        picker.sync()

        expect(picker.current).toBe('red')
    })

    it('writes through Filament’s command, which takes an object', () => {
        const editor = editorWith()
        const picker = mount('text', editor)

        picker.open = true
        picker.apply('red')

        expect(editor.calls).toEqual([['focus'], ['setTextColor', { color: 'red' }], ['run']])
        expect(picker.current).toBe('red')
        expect(picker.open).toBe(false)
    })

    it('clears through Filament’s command', () => {
        const editor = editorWith()
        const picker = mount('text', editor)

        picker.current = 'red'
        picker.clear()

        expect(editor.calls).toEqual([['focus'], ['unsetTextColor'], ['run']])
        expect(picker.current).toBeNull()
    })
})

describe('the background', () => {
    it('reads the colour off the package’s own plain attribute', () => {
        const picker = mount('background', editorWith({ attributes: { textBackground: { color: '#fef08a' } } }))

        picker.sync()

        expect(picker.current).toBe('#fef08a')
    })

    it('writes through the package’s command, which takes the colour itself', () => {
        const editor = editorWith()

        mount('background', editor).apply('#fef08a')

        expect(editor.calls).toEqual([['focus'], ['setTextBackground', '#fef08a'], ['run']])
    })

    it('clears through the package’s command', () => {
        const editor = editorWith()

        mount('background', editor).clear()

        expect(editor.calls).toEqual([['focus'], ['unsetTextBackground'], ['run']])
    })
})

describe('without a selection', () => {
    it('recolours the whole run under the caret, which is what a click into a word means', () => {
        const editor = editorWith({ empty: true })

        mount('text', editor).apply('ink')

        expect(editor.calls).toEqual([['focus'], ['extendMarkRange', 'textColor'], ['setTextColor', { color: 'ink' }], ['run']])
    })

    it('clears the whole run too', () => {
        const editor = editorWith({ empty: true })

        mount('background', editor).clear()

        expect(editor.calls).toEqual([['focus'], ['extendMarkRange', 'textBackground'], ['unsetTextBackground'], ['run']])
    })
})

describe('the swatch on the trigger', () => {
    it('paints a palette entry in its colour, and a free colour as itself', () => {
        const picker = mount('text', editorWith())

        expect(picker.swatch('red')).toBe('#dc2626')
        expect(picker.swatch('#123456')).toBe('#123456')
    })

    it('shows no colour where the text has none', () => {
        const picker = mount('text', editorWith())

        picker.current = 'red'
        picker.sync()

        expect(picker.current).toBeNull()
    })
})

describe('an editor that is not there yet', () => {
    it('writes nothing and keeps what it showed', () => {
        const picker = mount('text', null)

        picker.current = 'red'
        picker.apply('ink')
        picker.clear()
        picker.sync()

        expect(picker.current).toBeNull()
    })
})

describe('the menu', () => {
    it('is turned with the class the field hands over, and measured by the module it names', async () => {
        const picker = colorPicker({ mode: 'text', colors: [], menuPosition: 'data:text/javascript,export const positionMenu = (c) => { c.measured = true }', menuUpClass: 'fi-arte-menu-up' })

        picker.dropUp = true
        picker.positionMenu()

        expect(picker.dropUp).toBe(false)
        expect(picker.menuUpClass).toBe('fi-arte-menu-up')

        await vi.waitFor(() => expect(picker.measured).toBe(true))
    })
})
