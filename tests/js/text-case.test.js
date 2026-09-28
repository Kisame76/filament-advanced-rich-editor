import { afterEach, describe, expect, it, vi } from 'vitest'
import textCaseExtension, {
    CASE_MODES,
    applyCase,
    caseEditsIn,
    nextCaseMode,
} from '../../resources/js/text-case.js'

/**
 * Changing the case of a selection.
 *
 * The transform is a function over a string and a carried flag, and that is the design
 * rather than a convenience. A selection is several text nodes - `he<strong>ll</strong>o`
 * is three - and each has to be written back on its own so that its marks survive. Sentence
 * case, though, cannot be decided one node at a time: whether the `w` in `world` is a
 * sentence start depends on what came before it, possibly in another node. So the flag
 * travels with the caller from node to node, and everything here stays testable without a
 * ProseMirror instance.
 *
 * The alternative - flattening the selection, transforming it, and mapping the offsets back
 * - breaks on the one case that matters most here: uppercasing `ß` yields `SS`, so the
 * transformed string is longer than the one the offsets were taken from.
 */

const upper = (text, start = true) => applyCase(text, 'upper', start)
const lower = (text, start = true) => applyCase(text, 'lower', start)
const sentence = (text, start = true) => applyCase(text, 'sentence', start)

describe('upper and lower', () => {
    it('raises and lowers', () => {
        expect(upper('hello').text).toBe('HELLO')
        expect(lower('HeLLo').text).toBe('hello')
    })

    it('grows the string where a letter has no single-character upper case', () => {
        // The reason the caller writes back to front: this is longer than what it replaces.
        expect(upper('straße').text).toBe('STRASSE')
    })

    it('leaves the sentence flag alone, because neither mode reads it', () => {
        expect(upper('hello.', false).atSentenceStart).toBe(false)
        expect(lower('hello.', true).atSentenceStart).toBe(true)
    })
})

describe('sentence case', () => {
    it('raises the first letter and lowers the rest', () => {
        expect(sentence('hELLO WORLD').text).toBe('Hello world')
    })

    it('starts a new sentence after a full stop', () => {
        expect(sentence('one thing. another thing').text).toBe('One thing. Another thing')
    })

    it('treats a question and an exclamation the same way', () => {
        expect(sentence('who? nobody! really').text).toBe('Who? Nobody! Really')
    })

    it('does not start a sentence on a full stop inside a word', () => {
        // A file name and an abbreviation both end up here, and neither is a new sentence.
        expect(sentence('see readme.md for this').text).toBe('See readme.md for this')
    })

    it('raises the first letter rather than the first character', () => {
        expect(sentence('"hello there"').text).toBe('"Hello there"')
    })

    it('counts a digit as the start of the sentence, not as something to skip past', () => {
        // Otherwise "3 things happened" comes back as "3 Things happened": the sentence has
        // begun, it just did not begin with a letter. An opening quote is the opposite case,
        // which is why the two are told apart rather than lumped together as punctuation.
        expect(sentence('3 things happened').text).toBe('3 things happened')
    })

    it('carries the flag out for the next node to use', () => {
        // `he` ends mid-sentence, so whatever follows continues it.
        expect(sentence('he said.').atSentenceStart).toBe(true)
        expect(sentence('he said').atSentenceStart).toBe(false)
    })

    it('carries the flag in, so a node can continue what another one started', () => {
        // `he` then `llo. and` - the second node must not raise its own first letter.
        expect(sentence('llo. and', false).text).toBe('llo. And')
    })

    it('keeps whitespace from closing a sentence on its own', () => {
        expect(sentence('   ', false).atSentenceStart).toBe(false)
        expect(sentence('   ', true).atSentenceStart).toBe(true)
    })

    it('says nothing about an empty string', () => {
        expect(sentence('', false)).toEqual({ text: '', atSentenceStart: false })
    })
})

