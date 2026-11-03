import { afterEach, describe, expect, it, vi } from 'vitest'
import lockedExtension, { isLoad, keepsLockedParts, lockedParts, settingsOf } from '../../resources/js/locked.js'

/**
 * Locked parts on the editor's side: which parts a document has, whether a change left them as
 * they were, and what the guard lets through. The server checks every save again - see
 * `LockedRegionsTest` - so this is about the parts feeling locked while somebody writes.
 */

// Nodes that know their name, whether they hold text, what they hold, and how to compare
// themselves - all the guard ever asks of one.
const node = (name, children = [], { text = null, textblock = false } = {}) => {
    const self = {
        type: { name },
        isTextblock: textblock,
        children,
        text,
        shape: () => ({ name, text, children: children.map((child) => child.shape()) }),
        eq: (other) => JSON.stringify(self.shape()) === JSON.stringify(other.shape()),
        descendants(callback) {
            for (const child of children) {
                if (callback(child) !== false) {
                    child.descendants(callback)
                }
            }
        },
    }

    return self
}

const paragraph = (text) => node('paragraph', [node('text', [], { text })], { textblock: true })
const locked = (...blocks) => node('locked', blocks)
const doc = (...blocks) => node('doc', blocks)

afterEach(() => {
    vi.unstubAllGlobals()
    delete window.FilamentRichEditor
})

describe('the locked parts of a document', () => {
    it('are the outermost ones - a part inside another is part of it', () => {
        const inner = locked(paragraph('inner'))
        const outer = locked(paragraph('outer'), inner)

        expect(lockedParts(doc(paragraph('free'), outer))).toEqual([outer])
    })

    it('are kept by writing around them, and by moving them', () => {
        const before = doc(locked(paragraph('a')), paragraph('free'), locked(paragraph('b')))

        expect(keepsLockedParts(before, doc(locked(paragraph('a')), paragraph('written'), locked(paragraph('b'))))).toBe(true)
        expect(keepsLockedParts(before, doc(locked(paragraph('b')), locked(paragraph('a'))))).toBe(true)
    })

    it('are not kept by changing, removing or adding one', () => {
        const before = doc(locked(paragraph('a')), paragraph('free'))

        expect(keepsLockedParts(before, doc(locked(paragraph('A')), paragraph('free')))).toBe(false)
        expect(keepsLockedParts(before, doc(paragraph('free')))).toBe(false)
        expect(keepsLockedParts(before, doc(locked(paragraph('a')), locked(paragraph('free'))))).toBe(false)
    })
})

describe('telling a load from an edit', () => {
    const transaction = ({ meta, from = 0, to = 10, size = 10, steps = 1 }) => ({
        getMeta: (key) => (key === 'preventUpdate' ? meta : undefined),
        steps: Array.from({ length: steps }, () => ({ from, to })),
        before: { content: { size } },
    })

    it("is Filament's setContent: the whole document, with preventUpdate said either way", () => {
        expect(isLoad(transaction({ meta: false }))).toBe(true)
        expect(isLoad(transaction({ meta: true }))).toBe(true)
    })

    it('is not an edit that happens to cover everything, nor one that says less than everything', () => {
        expect(isLoad(transaction({ meta: undefined }))).toBe(false)
        expect(isLoad(transaction({ meta: false, to: 9 }))).toBe(false)
        expect(isLoad(transaction({ meta: false, steps: 2 }))).toBe(false)
    })
})

describe('the settings', () => {
    it('reads what the field handed over, and refuses editing for anything it cannot read', () => {
        const element = document.createElement('div')

        expect(settingsOf(element)).toEqual({ editable: false, label: '' })

        element.dataset.arteLocked = JSON.stringify({ editable: true, label: 'Locked' })
        expect(settingsOf(element)).toEqual({ editable: true, label: 'Locked' })

        element.dataset.arteLocked = '{oops'
        expect(settingsOf(element).editable).toBe(false)

        element.dataset.arteLocked = JSON.stringify({ editable: 'yes' })
        expect(settingsOf(element).editable).toBe(false)
    })
})

describe('the node', () => {
    const extensionFor = (settings) => {
        window.FilamentRichEditor = {
            tiptap: {
                core: { Node: { create: (definition) => definition }, mergeAttributes: Object.assign },
                pmState: {
                    Plugin: class {
                        constructor(spec) {
                            this.spec = spec
                        }
                    },
                    PluginKey: class {},
                },
            },
        }

        const extension = lockedExtension()
        const element = document.createElement('div')

        if (settings) {
            element.dataset.arteLocked = JSON.stringify(settings)
        }

        extension.editor = { options: { element } }
        extension.options = extension.addOptions()

        return extension
    }

    it('is written the way the PHP half reads it', () => {
        const extension = extensionFor()

        expect(extension.parseHTML()[0].tag).toBe('div[data-type="locked"]')
        expect(extension.renderHTML({ HTMLAttributes: {} })).toEqual([
            'div',
            { class: 'fi-arte-locked', 'data-type': 'locked' },
            0,
        ])
    })

    it('guards the parts for somebody who may not change them, and refuses a change to one', () => {
        const [guard] = extensionFor({ editable: false }).addProseMirrorPlugins()
        const before = doc(locked(paragraph('a')), paragraph('free'))
        const edit = (after) => ({ docChanged: true, getMeta: () => undefined, steps: [{}], before, doc: after })

        expect(guard.spec.filterTransaction(edit(doc(locked(paragraph('a')), paragraph('freer'))))).toBe(true)
        expect(guard.spec.filterTransaction(edit(doc(locked(paragraph('A')), paragraph('free'))))).toBe(false)
        expect(guard.spec.filterTransaction({ docChanged: false })).toBe(true)
    })

    it('has no guard for somebody who may lock and unlock', () => {
        expect(extensionFor({ editable: true }).addProseMirrorPlugins()).toEqual([])
    })

    it('draws a frame the caret stays out of, unless the person may change it', () => {
        const writer = extensionFor({ editable: false, label: 'Locked' }).addNodeView()()
        const author = extensionFor({ editable: true, label: 'Locked' }).addNodeView()()

        expect(writer.dom.contentEditable).toBe('false')
        expect(writer.dom.dataset.label).toBe('Locked')
        expect(writer.dom.contains(writer.contentDOM)).toBe(true)
        expect(author.dom.contentEditable).not.toBe('false')
    })
})
