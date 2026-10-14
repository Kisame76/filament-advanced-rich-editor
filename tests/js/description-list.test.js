import { afterEach, describe, expect, it } from 'vitest'
import descriptionListExtension, { alternated, inlineContentOf, liftedOut, splitOff, strayParts, unwrapped } from '../../resources/js/description-list.js'

/**
 * The definition list: `<dl>`, its terms and their descriptions.
 *
 * What is tested here is what the nodes say about themselves - the schema, how they are read
 * and written - and the three rearrangements the commands make of a list, as functions over
 * nodes. How they behave under a caret is ProseMirror's work and was checked in a running
 * editor; a stand-in for ProseMirror would test the stand-in.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: {
                Node: { create: (definition) => definition },
                mergeAttributes: (...sets) => Object.assign({}, ...sets),
            },
            pmState: {
                Plugin: class {
                    constructor(spec) {
                        this.spec = spec
                    }
                },
                PluginKey: class {},
                TextSelection: { create: (doc, pos) => ({ doc, pos }) },
            },
            pmModel: {
                DOMParser: { fromSchema: () => ({ parseSlice: () => ({ content: { forEach: () => {} } }) }) },
                Fragment: { fromArray: (nodes) => ({ nodes }) },
            },
        },
    }

    const list = descriptionListExtension()
    const [term, details] = list.addExtensions.call({})

    return { list, term, details }
}

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('what it is in the schema', () => {
    it('is a block of terms and descriptions, in any order', () => {
        // Any order rather than HTML's strict groups of terms then descriptions: a list that
        // must always end on a description cannot be typed into one keystroke at a time - the
        // term that was just started would have to arrive with a description already under it.
        const { list } = build()

        expect(list.name).toBe('descriptionList')
        // A block, and not in the `list` group: TipTap's `toggleList` turns any list it finds
        // the caret in into the one asked for, and a `<ul>` holding terms is not a document.
        expect(list.group).toBe('block')
        expect(list.content).toBe('(descriptionTerm | descriptionDetails)+')
    })

    it('holds text in both of its parts, and neither stands anywhere else', () => {
        const { term, details } = build()

        for (const part of [term, details]) {
            expect(part.content).toBe('inline*')
            expect(part.group).toBeUndefined()
            expect(part.defining).toBe(true)
        }

        expect(term.name).toBe('descriptionTerm')
        expect(details.name).toBe('descriptionDetails')
    })

    it('brings its two parts along, so one file is the whole feature', () => {
        const { list, term, details } = build()

        expect(list.addExtensions.call({}).map((part) => part.name)).toEqual([term.name, details.name])
    })
})

describe('how it is read and written', () => {
    it('reads the three elements HTML has for it', () => {
        const { list, term, details } = build()

        expect(list.parseHTML()).toEqual([{ tag: 'dl' }])
        expect(term.parseHTML()).toEqual([{ tag: 'dt', getContent: expect.any(Function) }])
        expect(details.parseHTML()).toEqual([{ tag: 'dd', getContent: expect.any(Function) }])
    })

    it('writes the list with the class the stylesheet knows, and its parts as they are', () => {
        // The class is baked into the markup, as the task list's is, because a stored
        // document is also drawn outside a form by a front end that has never heard of
        // this package.
        const { list, term, details } = build()

        expect(list.renderHTML({ HTMLAttributes: {} })).toEqual(['dl', { class: 'fi-arte-description-list' }, 0])
        expect(term.renderHTML({ HTMLAttributes: {} })).toEqual(['dt', {}, 0])
        expect(details.renderHTML({ HTMLAttributes: {} })).toEqual(['dd', {}, 0])
    })
})

/**
 * Nodes as ProseMirror builds them, as far as the rearrangements look: a type that creates,
 * content, a size, and a walk over the children.
 */
const type = (name) => ({
    name,
    create(attrs, content = []) {
        const children = Array.isArray(content) ? content : (content?.children ?? [])

        return node(this, attrs, children)
    },
})