describe('the cycle', () => {
    it('offers exactly the three modes', () => {
        expect(CASE_MODES).toEqual(['sentence', 'lower', 'upper'])
    })

    it('walks the modes in order and wraps', () => {
        expect(nextCaseMode('sentence')).toBe('lower')
        expect(nextCaseMode('lower')).toBe('upper')
        expect(nextCaseMode('upper')).toBe('sentence')
    })

    it('starts at the first mode when nothing was applied yet', () => {
        expect(nextCaseMode(null)).toBe('sentence')
        expect(nextCaseMode('nonsense')).toBe('sentence')
    })
})

/**
 * The walk over the selection.
 *
 * A stand-in document, built the same way `find-replace.test.js` builds one: ProseMirror is
 * not installed here, and what is being asserted is the arithmetic, not ProseMirror. A block
 * occupies one position on each side of its children, so a paragraph holding `hello` starts
 * at 0, its text at 1, and the next block at 7.
 */
const text = (value) => ({ isText: true, isBlock: false, text: value })

const doc = (blocks) => {
    const visits = []

    let pos = 0

    for (const children of blocks) {
        visits.push([{ isText: false, isBlock: true }, pos])
        pos += 1

        for (const child of children) {
            visits.push([child, pos])
            pos += child.text.length
        }

        pos += 1
    }

    return {
        nodesBetween(from, to, callback) {
            for (const [node, at] of visits) {
                const end = at + (node.isText ? node.text.length : 1)

                if (end > from && at < to) {
                    callback(node, at)
                }
            }
        },
    }
}

describe('the edits a selection produces', () => {
    it('writes one edit per text node, so every node keeps its own marks', () => {
        expect(caseEditsIn(doc([[text('he'), text('ll'), text('o')]]), 1, 6, 'upper')).toEqual([
            { from: 5, to: 6, text: 'O' },
            { from: 3, to: 5, text: 'LL' },
            { from: 1, to: 3, text: 'HE' },
        ])
    })

    it('hands them back last first, so applying one does not move the next', () => {
        const edits = caseEditsIn(doc([[text('one'), text('two')]]), 1, 7, 'upper')

        expect(edits.map((edit) => edit.from)).toEqual([4, 1])
    })

    it('clips to the selection rather than taking the whole node', () => {
        // Only `ell` of `hello` is selected.
        expect(caseEditsIn(doc([[text('hello')]]), 2, 5, 'upper')).toEqual([
            { from: 2, to: 5, text: 'ELL' },
        ])
    })

    it('leaves out a node the mode does not change', () => {
        expect(caseEditsIn(doc([[text('AB'), text('cd')]]), 1, 5, 'upper')).toEqual([
            { from: 3, to: 5, text: 'CD' },
        ])
    })

    it('carries sentence case across the nodes of one paragraph', () => {
        // `he` `llo. and` is one word split over two nodes and then a second sentence. The
        // first node is raised because the selection opens a sentence; the second must not
        // raise its own first letter, and must raise the one after the full stop.
        expect(caseEditsIn(doc([[text('he'), text('llo. and')]]), 1, 11, 'sentence')).toEqual([
            { from: 3, to: 11, text: 'llo. And' },
            { from: 1, to: 3, text: 'He' },
        ])
    })

    it('starts a new sentence at a block boundary', () => {
        // Two paragraphs: the second begins a sentence even though the first did not end one.
        // `three` sits at 10, not 9 - a block closes at one position and the next opens at
        // another, which is the arithmetic this walk has to get right.
        expect(caseEditsIn(doc([[text('one two')], [text('three')]]), 1, 15, 'sentence')).toEqual([
            { from: 10, to: 15, text: 'Three' },
            { from: 1, to: 8, text: 'One two' },
        ])
    })

    it('has nothing to do for an empty selection', () => {
        expect(caseEditsIn(doc([[text('hello')]]), 3, 3, 'upper')).toEqual([])
    })
})

