/**
 * Changing the case of the selection: UPPER, lower, and Sentence case.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Nothing here is stored: a case change is ordinary text by the time the document is saved,
 * so there is no node, no mark, no PHP extension and nothing for the sanitiser to allow -
 * the same reason `find-replace.js` has no PHP half either.
 *
 * The transform is deliberately a function over a string plus one carried flag. A selection
 * is several text nodes, each of which has to be written back on its own so its marks
 * survive, and sentence case cannot be decided a node at a time: whether the `w` of `world`
 * begins a sentence depends on what came before it, possibly in a different node. Flattening
 * the selection and mapping offsets back - the way the search does - would not survive this
 * particular job, because uppercasing `ß` yields `SS` and the transformed string is then
 * longer than the one the offsets were taken from.
 */

/** In the order `Shift+F3` walks them. */
export const CASE_MODES = ['sentence', 'lower', 'upper']

const TERMINATORS = new Set(['.', '!', '?', '…', '。', '！', '？'])

// Characters a sentence may open with without having started yet. Whitespace, and the marks
// that come before the first word rather than counting as it. A digit is deliberately not
// here: "3 things happened" has begun, it just did not begin with a letter.
const BEFORE_THE_FIRST_WORD = /[\s"'«»„“‘([{]/u

const LETTER = /\p{L}/u

/**
 * The next mode in the cycle. Anything unrecognised - including nothing at all, which is
 * what the first press hands in - starts at the beginning.
 */
export function nextCaseMode(current) {
    const index = CASE_MODES.indexOf(current)

    return index === -1 ? CASE_MODES[0] : CASE_MODES[(index + 1) % CASE_MODES.length]
}

/**
 * One text node's worth of the transform.
 *
 * `atSentenceStart` comes in from the node before and goes back out for the node after, so
 * a caller walking a selection threads it through and nothing else needs to know that the
 * selection was split at all.
 *
 * @returns {{text: string, atSentenceStart: boolean}}
 */
export function applyCase(text, mode, atSentenceStart = true) {
    if (mode === 'upper') {
        return { text: text.toUpperCase(), atSentenceStart }
    }

    if (mode === 'lower') {
        return { text: text.toLowerCase(), atSentenceStart }
    }

    let start = atSentenceStart
    let out = ''

    for (let index = 0; index < text.length; index++) {
        const character = text[index]

        if (LETTER.test(character)) {
            const lowered = character.toLowerCase()

            out += start ? lowered.toUpperCase() : lowered
            start = false

            continue
        }

        out += character.toLowerCase()

        if (TERMINATORS.has(character)) {
            // Only when something follows it that a sentence could end before. Without this,
            // `readme.md` and `1.5` each begin a sentence in the middle of themselves.
            const next = text[index + 1]

            start = next === undefined || /\s/u.test(next)

            continue
        }

        if (!BEFORE_THE_FIRST_WORD.test(character)) {
            start = false
        }
    }

    return { text: out, atSentenceStart: start }
}

/**
 * What has to be written where, for a selection and a mode.
 *
 * One edit per text node rather than one over the whole range: replacing the range in a
 * single step would put plain text where marked text was, so a bold word inside the
 * selection would come back unbold, and a selection spanning two paragraphs would come back
 * as one. Each node is written on its own, with its own marks, and nothing structural moves.
 *
 * Handed back last first. Uppercasing `ß` yields two characters where one stood, so an edit
 * moves everything after it; going backwards means the positions still to be used were
 * measured after the ones already applied, not before.
 *
 * @returns {Array<{from: number, to: number, text: string}>}
 */
export function caseEditsIn(doc, from, to, mode) {
    if (from >= to) {
        return []
    }

    const edits = []

    // A selection opens a sentence: whatever stands before it is not part of what is being
    // changed, so there is nothing to continue.
    let atSentenceStart = true

    doc.nodesBetween(from, to, (node, position) => {
        if (!node.isText) {
            if (node.isBlock) {
                atSentenceStart = true
            }

            return
        }

        const start = Math.max(from, position)
        const end = Math.min(to, position + node.text.length)

        if (start >= end) {
            return
        }

        const slice = node.text.slice(start - position, end - position)
        const result = applyCase(slice, mode, atSentenceStart)

        atSentenceStart = result.atSentenceStart

        // A node the mode leaves alone is left alone, so that a document already in the
        // right case is not rewritten into an undo step that changed nothing.
        if (result.text !== slice) {
            edits.push({ from: start, to: end, text: result.text })
        }
    })

    return edits.reverse()
}

/*
 * The extension.
 *
 * Everything above is a function over plain data and is tested as such. What follows is the
 * part only an editor can prove: reading the selection, writing one transaction, and putting
 * the selection back where it was.
 */

export default () => {
    const { Extension } = window.FilamentRichEditor.tiptap.core
    const { TextSelection } = window.FilamentRichEditor.tiptap.pmState

    return Extension.create({
        name: 'arteTextCase',

        addStorage() {
            return {
                // What the last press applied, and to what. The cycle starts over on a
                // different selection: pressing the key on a new word means "change this
                // one", not "carry on from whatever the last word ended up as".
                lastMode: null,
                lastAt: null,
            }
        },

        addCommands() {
            const applyTo = (editor, mode) => {
                const { state } = editor
                const { from, to } = state.selection

                const edits = caseEditsIn(state.doc, from, to, mode)

                if (edits.length === 0) {
                    // Still a success: the selection is already in that case, and reporting
                    // failure would let the key fall through to the browser.
                    return true
                }

                const tr = state.tr

                for (const edit of edits) {
                    // The marks are taken from the node rather than left to the transaction
                    // to infer. `insertText()` reads them off the position, and a position
                    // that sits exactly between two differently marked nodes is the one case
                    // where that answers with the wrong node's marks.
                    const node = state.doc.nodeAt(edit.from)

                    tr.replaceWith(
                        edit.from,
                        edit.to,
                        state.schema.text(edit.text, node ? node.marks : null),
                    )
                }

                // One transaction, so one step in the undo chain. Without the selection being
                // put back, it collapses to the caret - and the second press of the cycle
                // would then have nothing to work on.
                const grew = edits.reduce(
                    (total, edit) => total + edit.text.length - (edit.to - edit.from),
                    0,
                )

                tr.setSelection(TextSelection.create(tr.doc, from, to + grew))

                editor.view.dispatch(tr)

                return true
            }

            return {
                setTextCase:
                    (mode) =>
                    ({ editor }) => {
                        if (editor.state.selection.empty) {
                            return false
                        }

                        editor.storage.arteTextCase.lastMode = mode
                        editor.storage.arteTextCase.lastAt = null

                        return applyTo(editor, mode)
                    },

                cycleTextCase:
                    () =>
                    ({ editor }) => {
                        const { from, to, empty } = editor.state.selection

                        if (empty) {
                            return false
                        }

                        const storage = editor.storage.arteTextCase
                        const at = `${from}:${to}`
                        const mode = nextCaseMode(storage.lastAt === at ? storage.lastMode : null)

                        storage.lastMode = mode
                        storage.lastAt = at

                        const applied = applyTo(editor, mode)

                        // The selection may have grown, so the next press has to recognise
                        // the same selection under its new measurements.
                        const next = editor.state.selection

                        storage.lastAt = `${next.from}:${next.to}`

                        return applied
                    },
            }
        },

        addKeyboardShortcuts() {
            // What Word binds it to, and free in a browser.
            return {
                'Shift-F3': () => this.editor.commands.cycleTextCase(),
            }
        },
    })
}
