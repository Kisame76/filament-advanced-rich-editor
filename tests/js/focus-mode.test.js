import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import focusModeExtension, { center, centeringDelta, focusedBlocks, scrollerOf } from '../../resources/js/focus-mode.js'
import { documentOf as doc, leafNode as leaf, blockNode as node, textNode as text } from './helpers.js'

/**
 * Writing with the rest of the document out of the way: which blocks stay bright, and how far
 * the view moves to keep the line being written in the middle. Both are functions over the
 * document and the layout, so they are tested against a document counted the way ProseMirror
 * counts one, and a layout that says where things are.
 */

const paragraph = (...children) => node('paragraph', children, { textblock: true })
const list = (...items) => node('bulletList', items.map((item) => node('listItem', [item])))

// `<p>one</p>` is 0-5, `<p>two</p>` 5-10, `<p>three</p>` 10-17.
const three = () => doc(paragraph(text('one')), paragraph(text('two')), paragraph(text('three')))

const caret = (pos) => ({ from: pos, to: pos, head: pos })

describe('where the focus is', () => {
    it('is the paragraph the caret is in', () => {
        expect(focusedBlocks({ doc: three(), selection: caret(7) })).toEqual([{ from: 5, to: 10 }])
        // At either end of its text, and not the neighbour it touches.
        expect(focusedBlocks({ doc: three(), selection: caret(9) })).toEqual([{ from: 5, to: 10 }])
        expect(focusedBlocks({ doc: three(), selection: caret(6) })).toEqual([{ from: 5, to: 10 }])
    })

    it('is every paragraph a selection reaches into', () => {
        // A selection never hides its own text.
        expect(focusedBlocks({ doc: three(), selection: { from: 2, to: 12, head: 12 } })).toEqual([
            { from: 0, to: 5 },
            { from: 5, to: 10 },
            { from: 10, to: 17 },
        ])
    })

    it('is the item in a list, not the whole list', () => {
        // `<ul>` opens at 0, the first `<li>` at 1 and its `<p>` at 2.
        const document = doc(list(paragraph(text('one')), paragraph(text('two'))))

        expect(focusedBlocks({ doc: document, selection: caret(3) })).toEqual([{ from: 2, to: 7 }])
    })

    it('is a picture or an embed that is selected as a whole', () => {
        const embed = leaf('embed')

        expect(
            focusedBlocks({ doc: three(), selection: { from: 5, to: 6, head: 6, node: { ...embed, isInline: false } } }),
        ).toEqual([{ from: 5, to: 6 }])
    })
})

describe('where the line goes', () => {
    it('moves the line to the middle of the view', () => {
        // A 20px line at 700 in a view from 0 to 800: its middle is at 710, the view's at 400.
        expect(centeringDelta(700, 720, 0, 800)).toBe(310)
        expect(centeringDelta(100, 120, 0, 800)).toBe(-290)
        // Measured from where the view starts on the page.
        expect(centeringDelta(700, 720, 100, 800)).toBe(210)
    })

    it('leaves a line that is all but in the middle alone', () => {
        // A line that jitters while it is typed into is worse than one a hair off.
        expect(centeringDelta(390, 410, 0, 800)).toBe(0)
        expect(centeringDelta(392, 414, 0, 800)).toBe(0)
    })

    it('scrolls the element that scrolls, or the page', () => {
        const outer = document.createElement('div')
        const inner = document.createElement('div')
        const editor = document.createElement('div')

        outer.append(inner)
        inner.append(editor)
        document.body.append(outer)

        expect(scrollerOf(editor)).toBeNull()

        outer.style.overflowY = 'auto'
        Object.defineProperty(outer, 'scrollHeight', { value: 2000 })
        Object.defineProperty(outer, 'clientHeight', { value: 800 })

        expect(scrollerOf(editor)).toBe(outer)

        // The editor itself, which is what scrolls in fullscreen.
        editor.style.overflowY = 'auto'
        Object.defineProperty(editor, 'scrollHeight', { value: 1600 })
        Object.defineProperty(editor, 'clientHeight', { value: 1000 })

        expect(scrollerOf(editor)).toBe(editor)

        outer.remove()
    })

    it('jumps while typing and glides when the caret was moved', () => {
        const scrolled = []
        const view = {
            dom: document.createElement('div'),
            state: { selection: { head: 1 } },
            coordsAtPos: () => ({ top: 700, bottom: 720 }),
        }

        vi.stubGlobal('scrollBy', (options) => scrolled.push(options))
        vi.stubGlobal('innerHeight', 800)

        center(view, { typing: true })
        center(view)

        expect(scrolled).toEqual([
            { top: 310, behavior: 'instant' },
            { top: 310, behavior: 'smooth' },
        ])

        vi.unstubAllGlobals()
    })
})

