import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import imageResizeExtension from '../../resources/js/image-resize.js'

/**
 * What the module adds to Filament's resizable pictures: a size readout while a handle is
 * held, a switch that frees the drag from the ratio, a drag that still works on a turned
 * picture, a bar that stays open through all of it, and a mark on a picture that did not load.
 *
 * Everything the module does is done to elements TipTap made, so the tests build the same
 * shape - a container, the wrapper in it, the picture and its handles, with the node view TipTap
 * keeps on the container - and press on it with real events. The node view is a small stand-in
 * that records what it was asked to do; the two things it reads on every step of a drag,
 * `preserveAspectRatio` and `activeHandle`, are the ones the module works through.
 */

const nodeSelection = () => ({
    isSelectable: vi.fn(() => true),
    create: vi.fn((_doc, position) => ({ selectedAt: position })),
})

const build = ({ selection = nodeSelection() } = {}) => {
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
                ...(selection ? { NodeSelection: selection } : {}),
            },
        },
    }

    return { definition: imageResizeExtension(), selection }
}

/**
 * A transaction as far as the module builds one: it records what it was told.
 */
const transaction = () => {
    const tr = {
        meta: {},
        selection: null,
        markup: null,
        setMeta: (key, value) => {
            tr.meta[key] = value

            return tr
        },
        setSelection: (selection) => {
            tr.selection = selection

            return tr
        },
        setNodeMarkup: (...args) => {
            tr.markup = args

            return tr
        },
    }

    return tr
}

const editorViewFor = ({ node = { type: { name: 'image' }, attrs: {} }, selection = { from: -1, node: null } } = {}) => {
    const dom = document.createElement('div')

    document.body.append(dom)

    const state = {
        doc: { nodeAt: vi.fn(() => node) },
        selection,
        get tr() {
            return transaction()
        },
    }

    return { dom, state, dispatch: vi.fn() }
}

const live = []

const mount = ({ selection, node, editorSelection, storage } = {}) => {
    const { definition, selection: pmSelection } = build({ selection })
    const editorView = editorViewFor({ node, selection: editorSelection })
    const shared = storage ?? { unlocked: false, resizing: false }
    const [plugin] = definition.addProseMirrorPlugins.call({ storage: shared })
    const view = plugin.spec.view(editorView)

    live.push(view)

    return { definition, plugin, view, editorView, storage: shared, nodeSelection: pmSelection }
}

/**
 * TipTap's node view, reduced to what the module touches. `handleResize` is a method on the
 * prototype, as TipTap's is, so that removing the module's own replacement gives the real one
 * back; every call is recorded together with the handle that was active when it was made.
 */
class NodeViewDouble {
    constructor(properties) {
        this.calls = []
        Object.assign(this, properties)
    }

    handleResize(deltaX, deltaY) {
        this.calls.push({ deltaX, deltaY, handle: this.activeHandle })

        return 'resized'
    }
}

/**
 * A picture in the editor, the way TipTap draws it.
 */
const picture = (
    editorView,
    { rotate = 0, width = null, height = null, handles = ['bottom-right'], position = 5, offset = null, extra = {}, nest = false } = {},
) => {
    const container = document.createElement('div')
    const wrapper = document.createElement('div')
    const image = document.createElement('img')
    const handleElements = {}

    container.dataset.resizeContainer = ''
    wrapper.dataset.resizeWrapper = ''
    wrapper.append(image)

    for (const name of handles) {
        const handle = document.createElement('div')

        handle.dataset.resizeHandle = name
        wrapper.append(handle)
        handleElements[name] = handle
    }

    if (offset) {
        Object.defineProperty(image, 'offsetWidth', { configurable: true, value: offset[0] })
        Object.defineProperty(image, 'offsetHeight', { configurable: true, value: offset[1] })
    }

    container.append(wrapper)

    const nodeView = new NodeViewDouble({
        node: { attrs: { rotate, width, height } },
        getPos: () => position,
        element: image,
        preserveAspectRatio: true,
        isShiftKeyPressed: false,
        activeHandle: 'bottom-right',
        minSize: undefined,
        ...extra,
    })

    if (nest) {
        // The node view is on an outer element, and the nearest container is somebody else's.
        const outer = document.createElement('div')

        outer.pmViewDesc = { spec: nodeView }
        container.pmViewDesc = { spec: {} }
        outer.append(container)
        editorView.dom.append(outer)
    } else {
        container.pmViewDesc = { spec: nodeView }
        editorView.dom.append(container)
    }

    return { container, wrapper, image, handles: handleElements, handle: handleElements[handles[0]], nodeView }
}

const press = (target, { button = 0, shiftKey = false } = {}) => {
    const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true, button, shiftKey })

    target.dispatchEvent(event)

    return event
}

