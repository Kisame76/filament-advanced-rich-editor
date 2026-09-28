import { describe, expect, it } from 'vitest'
import {
    allowsTransaction,
    charactersIn,
    countDocument,
    isHistoryTransaction,
    limitFrom,
    limitPluginsFor,
    replacesWholeDocument,
} from '../../resources/js/character-count.js'

/**
 * What `window.FilamentRichEditor.tiptap.pmState` gives the module, reduced to what it uses.
 *
 * The key-building is copied from ProseMirror rather than simplified, because the key is
 * exactly what this module reads: `PluginKey` appends `$` to the name and a counter after
 * that for every repeat, and a `Plugin` takes its key off the `PluginKey` it was given. A
 * stub that handed back the bare name would let a test pass against a shape no editor has.
 */
const generatedKeys = Object.create(null)

const createKey = (name) => {
    if (name in generatedKeys) {
        return `${name}$${++generatedKeys[name]}`
    }

    generatedKeys[name] = 0

    return `${name}$`
}

const stubPmState = {
    Plugin: class { constructor(spec) { this.spec = spec; this.key = spec.key ? spec.key.key : createKey('plugin') } },
    PluginKey: class { constructor(name = 'key') { this.key = createKey(name) } },
}

/** A plugin list of the shape `state.plugins` has, built through the stub above. */
const pluginsNamed = (...names) => names.map((name) => new stubPmState.Plugin({ key: new stubPmState.PluginKey(name) }))

/**
 * The two decisions the character count makes, without an editor to make them in.
 *
 * Counting is not this package's own idea of how long a text is: it mirrors what Filament
 * validates `maxLength` against, down to two things nobody would expect. The serialiser
 * escapes for HTML, so a single `&` costs the five characters of `&amp;`, and it joins
 * *every* nesting level with a blank line, so a list item inside a list costs two
 * separators rather than one. A friendlier number here would be a number the save is not
 * refused over.
 *
 * The second decision is which transactions the editor refuses once a field enforces that
 * limit, and it is the one with the trap in it - a filter that only looked at the size
 * would lock a document that is already too long, and would refuse to load it in the first
 * place.
 */

const doc = (...content) => ({ type: 'doc', content })
const paragraph = (text) => ({ type: 'paragraph', content: [{ type: 'text', text }] })

describe('counting the way the save is measured', () => {
    it('counts what the serialiser escapes rather than what was typed', () => {
        // `htmlspecialchars` on the PHP side, so one ampersand is five characters. This is
        // the number `maxLength` compares against; anything smaller is a friendlier lie.
        expect(countDocument(doc(paragraph('Fish & chips'))).characters).toBe(16)
    })

    it('counts words on the text as written', () => {
        // Nobody means `&amp;` when they count words, so the escaping is undone first.
        expect(countDocument(doc(paragraph('Fish & chips'))).words).toBe(3)
    })

    it('joins blocks with a blank line, at every level', () => {
        expect(countDocument(doc(paragraph('ab'), paragraph('cd'))).characters).toBe(6)
    })

    it('counts an emoji as one character, the way mb_strlen does', () => {
        expect(countDocument(doc(paragraph('a🙂'))).characters).toBe(2)
    })

    it('counts an empty document as nothing', () => {
        expect(countDocument(doc())).toEqual({ characters: 0, words: 0 })
    })

    it('gives the same character count on its own as it does with the words', () => {
        // The limit asks only for this one, and asking through `countDocument()` walked the
        // document a second time to build a word count it threw away. Two numbers that can
        // disagree would be worse than the walk, so they are asserted equal.
        for (const document of [doc(), doc(paragraph('Fish & chips')), doc(paragraph('ab'), paragraph('cd')), doc(paragraph('a🙂'))]) {
            expect(charactersIn(document)).toBe(countDocument(document).characters)
        }
    })
})

describe('recognising a whole-document replacement', () => {
    it('knows the one step that replaces everything', () => {
        // What `setContent` does: hydration, the source dialog, a restored draft. One step
        // covering the old document from end to end.
        expect(replacesWholeDocument([{ from: 0, to: 12 }], 12)).toBe(true)
    })

    it('does not mistake typing for one', () => {
        expect(replacesWholeDocument([{ from: 4, to: 4 }], 12)).toBe(false)
        expect(replacesWholeDocument([{ from: 0, to: 6 }], 12)).toBe(false)
    })

    it('does not mistake two steps for one', () => {
        expect(replacesWholeDocument([{ from: 0, to: 12 }, { from: 0, to: 3 }], 12)).toBe(false)
    })
})