const node = (nodeType, attrs, children, text = null) => ({
    type: nodeType,
    attrs,
    children,
    text,
    content: { children, size: children.reduce((size, child) => size + child.nodeSize, 0) },
    nodeSize: text !== null ? text.length : children.reduce((size, child) => size + child.nodeSize, 0) + 2,
    childCount: children.length,
    child: (index) => children[index],
    forEach(callback) {
        let offset = 0

        for (const child of children) {
            callback(child, offset)
            offset += child.nodeSize
        }
    },
})

const TEXT = type('text')
const PARAGRAPH = type('paragraph')
const LIST = type('descriptionList')
const TERM = type('descriptionTerm')
const DETAILS = type('descriptionDetails')

const text = (value) => node(TEXT, null, [], value)
const part = (partType, value) => partType.create(null, value === '' ? [] : [text(value)])
const shape = (target) =>
    target.type === TEXT ? target.text : { [target.type.name]: target.children.map(shape) }

describe('reading what a part holds', () => {
    const BREAK = type('hardBreak')
    const inline = (value) => ({ ...text(value), isInline: true, isTextblock: false })
    const block = (blockType, ...children) => ({
        ...blockType.create(null, children),
        isInline: false,
        isTextblock: blockType !== LIST,
    })
    const fragment = (...nodes) => ({
        forEach: (callback) => {
            for (const one of nodes) {
                callback(one)
            }
        },
    })
    const names = (nodes) => nodes.map((one) => (one.type === TEXT ? one.text : one.type.name))

    it('keeps text that was already text', () => {
        expect(names(inlineContentOf(fragment(inline('One')), BREAK))).toEqual(['One'])
    })

    it('takes the text out of the paragraphs inside a part, a line apiece', () => {
        // `<dd><p>One</p><p>Two</p></dd>` is what CommonMark writes for a loose list and what
        // most sites paste. A part holds text, and ProseMirror's own way of reading past a
        // paragraph closes the part at the second one - which turned `Two` into a term.
        const paragraphs = fragment(block(PARAGRAPH, inline('One')), block(PARAGRAPH, inline('Two')))

        expect(names(inlineContentOf(paragraphs, BREAK))).toEqual(['One', 'hardBreak', 'Two'])
    })

    it('reaches the text of whatever else a pasted part held', () => {
        // A list inside a description has no place in one, but its words do.
        const nested = fragment(block(LIST, block(PARAGRAPH, inline('a')), block(PARAGRAPH, inline('b'))))

        expect(names(inlineContentOf(nested, BREAK))).toEqual(['a', 'hardBreak', 'b'])
    })
})

describe('taking a part out of the list', () => {
    const list = () => LIST.create({}, [part(TERM, 'Apple'), part(DETAILS, 'A fruit'), part(TERM, 'Pear'), part(DETAILS, '')])

    it('splits the list round a part from the middle, which becomes a paragraph', () => {
        const { nodes, caret } = liftedOut(list(), 1, PARAGRAPH)

        expect(nodes.map(shape)).toEqual([
            { descriptionList: [{ descriptionTerm: ['Apple'] }] },
            { paragraph: ['A fruit'] },
            { descriptionList: [{ descriptionTerm: ['Pear'] }, { descriptionDetails: [] }] },
        ])
        // `<dl>` 0, `<dt>` 1, `Apple` 2-7, `</dt>` 7, `</dl>` 8 - and the paragraph opens at
        // 9, so its text starts at 10.
        expect(caret).toBe(10)
    })

    it('leaves nothing empty behind when the part was at either end', () => {
        expect(liftedOut(list(), 3, PARAGRAPH).nodes.map(shape)).toEqual([
            {
                descriptionList: [
                    { descriptionTerm: ['Apple'] },
                    { descriptionDetails: ['A fruit'] },
                    { descriptionTerm: ['Pear'] },
                ],
            },
            { paragraph: [] },
        ])

        const first = liftedOut(list(), 0, PARAGRAPH)

        expect(first.nodes.map(shape)[0]).toEqual({ paragraph: ['Apple'] })
        expect(first.caret).toBe(1)
    })

    it('turns a list of one part into the paragraph alone', () => {
        const { nodes, caret } = liftedOut(LIST.create({}, [part(TERM, 'Only')]), 0, PARAGRAPH)

        expect(nodes.map(shape)).toEqual([{ paragraph: ['Only'] }])
        expect(caret).toBe(1)
    })
})