const touch = (target) => {
    const event = new Event('touchstart', { bubbles: true, cancelable: true })

    target.dispatchEvent(event)

    return event
}

const release = () => document.dispatchEvent(new MouseEvent('mouseup'))

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
})

afterEach(() => {
    // Every drag a test left running is ended, so that none of them is heard by the next one.
    release()
    vi.runAllTimers()

    for (const view of live.splice(0)) {
        view.destroy()
    }

    delete window.FilamentRichEditor
    vi.useRealTimers()
    document.body.replaceChildren()
})

describe('the extension', () => {
    it('is named for what it assists, and starts with the ratio locked and no drag running', () => {
        const { definition, plugin } = mount()

        expect(definition.name).toBe('arteImageResize')
        expect(definition.addStorage()).toEqual({ unlocked: false, resizing: false })
        expect(plugin.spec.key.name).toBe('arteImageResize')
    })

    it('says so rather than throwing when TipTap is not there', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})

        delete window.FilamentRichEditor
        expect(imageResizeExtension()).toBeNull()

        window.FilamentRichEditor = { tiptap: {} }
        expect(imageResizeExtension()).toBeNull()

        expect(complaint).toHaveBeenCalledTimes(2)
    })
})

describe('keeping the image toolbar open', () => {
    const create = ({ resizing = false } = {}) => {
        const { definition } = build()
        const dispatched = []
        const editor = {
            state: { tr: transaction() },
            view: { dispatch: (tr) => dispatched.push(tr) },
        }

        definition.onCreate.call({ editor, storage: { resizing } })

        return dispatched
    }

    /**
     * The rule the module installs, asked the way the floating toolbar asks it.
     */
    const rule = (state, options = {}) => {
        const [tr] = create(options)

        return (editor, element) => tr.meta['floatingToolbar::image'].options.shouldShow({ editor: { ...state, ...editor }, element })
    }

    it('sends the widened rule for the image toolbar, and nothing else, without a history entry', () => {
        const [tr, ...others] = create()

        expect(others).toHaveLength(0)
        expect(tr.meta.addToHistory).toBe(false)
        expect(Object.keys(tr.meta).sort()).toEqual(['addToHistory', 'floatingToolbar::image'])
        expect(tr.meta['floatingToolbar::image'].type).toBe('updateOptions')
        expect(typeof tr.meta['floatingToolbar::image'].options.shouldShow).toBe('function')
    })

    it('shows the bar while the picture is active and the editor has the focus', () => {
        expect(rule({})({ isActive: (name) => name === 'image', isFocused: true })).toBe(true)
    })

    it('keeps the bar while the focus is in the bar itself, which typing into an input does', () => {
        const bar = document.createElement('div')
        const input = document.createElement('input')

        bar.append(input)
        document.body.append(bar)
        input.focus()

        expect(rule({})({ isActive: () => true, isFocused: false }, bar)).toBe(true)
    })

    it('hides it when the focus is somewhere else altogether', () => {
        const bar = document.createElement('div')
        const elsewhere = document.createElement('input')

        document.body.append(bar, elsewhere)
        elsewhere.focus()

        expect(rule({})({ isActive: () => true, isFocused: false }, bar)).toBe(false)
        expect(rule({})({ isActive: () => true, isFocused: false })).toBe(false)
    })

    it('does not show it for anything but a picture', () => {
        expect(rule({})({ isActive: (name) => name === 'link', isFocused: true })).toBe(false)
    })

    it('holds it open for as long as a drag is running, whatever else is true', () => {
        // The transaction that commits a drag lands with the picture briefly unselected.
        expect(rule({}, { resizing: true })({ isActive: () => false, isFocused: false })).toBe(true)
    })
})