describe('deciding what the editor refuses', () => {
    const decide = ({ before = 0, after = 0, isHistory = false, ...attributes }) => allowsTransaction({
        limit: 10,
        changed: true,
        replacesWholeDocument: false,
        // Written as values here and handed on as callbacks, which is how the rule takes
        // them: each of the three costs either a walk of the whole document or a scan of
        // the editor's plugins, and the branches above them answer most transactions
        // without asking. The laziness itself is asserted further down.
        isHistory: () => isHistory,
        before: () => before,
        after: () => after,
        ...attributes,
    })

    it('allows anything while there is no limit', () => {
        expect(decide({ limit: null, before: 5, after: 500 })).toBe(true)
    })

    it('allows a transaction that changes no text', () => {
        // Moving the caret, selecting, marking a node: nothing to measure.
        expect(decide({ changed: false, before: 50, after: 50 })).toBe(true)
    })

    it('allows what stays inside the limit', () => {
        expect(decide({ before: 5, after: 9 })).toBe(true)
    })

    it('allows what lands exactly on it', () => {
        expect(decide({ before: 5, after: 10 })).toBe(true)
    })

    it('refuses what would go past it', () => {
        expect(decide({ before: 9, after: 11 })).toBe(false)
    })

    it('lets a document that is already too long be shortened', () => {
        // The trap: a rule that only asked "is the result too long" would lock a document
        // somebody saved before the limit existed - too long to edit, and too long to fix.
        expect(decide({ before: 50, after: 40 })).toBe(true)
        expect(decide({ before: 50, after: 50 })).toBe(true)
    })

    it('refuses to let a document that is already too long grow', () => {
        expect(decide({ before: 50, after: 51 })).toBe(false)
    })

    it('measures the old document only when the new one is too long', () => {
        // The reason `before` is a callback: on every keystroke that stays inside the limit
        // - which is nearly all of them - the old size is never asked for.
        const counted = { asked: 0 }
        const before = () => {
            counted.asked += 1

            return 5
        }

        allowsTransaction({ limit: 10, changed: true, replacesWholeDocument: false, isHistory: () => false, before, after: () => 8 })
        expect(counted.asked).toBe(0)

        allowsTransaction({ limit: 10, changed: true, replacesWholeDocument: false, isHistory: () => false, before, after: () => 12 })
        expect(counted.asked).toBe(1)
    })

    it('measures nothing at all for a transaction that changes no text', () => {
        // A caret moving is most of what an editor sees, and it is answered by the first
        // line. Measuring the new document there cost a walk of the whole thing per arrow
        // key - about nine milliseconds on a hundred-thousand-character article, for a
        // number no branch went on to read.
        const asked = []
        const record = (what, value) => () => {
            asked.push(what)

            return value
        }

        const answer = allowsTransaction({
            limit: 10,
            changed: false,
            replacesWholeDocument: false,
            isHistory: record('isHistory', false),
            before: record('before', 500),
            after: record('after', 500),
        })

        expect(answer).toBe(true)
        expect(asked).toEqual([])
    })

    it('asks whether it is an undo before it measures anything', () => {
        // The plugin scan is cheaper than a walk of the document, so it comes first - and
        // once it says yes, neither size is ever asked for.
        const asked = []
        const record = (what, value) => () => {
            asked.push(what)

            return value
        }

        const answer = allowsTransaction({
            limit: 10,
            changed: true,
            replacesWholeDocument: false,
            isHistory: record('isHistory', true),
            before: record('before', 40),
            after: record('after', 90),
        })

        expect(answer).toBe(true)
        expect(asked).toEqual(['isHistory'])
    })

    it('lets an undo through even where it grows the document', () => {
        // A document that was already too long, shortened and then taken back: without this
        // the undo is dropped and the deletion cannot be undone. Nothing is being typed, so
        // nothing is being let past the limit that was not already past it.
        expect(decide({ isHistory: true, before: 40, after: 90 })).toBe(true)
    })

    it('lets the whole document be replaced whatever its size', () => {
        // Hydration, the source dialog, a restored draft, an undo that walks back over the
        // limit. Without this a stored document past the limit could not even be loaded.
        expect(decide({ replacesWholeDocument: true, before: 0, after: 500 })).toBe(true)
    })
})

describe('reading the limit off the field', () => {
    it('holds nothing where the field said nothing', () => {
        expect(limitFrom(undefined)).toBeNull()
        expect(limitFrom('')).toBeNull()
    })

    it('holds the limit a field asked it to hold', () => {
        expect(limitFrom('{"limit":200,"enforce":true}')).toBe(200)
    })

    it('holds nothing while the field only shows the number', () => {
        // A counter under the field is not a rule; only `enforce` makes it one.
        expect(limitFrom('{"limit":200,"enforce":false}')).toBeNull()
    })

    it('holds nothing rather than throwing on markup it cannot read', () => {
        // Whatever went wrong, the field keeps working - it just stops refusing.
        expect(limitFrom('{ not json')).toBeNull()
    })
})

describe('the boundary between the page and the rule', () => {
    it('hands on a number whatever the page wrote', () => {
        // A limit compared to `after` by coercion works until the day it does not.
        expect(limitFrom('{"limit":"260","enforce":true}')).toBe(260)
    })
})

