/**
 * The definition list: `<dl>`, its terms (`<dt>`) and their descriptions (`<dd>`).
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning. The two parts come in with the list through
 * `addExtensions()`, so one file is the whole feature.
 *
 * Both parts hold text, not paragraphs: a glossary is a word and a sentence about it, and a
 * part that held blocks would need a second Enter for every entry. A description that runs
 * to two paragraphs uses a line break.
 *
 * Typing it is the glossary's own rhythm: Enter after a term starts its description, Enter
 * after a description starts the next term, and Enter in an empty part leaves the list. Tab
 * turns a term into a description and Shift+Tab turns it back - and out of the list, from a
 * term. Neither key ever leaves the editor from inside a list.
 */

const TERM = 'descriptionTerm'
const DETAILS = 'descriptionDetails'

/**
 * The paragraphs of a run, read the way glossaries are written: term, meaning, term,
 * meaning. A run of an odd length ends on a term, which is a list somebody is in the middle
 * of writing rather than a mistake.
 */
export function alternated(blocks, term, details) {
    return blocks.map((block, index) => (index % 2 === 0 ? term : details).create(null, block.content))
}

/**
 * The inline content of whatever a part was parsed from, flattened.
 *
 * `<dd><p>One</p><p>Two</p></dd>` is what CommonMark writes for a loose list and what most
 * sites paste. A part holds text, and ProseMirror's own way of reading past a paragraph -
 * a `skip` rule - closes the part at the second one, which turned `Two` into a term. So the
 * part reads its own children and keeps their text: a paragraph after something is a line
 * of its own, and anything with blocks inside, a nested list say, gives up its words.
 */
export function inlineContentOf(fragment, hardBreak) {
    const inline = []

    const add = (node) => {
        if (node.isInline) {
            inline.push(node)

            return
        }

        if (node.isTextblock && inline.length > 0 && hardBreak) {
            inline.push(hardBreak.create())
        }

        node.forEach(add)
    }

    fragment.forEach(add)

    return inline
}

/**
 * Every part of a list as a paragraph of its own, text and marks kept.
 */
export function unwrapped(list, paragraph) {
    const paragraphs = []

    list.forEach((part) => {
        paragraphs.push(paragraph.create(null, part.content))
    })

    return paragraphs
}

/**
 * The list with one part taken out of it as a paragraph, and where the caret goes.
 *
 * What is left before and after the part stays a list each, and an end with nothing left is
 * dropped rather than kept as an empty list. `caret` is counted from where the list started,
 * and lands at the start of the paragraph's text.
 *
 * @returns {{nodes: Array, caret: number}}
 */
export function liftedOut(list, index, paragraph) {
    const parts = []

    list.forEach((part) => {
        parts.push(part)
    })

    const before = parts.slice(0, index)
    const after = parts.slice(index + 1)
    const head = before.length > 0 ? [list.type.create(list.attrs, before)] : []

    return {
        nodes: [
            ...head,
            paragraph.create(null, parts[index].content),
            ...(after.length > 0 ? [list.type.create(list.attrs, after)] : []),
        ],
        caret: (head[0]?.nodeSize ?? 0) + 1,
    }
}

/**
 * The parts of a list that are left over after a block went into one of them, by where each
 * starts in the document, last first.
 *
 * A rule or a table typed into a term cannot stand inside the list, so ProseMirror splits
 * the list round it - and since a list may not be empty, it fills what was cut off with
 * empty terms: `<dl><dt></dt></dl>` after the rule, and an empty term in front of the
 * description that followed. Invisible on the page, and stored all the same. An empty part
 * the caret is in is one somebody is about to type into, and stays; a caret standing between
 * the tags, where TipTap's rule command leaves one, is in no part.
 *
 * Only asked of a list that was cut off another one: see `splitOff()`.
 */