describe('a picture that did not load', () => {
    const decided = (image, { complete, naturalWidth }) => {
        Object.defineProperty(image, 'complete', { configurable: true, value: complete })
        Object.defineProperty(image, 'naturalWidth', { configurable: true, value: naturalWidth })
    }

    it('marks the wrapper broken when the file did not come, and loaded when it did', () => {
        const { editorView } = mount()
        const broken = picture(editorView)
        const fine = picture(editorView)

        decided(broken.image, { complete: true, naturalWidth: 0 })
        decided(fine.image, { complete: true, naturalWidth: 640 })
        broken.image.dispatchEvent(new Event('error'))
        fine.image.dispatchEvent(new Event('load'))

        expect(broken.wrapper.getAttribute('data-arte-image')).toBe('error')
        expect(fine.wrapper.getAttribute('data-arte-image')).toBe('loaded')
    })

    it('hears both events although neither of them bubbles', () => {
        const { editorView } = mount()
        const { image, wrapper } = picture(editorView)

        decided(image, { complete: true, naturalWidth: 10 })
        image.dispatchEvent(new Event('load', { bubbles: false }))
        expect(wrapper.getAttribute('data-arte-image')).toBe('loaded')

        decided(image, { complete: true, naturalWidth: 0 })
        image.dispatchEvent(new Event('error', { bubbles: false }))
        expect(wrapper.getAttribute('data-arte-image')).toBe('error')
    })

    it('ignores anything that is not a picture', () => {
        const { editorView } = mount()
        const { wrapper } = picture(editorView)

        wrapper.dispatchEvent(new Event('load'))

        expect(wrapper.hasAttribute('data-arte-image')).toBe(false)
    })

    it('marks nothing that is not inside a resize wrapper', () => {
        // ProseMirror puts an `img` of its own after an inline node at the end of a line, with
        // no source. Marking it hands the paragraph an attribute ProseMirror takes for an edit.
        const { editorView } = mount()
        const paragraph = document.createElement('p')
        const separator = document.createElement('img')

        paragraph.append(separator)
        editorView.dom.append(paragraph)
        decided(separator, { complete: true, naturalWidth: 0 })
        separator.dispatchEvent(new Event('error'))

        expect(editorView.dom.querySelector('[data-arte-image]')).toBeNull()
        expect(paragraph.hasAttribute('data-arte-image')).toBe(false)
    })

    it('writes the mark only when it changes, since it sits in the editable DOM', () => {
        const { editorView } = mount()
        const { image, wrapper } = picture(editorView)
        const write = vi.spyOn(wrapper, 'setAttribute')

        decided(image, { complete: true, naturalWidth: 10 })
        image.dispatchEvent(new Event('load'))
        image.dispatchEvent(new Event('load'))
        image.dispatchEvent(new Event('load'))

        expect(write).toHaveBeenCalledTimes(1)
    })

    it('reads the pictures that were decided before it started, once, and only those', () => {
        const { editorView } = mount()
        const cached = picture(editorView)
        const pending = picture(editorView)

        decided(cached.image, { complete: true, naturalWidth: 50 })
        decided(pending.image, { complete: false, naturalWidth: 0 })
        vi.runAllTimers()

        expect(cached.wrapper.getAttribute('data-arte-image')).toBe('loaded')
        expect(pending.wrapper.hasAttribute('data-arte-image')).toBe(false)
    })

    it('does not sweep once it has been taken away, and stops listening to both events', () => {
        const { editorView, view } = mount()
        const { image, wrapper } = picture(editorView)

        decided(image, { complete: true, naturalWidth: 50 })
        view.destroy()
        vi.runAllTimers()
        image.dispatchEvent(new Event('load'))
        decided(image, { complete: true, naturalWidth: 0 })
        image.dispatchEvent(new Event('error'))

        expect(wrapper.hasAttribute('data-arte-image')).toBe(false)
    })
})

describe('a turned picture that was not measured', () => {
    const load = ({ rotate = 90, width = null, height = null, offset = [300, 200], node, getPos } = {}) => {
        const { editorView } = mount({ node })
        const shown = picture(editorView, { rotate, width, height, offset, extra: getPos === undefined ? {} : { getPos } })

        Object.defineProperty(shown.image, 'complete', { configurable: true, value: true })
        Object.defineProperty(shown.image, 'naturalWidth', { configurable: true, value: 600 })
        shown.image.dispatchEvent(new Event('load'))

        return { editorView, ...shown }
    }

    const pinned = (editorView) => editorView.dispatch.mock.calls.map(([tr]) => tr)

    it('takes the size it has on screen the moment the file arrives', () => {
        const node = { type: { name: 'image' }, attrs: { src: 'a.png', rotate: 90 } }
        const { editorView } = load({ node })
        const [tr] = pinned(editorView)

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
        expect(tr.meta.addToHistory).toBe(false)
        expect(tr.markup).toEqual([5, undefined, { src: 'a.png', rotate: 90, width: 300, height: 200 }])
    })

    it('pins it as soon as one of the two is missing', () => {
        const { editorView } = load({ width: 300, height: null })

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
    })

    it.each([
        ['a picture that is not turned', { rotate: 0 }],
        ['a picture that already has both', { width: '300', height: 200 }],
        ['a picture that has not been laid out', { offset: [0, 0] }],
        ['a picture with a width but no height on screen', { offset: [300, 0] }],
        ['a picture with a height but no width on screen', { offset: [0, 200] }],
        ['a node that has no way to say where it is', { getPos: null }],
    ])('leaves %s alone', (_, options) => {
        const { editorView } = load(options)

        expect(editorView.dispatch).not.toHaveBeenCalled()
    })

    it('leaves it alone where the position no longer holds a picture', () => {
        expect(load({ node: { type: { name: 'paragraph' }, attrs: {} } }).editorView.dispatch).not.toHaveBeenCalled()
        expect(load({ node: null }).editorView.dispatch).not.toHaveBeenCalled()
    })

    it('leaves it alone where the position is not a number', () => {
        expect(load({ getPos: () => undefined }).editorView.dispatch).not.toHaveBeenCalled()
    })

    it('does nothing about a picture that failed to load', () => {
        const { editorView } = mount()
        const shown = picture(editorView, { rotate: 90, offset: [300, 200] })

        Object.defineProperty(shown.image, 'complete', { configurable: true, value: true })
        Object.defineProperty(shown.image, 'naturalWidth', { configurable: true, value: 0 })
        shown.image.dispatchEvent(new Event('error'))

        expect(editorView.dispatch).not.toHaveBeenCalled()
    })
})

