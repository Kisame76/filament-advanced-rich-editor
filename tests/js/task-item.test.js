import { afterEach, describe, expect, it, vi } from 'vitest'
import taskItemExtension from '../../resources/js/task-item.js'

/**
 * The task item: what it is in the schema, what it writes into a document, what the keys do
 * inside it and what the editor draws for it.
 *
 * The module exports nothing but the factory, so everything is reached the way TipTap
 * reaches it - through the definition's own methods, called with the `this` TipTap would
 * give them. `Node.create` is stubbed to hand the definition straight back, which is the
 * whole of what a test needs from it.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: {
                Node: { create: (definition) => definition },
                mergeAttributes: (...sets) => Object.assign({}, ...sets),
                // The rule's own configuration is what matters, and it is what is handed back.
                wrappingInputRule: (config) => config,
            },
        },
    }

    return taskItemExtension()
}

const type = { name: 'taskItem' }

/**
 * What TipTap binds to `this` inside the definition's methods.
 */
const context = (extra = {}) => ({
    name: 'taskItem',
    type,
    options: { HTMLAttributes: {}, taskListTypeName: 'taskList' },
    editor: {},
    ...extra,
})

afterEach(() => {
    delete window.FilamentRichEditor
    delete Range.prototype.getClientRects
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.replaceChildren()
})

describe('what it is in the schema', () => {
    it('is an item that starts with a paragraph and may carry blocks after it', () => {
        // `block*` is what lets a nested task list, and so `sinkListItem`, work at all.
        const definition = build()

        expect(definition.name).toBe('taskItem')
        expect(definition.content).toBe('paragraph block*')
        expect(definition.defining).toBe(true)
    })

    it('knows the list it belongs to', () => {
        expect(build().addOptions()).toEqual({ HTMLAttributes: {}, taskListTypeName: 'taskList' })
    })

    it('outranks the bundled list item for the same element', () => {
        // The bundled one claims a plain `li` at the default priority of 50.
        const [rule] = build().parseHTML.call(context())

        expect(rule.tag).toBe('li[data-type="taskItem"]')
        expect(rule.priority).toBeGreaterThan(50)
    })
})

describe('the ticked state', () => {
    const attribute = () => build().addAttributes().checked
    const read = (value) => {
        const element = document.createElement('li')

        if (value !== null) {
            element.setAttribute('data-checked', value)
        }

        return attribute().parseHTML(element)
    }

    it('starts unticked, and an item split with Enter is unticked too', () => {
        expect(attribute().default).toBe(false)
        expect(attribute().keepOnSplit).toBe(false)
    })

    it('reads "true", and a bare attribute, as ticked', () => {
        // PHP writes the string "true"; somebody's hand-written HTML writes `data-checked`
        // and nothing else.
        expect(read('true')).toBe(true)
        expect(read('')).toBe(true)
    })

    it('reads everything else as unticked', () => {
        expect(read('false')).toBe(false)
        expect(read('0')).toBe(false)
        expect(read(null)).toBe(false)
    })

    it('writes the state into the document', () => {
        expect(attribute().renderHTML({ checked: true })).toEqual({ 'data-checked': true })
        expect(attribute().renderHTML({ checked: false })).toEqual({ 'data-checked': false })
    })
})

