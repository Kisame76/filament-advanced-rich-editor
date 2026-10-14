import { afterEach, describe, expect, it } from 'vitest'
import invisiblesExtension, { INVISIBLE_CHARACTERS, invisiblesIn, touchedTextblocks } from '../../resources/js/invisibles.js'

/**
 * Showing what a document holds and does not draw.
 *
 * What is found is a function over the document and nothing else, so it is tested against a
 * document built the way ProseMirror counts one: a text node is as long as its text, a leaf
 * is one, and every other node is its content plus an opening and a closing token. Getting
 * those positions right is the whole job - a mark one position off sits on the wrong letter.
 */

const text = (value) => ({ isText: true, isTextblock: false, text: value, nodeSize: value.length, type: { name: 'text', spec: {} } })

const leaf = (name) => ({ isText: false, isTextblock: false, nodeSize: 1, type: { name, spec: {} }, children: [] })

const node = (name, children = [], { textblock = false, code = false } = {}) => ({
    isText: false,
    isTextblock: textblock,
    type: { name, spec: code ? { code: true } : {} },
    children,
    nodeSize: children.reduce((size, child) => size + child.nodeSize, 0) + 2,
})

const paragraph = (...children) => node('paragraph', children, { textblock: true })
const heading = (...children) => node('heading', children, { textblock: true })
const codeBlock = (...children) => node('codeBlock', children, { textblock: true, code: true })
const hardBreak = () => leaf('hardBreak')

/**
 * `descendants()`, `nodesBetween()` and `forEach()` as ProseMirror defines them, over the
 * nodes above. `walked` counts how often a node's inline content was read, which is how a
 * test sees that a paragraph nobody touched was left alone.
 */
function walkable(target) {
    const children = (target.children ?? []).map(walkable)

    return {
        ...target,
        children,
        walked: 0,
        content: { size: children.reduce((size, child) => size + child.nodeSize, 0) },
        nodesBetween(from, to, callback, start = 0) {
            let pos = start

            for (const child of children) {
                const end = pos + child.nodeSize

                if (end > from && pos < to && callback(child, pos) !== false && child.children.length > 0) {
                    child.nodesBetween(from, to, callback, pos + 1)
                }

                pos = end
            }
        },
        forEach(callback) {
            this.walked++

            let offset = 0

            for (const child of children) {
                callback(child, offset)
                offset += child.nodeSize
            }
        },
        descendants(callback) {
            const visit = (parent, start) => {
                let pos = start

                for (const child of parent.children) {
                    if (callback(child, pos, parent) !== false && child.children.length > 0) {
                        visit(child, pos + 1)
                    }

                    pos += child.nodeSize
                }
            }

            visit(this, 0)
        },
    }
}

const doc = (...children) => walkable({ ...node('doc', children), nodeSize: undefined })

describe('what counts as invisible', () => {
    it('knows the spaces that may not break, the soft hyphen and the characters with no width', () => {
        expect(INVISIBLE_CHARACTERS).toEqual({
            ' ': 'space',
            ' ': 'space',
            ' ': 'space',
            '­': 'hyphen',
            '​': 'zero-width',
            '⁠': 'zero-width',
            '﻿': 'zero-width',
        })
    })

    it('leaves the joiners alone, because every family emoji is held together by one', () => {
        // U+200D joins the people of 👨‍👩‍👧 into one picture and U+200C keeps Persian letters
        // apart. Neither is a stray character to be found, and marking them would put a
        // hairline inside an emoji.
        expect(INVISIBLE_CHARACTERS).not.toHaveProperty('‍')
        expect(INVISIBLE_CHARACTERS).not.toHaveProperty('‌')
        expect(invisiblesIn(doc(paragraph(text('👨‍👩‍👧'))))).toEqual([{ type: 'paragraph', pos: 9 }])
    })

    it('does not mark an ordinary space or a tab', () => {
        // A mark on every space is what makes Word's view so loud, and one decoration per
        // space is the one cost here that grows with every word typed.
        expect(invisiblesIn(doc(paragraph(text('a b\tc'))))).toEqual([{ type: 'paragraph', pos: 6 }])
    })
})

