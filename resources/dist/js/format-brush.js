/*
 * The brush that picks formatting up at one place and puts it down at another.
 *
 * Word calls it the format painter and TinyMCE the permanent pen. What both do, and what
 * this does, is copy how a passage looks without copying the passage.
 *
 * Three things about it were measured on a running editor rather than reasoned about, and
 * each one changed the design.
 *
 * What may be carried is decided by the schema of *this* field. Two editors on one page do
 * not have the same one - the styles plugin is switched on for one and off for the other -
 * so a written-out list of marks would promise one that is not there and drop one that is.
 *
 * What is picked up at a caret is a different question from what is picked up over a
 * selection. At a caret the answer is `storedMarks ?? $from.marks()`, which is what
 * ProseMirror itself consults to decide what typed text would get - so the brush agrees
 * with the toolbar's own active states at the same place. Over a selection it is what every
 * character has in common. `marksAcross` looks like the second answer and is the first: it
 * returns `['bold']` for a range running out of a bold word into plain text.
 *
 * And three marks are refused. `code` declares `excludes: "_"`, so applying it takes every
 * other mark back off - the brush would put down something other than what it picked up.
 * `link` is a destination rather than a look, and carries an `id` that has to be unique.
 * `language` says what tongue a passage is written in, which is what a screen reader
 * switches voice on. The first two are refused by what they declare, so a plugin's own
 * anchor-carrying mark is refused on the same grounds without an entry anywhere; the third
 * is named, because nothing structural gives it away.
 */

/**
 * Marks refused by name rather than by what they declare.
 *
 * One entry, and it needs the sentence beside it: `language` is content. It is not how a
 * passage looks, it is what it is, and brushing it would tell a screen reader to read
 * German in a French voice because the two paragraphs happened to be the same size.
 */
export const REFUSED_BY_NAME = ['language']

/**
 * The marks this field's brush may carry, in the schema's own order.
 *
 * @param {object} schema
 * @returns {Array<string>}
 */
export function carriedMarkNames(schema) {
    const marks = schema?.marks ?? {}

    return Object.keys(marks).filter((name) => {
        if (REFUSED_BY_NAME.includes(name)) {
            return false
        }

        const spec = marks[name]?.spec ?? {}

        // The mark that excludes everything. Measured in both orders: picked up alongside
        // others it arrives alone, and applied first it makes the others refuse.
        if (spec.excludes === '_') {
            return false
        }

        const attrs = spec.attrs ?? {}

        // An address is not a look, and a fragment has to be unique - two passages
        // answering to one `#name` leaves the second unreachable.
        return !('href' in attrs) && !('id' in attrs)
    })
}

/**
 * The formatting at the selection, or nothing where there is none to take.
 *
 * @param {object} state
 * @returns {Array<{name: string, attrs: object}> | null}
 */
export function pickFrom(state) {
    const selection = state?.selection

    if (! selection) {
        return null
    }

    // A selected picture is not a selection of text. An image declares no marks of its own,
    // so ProseMirror's default lets every mark onto it: the chain returns true, `can()`
    // returns true, and the document quietly gains a bold picture.
    if (selection.node) {
        return null
    }

    const carried = carriedMarkNames(state.schema)
    const keep = (marks) => (marks ?? []).filter((mark) => carried.includes(mark?.type?.name))

    if (selection.empty) {
        return serialise(keep(state.storedMarks ?? selection.$from.marks()))
    }

    let common = null

    for (const range of selection.ranges ?? []) {
        state.doc.nodesBetween(range.$from.pos, range.$to.pos, (node, position, parent) => {
            if (! node?.isText) {
                return true
            }

            // A code block declares `marks: ""`, so nothing in it carries formatting and
            // nothing put there would stay. Counted towards what the selection has in
            // common, every selection crossing one would pick up nothing at all - and the
            // same goes for a node that names the marks it holds.
            if (restrictsMarks(parent)) {
                return false
            }

            const marks = keep(node.marks)

            common = common === null
                ? marks
                : common.filter((mark) => marks.some((other) => mark.eq(other)))

            return false
        })
    }

    return serialise(common ?? [])
}