describe('what it writes', () => {
    const write = (checked, HTMLAttributes = {}, options = {}) =>
        build().renderHTML.call(context({ options: { HTMLAttributes: {}, ...options } }), {
            node: { attrs: { checked } },
            HTMLAttributes,
        })

    it('is a list item with a drawn box and a place for the content', () => {
        const [tag, attributes, control, content] = write(false)

        expect(tag).toBe('li')
        expect(attributes['data-type']).toBe('taskItem')
        expect(control).toEqual(['label', { class: 'fi-arte-task-item-control' }, ['span', { class: 'fi-arte-task-item-box' }]])
        // `0` is ProseMirror's hole: where the item's content goes.
        expect(content).toEqual(['div', { class: 'fi-arte-task-item-content' }, 0])
    })

    it('carries the state in a class, which is what survives a sanitiser', () => {
        expect(write(false)[1].class).toBe('fi-arte-task-item')
        expect(write(true)[1].class).toBe('fi-arte-task-item fi-arte-task-item-checked')
    })

    it('writes no checkbox, which the sanitiser would take together with the state', () => {
        // Filament sanitises content before it is shown on a page and drops `input`. The
        // box is drawn in CSS, and only the editor's own view has a real one.
        expect(JSON.stringify(write(true))).not.toContain('input')
    })

    it('keeps the attributes it was handed and the ones the field was configured with', () => {
        const [, attributes] = write(false, { 'data-x': 'a' }, { HTMLAttributes: { 'data-y': 'b' } })

        expect(attributes['data-x']).toBe('a')
        expect(attributes['data-y']).toBe('b')
    })
})

describe('the keys', () => {
    const keysWith = (commands, selection) =>
        build().addKeyboardShortcuts.call(context({ editor: { commands, state: { selection } } }))

    /**
     * A selection as far as Backspace looks at it: collapsed or not, how far into the block,
     * how deep, what the parent is and which child of it this is.
     */
    const caret = ({ empty = true, parentOffset = 0, depth = 3, parent = 'taskItem', index = 0 } = {}) => ({
        empty,
        $anchor: {
            parentOffset,
            depth,
            node: () => ({ type: { name: parent } }),
            index: () => index,
        },
    })

    const commands = () => ({
        splitListItem: vi.fn(() => 'split'),
        sinkListItem: vi.fn(() => 'sink'),
        liftListItem: vi.fn(() => 'lift'),
    })

    it('splits the item on Enter', () => {
        const spy = commands()

        expect(keysWith(spy).Enter()).toBe('split')
        expect(spy.splitListItem).toHaveBeenCalledWith('taskItem')
    })

    it('indents on Tab and outdents on Shift-Tab', () => {
        const spy = commands()
        const keys = keysWith(spy)

        expect(keys.Tab()).toBe('sink')
        expect(keys['Shift-Tab']()).toBe('lift')
        expect(spy.sinkListItem).toHaveBeenCalledWith('taskItem')
        expect(spy.liftListItem).toHaveBeenCalledWith('taskItem')
    })

    it('lifts the item out on Backspace at the very start of its first block', () => {
        const spy = commands()

        expect(keysWith(spy, caret()).Backspace()).toBe('lift')
        expect(spy.liftListItem).toHaveBeenCalledWith('taskItem')
    })

    it.each([
        ['while something is selected', { empty: false }],
        ['when the caret is not at the start of the block', { parentOffset: 3 }],
        ['outside anything nested', { depth: 1 }],
        ['when the block sits in some other kind of item', { parent: 'listItem' }],
        ['in a block that is not the first of the item', { index: 1 }],
    ])('leaves Backspace to the browser %s', (_, selection) => {
        const spy = commands()

        expect(keysWith(spy, caret(selection)).Backspace()).toBe(false)
        expect(spy.liftListItem).not.toHaveBeenCalled()
    })
})

describe('the shorthand that starts one', () => {
    const rule = () => build().addInputRules.call(context())[0]

    it('is a rule that wraps in this item', () => {
        expect(rule().type).toBe(type)
    })

    it('reacts to a bracket pair and a space at the start of a line', () => {
        const { find } = rule()

        expect(find.test('[] ')).toBe(true)
        expect(find.test('[ ] ')).toBe(true)
        expect(find.test('[x] ')).toBe(true)
        expect(find.test('  [x] ')).toBe(true)
    })

    it('leaves brackets in the middle of a sentence, and unfinished ones, alone', () => {
        const { find } = rule()

        expect(find.test('see [x] ')).toBe(false)
        expect(find.test('[x]')).toBe(false)
        expect(find.test('[y] ')).toBe(false)
    })

    it('ticks the item only for an x', () => {
        const { find, getAttributes } = rule()

        expect(getAttributes(find.exec('[x] '))).toEqual({ checked: true })
        expect(getAttributes(find.exec('[ ] '))).toEqual({ checked: false })
        expect(getAttributes(find.exec('[] '))).toEqual({ checked: false })
    })
})

