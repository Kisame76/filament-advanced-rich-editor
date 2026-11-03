/*
 * Locked parts of a document, on the editor's side: the node, the guard and the command that
 * locks and unlocks.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * The PHP half is `Nodes/Locked.php`, and `LockedRegions` checks every save again: the guard
 * here is what makes a locked part feel locked, not what keeps it locked. A browser can be
 * made to send anything.
 */

export const NAME = 'locked'

/**
 * The settings the field handed over: whether this person may lock, unlock and change locked
 * parts, and what the padlock is called. Anything unreadable is the safe answer - not
 * editable.
 */
export function settingsOf(element) {
    try {
        const settings = JSON.parse(element?.dataset?.arteLocked ?? 'null')

        return { editable: settings?.editable === true, label: String(settings?.label ?? '') }
    } catch {
        return { editable: false, label: '' }
    }
}

/**
 * The locked parts of a document, outermost only - one inside another is part of it. Text is
 * never descended into: nothing locked lives inside a paragraph.
 */
export function lockedParts(doc) {
    const found = []

    doc.descendants((node) => {
        if (node.type.name === NAME) {
            found.push(node)

            return false
        }

        return !node.isTextblock
    })

    return found
}

/**
 * Whether `after` holds exactly the locked parts `before` held: each of them, unchanged, and
 * nothing else locked. As a collection, because moving a locked part is not changing it - the
 * same rule `LockedRegions::kept()` applies to a save.
 */
export function keepsLockedParts(before, after) {
    const left = lockedParts(after)
    const parts = lockedParts(before)

    if (parts.length !== left.length) {
        return false
    }

    return parts.every((part) => {
        const index = left.findIndex((candidate) => candidate.eq(part))

        if (index === -1) {
            return false
        }

        left.splice(index, 1)

        return true
    })
}

/**
 * Whether a transaction is Filament putting the field's state into the editor - after a save,
 * or an action that rewrote the document - rather than somebody editing. TipTap's `setContent`
 * replaces the whole document and is the only thing that says whether it should be reported,
 * as `preventUpdate`; an edit that happens to select everything says nothing.
 */
export function isLoad(tr) {
    const [step] = tr.steps

    return (
        tr.getMeta('preventUpdate') !== undefined &&
        tr.steps.length === 1 &&
        step.from === 0 &&
        step.to === tr.before.content.size
    )
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor locked parts extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Node, mergeAttributes } = tiptap
    const { Plugin, PluginKey } = pmState

    return Node.create({
        name: NAME,

        group: 'block',

        content: 'block+',

        // A paste or a lift does not carry it off with the content, and the caret stays on
        // its side of the edge.
        defining: true,

        isolating: true,

        addOptions() {
            return { HTMLAttributes: {} }
        },

        parseHTML() {
            // Above the default 50, for the reasons `callout.js` gives.
            return [{ tag: `div[data-type="${NAME}"]`, priority: 51 }]
        },

        renderHTML({ HTMLAttributes }) {
            return [
                'div',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
                    class: 'fi-arte-locked',
                    'data-type': NAME,
                }),
                0,
            ]
        },

        /**
         * The frame and the padlock, and - for somebody who may not change the part - text
         * the browser will not put a caret into. The guard below is what refuses a change;
         * this is what keeps a person from trying.
         */
        addNodeView() {
            return () => {
                const { editable, label } = settingsOf(this.editor.options.element)
                const dom = document.createElement('div')
                const contentDOM = document.createElement('div')

                dom.className = 'fi-arte-locked'
                dom.dataset.type = NAME
                dom.dataset.label = label
                contentDOM.className = 'fi-arte-locked-content'

                if (!editable) {
                    dom.contentEditable = 'false'
                }

                dom.append(contentDOM)

                return { dom, contentDOM }
            }
        },

        addCommands() {
            return {
                /**
                 * Locks the blocks the selection is in, or unlocks the part it is in. Unlocking
                 * takes the frame away and leaves everything it held where it was.
                 */
                toggleLock:
                    () =>
                    ({ state, tr, dispatch, commands }) => {
                        const { $from } = state.selection

                        for (let depth = $from.depth; depth > 0; depth--) {
                            const node = $from.node(depth)

                            if (node.type.name !== NAME) {
                                continue
                            }

                            if (dispatch) {
                                tr.replaceWith($from.before(depth), $from.after(depth), node.content)
                            }

                            return true
                        }

                        return commands.wrapIn(this.name)
                    },
            }
        },

        addProseMirrorPlugins() {
            // Read off the element rather than kept from a hook: the plugins are asked for while
            // the editor is still being built, and `options.element` is the one thing that is
            // already there.
            if (settingsOf(this.editor.options.element).editable) {
                return []
            }

            return [
                new Plugin({
                    key: new PluginKey('arteLocked'),
                    filterTransaction: (tr) => !tr.docChanged || isLoad(tr) || keepsLockedParts(tr.before, tr.doc),
                }),
            ]
        },
    })
}
