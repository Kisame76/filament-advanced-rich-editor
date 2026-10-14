import { afterEach, describe, expect, it, vi } from 'vitest'
import fontSizePicker from '../../resources/js/font-size-picker.js'

/**
 * The toolbar's font size: a number to type into, and a menu of the sizes anyone picks.
 *
 * The number mirrors the size at the caret. Text without a size of its own still has one -
 * the theme's, or a heading's - so where the mark says nothing, the size is measured off the
 * rendered text; a guess would make the first change go the wrong way. Typing a size and
 * clicking back into the text applies on the way out, by which time the click has moved the
 * caret - so the selection the field was reached from is remembered and put back first.
 *
 * This used to be JavaScript inside the field's `x-data`, checked as strings. It is run here,
 * against an editor that records the chain it is asked for.
 */

const SETTINGS = { min: 8, max: 96, step: 1, fallback: 16, unit: 'px', menuPosition: null, menuUpClass: 'fi-arte-menu-up' }

afterEach(() => {
    document.body.innerHTML = ''
})

// An editor whose caret sits in `text`, rendered inside an element of the given font size.
function editorWith({ marked = null, renderedSize = null, view = true } = {}) {
    const calls = []
    const paragraph = document.createElement('p')

    paragraph.textContent = 'Some text'

    if (renderedSize) {
        paragraph.style.fontSize = renderedSize
    }

    document.body.append(paragraph)

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
        state: { selection: { from: 3, toJSON: () => ({ type: 'text', anchor: 2, head: 6 }) } },
        getAttributes: (mark) => (mark === 'fontSize' && marked ? { size: marked } : {}),
        view: view ? { domAtPos: () => ({ node: paragraph.firstChild, offset: 2 }) } : null,
        chain: () => chain,
    }
}

function mount(editor, settings = {}) {
    const restored = []
    const picker = Object.assign(fontSizePicker({ ...SETTINGS, ...settings }), {
        $getEditor: () => editor,
        // The field's own method, from the scope the picker sits in.
        setEditorSelection: (selection) => restored.push(selection),
    })

    return { picker, restored }
}