/**
 * What to take off and what to put on, for a set that was picked up.
 *
 * Replacing rather than adding, which is what Word does - and done mark by mark rather
 * than with `unsetAllMarks`, which was measured taking the hyperlink off the target as
 * well. Naming only the carried set spares a link, a language and a code run without any
 * of them being a special case.
 *
 * @param {Array<{name: string, attrs: object}>} picked
 * @param {Array<string>} carried
 * @returns {{unset: Array<string>, set: Array<{name: string, attrs: object}>}}
 */
export function applicationFor(picked, carried, present = null) {
    const set = picked ?? []
    const removable = present === null
        ? (carried ?? [])
        : (carried ?? []).filter((name) => present.includes(name))

    return {
        unset: removable.filter((name) => ! set.some((mark) => mark.name === name)),
        set,
    }
}

/**
 * The names of every carried mark anywhere in the selection.
 *
 * What makes the difference between taking one thing off a target and taking a dozen off
 * it: without this, every stroke issues an `unsetMark` for each mark the brush can carry,
 * and each one is a step in the transaction whether or not the target had anything to
 * remove. Told what is really there, the stroke names only what it means.
 *
 * @param {object} state
 * @returns {Array<string>}
 */
export function marksInSelection(state) {
    const carried = carriedMarkNames(state?.schema)
    const found = new Set()

    for (const range of state?.selection?.ranges ?? []) {
        state.doc.nodesBetween(range.$from.pos, range.$to.pos, (node, position, parent) => {
            if (! node?.isText) {
                return true
            }

            if (! restrictsMarks(parent)) {
                for (const mark of node.marks ?? []) {
                    if (carried.includes(mark?.type?.name)) {
                        found.add(mark.type.name)
                    }
                }
            }

            return false
        })
    }

    return [...found]
}

/**
 * The next state of the button, on a plain click.
 *
 * Three states rather than click-versus-double-click, which is the gesture every editor
 * copies from Word and the one part of this that could not be built honestly: telling the
 * two apart means reading `event.detail`, and the second click of a double-click is
 * indistinguishable from a deliberate second click meaning "keep it armed".
 *
 * @param {string | null} mode
 * @returns {string | null}
 */
export function nextMode(mode) {
    if (mode === 'once') {
        return 'sticky'
    }

    return mode === 'sticky' ? null : 'once'
}

/**
 * Whether a node says which marks it will hold, rather than taking any.
 *
 * `marks: ""` is the total ban a code block declares, and `marks: "_"` is the opposite -
 * everything, which is also the default when nothing is said at all. Anything else is a
 * list, and a list is the case this used to miss: the marks inside such a node are not what
 * the rest of the selection means by formatting, and carrying one out of it lands nowhere
 * on a target that does not allow it. Nothing in the shipped schema declares a list today;
 * the brush reads whatever schema the field has, including a plugin's own nodes.
 */
function restrictsMarks(parent) {
    const spec = parent?.type?.spec ?? {}

    return spec.code === true || (typeof spec.marks === 'string' && spec.marks !== '_')
}

function serialise(marks) {
    return marks.length === 0
        ? null
        : marks.map((mark) => ({ name: mark.type.name, attrs: { ...(mark.attrs ?? {}) } }))
}