describe('taking hold of a handle', () => {
    it('ignores a press that is not on a handle', () => {
        const { editorView, storage } = mount()
        const { wrapper, nodeView } = picture(editorView)

        press(wrapper)

        expect(storage.resizing).toBe(false)
        expect(nodeView.minSize).toBeUndefined()
    })

    it('lets only the primary button drag, and keeps every other from reaching the handle', () => {
        // The node view starts a resize on any button, so a right click would resize the
        // picture behind its own context menu, without the corrections.
        const { editorView, storage } = mount()
        const { handle } = picture(editorView)
        const heard = vi.fn()

        handle.addEventListener('mousedown', heard)

        press(handle, { button: 2 })
        expect(storage.resizing).toBe(false)
        expect(heard).not.toHaveBeenCalled()

        press(handle, { button: 0 })
        expect(storage.resizing).toBe(true)
        expect(heard).toHaveBeenCalledTimes(1)
    })

    it('starts on a touch, which has no button to look at', () => {
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView)

        touch(handle)

        expect(storage.resizing).toBe(true)
        expect(nodeView.isShiftKeyPressed).toBe(false)
    })

    it('does nothing about a handle that no node view stands behind', () => {
        const { editorView, storage } = mount()
        const { handle, container } = picture(editorView)

        delete container.pmViewDesc
        press(handle)

        expect(storage.resizing).toBe(false)
    })

    it('does nothing about a container whose view is not a resizable image', () => {
        const { editorView, storage } = mount()
        const { handle, container } = picture(editorView)

        container.pmViewDesc = { spec: { somethingElse: true } }
        press(handle)

        expect(storage.resizing).toBe(false)
    })

    it('looks further up where the nearest container is not the node view', () => {
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView, { nest: true })

        press(handle)

        expect(storage.resizing).toBe(true)
        expect(nodeView.minSize).toEqual({ width: 8, height: 8 })
    })

    it('holds the bar open, and puts a floor under the size that the node view has none of', () => {
        // Without it `Math.max(undefined, n)` is NaN as soon as the ratio stops forcing a value.
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView)

        press(handle)

        expect(storage.resizing).toBe(true)
        expect(nodeView.minSize).toEqual({ width: 8, height: 8 })
    })

    it('hands every drag a floor of its own', () => {
        const { editorView } = mount()
        const first = picture(editorView)
        const second = picture(editorView)

        press(first.handle)
        press(second.handle)
        first.nodeView.minSize.width = 500

        expect(second.nodeView.minSize.width).toBe(8)
    })

    it('keeps the ratio while the lock is on, and frees it once the switch is off', () => {
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView)

        press(handle)
        expect(nodeView.preserveAspectRatio).toBe(true)

        release()
        vi.runAllTimers()
        storage.unlocked = true
        press(handle)

        expect(nodeView.preserveAspectRatio).toBe(false)
    })

    it('takes the shift key from the press that starts the drag, not from one left over', () => {
        // The node view only listens for shift while a drag runs, so one released after the
        // mouse button leaves the flag standing.
        const { editorView } = mount()
        const { handle, nodeView } = picture(editorView)

        press(handle, { shiftKey: true })
        expect(nodeView.isShiftKeyPressed).toBe(true)

        release()
        vi.runAllTimers()
        press(handle)

        expect(nodeView.isShiftKeyPressed).toBe(false)
    })
})

