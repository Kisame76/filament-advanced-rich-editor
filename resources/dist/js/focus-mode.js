/*
 * Writing with the rest of the document out of the way: every block but the one being written
 * in is dimmed, and the line being written stays in the middle of the view - the focus and the
 * typewriter mode of the writing apps, as one switch.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Nothing here is stored: a class on the editor, a decoration on the block in focus and a scroll
 * position. The saved HTML, the clipboard and the undo history are the same with it on as off,
 * which is why there is no PHP half - as for the invisible characters.
 */

/**
 * Where the focus is: every textblock the selection touches - one paragraph for a caret, each
 * of them for a selection across several - or the block a node selection holds, a picture or an
 * embed. What is dimmed is everything else, so a selection never hides its own text.
 *
 * @returns {Array<{from: number, to: number}>}
 */
export function focusedBlocks(state) {
    const { selection, doc } = state

    if (selection.node && !selection.node.isInline) {
        return [{ from: selection.from, to: selection.to }]
    }

    const found = []

    doc.nodesBetween(selection.from, selection.to, (node, pos) => {
        if (node.isTextblock) {
            found.push({ from: pos, to: pos + node.nodeSize })

            return false
        }

        return true
    })

    return found
}

/**
 * The element the editor scrolls in: the nearest one that scrolls its own overflow, starting
 * with the editor - which is the one that does in fullscreen, or with a capped height - or null
 * for the page.
 */
export function scrollerOf(element) {
    for (let node = element; node && node !== document.body; node = node.parentElement) {
        const { overflowY } = getComputedStyle(node)

        if ((overflowY === 'auto' || overflowY === 'scroll') && node.scrollHeight > node.clientHeight) {
            return node
        }
    }

    return null
}

/**
 * How far to scroll to bring a line, `top` to `bottom`, to the middle of a view starting at
 * `viewTop` and `viewHeight` high. Nothing within a few pixels: a line that jitters while it is
 * typed into is worse than one a hair off the middle.
 */
export function centeringDelta(top, bottom, viewTop, viewHeight) {
    const delta = Math.round((top + bottom) / 2 - (viewTop + viewHeight / 2))

    return Math.abs(delta) < 4 ? 0 : delta
}

/**
 * Brings the caret's line to the middle. Instantly while typing - a page that glides after
 * every letter lags behind it - and smoothly when the caret was moved somewhere, unless the
 * reader asked for less motion.
 */
export function center(view, { typing = false } = {}) {
    let coords

    try {
        coords = view.coordsAtPos(view.state.selection.head)
    } catch {
        return
    }

    const scroller = scrollerOf(view.dom)
    const delta = centeringDelta(
        coords.top,
        coords.bottom,
        scroller ? scroller.getBoundingClientRect().top : 0,
        scroller ? scroller.clientHeight : window.innerHeight,
    )

    if (delta === 0) {
        return
    }

    const still = typing || window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches

    ;(scroller ?? window).scrollBy({ top: delta, behavior: still ? 'instant' : 'smooth' })
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState
    const pmView = window.FilamentRichEditor?.tiptap?.pmView

    if (!tiptap || !pmState || !pmView) {
        console.error(
            'The advanced rich editor focus mode extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState
    const { Decoration, DecorationSet } = pmView

    const key = new PluginKey('arteFocusMode')

    return Extension.create({
        name: 'arteFocusMode',

        addStorage() {
            return {
                on: false,
                // Switched on and not yet centred: the transaction the command sends changes
                // neither the selection nor the document, so the view would otherwise wait for
                // the next keystroke.
                pending: false,
            }
        },

        addCommands() {
            return {
                // Through the command so that a transaction follows: one without steps is what
                // makes the view draw again, and what Filament moves the button's state with.
                toggleFocusMode:
                    () =>
                    ({ tr, dispatch }) => {
                        if (dispatch) {
                            this.storage.on = !this.storage.on
                            this.storage.pending = this.storage.on
                            tr.setMeta(key, this.storage.on)
                        }

                        return true
                    },
            }
        },

        addProseMirrorPlugins() {
            const storage = this.storage

            return [
                new Plugin({
                    key,
                    props: {
                        // Worked out from the selection each time rather than kept: it is one
                        // block, and a kept set would have to be moved along with every edit.
                        decorations: (state) =>
                            storage.on
                                ? DecorationSet.create(
                                      state.doc,
                                      focusedBlocks(state).map(({ from, to }) =>
                                          Decoration.node(from, to, { class: 'fi-arte-focus-current' }),
                                      ),
                                  )
                                : DecorationSet.empty,
                        attributes: () => (storage.on ? { class: 'fi-arte-focus-mode' } : {}),
                    },
                    view: () => {
                        let frame = null

                        return {
                            update: (view, previous) => {
                                if (!storage.on || !view.hasFocus()) {
                                    return
                                }

                                const typing = !previous.doc.eq(view.state.doc)

                                if (!storage.pending && !typing && previous.selection.eq(view.state.selection)) {
                                    return
                                }

                                storage.pending = false

                                // After the browser has laid out what changed, so the caret is
                                // measured where it is rather than where it was.
                                cancelAnimationFrame(frame)
                                frame = requestAnimationFrame(() => center(view, { typing }))
                            },
                            destroy: () => cancelAnimationFrame(frame),
                        }
                    },
                }),
            ]
        },
    })
}