describe('where each one is', () => {
    it('finds a character by the positions around it', () => {
        // `<p>` opens at 0, so the text starts at 1: `1` is at 1, the space at 2.
        expect(invisiblesIn(doc(paragraph(text('1 kg'))))).toEqual([
            { type: 'character', kind: 'space', from: 2, to: 3 },
            { type: 'paragraph', pos: 5 },
        ])
    })

    it('tells the three kinds apart', () => {
        const found = invisiblesIn(doc(paragraph(text('a b­c​d﻿'))))

        expect(found.filter((one) => one.type === 'character').map((one) => one.kind)).toEqual([
            'space',
            'hyphen',
            'zero-width',
            'zero-width',
        ])
    })

    it('counts on across text nodes, the way a mark splits them', () => {
        // `a<strong>b</strong> c`: three text nodes, and the space in the third.
        expect(invisiblesIn(doc(paragraph(text('a'), text('b'), text(' c'))))).toEqual([
            { type: 'character', kind: 'space', from: 3, to: 4 },
            { type: 'paragraph', pos: 5 },
        ])
    })

    it('puts a line break in front of the break it stands for', () => {
        // `a`, then the break at 2, then `b` at 3.
        expect(invisiblesIn(doc(paragraph(text('a'), hardBreak(), text('b'))))).toEqual([
            { type: 'break', pos: 2 },
            { type: 'paragraph', pos: 4 },
        ])
    })

    it('ends every paragraph and heading where a caret after the last letter sits', () => {
        expect(invisiblesIn(doc(heading(text('Title')), paragraph(text('Body'))))).toEqual([
            { type: 'paragraph', pos: 6 },
            { type: 'paragraph', pos: 12 },
        ])
    })

    it('ends an empty paragraph too, which is the one most worth seeing', () => {
        // Two empty paragraphs in a row are what a stray Enter leaves behind, and nothing
        // else on the page says they are there.
        expect(invisiblesIn(doc(paragraph(), paragraph()))).toEqual([
            { type: 'paragraph', pos: 1 },
            { type: 'paragraph', pos: 3 },
        ])
    })

    it('reaches paragraphs inside lists, quotes and tables', () => {
        // ul(0) li(1) p(2) `a`(3) - the paragraph ends at 4.
        const list = node('bulletList', [node('listItem', [paragraph(text('a'))])])
        const table = node('table', [node('tableRow', [node('tableCell', [paragraph(text(' '))])])])

        expect(invisiblesIn(doc(list))).toEqual([{ type: 'paragraph', pos: 4 }])
        // table(0) row(1) cell(2) p(3) and the space at 4.
        expect(invisiblesIn(doc(table))).toEqual([
            { type: 'character', kind: 'space', from: 4, to: 5 },
            { type: 'paragraph', pos: 5 },
        ])
    })

    it('marks the characters in code but gives code no paragraph sign', () => {
        // A no-break space pasted into a shell command is exactly the thing to find. A `¶`
        // after the code would read as part of it.
        expect(invisiblesIn(doc(codeBlock(text('ls -l'))))).toEqual([
            { type: 'character', kind: 'space', from: 3, to: 4 },
        ])
    })

    it('steps over blocks with nothing inside them', () => {
        expect(invisiblesIn(doc(paragraph(text('a')), leaf('horizontalRule'), paragraph(text('b'))))).toEqual([
            { type: 'paragraph', pos: 2 },
            { type: 'paragraph', pos: 6 },
        ])
    })
})

describe('what an edit touched', () => {
    it('finds the paragraph of every step, in the positions of the finished document', () => {
        // Two steps: a letter typed into `two`, then a whole paragraph put in front of
        // everything. The first step's place has to be carried through the second - read as it
        // was, it would point into `one` and leave `txwo` with the signs of `two`.
        const after = doc(paragraph(text('new')), paragraph(text('one')), paragraph(text('txwo')))
        const steps = [(pos) => pos, (pos) => pos + 5]
        const mapping = {
            maps: [
                // `x` typed at 7, after the `t` of `two`.
                { forEach: (callback) => callback(7, 7, 7, 8) },
                // `<p>new</p>`, five positions, put in at the start.
                { forEach: (callback) => callback(0, 0, 0, 5) },
            ],
            slice: (from) => ({ map: (pos) => steps.slice(from).reduce((moved, step) => step(moved), pos) }),
        }

        // `<p>new</p>` 0-5, `<p>one</p>` 5-10, `<p>txwo</p>` 10-16. `one` is read because it
        // borders the new paragraph.
        expect([...touchedTextblocks({ mapping }, after).keys()].sort((a, b) => a - b)).toEqual([0, 5, 10])
    })

    it('reads the neighbours of a paragraph that was taken out', () => {
        // Deleting the middle paragraph leaves a range with nothing in it, at the seam between
        // the other two. Both of them are read again, since either may have lost a sign there.
        const after = doc(paragraph(text('one')), paragraph(text('three')))
        const mapping = {
            maps: [{ forEach: (callback) => callback(5, 10, 5, 5) }],
            slice: () => ({ map: (pos) => pos }),
        }

        expect([...touchedTextblocks({ mapping }, after).keys()]).toEqual([0, 5])
    })
})