export function strayParts(list, pos, { from, to }) {
    const stray = []

    list.forEach((part, offset) => {
        const start = pos + 1 + offset + 1
        const end = start + part.content.size

        if (part.content.size === 0 && !(from <= end && to >= start)) {
            stray.unshift(pos + 1 + offset)
        }
    })

    return stray
}

/**
 * Whether a list was cut off another one, from where its start was before the edit.
 *
 * A split leaves the start of what was cut off inside the list it came from. A list that
 * arrives whole - the field's content set when the server sends it, a paste, an undo, a
 * drag to a new place - starts somewhere outside any list, and its empty parts are the
 * ones somebody left there; they are never tidied away.
 */
export function splitOff($before, type) {
    for (let depth = $before.depth; depth > 0; depth--) {
        if ($before.node(depth).type === type) {
            return true
        }
    }

    return false
}

/**
 * Where the transactions changed the document, in the positions of the last of them.
 *
 * @returns {Array<[number, number]>}
 */
function changedRanges(transactions) {
    const ranges = []

    transactions.forEach((tr, index) => {
        tr.mapping.maps.forEach((stepMap, step) => {
            const rest = tr.mapping.slice(step + 1)

            stepMap.forEach((_oldStart, _oldEnd, start, end) => {
                let from = rest.map(start, -1)
                let to = rest.map(end, 1)

                for (const later of transactions.slice(index + 1)) {
                    from = later.mapping.map(from, -1)
                    to = later.mapping.map(to, 1)
                }

                ranges.push([from, to])
            })
        })
    })

    return ranges
}

