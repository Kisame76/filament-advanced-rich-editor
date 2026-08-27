/*
 * Finding and replacing inside one field.
 *
 * The document is a tree and a hit is a flat thing: `he<strong>ll</strong>o` is three text
 * nodes and one word, and a search that walked the tree would find neither `hello` nor the
 * `ll` in the middle of it. So the text nodes are laid end to end into one string, the
 * search happens there, and the offsets are mapped back onto document positions afterwards.
 * Both halves are plain functions over plain data, which is why they can be tested without
 * an editor - what is left is the decorations and the commands, and only an editor can
 * prove those.
 *
 * Between two blocks sits a break: a segment with no position, holding a character nobody
 * can type. That is what stops a search for `hello world` from matching the end of one
 * paragraph and the start of the next, and it does it without a special case in the search
 * itself - the text simply does not say that.
 */

/**
 * The character standing between two blocks in the flat text. A null character rather than
 * a newline, because a newline is something a query could contain and this must not be.
 */
export const BREAK = '\u0000'

/**
 * What counts as part of a word when whole words are asked for.
 *
 * `\b` is not usable here: it is defined over ASCII, so it calls the seam between `Müller`
 * and the letters around it a word boundary, and a whole word search for `Mü` would match
 * inside the name.
 */
const WORD = '[\\p{L}\\p{N}_]'

/**
 * Only the characters a regular expression gives a meaning to, and only the ones that are
 * escapable under the `u` flag - it rejects an escape it has no meaning for, so a wider
 * list would turn a query with a hyphen in it into a syntax error.
 */
