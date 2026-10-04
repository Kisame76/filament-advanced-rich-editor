import { afterEach, describe, expect, it, vi } from 'vitest'
import imagePanel from '../../resources/js/image-panel.js'

/**
 * The two panels of the image toolbar: the alt text and caption, and the width and height.
 *
 * Both used to be JavaScript inside their own `x-data`, assembled as a PHP string and checked
 * with `toContain()` - which proves a line of code is present, never what it does. The panel is
 * a module now, so what it does is run here: against a stubbed editor that records what is
 * written, the way the toolbar's own commands would be called.
 *
 * Alpine gives a component its magics and the scope it sits in; the panel reads the editor
 * through `$getEditor()` from the field around it. Both are handed over here.
 */

// A document with one picture at position 3, and an editor that records what is written.
function editorWith(attributes = {}, { selectionFrom = 3, unlocked = false, rendered = null } = {}) {
    const writes = []
    const image = { type: { name: 'image' }, attrs: attributes }

    const chain = {
        setNodeSelection(position) {
            writes.push(['select', position])

            return chain
        },
        updateAttributes(type, values) {
            writes.push(['update', type, values])

            return chain
        },
        run: () => true,
    }

    return {
        writes,
        state: {
            selection: { from: selectionFrom },
            doc: { nodeAt: (position) => (position === 3 ? image : { type: { name: 'paragraph' }, attrs: {} }) },
        },
        storage: { arteImageResize: { unlocked } },
        view: { nodeDOM: () => rendered },
        chain: () => chain,
    }
}

function mount(mode, editor, root = document.createElement('div')) {
    const panel = imagePanel({ mode, menuPosition: null, menuUpClass: 'fi-arte-menu-up' })

    return Object.assign(panel, {
        $getEditor: () => editor,
        $root: root,
        $nextTick: (callback) => callback(),
    })
}

afterEach(() => {
    vi.restoreAllMocks()
})

describe('finding the picture', () => {
    it('reads the picture the selection is on', () => {
        const panel = mount('alt', editorWith({ alt: 'A cat' }))

        panel.anchor()
        panel.read()

        expect(panel.alt).toBe('A cat')
    })

    it('keeps writing to the picture it opened on after the selection has moved', () => {
        // Focusing a field collapses the node selection to a caret. Asking the selection at
        // that point wrote the alt text wherever the caret ended up.
        const editor = editorWith({ alt: '' })
        const panel = mount('alt', editor)

        panel.anchor()
        editor.state.selection.from = 9
        panel.alt = 'A cat'
        panel.commit()

        expect(editor.writes).toEqual([['select', 3], ['update', 'image', { alt: 'A cat', caption: null }]])
    })

    it('writes nothing when the picture has gone from under the open panel', () => {
        const editor = editorWith({})
        const panel = mount('alt', editor)

        panel.anchored = 7
        panel.alt = 'A cat'
        panel.commit()

        expect(editor.writes).toEqual([])
    })
})

describe('the alt text and caption', () => {
    it('removes an empty one rather than storing it', () => {
        // The renderer drops falsy attributes on both sides, so an empty alt cannot be stored.
        const editor = editorWith({ alt: 'A cat', caption: 'Mine' })
        const panel = mount('alt', editor)

        panel.anchor()
        panel.alt = '   '
        panel.caption = 'Still mine'
        panel.commit()

        expect(editor.writes.at(-1)).toEqual(['update', 'image', { alt: null, caption: 'Still mine' }])
    })

    it('writes when focus leaves the panel, not when it moves between its fields', () => {
        const root = document.createElement('div')
        const field = document.createElement('input')

        root.append(field)

        const editor = editorWith({})
        const panel = mount('alt', editor, root)

        panel.anchor()
        panel.commitOnLeaving({ relatedTarget: field })

        expect(editor.writes).toEqual([])

        panel.commitOnLeaving({ relatedTarget: document.body })

        expect(editor.writes).toHaveLength(2)
    })
})

describe('the width and height', () => {
    it('reads the stored size, and the drawn one for a picture never sized', () => {
        const rendered = document.createElement('div')
        const img = document.createElement('img')

        rendered.append(img)
        Object.defineProperty(img, 'offsetWidth', { value: 640 })
        Object.defineProperty(img, 'offsetHeight', { value: 480 })

        const sized = mount('size', editorWith({ width: '200', height: '100' }))
        const unsized = mount('size', editorWith({}, { rendered }))

        sized.anchor()
        sized.read()
        unsized.anchor()
        unsized.read()

        expect([sized.width, sized.height, sized.ratio]).toEqual([200, 100, 2])
        expect([unsized.width, unsized.height]).toEqual([640, 480])
    })

    it('carries the ratio over to the other field while it is locked', () => {
        const panel = mount('size', editorWith({ width: '200', height: '100' }))

        panel.anchor()
        panel.read()
        panel.link('width', '300')

        expect([panel.width, panel.height]).toEqual([300, 150])

        panel.link('height', '50')

        expect([panel.width, panel.height]).toEqual([100, 50])
    })

    it('lets the fields go their own way once unlocked', () => {
        const panel = mount('size', editorWith({ width: '200', height: '100' }, { unlocked: true }))

        panel.anchor()
        panel.read()
        panel.link('width', '300')

        expect(panel.locked).toBe(false)
        expect([panel.width, panel.height]).toEqual([300, 100])
    })

    it('applies both at once, and only a size that differs and makes sense', () => {
        // With the ratio locked, writing each field on its own would undo the other one
        // before the second number had been typed.
        const editor = editorWith({ width: '200', height: '100' })
        const panel = mount('size', editor)

        panel.anchor()
        panel.read()

        expect(panel.isDirty()).toBe(false)

        panel.link('width', '0')

        expect(panel.isDirty()).toBe(false)

        panel.link('width', '400')
        panel.open = true
        panel.apply()

        expect(editor.writes).toEqual([['select', 3], ['update', 'image', { width: 400, height: 200 }]])
        expect(panel.open).toBe(false)
        expect(panel.ratio).toBe(2)
    })

    it('resets the picture to its own size', () => {
        const editor = editorWith({ width: '200', height: '100' })
        const panel = mount('size', editor)

        panel.anchor()
        panel.reset()

        expect(editor.writes).toEqual([['select', 3], ['update', 'image', { width: null, height: null }]])
    })

    it('flips the one lock both switches share, and tells the other one', () => {
        const editor = editorWith({ width: '200', height: '100' })
        const panel = mount('size', editor)
        const heard = vi.fn()

        window.addEventListener('arte-image-lock', heard)
        panel.anchor()
        panel.read()
        panel.toggleLock()
        window.removeEventListener('arte-image-lock', heard)

        expect(editor.storage.arteImageResize.unlocked).toBe(true)
        expect(panel.locked).toBe(false)
        expect(heard.mock.calls[0][0].detail).toEqual({ unlocked: true })
    })

    it('has no commit on blur, because it writes on its own button', () => {
        expect(mount('size', editorWith({})).commit).toBeUndefined()
    })
})

describe('the menu', () => {
    it('is turned with the class the field hands over, and measured by the module it names', async () => {
        const panel = imagePanel({ mode: 'alt', menuPosition: 'data:text/javascript,export const positionMenu = (c) => { c.measured = true }', menuUpClass: 'fi-arte-menu-up' })

        panel.dropUp = true
        panel.positionMenu()

        // Reset before the module is asked, so a menu turned last time does not open turned.
        expect(panel.dropUp).toBe(false)
        expect(panel.menuUpClass).toBe('fi-arte-menu-up')

        await vi.waitFor(() => expect(panel.measured).toBe(true))
    })
})