export default () => {
    const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core
    const { Plugin, PluginKey, Selection, TextSelection } = window.FilamentRichEditor.tiptap.pmState
    const { DOMParser, Fragment } = window.FilamentRichEditor.tiptap.pmModel

    // `defining`, so that pasting over the whole of a part keeps it a part rather than
    // turning it into whatever was copied.
    const part = (name, tag) =>
        Node.create({
            name,
            content: 'inline*',
            defining: true,

            parseHTML() {
                return [
                    {
                        tag,
                        // Read for its text, whatever it holds: see `inlineContentOf()`.
                        getContent: (dom, schema) =>
                            Fragment.fromArray(
                                inlineContentOf(
                                    DOMParser.fromSchema(schema).parseSlice(dom).content,
                                    schema.nodes.hardBreak,
                                ),
                            ),
                    },
                ]
            },

            renderHTML({ HTMLAttributes }) {
                return [tag, mergeAttributes(HTMLAttributes), 0]
            },
        })

    const isPart = (node) => node.type.name === TERM || node.type.name === DETAILS

    return Node.create({
        name: 'descriptionList',

        // Not in the `list` group. TipTap's `toggleList` treats anything in it as a list it
        // may turn into another, and a `<ul>` holding terms is not a document.
        group: 'block',

        // Any order rather than HTML's strict groups of terms followed by descriptions: a
        // list that must always end on a description cannot be typed one keystroke at a time.
        content: `(${TERM} | ${DETAILS})+`,

        addExtensions() {
            return [part(TERM, 'dt'), part(DETAILS, 'dd')]
        },

        parseHTML() {
            return [{ tag: 'dl' }]
        },

        renderHTML({ HTMLAttributes }) {
            // The class is baked into the markup, as the task list's is: a stored document is
            // also drawn outside a form, by a front end that has never heard of this package.
            return ['dl', mergeAttributes(HTMLAttributes, { class: 'fi-arte-description-list' }), 0]
        },

        addCommands() {
            return {
                /**
                 * On: the paragraphs the selection covers become terms and descriptions in
                 * turn. Off: the list the caret is in becomes paragraphs again. Every position
                 * inside moves by the one token the list opens with, so the selection is kept
                 * where it was rather than mapped to the edge of what was replaced.
                 */
                toggleDescriptionList:
                    () =>
                    ({ state, tr, dispatch }) => {
                        const { $from, $to, from, to } = state.selection
                        const paragraph = state.schema.nodes.paragraph

                        for (let depth = $from.depth; depth > 0; depth--) {
                            const list = $from.node(depth)

                            if (list.type !== this.type) {
                                continue
                            }

                            if (dispatch) {
                                const start = $from.before(depth)
                                const end = start + list.nodeSize
                                const moved = (pos) => (pos < end ? pos - 1 : pos - 2)

                                tr.replaceWith(start, end, unwrapped(list, paragraph))
                                tr.setSelection(TextSelection.create(tr.doc, moved(from), moved(to)))
                            }

                            return true
                        }

                        const range = $from.blockRange($to)

                        if (!range) {
                            return false
                        }

                        const blocks = []

                        for (let index = range.startIndex; index < range.endIndex; index++) {
                            blocks.push(range.parent.child(index))
                        }

                        if (
                            !blocks.every((block) => block.isTextblock && !block.type.spec.code) ||
                            !range.parent.canReplaceWith(range.startIndex, range.endIndex, this.type)
                        ) {
                            return false
                        }

                        if (dispatch) {
                            const { schema } = state

                            tr.replaceWith(
                                range.start,
                                range.end,
                                this.type.create(null, alternated(blocks, schema.nodes[TERM], schema.nodes[DETAILS])),
                            )
                            tr.setSelection(TextSelection.create(tr.doc, from + 1, to + 1))
                        }

                        return true
                    },

                /**
                 * The part the caret is in, split at the caret, with what comes after it
                 * as the other kind: after a term, its description; after a description,
                 * the next term. Split straight into that kind rather than split and then
                 * changed - at the end of a description ProseMirror already starts a term,
                 * and TipTap's `setNode()` answers a change to the type a node already has
                 * by clearing the block instead.
                 */
                splitDescriptionPart:
                    () =>
                    ({ state, tr, dispatch }) => {
                        const { $from, empty } = state.selection

                        if (!empty || !isPart($from.parent) || $from.node(-1).type !== this.type) {
                            return false
                        }

                        if (dispatch) {
                            const other = state.schema.nodes[$from.parent.type.name === TERM ? DETAILS : TERM]

                            tr.split($from.pos, 1, [{ type: other }]).scrollIntoView()
                        }

                        return true
                    },

                /**
                 * Every part the selection touches made a term or a description. False when
                 * none of them changed, which is how Shift+Tab knows a term has nowhere left
                 * to go inside the list.
                 */
                setDescriptionPart:
                    (name) =>
                    ({ state, tr, dispatch }) => {
                        const type = state.schema.nodes[name]

                        if (!type || !isPart({ type })) {
                            return false
                        }

                        const changing = []

                        state.doc.nodesBetween(state.selection.from, state.selection.to, (node, pos) => {
                            if (!isPart(node)) {
                                return true
                            }

                            if (node.type !== type) {
                                changing.push(pos)
                            }

                            return false
                        })

                        if (changing.length === 0) {
                            return false
                        }

                        if (dispatch) {
                            for (const pos of changing) {
                                tr.setNodeMarkup(pos, type)
                            }
                        }

                        return true
                    },

                /**
                 * The part the caret is in, taken out of its list as a paragraph.
                 */
                liftDescriptionPart:
                    () =>
                    ({ state, tr, dispatch }) => {
                        const { $from } = state.selection

                        if ($from.depth < 2 || !isPart($from.parent) || $from.node(-1).type !== this.type) {
                            return false
                        }

                        if (dispatch) {
                            const start = $from.before(-1)
                            const list = $from.node(-1)
                            const { nodes, caret } = liftedOut(list, $from.index(-1), state.schema.nodes.paragraph)

                            tr.replaceWith(start, start + list.nodeSize, nodes)
                            tr.setSelection(TextSelection.create(tr.doc, start + caret + $from.parentOffset))
                        }

                        return true
                    },
            }
        },

        addProseMirrorPlugins() {
            const type = this.type

            return [
                new Plugin({
                    key: new PluginKey('arteDescriptionListRemainders'),

                    // Only where an edit happened, so nothing is walked on a keystroke far from
                    // any list - and never on a click, so an empty list somebody left behind on
                    // purpose is not taken away for moving the caret.
                    appendTransaction(transactions, oldState, state) {
                        // Where the list starting here began before these edits.
                        const before = (pos) => {
                            let mapped = pos

                            for (const tr of [...transactions].reverse()) {
                                mapped = tr.mapping.invert().map(mapped, 1)
                            }

                            return oldState.doc.resolve(Math.min(mapped, oldState.doc.content.size))
                        }

                        const stray = new Map()

                        for (const [from, to] of changedRanges(transactions)) {
                            state.doc.nodesBetween(
                                Math.max(0, from - 1),
                                Math.min(state.doc.content.size, to + 1),
                                (node, pos) => {
                                    if (node.type !== type) {
                                        return !node.isTextblock
                                    }

                                    if (!stray.has(pos) && splitOff(before(pos), type)) {
                                        stray.set(pos, { node, parts: strayParts(node, pos, state.selection) })
                                    }

                                    return false
                                },
                            )
                        }

                        const tr = state.tr

                        for (const [pos, { node, parts }] of [...stray].sort(([a], [b]) => b - a)) {
                            try {
                                if (parts.length === node.childCount) {
                                    tr.delete(pos, pos + node.nodeSize)

                                    continue
                                }

                                for (const part of parts) {
                                    tr.delete(part, part + tr.doc.nodeAt(part).nodeSize)
                                }
                            } catch {
                                // A container that may not be left empty keeps it.
                            }
                        }

                        if (!tr.docChanged) {
                            return null
                        }

                        // A caret left between tags by the command that made the remainder is
                        // put where it can stand.
                        if (!tr.selection.$from.parent.inlineContent) {
                            tr.setSelection(Selection.near(tr.selection.$from))
                        }

                        return tr
                    },
                }),
            ]
        },

        addKeyboardShortcuts() {
            const partAtCaret = () => {
                const { $from, empty } = this.editor.state.selection

                return empty && isPart($from.parent) ? $from : null
            }

            return {
                Enter: () => {
                    const $from = partAtCaret()

                    if (!$from) {
                        return false
                    }

                    if ($from.parent.content.size === 0) {
                        return this.editor.commands.liftDescriptionPart()
                    }

                    return this.editor.commands.splitDescriptionPart()
                },

                // At the very start of the list the part has nothing above it to join, so it
                // leaves the list instead - as a list item does.
                Backspace: () => {
                    const $from = partAtCaret()

                    if ($from?.parentOffset !== 0 || $from.index(-1) !== 0) {
                        return false
                    }

                    return this.editor.commands.liftDescriptionPart()
                },

                // Inside a list Tab never leaves the editor. A handler that declines hands the
                // key to the browser, which moves the focus on - to the grip's plus button, the
                // next field - and a description, which has nowhere further in to go, did that
                // on every press. So both keys are always taken: Tab makes terms descriptions
                // and otherwise stays put, Shift+Tab makes descriptions terms, and a term it
                // cannot go further out than leaves the list as a paragraph, as a list item
                // does.
                Tab: () => {
                    if (!isPart(this.editor.state.selection.$from.parent)) {
                        return false
                    }

                    this.editor.commands.setDescriptionPart(DETAILS)

                    return true
                },

                'Shift-Tab': () => {
                    const { $from, empty } = this.editor.state.selection

                    if (!isPart($from.parent)) {
                        return false
                    }

                    if (!this.editor.commands.setDescriptionPart(TERM) && empty) {
                        this.editor.commands.liftDescriptionPart()
                    }

                    return true
                },
            }
        },
    })
}