describe('selecting the picture that is grabbed', () => {
    const grab = (options = {}) => {
        const field = mount(options.mount)
        const shown = picture(field.editorView, options.picture)

        press(shown.handle)

        return { ...field, ...shown }
    }

    it('puts the selection on it, without a history entry, so that the bar opens', () => {
        const { editorView, nodeSelection: pm } = grab()
        const [[tr]] = editorView.dispatch.mock.calls

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
        expect(tr.meta.addToHistory).toBe(false)
        expect(tr.selection).toEqual({ selectedAt: 5 })
        expect(pm.create).toHaveBeenCalledWith(editorView.state.doc, 5)
    })

    it('leaves it alone where the picture is what is selected already', () => {
        const { editorView } = grab({ mount: { editorSelection: { from: 5, node: {} } } })

        expect(editorView.dispatch).not.toHaveBeenCalled()
    })

    it('selects it where some other node is what is selected', () => {
        const { editorView } = grab({ mount: { editorSelection: { from: 9, node: {} } } })

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
    })

    it('selects it where the caret is at the picture, but is not a selection of it', () => {
        const { editorView } = grab({ mount: { editorSelection: { from: 5, node: null } } })

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
    })

    it('leaves it alone where the node cannot be selected, or is no longer there', () => {
        const unselectable = nodeSelection()

        unselectable.isSelectable.mockReturnValue(false)

        expect(grab({ mount: { selection: unselectable } }).editorView.dispatch).not.toHaveBeenCalled()
        expect(grab({ mount: { node: null } }).editorView.dispatch).not.toHaveBeenCalled()
    })

    it('leaves it alone where the node view cannot say where it is', () => {
        expect(grab({ picture: { extra: { getPos: undefined } } }).editorView.dispatch).not.toHaveBeenCalled()
        expect(grab({ picture: { extra: { getPos: () => undefined } } }).editorView.dispatch).not.toHaveBeenCalled()
    })

    it('carries on without it where the build does not expose the selection class', () => {
        const { editorView, storage, nodeView } = grab({ mount: { selection: null } })

        expect(editorView.dispatch).not.toHaveBeenCalled()
        expect(storage.resizing).toBe(true)
        expect(nodeView.preserveAspectRatio).toBe(true)
    })
})

describe('a drag that keeps the ratio', () => {
    const drag = (handleName, deltaX, deltaY, { rotate = 0, shift = false, unlocked = false } = {}) => {
        const { editorView, storage } = mount()
        const shown = picture(editorView, { handles: [handleName], rotate })

        storage.unlocked = unlocked
        press(shown.handle, { shiftKey: shift })

        const returned = shown.nodeView.handleResize(deltaX, deltaY)

        return { ...shown, returned, storage, calls: shown.nodeView.calls }
    }

    it.each([
        // A corner is dragged along the way it points, and it is the longer of the two
        // distances that counts. Straight down on a bottom corner used to do nothing at all.
        ['bottom-right', 10, 30, 30],
        ['bottom-right', 40, 5, 40],
        ['bottom-right', -20, -5, -20],
        ['bottom-left', -40, 5, -40],
        ['bottom-left', 0, 30, -30],
        ['top-right', 10, -30, 30],
        ['top-right', 0, -30, 30],
        ['top-left', -10, -30, -30],
        ['top-left', -30, 0, -30],
        // A side handle only ever answers to the horizontal.
        ['right', 25, 100, 25],
        ['left', -25, 100, -25],
    ])('reads %s dragged by (%i, %i) as one distance of %i, on the width', (handleName, deltaX, deltaY, distance) => {
        const { calls } = drag(handleName, deltaX, deltaY)

        expect(calls).toHaveLength(1)
        expect(calls[0].deltaX).toBe(distance)
        expect(calls[0].deltaY).toBe(0)
    })

    it('does the same in a picture that is turned, since one distance has no direction to get wrong', () => {
        // The pointer is measured in the screen's own frame and a corner keeps its place at
        // the corner of the wrapper at every angle.
        const { calls } = drag('bottom-right', 0, 30, { rotate: 90 })

        expect(calls[0].deltaX).toBe(30)
    })

    it('leaves the handle as it is, since the node view does its own arithmetic on it', () => {
        const { calls } = drag('bottom-left', -40, 5)

        expect(calls[0].handle).toBe('bottom-right')
    })

    it('keeps the ratio too while it is off, for as long as shift is held', () => {
        const { calls } = drag('bottom-right', 10, 30, { unlocked: true, shift: true })

        expect(calls).toHaveLength(1)
        expect(calls[0].deltaX).toBe(30)
        expect(calls[0].deltaY).toBe(0)
    })

    it('gives back what the node view answered', () => {
        expect(drag('bottom-right', 1, 2).returned).toBe('resized')
    })

    it('runs the node view\'s own method, on the node view', () => {
        const { nodeView } = drag('bottom-right', 1, 2)

        expect(nodeView.calls[0].handle).toBe('bottom-right')
    })
})