/**
 * The view. It is where the checkbox is - a real one, which the saved markup deliberately
 * does not have - and where the box is kept level with the first line of text.
 */

/**
 * An editor as far as the view looks at it, with the chain it runs a tick through recorded.
 */
const viewEditor = ({ editable = true, node = null, position = 4 } = {}) => {
    const tr = { doc: { nodeAt: vi.fn(() => node) }, setNodeMarkup: vi.fn() }
    const chain = {
        focus: vi.fn(() => chain),
        command: vi.fn((callback) => {
            chain.callback = callback

            return chain
        }),
        run: vi.fn(() => chain.callback({ tr })),
    }

    return { isEditable: editable, chain: () => chain, tr, chainSpy: chain, position }
}

const taskNode = (checked = false, text = '', attrs = {}) => ({
    type,
    attrs: { checked, ...attrs },
    textContent: text,
})

const view = ({ node = taskNode(), editor = viewEditor(), HTMLAttributes = {}, getPos, options } = {}) => {
    const definition = build()
    const bound = context({ editor, ...(options ? { options } : {}) })

    return definition.addNodeView.call(bound)({
        node,
        HTMLAttributes,
        getPos: getPos ?? (() => editor.position),
        editor,
    })
}

const checkboxOf = (nodeView) => nodeView.dom.querySelector('input')

describe('what is drawn', () => {
    it('is a list item with a checkbox outside the editable part and a place for the content', () => {
        const nodeView = view()

        expect(nodeView.dom.tagName).toBe('LI')
        expect(nodeView.dom.getAttribute('data-type')).toBe('taskItem')
        expect(nodeView.dom.classList.contains('fi-arte-task-item')).toBe(true)
        expect(nodeView.dom.querySelector('label').contentEditable).toBe('false')
        expect(checkboxOf(nodeView).type).toBe('checkbox')
        expect(nodeView.contentDOM.parentElement).toBe(nodeView.dom)
        expect(nodeView.dom.contains(nodeView.contentDOM)).toBe(true)
    })

    it('puts the box before the content, with the drawn square beside the real checkbox', () => {
        // The stylesheet lays the item out as box, then text; and the square the theme
        // paints has to be there to be painted on.
        const nodeView = view()
        const label = nodeView.dom.querySelector('label')

        expect(nodeView.dom.firstElementChild).toBe(label)
        expect(nodeView.dom.lastElementChild).toBe(nodeView.contentDOM)
        expect(label.children).toHaveLength(2)
        expect(label.children[0].tagName).toBe('INPUT')
        expect(label.children[1].tagName).toBe('SPAN')
    })

    it('shows the state the node has', () => {
        const ticked = view({ node: taskNode(true) })
        const open = view({ node: taskNode(false) })

        expect(checkboxOf(ticked).checked).toBe(true)
        expect(ticked.dom.dataset.checked).toBe('true')
        expect(checkboxOf(open).checked).toBe(false)
        expect(open.dom.dataset.checked).toBe('false')
    })

    it('cannot be ticked in an editor that cannot be written to', () => {
        expect(checkboxOf(view({ editor: viewEditor({ editable: false }) })).disabled).toBe(true)
        expect(checkboxOf(view()).disabled).toBe(false)
    })

    it('takes the attributes it was handed, and keeps its own hooks over them', () => {
        const nodeView = view({
            HTMLAttributes: { class: 'extra', 'data-type': 'somethingElse', 'data-y': 'b' },
            options: { HTMLAttributes: { 'data-z': 'c' }, taskListTypeName: 'taskList' },
        })

        expect(nodeView.dom.getAttribute('data-type')).toBe('taskItem')
        expect(nodeView.dom.classList.contains('extra')).toBe(true)
        expect(nodeView.dom.classList.contains('fi-arte-task-item')).toBe(true)
        expect(nodeView.dom.getAttribute('data-y')).toBe('b')
        expect(nodeView.dom.getAttribute('data-z')).toBe('c')
    })
})

