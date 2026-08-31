import { describe, expect, it } from 'vitest'
import {
    allowsTransaction,
    countDocument,
    historyKeyIn,
    limitFrom,
    limitPluginsFor,
    replacesWholeDocument,
} from '../../resources/js/character-count.js'

/** What `window.FilamentRichEditor.tiptap.pmState` gives the module, reduced to what it uses. */
const stubPmState = {
    Plugin: class { constructor(spec) { this.spec = spec } },
    PluginKey: class { constructor(name) { this.key = name } },
}

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
    const decide = ({ before = 0, ...attributes }) => allowsTransaction({
        limit: 10,
        changed: true,
        replacesWholeDocument: false,
        isHistory: false,
        // Handed as a callback rather than a number: the old size is only needed in the one
        // branch where the result is already too long, and measuring it costs a walk of the
        // whole document on a path that runs per keystroke.
        before: () => before,
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
        let asked = 0
        const before = () => {
            asked += 1

            return 5
        }

        allowsTransaction({ limit: 10, changed: true, replacesWholeDocument: false, isHistory: false, before, after: 8 })
        expect(asked).toBe(0)

        allowsTransaction({ limit: 10, changed: true, replacesWholeDocument: false, isHistory: false, before, after: 12 })
        expect(asked).toBe(1)
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

describe('finding the history plugin', () => {
    it('reads the key off the plugins that are actually there', () => {
        // `history$` is a name ProseMirror generates by appending a counter, not an API. A
        // second key of that name registered first makes it `history$1`, and a literal would
        // stop matching without anything saying so.
        expect(historyKeyIn([{ key: 'arte$' }, { key: 'history$' }])).toBe('history$')
        expect(historyKeyIn([{ key: 'history$1' }])).toBe('history$1')
    })

    it('says so rather than guessing where there is no history plugin', () => {
        expect(historyKeyIn([{ key: 'arte$' }])).toBeNull()
    })
})

describe('the extension against an editor', () => {
    const editorWith = (dataset) => ({ options: { element: { dataset } } })

    it('registers no filter for a field that only shows the number', () => {
        expect(limitPluginsFor(editorWith({}), stubPmState)).toEqual([])
        expect(limitPluginsFor(editorWith({ arteCharacterCount: '{"limit":9,"enforce":false}' }), stubPmState)).toEqual([])
    })

    it('registers one that reads the limit the field was given', () => {
        const plugins = limitPluginsFor(editorWith({ arteCharacterCount: '{"limit":9,"enforce":true}' }), stubPmState)

        expect(plugins).toHaveLength(1)

        // The wiring the pure rule cannot check: that the limit came off the element, and
        // that an undo is recognised through the key the editor's own plugins carry.
        const state = { doc: { toJSON: () => doc(paragraph('aaaa')), content: { size: 6 } }, plugins: [{ key: 'history$' }] }
        const typed = { docChanged: true, steps: [{ from: 2, to: 2 }], doc: { toJSON: () => doc(paragraph('aaaaaaaaaaaa')) }, getMeta: () => undefined }
        const undone = { ...typed, getMeta: (key) => (key === 'history$' ? {} : undefined) }

        expect(plugins[0].spec.filterTransaction(typed, state)).toBe(false)
        expect(plugins[0].spec.filterTransaction(undone, state)).toBe(true)
    })
})