describe('a drag that is free of the ratio', () => {
    const free = (handleName, deltaX, deltaY, rotate = 0) => {
        const { editorView, storage } = mount()
        const shown = picture(editorView, { handles: [handleName], rotate })

        storage.unlocked = true
        press(shown.handle)

        const returned = shown.nodeView.handleResize(deltaX, deltaY)

        return { ...shown, returned, call: shown.nodeView.calls[0] }
    }

    it('hands the pointer over as it is, on an upright picture', () => {
        const { call } = free('bottom-right', 12, -7)

        expect(call.deltaX).toBe(12)
        expect(call.deltaY).toBe(-7)
        expect(call.handle).toBe('bottom-right')
    })

    it.each([
        // The pointer is read in the element's axes, the inverse of the turn it is drawn with.
        [0, 12, -7, 12, -7],
        [90, 12, -7, -7, -12],
        [180, 12, -7, -12, 7],
        [270, 12, -7, 7, 12],
    ])('at %i degrees turns (%i, %i) into (%i, %i)', (rotate, deltaX, deltaY, expectedX, expectedY) => {
        const { call } = free('bottom-right', deltaX, deltaY, rotate)

        expect(call.deltaX).toBe(expectedX)
        expect(call.deltaY).toBe(expectedY)
    })

    it.each([
        // The corner of the wrapper that is pulled, named as the corner of the element it is.
        ['bottom-right', 0, 'bottom-right'],
        ['bottom-right', 90, 'top-right'],
        ['bottom-right', 180, 'top-left'],
        ['bottom-right', 270, 'bottom-left'],
        ['bottom-left', 0, 'bottom-left'],
        ['bottom-left', 90, 'bottom-right'],
        ['bottom-left', 180, 'top-right'],
        ['bottom-left', 270, 'top-left'],
        ['top-right', 0, 'top-right'],
        ['top-right', 90, 'top-left'],
        ['top-right', 180, 'bottom-left'],
        ['top-right', 270, 'bottom-right'],
        ['top-left', 0, 'top-left'],
        ['top-left', 90, 'bottom-left'],
        ['top-left', 180, 'bottom-right'],
        ['top-left', 270, 'top-right'],
        // And the sides, which have one edge to rename instead of two.
        ['right', 0, 'right'],
        ['right', 90, 'top'],
        ['right', 180, 'left'],
        ['right', 270, 'bottom'],
        ['left', 90, 'bottom'],
        ['left', 180, 'right'],
        ['left', 270, 'top'],
        ['top', 90, 'left'],
        ['top', 180, 'bottom'],
        ['top', 270, 'right'],
        ['bottom', 90, 'right'],
        ['bottom', 180, 'top'],
        ['bottom', 270, 'left'],
    ])('calls %s at %i degrees %s while the node view is at work', (handleName, rotate, renamed) => {
        expect(free(handleName, 5, 5, rotate).call.handle).toBe(renamed)
    })

    it('puts the handle back the moment the node view is done, and says what it answered', () => {
        const { nodeView, returned } = free('bottom-right', 5, 5, 90)

        expect(returned).toBe('resized')
        expect(nodeView.activeHandle).toBe('bottom-right')
    })

    it('puts it back too where the node view fails', () => {
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView, { rotate: 90 })

        storage.unlocked = true
        nodeView.handleResize = function failing() {
            throw new Error('boom')
        }
        press(handle)

        expect(() => nodeView.handleResize(1, 1)).toThrow('boom')
        expect(nodeView.activeHandle).toBe('bottom-right')
    })

    it.each([
        [-90, 270],
        [450, 90],
        [100, 90],
        [44, 0],
        [46, 90],
        [360, 0],
        ['90', 90],
        ['abc', 0],
        [null, 0],
        [undefined, 0],
    ])('reads a turn of %s as %s degrees', (rotate, degrees) => {
        // Told apart by what the delta becomes at each of the four angles.
        const expected = { 0: [12, -7], 90: [-7, -12], 180: [-12, 7], 270: [7, 12] }[degrees]
        const { call } = free('bottom-right', 12, -7, rotate)

        expect([call.deltaX, call.deltaY]).toEqual(expected)
    })
})