/**
 * The extension itself: the state the button reads, the commands it calls, and the one rule
 * that decides when a stroke is painted.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap

    if (! tiptap?.core || ! tiptap?.pmState) {
        console.error(
            'The advanced rich editor format brush needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap.core
    const { Plugin, PluginKey } = tiptap.pmState

    const key = new PluginKey('arteFormatBrush')

    /**
     * A transaction carrying no steps, which is how the button is told to look again.
     *
     * Filament wraps every tool's active expression as `editorUpdatedAt && (...)`, and
     * `editorUpdatedAt` is bumped by `editor.on('transaction')` - measured firing
     * synchronously inside the dispatch, for a transaction with zero steps and
     * `docChanged` false. So arming, which changes nothing about the document, still
     * repaints the button. The same trick `find-replace.js` uses to redraw its decorations.
     */
    const announce = (editor) => {
        editor.view.dispatch(editor.state.tr.setMeta(key, true))
    }

    const paint = (editor) => {
        const { mode } = editor.storage.arteFormatBrush

        editor.view.dom.classList.toggle('fi-arte-brush-armed', mode !== null)

        announce(editor)
    }

    return Extension.create({
        name: 'arteFormatBrush',

        addStorage() {
            return {
                picked: null,
                mode: null,
                escape: null,
            }
        },

        onCreate() {
            // Escape, from wherever the focus went.
            //
            // The plugin below handles it inside the editor, which is where a caret usually
            // is - but the brush is armed from a button, and a toolbar menu, a dialog or a
            // tab press all leave the focus somewhere the editor's own handlers never see.
            // Nothing is swallowed here: the key still reaches whatever else wanted it, so
            // a modal above the field closes on the same press it disarms on.
            this.storage.escape = (event) => {
                if (event.key === 'Escape' && this.editor.storage.arteFormatBrush.mode !== null) {
                    this.editor.commands.clearFormatBrush()
                }
            }

            document.addEventListener('keydown', this.storage.escape)
        },

        onUpdate({ transaction }) {
            // A document replaced whole is a different document, and formatting taken from
            // the one before it is a set the author can no longer see the source of. The
            // marks themselves would still apply - they are names and attributes, not
            // positions - so nothing breaks; what would be wrong is a lit button and a copy
            // cursor pointing back at text that is gone.
            //
            // A replacement rather than an edit: one step covering the whole of what the
            // document was. That is what `setContent` writes, and what selecting everything
            // and typing over it writes, and both are the case this is about.
            const before = transaction.before?.content?.size

            const replaced = before !== undefined
                && transaction.steps.some((step) => step.from === 0 && step.to === before)

            if (replaced && this.editor.storage.arteFormatBrush.mode !== null) {
                this.editor.commands.clearFormatBrush()
            }
        },

        onDestroy() {
            document.removeEventListener('keydown', this.storage.escape)

            this.storage.escape = null
            this.storage.picked = null
            this.storage.mode = null
        },

        addCommands() {
            return {
                /**
                 * The button. Idle takes formatting up and arms for one stroke, armed once
                 * keeps it armed, armed for good puts it away.
                 */
                cycleFormatBrush:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFormatBrush
                        const mode = nextMode(storage.mode)

                        if (mode === 'once') {
                            const picked = pickFrom(editor.state)

                            // Refusing rather than arming with nothing is what makes the
                            // button's own state exact: `picked` is either null or a set
                            // with something in it, so the expression that lights the
                            // button is the truth rather than an approximation of it.
                            if (! picked) {
                                return false
                            }

                            storage.picked = picked
                        }

                        storage.mode = mode

                        if (mode === null) {
                            storage.picked = null
                        }

                        paint(editor)

                        return true
                    },

                clearFormatBrush:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFormatBrush

                        if (storage.mode === null) {
                            return false
                        }

                        storage.picked = null
                        storage.mode = null

                        paint(editor)

                        return true
                    },

                /**
                 * One stroke. Public, because a project may want to reach it from a
                 * shortcut of its own - this package binds none, since the two keys Word
                 * uses are the browser's inspector and its paste.
                 */
                applyFormatBrush:
                    () =>
                    ({ editor, chain }) => {
                        const storage = editor.storage.arteFormatBrush
                        const { selection } = editor.state

                        if (! storage.picked || selection.empty || selection.node) {
                            return false
                        }

                        const plan = applicationFor(
                            storage.picked,
                            carriedMarkNames(editor.schema),
                            marksInSelection(editor.state),
                        )
                        const stroke = chain().focus()

                        // Braced on purpose: the chain call returns the chain, and a
                        // `forEach` callback that returns a value is the shape that reads
                        // like a `map` somebody forgot to collect.
                        plan.unset.forEach((name) => {
                            stroke.unsetMark(name)
                        })
                        plan.set.forEach((mark) => {
                            stroke.setMark(mark.name, mark.attrs)
                        })

                        const painted = stroke.run()

                        if (painted && storage.mode === 'once') {
                            storage.picked = null
                            storage.mode = null

                            paint(editor)
                        }

                        return painted
                    },
            }
        },

        addProseMirrorPlugins() {
            const editor = this.editor

            /**
             * Read off the editor, never off `this.storage`.
             *
             * The extension context a plugin is built with carries a `storage` that is not
             * the object the commands write to and the button reads - measured in a running
             * panel with `mode: 'once'` on `editor.storage.arteFormatBrush` and `null` on
             * the context's own copy at the same moment. Reading the wrong one made every
             * handler here return early, so the brush armed, lit its button, showed its
             * cursor and then painted nothing at all: the only gesture that puts formatting
             * down did not work, and none of it was visible from the commands, which were
             * right all along.
             */
            const brush = () => editor.storage.arteFormatBrush

            return [
                new Plugin({
                    key,

                    props: {
                        handleDOMEvents: {
                            /**
                             * The stroke is painted when a selection is finished, not while
                             * it is being made: a drag reports a new selection on every
                             * pixel, and painting each one would colour the text as the
                             * pointer moved over it.
                             *
                             * Bound to the editor's own element rather than to the
                             * document, which is what keeps the button out of it - the
                             * second click, the one that means "keep it armed", lands on
                             * the toolbar and must not be read as a stroke.
                             */
                            mouseup: (view, event) => {
                                // The left button only. A right-click inside the editor
                                // releases a button too, and a brush that painted on it
                                // would edit the document of somebody who reached for the
                                // context menu - and in one-stroke mode disarm itself
                                // afterwards, so the stroke they never asked for is also
                                // the one they cannot repeat.
                                if (event.button !== 0 || brush().mode === null) {
                                    return false
                                }

                                schedule(editor)

                                return false
                            },

                            keyup: (view, event) => {
                                // Shift with one of the keys that moves a selection edge,
                                // and not Shift with anything at all: a capital letter, a
                                // shifted bracket and `Shift+Enter` all release a key with
                                // `shiftKey` set, and reading only that asked a question
                                // about the modifier rather than about the gesture.
                                if (brush().mode !== null && event.shiftKey && EXTENDS_SELECTION.includes(event.key)) {
                                    schedule(editor)
                                }

                                return false
                            },

                            keydown: (view, event) => {
                                if (event.key !== 'Escape' || brush().mode === null) {
                                    return false
                                }

                                // Handled, so the key stops here: a modal above the field
                                // would otherwise close on the same press.
                                editor.commands.clearFormatBrush()

                                return true
                            },
                        },
                    },
                }),
            ]
        },
    })
}