describe('recognising an undo', () => {
    const marked = (...keys) => ({ getMeta: (key) => (keys.includes(key) ? {} : undefined) })

    it('reads the key off the plugins that are actually there', () => {
        // `history$` is a name ProseMirror generates, not an API. It is matched as a prefix
        // and read off the editor's own plugins, because a literal stops matching the day
        // something else registers that name first.
        const plugins = [{ key: 'arte$' }, { key: 'history$' }]

        expect(isHistoryTransaction(marked('history$'), plugins)).toBe(true)
        expect(isHistoryTransaction(marked(), plugins)).toBe(false)
    })

    it('finds the history that did not get the bare name', () => {
        // The counter lands on whoever came second, so `history$1` is a real history key.
        expect(isHistoryTransaction(marked('history$1'), [{ key: 'history$1' }])).toBe(true)
    })

    it('asks every plugin that looks like history rather than the first one', () => {
        // The case that made picking one wrong: whichever plugin registered the name first
        // owns the bare `history$`, so an impostor registered first is exactly the key a
        // `find()` would settle on - leaving the real history, now `history$1`, unasked and
        // every undo refused in a document that is already too long.
        const plugins = [{ key: 'history$' }, { key: 'arte$' }, { key: 'history$1' }]

        expect(isHistoryTransaction(marked('history$1'), plugins)).toBe(true)
        expect(isHistoryTransaction(marked('history$'), plugins)).toBe(true)
        expect(isHistoryTransaction(marked('arte$'), plugins)).toBe(false)
    })

    it('says no rather than guessing where there is no history plugin', () => {
        expect(isHistoryTransaction(marked('history$'), [{ key: 'arte$' }])).toBe(false)
        expect(isHistoryTransaction(marked('history$'), undefined)).toBe(false)
    })

    it('is not fooled by a name that merely starts with the word', () => {
        // `closeHistory` is a real key in the editor this ships into, and it is not history.
        expect(isHistoryTransaction(marked('closeHistory$'), [{ key: 'closeHistory$' }])).toBe(false)
    })
})

describe('the extension against an editor', () => {
    const editorWith = (dataset) => ({ options: { element: { dataset } } })

    const held = '{"limit":9,"enforce":true}'

    it('registers no filter for a field that only shows the number', () => {
        expect(limitPluginsFor(editorWith({}), stubPmState)).toEqual([])
        expect(limitPluginsFor(editorWith({ arteCharacterCount: '{"limit":9,"enforce":false}' }), stubPmState)).toEqual([])
    })

    it('registers no filter where Filament exposed no ProseMirror to build one with', () => {
        // The field keeps its counter and stops refusing, rather than throwing on every
        // editor on the page as the destructuring below would.
        expect(limitPluginsFor(editorWith({ arteCharacterCount: held }), undefined)).toEqual([])
        expect(limitPluginsFor(editorWith({ arteCharacterCount: held }), null)).toEqual([])
    })

    it('registers one that reads the limit the field was given', () => {
        const plugins = limitPluginsFor(editorWith({ arteCharacterCount: held }), stubPmState)

        expect(plugins).toHaveLength(1)

        // Named through `PluginKey`, so the key carries the generated suffix rather than
        // the bare name - which is the shape `isHistoryTransaction` reads.
        expect(plugins[0].key).toMatch(/^arteCharacterCountLimit\$/)

        // The wiring the pure rule cannot check: that the limit came off the element, and
        // that an undo is recognised through the key the editor's own plugins carry.
        const history = pluginsNamed('history')[0]
        const state = { doc: { toJSON: () => doc(paragraph('aaaa')), content: { size: 6 } }, plugins: [history] }
        const typed = { docChanged: true, steps: [{ from: 2, to: 2 }], doc: { toJSON: () => doc(paragraph('aaaaaaaaaaaa')) }, getMeta: () => undefined }
        const undone = { ...typed, getMeta: (key) => (key === history.key ? {} : undefined) }
        const moved = { ...typed, docChanged: false }

        expect(plugins[0].spec.filterTransaction(typed, state)).toBe(false)
        expect(plugins[0].spec.filterTransaction(undone, state)).toBe(true)
        expect(plugins[0].spec.filterTransaction(moved, state)).toBe(true)
    })

    it('lets the whole document arrive however long it is', () => {
        const plugins = limitPluginsFor(editorWith({ arteCharacterCount: held }), stubPmState)

        const state = { doc: { toJSON: () => doc(paragraph('aaaa')), content: { size: 6 } }, plugins: [] }
        const loaded = {
            docChanged: true,
            steps: [{ from: 0, to: 6 }],
            doc: { toJSON: () => doc(paragraph('aaaaaaaaaaaa')) },
            getMeta: () => undefined,
        }

        expect(plugins[0].spec.filterTransaction(loaded, state)).toBe(true)
    })
})