describe('the name a screen reader hears', () => {
    it('is the text of the item, since the label has none of its own', () => {
        expect(checkboxOf(view({ node: taskNode(false, '  Buy milk ') })).getAttribute('aria-label')).toBe('Buy milk')
    })

    it('is nothing for an item with no text yet, rather than an empty name', () => {
        expect(checkboxOf(view({ node: taskNode(false, '   ') })).hasAttribute('aria-label')).toBe(false)
    })

    it('follows the text as it is typed', () => {
        const nodeView = view({ node: taskNode(false, '') })

        nodeView.update(taskNode(false, 'Buy bread'))
        expect(checkboxOf(nodeView).getAttribute('aria-label')).toBe('Buy bread')

        nodeView.update(taskNode(false, ''))
        expect(checkboxOf(nodeView).hasAttribute('aria-label')).toBe(false)
    })
})

describe('ticking it', () => {
    const tick = (nodeView, checked) => {
        const checkbox = checkboxOf(nodeView)
        checkbox.checked = checked
        checkbox.dispatchEvent(new Event('change'))
    }

    it('keeps the click from moving the selection first', () => {
        const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true })

        checkboxOf(view()).dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
    })

    it('writes the new state into the node it belongs to, and keeps the rest of it', () => {
        const current = taskNode(false, 'x', { indent: 2 })
        const editor = viewEditor({ node: current, position: 7 })
        const nodeView = view({ editor, node: current })

        tick(nodeView, true)

        expect(editor.chainSpy.focus).toHaveBeenCalledWith(undefined, { scrollIntoView: false })
        expect(editor.chainSpy.run).toHaveBeenCalled()
        expect(editor.tr.doc.nodeAt).toHaveBeenCalledWith(7)
        expect(editor.tr.setNodeMarkup).toHaveBeenCalledWith(7, undefined, { checked: true, indent: 2 })
    })

    it('writes an unticked state just as it writes a ticked one', () => {
        const current = taskNode(true)
        const editor = viewEditor({ node: current, position: 3 })
        const nodeView = view({ editor, node: current })

        tick(nodeView, false)

        expect(editor.tr.setNodeMarkup).toHaveBeenCalledWith(3, undefined, { checked: false })
    })

    it('writes nothing where the position is no longer a number', () => {
        const current = taskNode(false)
        const editor = viewEditor({ node: current })
        const nodeView = view({ editor, node: current, getPos: () => undefined })

        tick(nodeView, true)

        expect(editor.tr.setNodeMarkup).not.toHaveBeenCalled()
    })

    it('writes nothing where the position now holds something else', () => {
        // The document moved on between the click and the transaction.
        const editor = viewEditor({ node: { type: { name: 'paragraph' }, attrs: {} } })
        const nodeView = view({ editor })

        tick(nodeView, true)

        expect(editor.tr.setNodeMarkup).not.toHaveBeenCalled()
    })

    it('undoes the browser\'s tick in an editor that cannot be written to', () => {
        const editor = viewEditor({ editable: false })
        const nodeView = view({ editor })

        tick(nodeView, true)

        expect(checkboxOf(nodeView).checked).toBe(false)
        expect(editor.chainSpy.run).not.toHaveBeenCalled()
    })

    it('undoes the tick where the node has no way to say where it is', () => {
        const editor = viewEditor()
        const nodeView = view({ editor, getPos: false })

        tick(nodeView, true)

        expect(checkboxOf(nodeView).checked).toBe(false)
        expect(editor.chainSpy.run).not.toHaveBeenCalled()
    })
})