/**
 * Painted after the browser has settled the selection.
 *
 * `mouseup` fires before the selection ProseMirror will report is the one the user let go
 * of, so reading it in the handler paints the previous stroke. A task rather than an
 * animation frame, and the difference is not cosmetic: a frame is not scheduled at all
 * while the tab is in the background, so a stroke made just before the tab is hidden waits
 * there and then lands on whatever is selected when the user comes back. Measured - a
 * hidden pane never ran the callback. Both run after the current task, which is all the
 * settling this needs.
 */
function schedule(editor) {
    // One stroke per gesture. A shift-click releases a button and a key, and both handlers
    // ask for the same stroke - which in the armed-for-good mode painted twice, so one
    // gesture took two presses of undo to take back.
    if (pending.has(editor)) {
        return
    }

    pending.set(
        editor,
        window.setTimeout(() => {
            pending.delete(editor)

            if (! editor.isDestroyed) {
                editor.commands.applyFormatBrush()
            }
        }, 0),
    )
}

/**
 * The stroke each editor has waiting, if any.
 *
 * A map keyed on the editor rather than a field on its storage: what is waiting is this
 * module's business and nothing a button or a theme should be reading, and a weak key means
 * a field that goes away takes its entry with it.
 */
const pending = new WeakMap()

/**
 * The keys that move the edge of a selection, and so finish one.
 */
const EXTENDS_SELECTION = [
    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
    'Home', 'End', 'PageUp', 'PageDown',
]