/*
 * The commands, run the way TipTap runs them: handed a transaction to write into, and a
 * `dispatch` that says whether this is a run or only a question.
 *
 * A command that dispatched a transaction of its own ran ahead of the chain it was part of.
 * `chain().focus().setTextCase()` then applied the chain's transaction on top of a document
 * it was not built from, which ProseMirror refuses with "Applying a mismatched
 * transaction" - and `can()`, which must not change anything, changed the text.
 */
describe('the commands', () => {
    const editable = (blocks) => ({
        ...doc(blocks),
        nodeAt: () => ({ marks: ['bold'] }),
        type: { schema: { text: (value, marks) => ({ text: value, marks }) } },
    })

    const transaction = (document, from, to) => {
        const tr = {
            doc: document,
            selection: { from, to, empty: from === to },
            written: [],
            replaceWith(start, end, node) {
                tr.written.push({ from: start, to: end, text: node.text, marks: node.marks })

                return tr
            },
            setSelection(selection) {
                tr.selection = selection

                return tr
            },
        }

        return tr
    }

    const run = (name, args, tr, dispatch) => {
        window.FilamentRichEditor = {
            tiptap: {
                core: { Extension: { create: (definition) => definition } },
                pmState: {
                    TextSelection: { create: (_doc, from, to) => ({ from, to, empty: from === to }) },
                },
            },
        }

        const editor = {
            storage: { arteTextCase: { lastMode: null, lastAt: null } },
            view: { dispatch: vi.fn() },
        }

        const commands = textCaseExtension().addCommands.call({ editor })

        return { result: commands[name](...args)({ tr, dispatch, editor }), editor }
    }

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('writes into the transaction it is handed and dispatches nothing of its own', () => {
        const tr = transaction(editable([[text('straße')]]), 1, 7)
        const { result, editor } = run('setTextCase', ['upper'], tr, () => undefined)

        expect(result).toBe(true)
        expect(tr.written).toEqual([{ from: 1, to: 7, text: 'STRASSE', marks: ['bold'] }])
        // `ß` became two letters, and the selection grew with it.
        expect(tr.selection).toEqual({ from: 1, to: 8, empty: false })
        expect(editor.view.dispatch).not.toHaveBeenCalled()
    })

    it('changes nothing when it is only asked', () => {
        const tr = transaction(editable([[text('hello')]]), 1, 6)
        const { result, editor } = run('setTextCase', ['upper'], tr, undefined)

        expect(result).toBe(true)
        expect(tr.written).toEqual([])
        expect(editor.storage.arteTextCase.lastMode).toBe(null)
        expect(editor.view.dispatch).not.toHaveBeenCalled()
    })

    it('refuses an empty selection either way', () => {
        expect(run('setTextCase', ['upper'], transaction(editable([[text('hello')]]), 3, 3), () => undefined).result).toBe(false)
        expect(run('cycleTextCase', [], transaction(editable([[text('hello')]]), 3, 3), undefined).result).toBe(false)
    })

    it('cycles through the same transaction and remembers the selection it grew to', () => {
        const tr = transaction(editable([[text('straße')]]), 1, 7)
        const { result, editor } = run('cycleTextCase', [], tr, () => undefined)

        expect(result).toBe(true)
        expect(tr.written.map((edit) => edit.text)).toEqual([applyCase('straße', nextCaseMode(null), true).text])
        expect(editor.storage.arteTextCase.lastAt).toBe(`${tr.selection.from}:${tr.selection.to}`)
        expect(editor.view.dispatch).not.toHaveBeenCalled()
    })

    it('leaves the cycle alone when it is only asked', () => {
        const tr = transaction(editable([[text('hello')]]), 1, 6)
        const { result, editor } = run('cycleTextCase', [], tr, undefined)

        expect(result).toBe(true)
        expect(tr.written).toEqual([])
        expect(editor.storage.arteTextCase).toEqual({ lastMode: null, lastAt: null })
    })
})