/**
 * The extension, built against a TipTap and a ProseMirror that hand back what they are
 * given - which is the honest shape of these assertions: what is checked is what the
 * extension asks ProseMirror for, not whether ProseMirror draws it.
 */
const EMPTY = { empty: true }

function build() {
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

                    getState(state) {
                        return state.plugins?.[this.name]
                    }
                },
            },
            pmView: {
                Decoration: {
                    inline: (from, to, attrs) => ({ inline: true, from, to, attrs }),
                    widget: (pos, toDOM, spec) => ({ widget: true, pos, toDOM, spec }),
                },
                DecorationSet: {
                    empty: EMPTY,
                    create: (target, decorations) => new FakeSet(target, decorations),
                },
            },
        },
    }

    const definition = invisiblesExtension()
    const storage = definition.addStorage()
    const [plugin] = definition.addProseMirrorPlugins.call({ storage })
    const commands = definition.addCommands.call({ storage })

    return { definition, storage, plugin, commands }
}

/**
 * A decoration set holding a plain list. What it has to get right for these tests is which
 * decorations survive a redraw and which are new, so mapping moves positions and nothing
 * else.
 */
class FakeSet {
    constructor(target, decorations) {
        this.doc = target
        this.decorations = decorations
    }

    map(mapping, target) {
        const moved = (pos) => mapping.map(pos)

        return new FakeSet(
            target,
            this.decorations.map((one) =>
                one.inline ? { ...one, from: moved(one.from), to: moved(one.to) } : { ...one, pos: moved(one.pos) },
            ),
        )
    }

    // Touching counts, at either end - ProseMirror's own rule.
    find(from, to) {
        return this.decorations.filter((one) => (one.from ?? one.pos) <= to && (one.to ?? one.pos) >= from)
    }

    remove(gone) {
        return new FakeSet(this.doc, this.decorations.filter((one) => !gone.includes(one)))
    }

    add(target, more) {
        return new FakeSet(target, [...this.decorations, ...more])
    }
}

/** What typing `length` characters at `at` maps positions by, as a one-step `Mapping`. */
function insertion(at, length) {
    const map = (pos) => (pos >= at ? pos + length : pos)

    return {
        maps: [{ forEach: (callback) => callback(at, at, at, at + length) }],
        map,
        slice: () => ({ map: (pos) => pos }),
    }
}

/** A transaction as a command and a plugin see one. */
function transaction({ meta = {}, docChanged = false, mapping = null } = {}) {
    const set = { ...meta }

    return {
        docChanged,
        mapping,
        setMeta(key, value) {
            set[key.name ?? key] = value

            return this
        },
        getMeta: (key) => set[key.name ?? key],
    }
}