describe('the extension', () => {
    const transaction = () => {
        const meta = {}

        return { meta, setMeta: (key, value) => (meta[key.name ?? key] = value) }
    }

    let extension

    beforeEach(() => {
        window.FilamentRichEditor = {
            tiptap: {
                core: { Extension: { create: (definition) => definition } },
                pmState: {
                    Plugin: class {
                        constructor(spec) {
                            this.spec = spec
                        }
                    },
                    PluginKey: class {
                        constructor(name) {
                            this.name = name
                        }
                    },
                },
                pmView: {
                    Decoration: { node: (from, to, attrs) => ({ from, to, attrs }) },
                    DecorationSet: { empty: 'empty', create: (_document, decorations) => decorations },
                },
            },
        }

        extension = focusModeExtension()
        extension.storage = extension.addStorage()
    })

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    const plugin = () => extension.addProseMirrorPlugins()[0]

    it('starts off, and draws nothing while it is', () => {
        expect(extension.storage.on).toBe(false)
        expect(plugin().spec.props.decorations({ doc: three(), selection: caret(7) })).toBe('empty')
        expect(plugin().spec.props.attributes()).toEqual({})
    })

    it('switches only when the command is actually run', () => {
        const command = extension.addCommands().toggleFocusMode()

        // `can()` asks without a dispatch, and asking must not change anything.
        expect(command({ tr: transaction(), dispatch: undefined })).toBe(true)
        expect(extension.storage.on).toBe(false)

        const tr = transaction()
        command({ tr, dispatch: () => {} })

        expect(extension.storage).toEqual({ on: true, pending: true })
        expect(tr.meta.arteFocusMode).toBe(true)
    })

    it('marks the editor and the block in focus once it is on', () => {
        extension.storage.on = true

        expect(plugin().spec.props.attributes()).toEqual({ class: 'fi-arte-focus-mode' })
        expect(plugin().spec.props.decorations({ doc: three(), selection: caret(7) })).toEqual([
            { from: 5, to: 10, attrs: { class: 'fi-arte-focus-current' } },
        ])
    })

    describe('keeping the line in the middle', () => {
        let frames

        const editorView = ({ focused = true, doc: document = three(), selection = caret(7) } = {}) => ({
            hasFocus: () => focused,
            state: { doc: document, selection: { ...selection, eq: (other) => other.head === selection.head } },
        })

        const previously = (view, { selection = view.state.selection, same = true } = {}) => ({
            doc: { eq: () => same },
            selection,
        })

        beforeEach(() => {
            frames = 0
            vi.stubGlobal('requestAnimationFrame', () => ++frames)
            vi.stubGlobal('cancelAnimationFrame', () => {})
        })

        afterEach(() => {
            vi.unstubAllGlobals()
        })

        it('does nothing while it is off, or while the editor has no focus', () => {
            const view = plugin().spec.view()

            view.update(editorView(), previously(editorView(), { same: false }))
            extension.storage.on = true
            view.update(editorView({ focused: false }), previously(editorView(), { same: false }))

            expect(frames).toBe(0)
        })

        it('centres after an edit or a move, and not after a transaction that changed neither', () => {
            extension.storage.on = true
            const view = plugin().spec.view()
            const current = editorView()

            view.update(current, previously(current))
            expect(frames).toBe(0)

            view.update(current, previously(current, { same: false }))
            view.update(current, previously(current, { selection: { head: 2, eq: () => false } }))
            expect(frames).toBe(2)
        })

        it('centres as it is switched on, without waiting for a keystroke', () => {
            extension.storage.on = true
            extension.storage.pending = true
            const view = plugin().spec.view()
            const current = editorView()

            view.update(current, previously(current))

            expect(frames).toBe(1)
            expect(extension.storage.pending).toBe(false)
        })
    })
})