describe('one replacement of the node view\'s method per drag', () => {
    it('leaves the method alone for a handle it does not know', () => {
        const { editorView } = mount()
        const { handle, nodeView } = picture(editorView, { handles: ['middle'] })

        press(handle)

        expect(Object.hasOwn(nodeView, 'handleResize')).toBe(false)
    })

    it('replaces it for the length of the drag, and gives the real one back afterwards', () => {
        const { editorView } = mount()
        const { handle, nodeView } = picture(editorView)

        press(handle)
        expect(Object.hasOwn(nodeView, 'handleResize')).toBe(true)

        release()
        vi.runAllTimers()

        expect(Object.hasOwn(nodeView, 'handleResize')).toBe(false)
        expect(nodeView.handleResize(1, 1)).toBe('resized')
    })

    it('takes off a replacement whose drag was never heard to end, rather than wrapping it', () => {
        // Free of the ratio and on a turned picture, where every wrapper turns the pointer once
        // more: a wrapper of a wrapper would turn it twice.
        const { editorView, storage } = mount()
        const { handle, nodeView } = picture(editorView, { rotate: 90 })

        storage.unlocked = true
        press(handle)
        press(handle)
        nodeView.handleResize(12, -7)

        expect(nodeView.calls).toHaveLength(1)
        expect(nodeView.calls[0].deltaX).toBe(-7)
        expect(nodeView.calls[0].deltaY).toBe(-12)
        expect(nodeView.calls[0].handle).toBe('top-right')
    })
})

describe('the size readout', () => {
    const start = (options = {}) => {
        const field = mount()
        const shown = picture(field.editorView, { offset: [400, 300], ...options })

        press(shown.handle)

        return { ...field, ...shown, badge: () => shown.wrapper.querySelector('.fi-arte-image-size') }
    }

    it('appears in the wrapper with the size the picture has, hidden from a screen reader', () => {
        const { badge } = start()

        expect(badge()).not.toBeNull()
        expect(badge().textContent).toBe('400 × 300')
        expect(badge().getAttribute('aria-hidden')).toBe('true')
    })

    it('follows the drag, and shows whole pixels', () => {
        const { badge, nodeView } = start()

        nodeView.onResize(410.4, 307.6)

        expect(badge().textContent).toBe('410 × 308')
    })

    it('passes every step on to a handler that was there before it', () => {
        const previous = vi.fn()
        const { nodeView } = start({ extra: { onResize: previous } })

        nodeView.onResize(410, 308)

        expect(previous).toHaveBeenCalledWith(410, 308)
    })

    it('shows nothing where the handle is not in a wrapper', () => {
        const { editorView } = mount()
        const shown = picture(editorView)

        shown.wrapper.removeAttribute('data-resize-wrapper')
        press(shown.handle)

        expect(document.querySelector('.fi-arte-image-size')).toBeNull()
    })
})

describe('a turned picture while it is dragged', () => {
    const turned = (rotate) => {
        const field = mount()
        const shown = picture(field.editorView, { rotate, offset: [400, 300] })

        press(shown.handle)

        return { ...field, ...shown }
    }

    it('keeps its layout box on the size it has, from one step to the next', () => {
        const { image, nodeView } = turned(90)

        nodeView.onResize(300, 200)

        // Half the difference, one way in the block direction and the other way in the inline.
        expect(image.style.marginBlock).toBe('50px')
        expect(image.style.marginInline).toBe('-50px')

        nodeView.onResize(200, 300)

        expect(image.style.marginBlock).toBe('-50px')
        expect(image.style.marginInline).toBe('50px')
    })

    it('does the same at a quarter turn the other way', () => {
        const { image, nodeView } = turned(270)

        nodeView.onResize(300, 200)

        expect(image.style.marginBlock).toBe('50px')
    })

    it.each([0, 180])('leaves the box alone at %i degrees, where it is the picture\'s own', (rotate) => {
        const { image, nodeView } = turned(rotate)

        nodeView.onResize(300, 200)

        expect(image.style.marginBlock).toBe('')
        expect(image.style.marginInline).toBe('')
    })

    it('leaves it alone for a size that is not a number', () => {
        const { image, nodeView } = turned(90)
        const written = {}

        // A plain object in place of the style, so that what is seen is what was written,
        // whatever a stylesheet object would make of a margin of `NaNpx`.
        Object.defineProperty(image, 'style', { configurable: true, value: written })

        nodeView.onResize(Number.NaN, 200)
        nodeView.onResize(300, Number.POSITIVE_INFINITY)

        expect(written).toEqual({})
    })

    it.each([['abc'], [null]])('takes a turn of %s for none at all', (rotate) => {
        // A picture that is not turned must not be given the margins of one that is.
        const { image, nodeView } = turned(rotate)

        nodeView.onResize(300, 200)

        expect(image.style.marginBlock).toBe('')
        expect(image.style.marginInline).toBe('')
    })

    it('goes by the angle the drag began at, whatever the node says while it runs', () => {
        const { image, nodeView } = turned(90)

        nodeView.node.attrs.rotate = 0
        nodeView.onResize(300, 200)

        expect(image.style.marginBlock).toBe('50px')
    })
})