const sample = () => doc(paragraph(text('a b'), hardBreak()))

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('the extension', () => {
    it('starts with both views off', () => {
        expect(build().storage).toEqual({ characters: false, blocks: false })
    })

    it('stays out of the way while the characters are not shown', () => {
        const { plugin } = build()
        const state = { doc: sample() }

        expect(plugin.spec.state.init({}, state)).toBe(EMPTY)
        // Not even on an edit: nothing is walked while nobody is looking.
        expect(plugin.spec.state.apply(transaction({ docChanged: true }), EMPTY, state, state)).toBe(EMPTY)
    })

    it('switches each view only when the command is actually run', () => {
        const { storage, commands } = build()

        // `can()` asks without a dispatch, and asking must not change anything.
        expect(commands.toggleInvisibleCharacters()({ tr: transaction(), dispatch: undefined })).toBe(true)
        expect(storage.characters).toBe(false)

        const tr = transaction()

        commands.toggleInvisibleCharacters()({ tr, dispatch: () => {} })
        expect(storage.characters).toBe(true)
        // A transaction with no steps is what makes the view draw again - and Filament
        // move the button's pressed state with it.
        expect(tr.getMeta('arteInvisibles')).toBe('characters')

        commands.toggleBlockOutlines()({ tr: transaction(), dispatch: () => {} })
        expect(storage).toEqual({ characters: true, blocks: true })

        commands.toggleInvisibleCharacters()({ tr: transaction(), dispatch: () => {} })
        expect(storage.characters).toBe(false)
    })

    it('draws the characters once switched on', () => {
        const { storage, plugin } = build()
        const state = { doc: sample() }
        storage.characters = true

        const drawn = plugin.spec.state.apply(transaction({ meta: { arteInvisibles: 'characters' } }), EMPTY, state, state)

        expect(drawn.doc).toBe(state.doc)
        expect(drawn.decorations.map((one) => one.from ?? one.pos)).toEqual([2, 4, 5])
    })

    it('draws again only the paragraph an edit touched', () => {
        // Measured on a document of 3,000 paragraphs: walking all of it for each keystroke
        // took typing from 6 to 33 ms, two frames. The edit says where it happened, and only
        // the paragraph around that place can have a sign that changed.
        const { storage, plugin } = build()
        storage.characters = true
        const before = doc(paragraph(text('one')), paragraph(text('two')), paragraph(text('three')))
        const drawn = plugin.spec.state.init({}, { doc: before })

        // A no-break space typed after `t` of `two`: `<p>one</p>` is 0-5, `<p>` of `two`
        // opens at 5, `t` is at 6, so the space goes in at 7.
        const after = doc(paragraph(text('one')), paragraph(text('t\u00A0wo')), paragraph(text('three')))
        const redrawn = plugin.spec.state.apply(
            transaction({ docChanged: true, mapping: insertion(7, 1) }),
            drawn,
            { doc: before },
            { doc: after },
        )

        const [first, second, third] = after.children

        expect(first.walked).toBe(0)
        expect(second.walked).toBe(1)
        expect(third.walked).toBe(0)
        expect(redrawn.doc).toBe(after)
        // The first paragraph's sign is where it was, the second has its new space and its
        // sign one further on, and the third's moved along with the text in front of it.
        expect(redrawn.decorations.map((one) => [one.inline ? 'space' : one.spec.key, one.from ?? one.pos]).sort((a, b) => a[1] - b[1]))
            .toEqual([
                ['paragraph', 4],
                ['space', 7],
                ['paragraph', 10],
                ['paragraph', 17],
            ])
    })

    it('does not draw the characters again when only the blocks are switched', () => {
        const { storage, plugin } = build()
        storage.characters = true
        const drawn = plugin.spec.state.init({}, { doc: sample() })

        expect(plugin.spec.state.apply(transaction({ meta: { arteInvisibles: 'blocks' } }), drawn, {}, {})).toBe(drawn)
    })

    it('keeps what it drew while only the caret moves', () => {
        // Every click and arrow key is a transaction. Walking the document for each of them
        // would make the view cost something on every keystroke that changes nothing.
        const { storage, plugin } = build()
        storage.characters = true
        const drawn = { doc: null, decorations: [] }

        expect(plugin.spec.state.apply(transaction(), drawn, {}, {})).toBe(drawn)
    })

    it('draws everything again when the editor starts with the view already on', () => {
        // A state that is set up again - the editor reconfigured - begins from `init`, and
        // the view must not go blank while the button still says it is on.
        const { storage, plugin } = build()
        storage.characters = true

        expect(plugin.spec.state.init({}, { doc: sample() }).decorations).toHaveLength(3)
    })

    it('draws a character over itself, and a break and a paragraph end beside the caret', () => {
        const { storage, plugin } = build()
        storage.characters = true
        const [space, lineBreak, end] = plugin.spec.state.init({}, { doc: sample() }).decorations

        expect(space).toEqual({ inline: true, from: 2, to: 3, attrs: { class: 'fi-arte-invisible fi-arte-invisible-space' } })
        // The caret at the break sits after the `↵`, at the end of a paragraph before the
        // `¶` - which is where Word draws both.
        expect(lineBreak.spec).toMatchObject({ side: -1, key: 'break', marks: [] })
        expect(end.spec).toMatchObject({ side: 1, key: 'paragraph', marks: [] })

        const sign = end.toDOM()

        expect(sign.className).toBe('fi-arte-invisible-paragraph')
        // The sign is drawn by the stylesheet: the element holds no text that could be read
        // out, selected or copied.
        expect(sign.getAttribute('aria-hidden')).toBe('true')
        expect(sign.textContent).toBe('')
        expect(lineBreak.toDOM().className).toBe('fi-arte-invisible-break')
    })

    it('hands ProseMirror what the plugin holds', () => {
        const { plugin } = build()
        const decorations = { doc: null, decorations: [] }

        expect(plugin.spec.props.decorations({ plugins: { arteInvisibles: decorations } })).toBe(decorations)
    })

    it('outlines the blocks with a class on the editor and nothing else', () => {
        // No decorations: the outlines and their labels are the stylesheet's, so they cost
        // the same in a document of ten paragraphs as in one of ten thousand.
        const { storage, plugin } = build()

        expect(plugin.spec.props.attributes({})).toEqual({})

        storage.blocks = true

        expect(plugin.spec.props.attributes({})).toEqual({ class: 'fi-arte-show-blocks' })
    })
})