describe('what is left over when a block goes into a part', () => {
    // A rule or a table typed into a term cannot stand inside the list, so ProseMirror splits
    // the list round it - and since a list may not be empty, it fills what was cut off with
    // empty terms: `<dl><dt></dt></dl>` after the rule, and an empty term in front of the
    // description that followed. Invisible on the page, and stored all the same.
    //
    // A list of three parts from 10: `<dl>` 10, the empty term 11-13 (its text at 12), `Apple`
    // 13-20 (text 14-19), the empty description 20-22 (text at 21).
    const list = () => LIST.create({}, [part(TERM, ''), part(TERM, 'Apple'), part(DETAILS, '')])

    it('is every empty part the caret is not in, last first', () => {
        expect(strayParts(list(), 10, { from: 40, to: 40 })).toEqual([20, 11])
    })

    it('is not the empty part the caret is in', () => {
        // The one somebody has just made by switching an empty paragraph into a list, or by
        // pressing Enter after a term.
        expect(strayParts(list(), 10, { from: 21, to: 21 })).toEqual([11])
        expect(strayParts(list(), 10, { from: 12, to: 12 })).toEqual([20])
    })

    it('counts a caret standing between the tags as in no part at all', () => {
        // Where TipTap's rule command leaves it: after the remainder's term and before the end
        // of its list, which is no place a caret can stand in.
        expect(strayParts(LIST.create({}, [part(TERM, '')]), 10, { from: 13, to: 13 })).toEqual([11])
    })

    it('leaves a part with text in it alone', () => {
        expect(strayParts(LIST.create({}, [part(TERM, 'Apple')]), 10, { from: 40, to: 40 })).toEqual([])
    })
})

describe('which lists are left over from a split', () => {
    // Only a list cut off another one can be a remainder: its start, followed back through the
    // edit, lands inside the list it was cut from. A list that arrives whole - set as the
    // field's content when the server sends it, pasted, brought back by undo, dragged to a new
    // place - lands somewhere else, and its empty parts are somebody's.
    const at = (...ancestors) => ({ depth: ancestors.length, node: (depth) => ancestors[depth - 1] })

    it('is a list whose start came from inside another', () => {
        expect(splitOff(at(LIST.create({}, []), TERM.create(null, [])), LIST)).toBe(true)
        expect(splitOff(at(LIST.create({}, [])), LIST)).toBe(true)
    })

    it('is not a list that arrived from outside any list', () => {
        expect(splitOff(at(), LIST)).toBe(false)
        expect(splitOff(at(PARAGRAPH.create(null, [])), LIST)).toBe(false)
    })
})

describe('turning paragraphs into a list and back', () => {
    it('makes a term of every other paragraph and a description of the ones between', () => {
        // Glossaries are written term, meaning, term, meaning - so that is how a run of
        // paragraphs is read when somebody turns them into a list.
        const blocks = ['Apple', 'A fruit', 'Pear', 'Another one', 'Quince'].map((value) => part(PARAGRAPH, value))

        expect(alternated(blocks, TERM, DETAILS).map(shape)).toEqual([
            { descriptionTerm: ['Apple'] },
            { descriptionDetails: ['A fruit'] },
            { descriptionTerm: ['Pear'] },
            { descriptionDetails: ['Another one'] },
            { descriptionTerm: ['Quince'] },
        ])
    })

    it('gives every part back as a paragraph of its own, text and all', () => {
        const list = LIST.create({}, [part(TERM, 'Apple'), part(DETAILS, 'A fruit'), part(DETAILS, '')])

        expect(unwrapped(list, PARAGRAPH).map(shape)).toEqual([
            { paragraph: ['Apple'] },
            { paragraph: ['A fruit'] },
            { paragraph: [] },
        ])
    })
})