describe('the number at the caret', () => {
    it('is the size the text was given', () => {
        const { picker } = mount(editorWith({ marked: '24px' }))

        picker.sync()

        expect(picker.size).toBe(24)
        expect(picker.isMarked).toBe(true)
    })

    it('is the size the text is drawn in where it was given none', () => {
        const { picker } = mount(editorWith({ renderedSize: '14px' }))

        picker.sync()

        expect(picker.size).toBe(14)
        expect(picker.isMarked).toBe(false)
    })

    it('follows the editor without measuring in the middle of its tick', () => {
        // The editor's tick is every transaction, and on a page of editors it is every one of
        // them starting up. Measuring there asks the browser for styles at once, which makes it
        // pay for every change any field made since it last drew - measured, 35 of those on a
        // page of twenty editors, 1.3 of the 3.5 seconds the page spent on styles. So the tick
        // reads the mark at once, which is free, and measures in the next frame, once.
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))

        try {
            const { picker } = mount(editorWith({ renderedSize: '14px' }))
            const measure = vi.spyOn(picker, 'measure')

            picker.follow()
            picker.follow()
            picker.follow()

            expect(measure).not.toHaveBeenCalled()
            expect(picker.size).toBe(16)
            expect(frames).toHaveLength(1)

            frames.shift()()

            expect(measure).toHaveBeenCalledTimes(1)
            expect(picker.size).toBe(14)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('gives the frame nothing to write that the tick reads, so the tick cannot set itself off', () => {
        // Alpine runs `follow()` as an effect and re-runs it whenever a property it read
        // changes. A flag the effect reads and the frame then resets is exactly that: measured
        // in a running panel, 121 measurements a second on an idle editor, one per frame,
        // for good. So what the frame writes and what the tick reads must not meet.
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))

        try {
            const { picker } = mount(editorWith({ renderedSize: '14px' }))
            const read = new Set()
            const written = new Set()
            let recording = null
            const watched = new Proxy(picker, {
                get(target, key, receiver) {
                    if (recording === 'read' && typeof key === 'string') {
                        read.add(key)
                    }

                    return Reflect.get(target, key, receiver)
                },
                set(target, key, value, receiver) {
                    if (recording === 'write' && typeof key === 'string') {
                        written.add(key)
                    }

                    return Reflect.set(target, key, value, receiver)
                },
            })

            recording = 'read'
            watched.follow()
            recording = 'write'
            frames.shift()()
            recording = null

            const functions = new Set(Object.keys(picker).filter((key) => typeof picker[key] === 'function'))

            expect([...written].filter((key) => read.has(key) && !functions.has(key))).toEqual([])
            expect(written).toContain('size')
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('reads a size the text was given at once, with nothing left to measure', () => {
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))

        try {
            const { picker } = mount(editorWith({ marked: '24px' }))

            picker.follow()

            expect(picker.size).toBe(24)
            expect(frames).toHaveLength(0)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('does not measure over a number somebody started typing before the frame came', () => {
        const frames = []
        vi.stubGlobal('requestAnimationFrame', (callback) => frames.push(callback))

        try {
            const { picker } = mount(editorWith({ renderedSize: '14px' }))

            picker.follow()
            picker.size = 30
            picker.edited()
            frames.shift()()

            expect(picker.size).toBe(30)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('falls back to the configured size where nothing can be measured', () => {
        const { picker } = mount(editorWith({ view: false }), { fallback: 18 })

        picker.sync()

        expect(picker.size).toBe(18)
    })
})

describe('applying a size', () => {
    it('writes the size with its unit to the selection the field was reached from', () => {
        const editor = editorWith()
        const { picker, restored } = mount(editor)

        picker.capture()
        picker.open = true
        picker.apply('24')

        expect(restored).toEqual([{ type: 'text', anchor: 2, head: 6 }])
        expect(picker.selection).toBeNull()
        expect(editor.calls).toEqual([['focus'], ['setFontSize', '24px'], ['run']])
        expect(picker.size).toBe(24)
        expect(picker.open).toBe(false)
    })

    it('keeps a size inside the bounds, rounded', () => {
        const editor = editorWith()
        const { picker } = mount(editor, { min: 10, max: 40, unit: 'pt' })

        picker.apply(4)
        picker.apply(400)
        picker.apply('12.6')

        expect(editor.calls.filter(([name]) => name === 'setFontSize')).toEqual([
            ['setFontSize', '10pt'],
            ['setFontSize', '40pt'],
            ['setFontSize', '13pt'],
        ])
    })

    it('keeps the size in force when what was typed is not a number', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = mount(editor)

        picker.apply('big')

        expect(editor.calls).toContainEqual(['setFontSize', '14px'])
    })

    it('does not put back a selection nobody took', () => {
        const { picker, restored } = mount(editorWith())

        picker.apply(12)

        expect(restored).toEqual([])
    })
})

describe('going back to the theme’s size', () => {
    it('removes the mark instead of writing the number the theme happens to use', () => {
        const editor = editorWith({ marked: '24px' })
        const { picker, restored } = mount(editor)

        picker.capture()
        picker.open = true
        picker.clear()

        expect(restored).toHaveLength(1)
        expect(editor.calls).toEqual([['focus'], ['unsetFontSize'], ['run']])
        expect(picker.open).toBe(false)
    })
})

describe('the menu', () => {
    it('is turned with the class the field hands over, and measured by the module it names', async () => {
        const picker = fontSizePicker({
            ...SETTINGS,
            menuPosition: 'data:text/javascript,export const positionMenu = (c) => { c.measured = true }',
        })

        picker.dropUp = true
        picker.positionMenu()

        expect(picker.dropUp).toBe(false)
        expect(picker.menuUpClass).toBe('fi-arte-menu-up')

        await vi.waitFor(() => expect(picker.measured).toBe(true))
    })

    it('opens on the configured size before anything is measured', () => {
        expect(fontSizePicker({ ...SETTINGS, fallback: 18 }).size).toBe(18)
    })
})

describe('leaving the field', () => {
    // The field applies what was typed on the way out. Picking from the menu and `Default`
    // focus the editor, which is a way out too - so leaving used to write the number on show
    // straight back: `Default` ended as a mark of the theme's own size, and merely clicking
    // into the field and back out marked the text with the size it already had.
    const reached = (editor) => {
        const { picker, restored } = mount(editor)

        picker.capture()
        picker.enter()

        return { picker, restored }
    }

    const writes = (editor) => editor.calls.filter(([name]) => name === 'setFontSize' || name === 'unsetFontSize')

    it('applies what was typed', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.size = 30
        picker.edited()
        picker.leave()

        expect(writes(editor)).toEqual([['setFontSize', '30px']])
    })

    it('leaves the text alone when nothing was typed', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.sync()
        picker.leave()

        expect(writes(editor)).toEqual([])
    })

    it('does not write the size on show back after Default', () => {
        const editor = editorWith({ marked: '24px', renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.clear()
        picker.size = 14
        picker.leave()

        expect(writes(editor)).toEqual([['unsetFontSize']])
    })

    it('does not write twice after a size was picked from the menu', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.apply(24)
        picker.leave()

        expect(writes(editor)).toEqual([['setFontSize', '24px']])
    })

    it('throws away what was typed on Escape', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.size = 30
        picker.edited()
        picker.cancel()
        picker.leave()

        expect(writes(editor)).toEqual([])
        expect(picker.size).toBe(14)
    })

    it('keeps what is being typed when the editor ticks in between', () => {
        // Every transaction re-reads the size at the caret - an autosave, a collaborator -
        // and a re-read in the middle of typing would replace the typed number with the old.
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.size = 30
        picker.edited()
        picker.sync()
        picker.leave()

        expect(writes(editor)).toEqual([['setFontSize', '30px']])
    })

    it('applies again the next time the field is reached', () => {
        const editor = editorWith({ renderedSize: '14px' })
        const { picker } = reached(editor)

        picker.apply(24)
        picker.leave()
        picker.enter()
        picker.size = 36
        picker.edited()
        picker.leave()

        expect(writes(editor)).toEqual([['setFontSize', '24px'], ['setFontSize', '36px']])
    })
})