describe('being updated', () => {
    it('refuses a node of another kind, so that ProseMirror draws a new view instead', () => {
        expect(view().update({ type: { name: 'paragraph' }, attrs: {}, textContent: '' })).toBe(false)
    })

    it('takes on the new state, and says it did', () => {
        const nodeView = view({ node: taskNode(false) })

        expect(nodeView.update(taskNode(true))).toBe(true)
        expect(checkboxOf(nodeView).checked).toBe(true)
        expect(nodeView.dom.dataset.checked).toBe('true')
    })

    it('follows the editor being switched to read-only', () => {
        const editor = viewEditor()
        const nodeView = view({ editor })

        editor.isEditable = false
        nodeView.update(taskNode(false))

        expect(checkboxOf(nodeView).disabled).toBe(true)
    })

    it('does not treat what happens outside the content as a change to the document', () => {
        // The checkbox and the data attributes live outside `contentDOM`, and ProseMirror
        // must not try to read them back as content.
        const nodeView = view()
        const inside = document.createElement('span')
        nodeView.contentDOM.append(inside)

        expect(nodeView.ignoreMutation({ target: checkboxOf(nodeView) })).toBe(true)
        expect(nodeView.ignoreMutation({ target: nodeView.dom })).toBe(true)
        expect(nodeView.ignoreMutation({ target: inside })).toBe(false)
        expect(nodeView.ignoreMutation({ target: nodeView.contentDOM })).toBe(false)
    })
})

/**
 * Where the browser says the first line is, and where the content block starts.
 */
const measured = ({ lineTop = 30, lineHeight = 24 } = {}) => {
    Object.defineProperty(Range.prototype, 'getClientRects', {
        configurable: true,
        value: () => [{ top: lineTop, height: lineHeight }],
    })
}

const attach = (nodeView, contentTop = 20) => {
    document.body.append(nodeView.dom)
    nodeView.contentDOM.getBoundingClientRect = () => ({ top: contentTop })
}

const line = (nodeView) => nodeView.dom.style.getPropertyValue('--fi-arte-task-line')
const offset = (nodeView) => nodeView.dom.style.getPropertyValue('--fi-arte-task-line-offset')

/**
 * The passes the view schedules, run the way the browser would run them: the timer, and the
 * animation frame that is handed to the test instead of to a real frame.
 */
const withScheduler = () => {
    const pending = []

    vi.useFakeTimers({ toFake: ['setTimeout'] })
    vi.stubGlobal('requestAnimationFrame', (callback) => pending.push(callback))

    const timers = () => vi.runAllTimers()
    const frames = () => {
        for (const frame of pending.splice(0)) {
            frame()
        }
    }

    return {
        timers,
        frames,
        settle: () => {
            timers()
            frames()
        },
    }
}