describe('the end of a drag', () => {
    const drag = (options = {}) => {
        const field = mount(options.mount)
        const shown = picture(field.editorView, { offset: [400, 300], ...options.picture })

        press(shown.handle)

        return { ...field, ...shown, badge: () => shown.wrapper.querySelector('.fi-arte-image-size') }
    }

    const ends = [
        ['the mouse button is released', () => document.dispatchEvent(new MouseEvent('mouseup'))],
        ['a finger is lifted', () => document.dispatchEvent(new Event('touchend'))],
        ['a touch is cancelled', () => document.dispatchEvent(new Event('touchcancel'))],
        ['the window loses the focus, which is how a button released outside it goes unheard', () => window.dispatchEvent(new Event('blur'))],
    ]

    it.each(ends)('is ended when %s', (_, end) => {
        const { badge, nodeView, handle } = drag()

        end()

        expect(badge()).toBeNull()
        expect(Object.hasOwn(nodeView, 'handleResize')).toBe(false)
        expect(handle.__arteRestoreResize).toBeUndefined()
    })

    it('gives the node view back the handler it had, or none', () => {
        const previous = vi.fn()
        const withOne = drag({ picture: { extra: { onResize: previous } } })

        release()
        expect(withOne.nodeView.onResize).toBe(previous)

        const without = drag()

        release()
        expect(without.nodeView.onResize).toBeUndefined()
    })

    it('holds the bar open until the commit has landed, and only then lets it go', () => {
        const { storage } = drag()

        release()
        expect(storage.resizing).toBe(true)

        vi.runAllTimers()
        expect(storage.resizing).toBe(false)
    })

    it('selects the picture again after the commit, which is what drops the selection', () => {
        // Already selected when the drag began, so nothing is dispatched for that.
        const { editorView, nodeView } = drag({ mount: { editorSelection: { from: 5, node: {} } } })

        expect(editorView.dispatch).not.toHaveBeenCalled()

        // The commit that ends the drag leaves a caret beside the picture instead.
        editorView.state.selection = { from: 4, node: null }
        release()
        expect(editorView.dispatch).not.toHaveBeenCalled()

        vi.runAllTimers()

        expect(editorView.dispatch).toHaveBeenCalledTimes(1)
        expect(editorView.dispatch.mock.calls[0][0].selection).toEqual({ selectedAt: 5 })
        expect(nodeView.getPos()).toBe(5)
    })

    it('is over once, however many of its ends are heard', () => {
        const { editorView, storage } = drag()

        release()
        document.dispatchEvent(new Event('touchend'))
        window.dispatchEvent(new Event('blur'))
        vi.runAllTimers()
        const dispatches = editorView.dispatch.mock.calls.length

        release()
        vi.runAllTimers()

        expect(editorView.dispatch.mock.calls).toHaveLength(dispatches)
        expect(storage.resizing).toBe(false)
    })

    it('stops listening to the page and the window', () => {
        const added = []
        const removed = []

        vi.spyOn(document, 'addEventListener').mockImplementation((...args) => added.push(['document', ...args]))
        vi.spyOn(document, 'removeEventListener').mockImplementation((...args) => removed.push(['document', ...args]))
        vi.spyOn(window, 'addEventListener').mockImplementation((...args) => added.push(['window', ...args]))
        vi.spyOn(window, 'removeEventListener').mockImplementation((...args) => removed.push(['window', ...args]))

        drag()

        const finishers = added.filter(([, name]) => ['mouseup', 'touchend', 'touchcancel', 'blur'].includes(name))

        expect(finishers.map(([target, name]) => `${target}:${name}`).sort()).toEqual([
            'document:mouseup',
            'document:touchcancel',
            'document:touchend',
            'window:blur',
        ])

        // The handler is called by hand: the spies above kept it from being registered.
        finishers[0][2]()

        for (const [target, name, handler] of finishers) {
            expect(removed).toContainEqual([target, name, handler])
        }
    })

    it('can be followed by another drag, which starts from scratch', () => {
        const { storage, handle, nodeView, editorView } = drag()

        release()
        vi.runAllTimers()
        expect(storage.resizing).toBe(false)

        press(handle)

        expect(storage.resizing).toBe(true)
        expect(editorView.dom.querySelectorAll('.fi-arte-image-size')).toHaveLength(1)
        expect(nodeView.minSize).toEqual({ width: 8, height: 8 })
    })
})

describe('being taken away', () => {
    it('stops hearing presses, so that no drag begins in an editor that is gone', () => {
        const { editorView, storage, view } = mount()
        const { handle } = picture(editorView)

        view.destroy()
        press(handle)
        touch(handle)

        expect(storage.resizing).toBe(false)
    })
})