function escape(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Every occurrence of a query in a string, as offsets into it.
 *
 * The query is text, not a pattern: someone searching for `a.b` means those three
 * characters. An empty query has nothing to find rather than everything.
 */
export function matchesIn(text, query, { caseSensitive = false, wholeWord = false } = {}) {
    if (!text || !query) {
        return []
    }

    const body = escape(query)

    const pattern = new RegExp(
        wholeWord ? `(?<!${WORD})${body}(?!${WORD})` : body,
        caseSensitive ? 'gu' : 'giu',
    )

    const matches = []

    let match

    while ((match = pattern.exec(text)) !== null) {
        matches.push({ start: match.index, end: match.index + match[0].length })
    }

    return matches
}

/**
 * The one string a search runs on. Segments arrive in document order, so joining them is
 * the whole job - the breaks are already among them.
 */
export function flatten(segments) {
    return segments.map((segment) => segment.text).join('')
}

/**
 * Where an offset into the flat text sits in the document, or null when it sits on a break.
 *
 * The end of a hit is exclusive, so it belongs to the segment it ends in rather than to the
 * one starting there - otherwise a hit ending at a block boundary would be measured from
 * the next block.
 */
function positionAt(segments, offset, isEnd) {
    let seen = 0

    for (const segment of segments) {
        const start = seen
        const end = seen + segment.text.length

        seen = end

        const isInside = isEnd ? offset > start && offset <= end : offset >= start && offset < end

        if (!isInside) {
            continue
        }

        return segment.from === null ? null : segment.from + (offset - start)
    }

    return null
}

/**
 * Hits as the editor can use them. A hit touching a break is dropped rather than clamped:
 * there is no position for it, and a range invented for it would select something else.
 */
export function segmentsToRanges(segments, matches) {
    const ranges = []

    for (const match of matches) {
        const from = positionAt(segments, match.start, false)
        const to = positionAt(segments, match.end, true)

        if (from === null || to === null) {
            continue
        }

        ranges.push({ from, to })
    }

    return ranges
}

/**
 * The next or previous hit, wrapping at either end - a search that stopped at the last hit
 * would send the reader back to the top by hand.
 */
export function stepIndex(count, current, direction) {
    if (count === 0) {
        return -1
    }

    return (((current + direction) % count) + count) % count
}

/**
 * The hit to start on when the bar opens: the first one at or after the caret, so the
 * search begins where the reader is rather than at the top of the document.
 */
export function indexAfter(ranges, position) {
    if (ranges.length === 0) {
        return -1
    }

    const index = ranges.findIndex((range) => range.from >= position)

    return index === -1 ? 0 : index
}

/**
 * The hits that still fit in a document of a given size.
 *
 * Between a replacement being applied and the search being run again, the hits belong to
 * the document as it was, and ProseMirror redraws the decorations in between against the
 * document as it now is. A position past the end of it is not a hit drawn in the wrong
 * place - it throws, and takes the editor with it.
 */
export function withinDoc(ranges, size) {
    return ranges.filter((range) => range.from >= 0 && range.to <= size)
}

/**
 * The order every hit is replaced in: back to front, so that replacing one does not move
 * the ones still to come. A copy, because the caller keeps its own list to draw from.
 */
export function descending(ranges) {
    return [...ranges].sort((a, b) => b.from - a.from)
}


/*
 * The bar, and the extension behind it.
 *
 * A small window hanging off the body, not a row inside the field. That is not a matter of
 * taste: Filament lays `.fi-fo-rich-editor-main` out as a two-column row from `2xl` up, so
 * a bar living in there is a column and takes half the editor on a wide screen. Off the
 * body it is 24rem wherever the field is, it opens in the top right corner of the field it
 * belongs to, and it can be dragged off whatever it is covering - the same window the emoji
 * picker is, one row tall instead of twenty.
 *
 * Its strings and icons come off the editor element as `data-arte-find`, because `Ctrl+F`
 * opens it as often as the button does and a handler nobody clicked would never have run.
 *
 * The caret is never moved. A hit is shown with a decoration and scrolled to through the
 * DOM, so the reader can type in the bar while the document keeps the place they were at -
 * and closing the bar therefore needs no restoring, because nothing was taken away.
 */
const SETTINGS = 'arteFind'

const WIDTH = 24 * 16

/**
 * The shared panel, fetched once per page rather than imported at the top of this file: a
 * static import would resolve without the version query Filament serves this module with,
 * and a package upgrade would then be served the old panel out of the cache. The URL is
 * derived from this module's own so the two stay together wherever the assets were
 * published.
 */
let panel = null

let panelPromise = null

function loadPanel() {
    if (!panelPromise) {
        const here = new URL(import.meta.url)
        const url = new URL('./floating-panel.js', here)

        url.search = here.search

        panelPromise = import(url.href)
            .then((module) => (panel = module))
            .catch((error) => {
                console.error('The advanced rich editor could not load its floating panel:', error)

                panelPromise = null

                return null
            })
    }

    return panelPromise
}

/**
 * Every text node of a document, with a break wherever a hit must not run through: between
 * two blocks, and either side of a leaf such as an image or a line break. Two runs of one
 * paragraph are pushed together with no seam, which is what lets a hit cross a `<strong>`.
 */
export function segmentsOf(doc) {
    const segments = []

    let previousParent = null
    let needsBreak = false

    doc.descendants((node, pos, parent) => {
        if (node.isText) {
            if (segments.length && (needsBreak || parent !== previousParent)) {
                segments.push({ from: null, text: BREAK })
            }

            previousParent = parent
            needsBreak = false

            segments.push({ from: pos, text: node.text })

            return false
        }

        if (node.isLeaf) {
            needsBreak = true
        }

        return true
    })

    return segments
}

function button(className, { label, icon = null, text = null }) {
    const element = document.createElement('button')

    element.type = 'button'
    element.className = className
    element.title = label
    element.setAttribute('aria-label', label)

    if (icon) {
        element.innerHTML = icon
    }

    if (text !== null) {
        element.textContent = text
    }

    return element
}

function field(className, label) {
    const element = document.createElement('input')

    element.type = 'text'
    element.className = className
    element.placeholder = label
    element.setAttribute('aria-label', label)
    // The panel is outside the form, but a stray Enter still must not submit anything, and
    // a browser's own suggestions over a search box this small would cover the document.
    element.autocomplete = 'off'
    element.spellcheck = false

    return element
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState
    const pmView = window.FilamentRichEditor?.tiptap?.pmView

    if (!tiptap || !pmState || !pmView) {
        console.error(
            'The advanced rich editor find extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState
    const { Decoration, DecorationSet } = pmView

    const key = new PluginKey('arteFind')

    return Extension.create({
        name: 'arteFind',

        addStorage() {
            return {
                labels: null,
                window: null,
                fields: null,
                isOpen: false,
                isReplacing: false,
                query: '',
                replacement: '',
                caseSensitive: false,
                wholeWord: false,
                ranges: [],
                current: -1,
                resumeAt: null,
            }
        },

        onCreate() {
            const element = this.editor.options.element

            try {
                this.storage.labels = JSON.parse(element.dataset[SETTINGS] ?? 'null')
            } catch (error) {
                console.error('The advanced rich editor could not read its find settings:', error)
            }

            // Warmed here rather than on the first keystroke: the panel is three kilobytes
            // and nobody should watch it arrive between pressing Ctrl+F and seeing a box.
            if (this.storage.labels) {
                loadPanel()
            }
        },

        onUpdate() {
            // The document changed under an open bar - by typing, by a replacement, by
            // another field's undo. The hits are stale either way.
            if (this.storage.isOpen) {
                this.editor.commands.refreshFind()
            }
        },

        onDestroy() {
            this.storage.window?.close()
        },

        addCommands() {
            /**
             * Runs the search again and redraws. The transaction carries no steps, so the
             * document is not touched and nothing downstream counts it as an edit - it is
             * only how a decoration is asked to be drawn again.
             */
            const redraw = (editor) => {
                editor.view.dispatch(editor.state.tr.setMeta(key, true))
            }

            const drawCount = (editor) => {
                const storage = editor.storage.arteFind

                if (!storage.fields) {
                    return
                }

                const { labels } = storage

                storage.fields.count.textContent = !storage.query
                    ? ''
                    : storage.ranges.length === 0
                      ? labels.noResults
                      : labels.count
                            .replace(':current', String(storage.current + 1))
                            .replace(':total', String(storage.ranges.length))
            }

            const run = (editor, { keepIndex = false } = {}) => {
                const storage = editor.storage.arteFind
                const segments = segmentsOf(editor.state.doc)

                storage.ranges = segmentsToRanges(
                    segments,
                    matchesIn(flatten(segments), storage.query, {
                        caseSensitive: storage.caseSensitive,
                        wholeWord: storage.wholeWord,
                    }),
                )

                if (storage.resumeAt !== null) {
                    // Carrying on from the end of what was just replaced, rather than from
                    // where the hit was: replacing `cat` with `cats` leaves a hit standing
                    // in the same place, and picking it again would replace it forever.
                    storage.current = indexAfter(storage.ranges, storage.resumeAt)
                    storage.resumeAt = null
                } else if (keepIndex) {
                    storage.current = Math.min(storage.current, storage.ranges.length - 1)
                } else {
                    storage.current = indexAfter(storage.ranges, editor.state.selection.from)
                }

                if (storage.current < 0 && storage.ranges.length) {
                    storage.current = 0
                }

                drawCount(editor)
                redraw(editor)
            }

            /**
             * The current hit, brought into view without moving the caret: the reader is
             * typing in the bar, and a selection change would take the cursor away from
             * what they are typing into.
             */
            const scrollToCurrent = (editor) => {
                requestAnimationFrame(() => {
                    editor.options.element
                        .querySelector('.fi-arte-find-hit-current')
                        ?.scrollIntoView({ block: 'center', behavior: 'smooth' })
                })
            }

            const step = (editor, direction) => {
                const storage = editor.storage.arteFind

                storage.current = stepIndex(storage.ranges.length, storage.current, direction)

                drawCount(editor)
                redraw(editor)
                scrollToCurrent(editor)

                return true
            }

            const place = (editor) => {
                const storage = editor.storage.arteFind

                if (!storage.window || storage.window.wasDragged) {
                    return
                }

                storage.window.moveTo(
                    panel.cornerOf(
                        editor.options.element.getBoundingClientRect(),
                        { width: WIDTH, height: storage.bar.offsetHeight },
                        { width: window.innerWidth, height: window.innerHeight },
                    ),
                )
            }

            const build = (editor) => {
                const storage = editor.storage.arteFind
                const { labels } = storage

                const bar = document.createElement('div')

                bar.className = 'fi-arte-find'
                bar.style.width = `${WIDTH}px`
                bar.setAttribute('role', 'dialog')
                bar.setAttribute('aria-label', labels.label)

                const search = document.createElement('div')

                search.className = 'fi-arte-find-row'

                // The one part of the bar with nothing to click, which is what makes it the
                // part that can be grabbed. A window with a text field in it cannot be
                // dragged by its whole surface - every click into the box would move it.
                const grip = document.createElement('span')

                grip.className = 'fi-arte-find-grip'
                grip.innerHTML = labels.icons.grip
                grip.setAttribute('aria-hidden', 'true')

                const query = field('fi-arte-find-input', labels.find)

                const count = document.createElement('span')

                count.className = 'fi-arte-find-count'

                const matchCase = button('fi-arte-find-toggle', { label: labels.matchCase, text: 'Aa' })
                const wholeWord = button('fi-arte-find-toggle fi-arte-find-whole-word', { label: labels.wholeWord, text: 'ab' })
                const previous = button('fi-arte-find-step', { label: labels.previous, icon: labels.icons.previous })
                const next = button('fi-arte-find-step', { label: labels.next, icon: labels.icons.next })
                const toggle = button('fi-arte-find-step', { label: labels.replace, icon: labels.icons.replace })
                const close = button('fi-arte-find-step', { label: labels.close, icon: labels.icons.close })

                search.append(grip, query, count, matchCase, wholeWord, previous, next, toggle, close)

                const replacing = document.createElement('div')

                replacing.className = 'fi-arte-find-row fi-arte-find-replacing'
                replacing.hidden = true

                const replacement = field('fi-arte-find-input', labels.replace)

                const replaceOne = button('fi-arte-find-action', { label: labels.replaceOne, text: labels.replaceOne })
                const replaceAll = button('fi-arte-find-action', { label: labels.replaceAll, text: labels.replaceAll })

                replacing.append(replacement, replaceOne, replaceAll)

                bar.append(search, replacing)

                storage.bar = bar
                storage.fields = { query, replacement, count, replacing, matchCase, wholeWord }

                query.value = storage.query
                replacement.value = storage.replacement

                query.addEventListener('input', () => {
                    storage.query = query.value
                    run(editor)
                    scrollToCurrent(editor)
                })

                replacement.addEventListener('input', () => {
                    storage.replacement = replacement.value
                })

                const option = (element, name) => {
                    element.classList.toggle('fi-arte-find-toggle-on', storage[name])
                    element.setAttribute('aria-pressed', String(storage[name]))

                    element.addEventListener('click', () => {
                        storage[name] = !storage[name]
                        element.classList.toggle('fi-arte-find-toggle-on', storage[name])
                        element.setAttribute('aria-pressed', String(storage[name]))
                        run(editor)
                    })
                }

                option(matchCase, 'caseSensitive')
                option(wholeWord, 'wholeWord')

                previous.addEventListener('click', () => step(editor, -1))
                next.addEventListener('click', () => step(editor, 1))
                close.addEventListener('click', () => editor.commands.closeFind())
                toggle.addEventListener('click', () => editor.commands.toggleFindReplace())
                replaceOne.addEventListener('click', () => editor.commands.replaceCurrentMatch())
                replaceAll.addEventListener('click', () => editor.commands.replaceAllMatches())

                // The bar has focus of its own, so the editor's own shortcuts cannot reach
                // it: everything it answers to is bound here as well.
                bar.addEventListener('keydown', (event) => {
                    const isMod = event.metaKey || event.ctrlKey

                    if (event.key === 'Enter') {
                        event.preventDefault()

                        if (event.target === replacement) {
                            editor.commands.replaceCurrentMatch()

                            return
                        }

                        step(editor, event.shiftKey ? -1 : 1)

                        return
                    }

                    // Pressed in the bar, so the selection in the document is stale and
                    // is not taken - the two keys still mean the two states they mean
                    // everywhere else.
                    if (isMod && event.key.toLowerCase() === 'f' && !event.altKey) {
                        event.preventDefault()
                        editor.commands.openFind(false)

                        return
                    }

                    if (isMod && (event.key.toLowerCase() === 'h' || (event.altKey && event.key.toLowerCase() === 'f'))) {
                        event.preventDefault()
                        editor.commands.openFindAndReplace(false)
                    }
                })

                storage.window = panel.floatingPanel(bar, {
                    handle: grip,
                    // Clicking into the text is how the next place to search from gets
                    // chosen, so it is the one click outside the bar that must not shut it.
                    keepOpenInside: [editor.options.element],
                    onResize: () => place(editor),
                    onClose: () => {
                        storage.isOpen = false
                        storage.isReplacing = false
                        storage.window = null
                        storage.bar = null
                        storage.fields = null
                        storage.ranges = []
                        storage.current = -1

                        redraw(editor)
                        editor.commands.focus()
                    },
                })

                return storage.window
            }

            /**
             * Opens the window, or brings it back to the state its key stands for. Doing the
             * same thing whether or not it is already open is the point: the second press of
             * a key is nearly always the first one repeated - something else got selected in
             * the meantime - and answering it with something different is how a shortcut
             * stops being predictable.
             *
             * `fromSelection` is false when the key was pressed inside the bar itself. The
             * selection in the document has not moved then, and taking it would throw away
             * whatever is being typed in the box.
             */
            const open = (editor, { replacing = false, fromSelection = true } = {}) => {
                const storage = editor.storage.arteFind

                if (!storage.labels) {
                    return false
                }

                // The panel arrives a microtask late at worst, and true is returned either
                // way: the key has been dealt with, and letting the browser open its own
                // find bar underneath instead would be the one unrecoverable answer.
                if (!panel) {
                    loadPanel().then((module) => module && open(editor, { replacing, fromSelection }))

                    return true
                }

                if (!storage.isOpen) {
                    build(editor)
                }

                if (fromSelection) {
                    const { selection } = editor.state

                    // What is selected is almost always what is being looked for, and where
                    // nothing is selected the last query is the better guess than nothing.
                    const selected = editor.state.doc.textBetween(selection.from, selection.to, BREAK)

                    if (selected && !selected.includes(BREAK)) {
                        storage.query = selected
                        storage.fields.query.value = selected
                    }
                }

                storage.isOpen = true

                // Set either way rather than only turned on: the two keys are the two states
                // this window has, so the one for finding puts the replacing row away again.
                storage.isReplacing = replacing
                storage.fields.replacing.hidden = !replacing

                run(editor)
                place(editor)
                scrollToCurrent(editor)

                // The query, in both states. Word, Docs and VS Code all put the cursor
                // there on their replace key too: what is being replaced has to be found
                // first, and with something selected the box already holds the answer.
                storage.fields.query.focus()
                storage.fields.query.select()

                return true
            }

            return {
                openFind:
                    (fromSelection = true) =>
                    ({ editor }) =>
                        open(editor, { replacing: false, fromSelection }),

                openFindAndReplace:
                    (fromSelection = true) =>
                    ({ editor }) =>
                        open(editor, { replacing: true, fromSelection }),

                /**
                 * The button in the bar, which is the one place the replacing row is a thing
                 * to be shown and hidden rather than the state a key stands for.
                 */
                toggleFindReplace:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFind

                        if (!storage.isOpen) {
                            return open(editor, { replacing: true })
                        }

                        storage.isReplacing = !storage.isReplacing
                        storage.fields.replacing.hidden = !storage.isReplacing

                        place(editor)

                        if (storage.isReplacing) {
                            storage.fields.replacement.focus()
                        }

                        return true
                    },

                closeFind:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFind

                        if (!storage.isOpen) {
                            return false
                        }

                        // Everything that has to be undone is undone in the panel's own
                        // close handler, so that Escape and a click outside end in exactly
                        // the same place as this button.
                        storage.window.close()

                        return true
                    },

                findNext:
                    () =>
                    ({ editor }) =>
                        step(editor, 1),

                findPrevious:
                    () =>
                    ({ editor }) =>
                        step(editor, -1),

                /**
                 * Runs the search again on the document as it now is, keeping the place in
                 * the list of hits.
                 */
                refreshFind:
                    () =>
                    ({ editor }) => {
                        run(editor, { keepIndex: true })

                        return true
                    },

                replaceCurrentMatch:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFind
                        const range = storage.ranges[storage.current]

                        if (!range) {
                            return false
                        }

                        const tr = editor.state.tr

                        storage.replacement === ''
                            ? tr.delete(range.from, range.to)
                            : tr.insertText(storage.replacement, range.from, range.to)

                        storage.resumeAt = range.from + storage.replacement.length

                        editor.view.dispatch(tr)

                        return true
                    },

                /**
                 * Every hit at once, back to front so that replacing one does not move the
                 * ones still to come - and in a single transaction, so a single undo takes
                 * all of them back rather than one per hit.
                 */
                replaceAllMatches:
                    () =>
                    ({ editor }) => {
                        const storage = editor.storage.arteFind

                        if (storage.ranges.length === 0) {
                            return false
                        }

                        const tr = editor.state.tr

                        for (const range of descending(storage.ranges)) {
                            storage.replacement === ''
                                ? tr.delete(range.from, range.to)
                                : tr.insertText(storage.replacement, range.from, range.to)
                        }

                        editor.view.dispatch(tr)

                        return true
                    },
            }
        },

        addKeyboardShortcuts() {
            // Two keys for one window, and each of them stands for one of its two
            // states rather than for a change to whichever it is in. Pressed a second time
            // they repeat themselves - which is the point, because the usual reason for
            // pressing Ctrl+F again is that something else is selected now.
            return {
                'Mod-f': () => this.editor.commands.openFind(),
                // Ctrl+H is what Word, Google Docs and VS Code use for replacing, and it is
                // free in a browser. On a Mac it never arrives - Cmd+H hides the
                // application before the page sees it - which is why Cmd+Alt+F is bound
                // alongside it, the same pair VS Code settled on for the same reason.
                'Mod-h': () => this.editor.commands.openFindAndReplace(),
                'Mod-Alt-f': () => this.editor.commands.openFindAndReplace(),
            }
        },

        addProseMirrorPlugins() {
            const extension = this

            return [
                new Plugin({
                    key,
                    props: {
                        decorations(state) {
                            const storage = extension.editor.storage.arteFind

                            if (!storage.isOpen || storage.ranges.length === 0) {
                                return DecorationSet.empty
                            }

                            return DecorationSet.create(
                                state.doc,
                                withinDoc(storage.ranges, state.doc.content.size).map((range, index) =>
                                    Decoration.inline(range.from, range.to, {
                                        class:
                                            index === storage.current
                                                ? 'fi-arte-find-hit fi-arte-find-hit-current'
                                                : 'fi-arte-find-hit',
                                    }),
                                ),
                            )
                        },
                    },
                }),
            ]
        },
    })
}