describe('keeping the box level with the first line', () => {
    it('hands the height of the first line to the stylesheet, and how far down it sits', () => {
        const { settle } = withScheduler()
        measured({ lineTop: 30, lineHeight: 24 })
        const nodeView = view()

        // Built before it is in the page, as ProseMirror does: nothing can be measured yet.
        expect(line(nodeView)).toBe('')

        attach(nodeView, 20)
        settle()

        expect(line(nodeView)).toBe('24px')
        expect(offset(nodeView)).toBe('10px')
    })

    it('rounds to two places and never offsets upwards', () => {
        const { settle } = withScheduler()
        measured({ lineTop: 18, lineHeight: 23.456 })
        const nodeView = view()

        attach(nodeView, 20)
        settle()

        expect(line(nodeView)).toBe('23.46px')
        expect(offset(nodeView)).toBe('0px')
    })

    it('says nothing where the line has no height, which is a hidden or empty block', () => {
        const { settle } = withScheduler()
        measured({ lineHeight: 0 })
        const nodeView = view()

        attach(nodeView)
        settle()

        expect(line(nodeView)).toBe('')
    })

    it('stays quiet where the browser cannot measure a range at all', () => {
        // jsdom is such a browser: `getClientRects` is not there, and the view has to carry on.
        const { settle } = withScheduler()
        const nodeView = view()

        attach(nodeView)

        expect(() => settle()).not.toThrow()
        expect(line(nodeView)).toBe('')
    })

    it('measures on the timer alone, which is what still runs in a background tab', () => {
        const { timers } = withScheduler()
        measured({ lineHeight: 26 })
        const nodeView = view()

        attach(nodeView)
        timers()

        expect(line(nodeView)).toBe('26px')
    })

    it('measures on the animation frame alone, which is the cheap pass in a visible one', () => {
        const { frames } = withScheduler()
        measured({ lineHeight: 27 })
        const nodeView = view()

        attach(nodeView)
        frames()

        expect(line(nodeView)).toBe('27px')
    })

    it('measures the first block of the content rather than all of it', () => {
        // Two paragraphs would otherwise make the "first line" the top of the first one and
        // the bottom of the last.
        const { settle } = withScheduler()
        measured()
        const select = vi.spyOn(Range.prototype, 'selectNodeContents')
        const nodeView = view()
        const first = document.createElement('p')

        nodeView.contentDOM.append(first, document.createElement('p'))
        attach(nodeView)
        settle()

        expect(select).toHaveBeenCalledWith(first)
        expect(select).not.toHaveBeenCalledWith(nodeView.contentDOM)
    })

    it('measures the content itself while it holds no block yet', () => {
        const { settle } = withScheduler()
        measured()
        const select = vi.spyOn(Range.prototype, 'selectNodeContents')
        const nodeView = view()

        attach(nodeView)
        settle()

        expect(select).toHaveBeenCalledWith(nodeView.contentDOM)
    })

    it('measures again when the node is updated', () => {
        const { settle } = withScheduler()
        measured({ lineHeight: 20 })
        const nodeView = view()

        attach(nodeView)
        settle()
        expect(line(nodeView)).toBe('20px')

        measured({ lineHeight: 32 })
        nodeView.update(taskNode(false, 'bigger text'))
        settle()

        expect(line(nodeView)).toBe('32px')
    })
})

describe('watching its own content', () => {
    /**
     * Stand-ins for the two observers. They have to be constructors, since the view makes
     * them with `new`, and each records the one it was asked to make.
     */
    const observers = () => {
        const made = { resize: [], mutation: [] }
        const stand = (kind) =>
            class {
                constructor(callback) {
                    this.callback = callback
                    this.observe = vi.fn()
                    this.disconnect = vi.fn()
                    made[kind].push(this)
                }
            }

        vi.stubGlobal('ResizeObserver', stand('resize'))
        vi.stubGlobal('MutationObserver', stand('mutation'))

        return made
    }

    it('watches the content for a resize and for a style or class landing on the text', () => {
        const made = observers()
        const nodeView = view()

        expect(made.resize[0].observe).toHaveBeenCalledWith(nodeView.contentDOM)
        expect(made.mutation[0].observe).toHaveBeenCalledWith(nodeView.contentDOM, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['style', 'class'],
        })
    })

    it('measures again as soon as the content is resized', () => {
        const made = observers()
        withScheduler()
        const nodeView = view()

        attach(nodeView)
        measured({ lineHeight: 28 })
        made.resize[0].callback()

        expect(line(nodeView)).toBe('28px')
    })

    it('measures again when the text is restyled, which a background tab still reports', () => {
        const made = observers()
        withScheduler()
        const nodeView = view()

        attach(nodeView)
        measured({ lineHeight: 40 })
        made.mutation[0].callback()

        expect(line(nodeView)).toBe('40px')
    })

    it('lets go of both when the item is removed', () => {
        const made = observers()

        view().destroy()

        expect(made.resize[0].disconnect).toHaveBeenCalledTimes(1)
        expect(made.mutation[0].disconnect).toHaveBeenCalledTimes(1)
    })

    it('does without either where the browser has none', () => {
        vi.stubGlobal('ResizeObserver', undefined)
        vi.stubGlobal('MutationObserver', undefined)

        const nodeView = view()

        expect(() => nodeView.destroy()).not.toThrow()
    })
})
