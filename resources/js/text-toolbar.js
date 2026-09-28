/*
 * The bar over selected text, and the rule that decides where it appears.
 *
 * Filament registers this one under the `paragraph` key and hard-codes its own rule for
 * that key alone: `isFocused && isActive('paragraph') && ! selection.empty`. The middle
 * clause is the whole of the problem, and it costs two things.
 *
 * In a heading the bar never appears. That is a gap on any field and a hole on one with no
 * toolbar at all - `->notion()`, or plain `->toolbarButtons([])` - because then the link,
 * the colours and the styles have nowhere else to be reached from. Registering a second bar
 * under `heading` does not help: for every key other than `paragraph` Filament asks only
 * whether the node is active, so that bar would appear on a click into the heading rather
 * than on a selection inside it.
 *
 * The other cost was already being paid in the stylesheet. A picture selected inside a
 * paragraph answers yes to both outer clauses, so the bar for formatting words was drawn on
 * top of the bar for laying out the picture, and a `:has(.ProseMirror-selectednode)` rule
 * hid it - a decision about what to paint standing in for a decision about what to show,
 * because `shouldShow` looked out of reach. It is not: the bubble menu plugin takes an
 * `updateOptions` message, which is the same route `image-resize.js` and
 * `list-properties.js` already take for their own bars.
 *
 * So this is not a new bubble menu. It is Filament's, with the rule replaced by one that
 * asks what actually matters: is a stretch of text selected, and is it somewhere a mark
 * means anything.
 */

/**
 * The key the bar is registered under. Filament's name rather than a choice: its JavaScript
 * treats this one key as a special case, and a bar keyed anything else would be drawn into
 * the markup and never shown.
 */
export const TOOLBAR = 'paragraph'

/**
 * Whether the marks the bar offers mean anything inside a block.
 *
 * Asked of the schema rather than of the node's name: a block whose spec says it holds code
 * is one where bold, italic, a colour and a link have nothing to do, and a project that
 * declares its own code-ish block gets the same answer without being listed here.
 */
export function allowsMarks(block) {
    return block?.isTextblock === true && block.type?.spec?.code !== true
}

/**
 * Whether the bar is holding the interaction the editor has just lost.
 *
 * A copy of the function `list-properties.js` exports, and deliberately a copy: the modules
 * this package ships are loaded one by one by Filament and never import a sibling
 * synchronously, while `shouldShow` is called synchronously. Two answers rather than one,
 * because focus alone does not cover the moment that matters - a press that is on its way
 * to a swatch has left the editor and not yet arrived anywhere.
 */
export function holdsInteraction(element, pressed = null) {
    if (! element) {
        return false
    }

    const focused = element.ownerDocument?.activeElement ?? null

    return (
        (focused !== null && element.contains(focused)) ||
        (pressed !== null && element.contains(pressed))
    )
}

/**
 * Whether anything the selection covers is somewhere a mark means something.
 *
 * The range rather than the block it starts in, and that distinction is the whole of this
 * function. `selection.$from.parent` is not always a text block even when every character
 * selected sits in one: a `CellSelection` resolves its first position inside the cell, so the
 * parent is the `tableCell`, and selecting across two cells would take the bar away from
 * text that bold, italic and a link all still apply to. A selection that starts in a code
 * block and runs on into prose reads the same way round.
 *
 * One text block that takes marks is enough. A selection is a stretch of the document rather
 * than a single node, and a bar offered for the part of it that can be formatted is right
 * where a bar refused for the part that cannot is simply missing.
 *
 * Every range rather than `from` to `to`, which is the same distinction one level down. A
 * selection carries a range per disjoint piece, and `from`/`to` are the first range's ends
 * rather than the outer bounds of all of them - measured on a live two-cell selection they
 * were `[11, 17]` while the ranges were `[[11, 17], [3, 9]]`, so the span misses a cell and
 * does not even know which one.
 */
export function selectionAllowsMarks({ doc, selection }) {
    let allowed = false

    for (const range of selection.ranges) {
        if (allowed) {
            break
        }

        doc.nodesBetween(range.$from.pos, range.$to.pos, (node) => {
            if (allowed) {
                return false
            }

            if (! node.isTextblock) {
                // Not a text block, so its children still might be - a cell holds paragraphs.
                return true
            }

            allowed = allowsMarks(node)

            // Nothing inside a text block is one, whatever this answered.
            return false
        })
    }

    return allowed
}

/**
 * Filament's rule for the text bar, replaced by the three questions it was standing in for.
 *
 * A node selection is refused first, and that is the general statement the stylesheet rule
 * was a special case of: a selected picture, embed or callout is not a selection of text,
 * and every button on this bar annotates text. It also keeps the two bars off each other,
 * since the picture's own bar shows itself on exactly that selection.
 *
 * Focus inside the bar counts as focus, for the same reason the list panel and the image
 * bar already say so: this bar carries the style picker and both colour pickers, and every
 * one of them is a panel that takes the focus when it opens. Without this the first
 * transaction after the click removes the bar from the DOM, taking the open panel with it.
 */
export function textToolbarVisibility({ pressed = () => null } = {}) {
    return ({ editor, element }) => {
        const selection = editor.state.selection

        if (selection.empty || selection.node != null) {
            return false
        }

        if (! selectionAllowsMarks(editor.state)) {
            return false
        }

        return editor.isFocused || holdsInteraction(element, pressed())
    }
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (! tiptap) {
        console.error(
            'The advanced rich editor text toolbar needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteTextToolbar',

        addStorage() {
            // What the pointer is currently pressed on, and the way to stop watching for it.
            return { pressed: null, release: null }
        },

        /**
         * `onCreate` rather than the extension's constructor because Filament registers the
         * bubble menu plugins after `new Editor(...)` returns, and TipTap emits `create` a
         * task later - so by the time this runs there is something to address the message
         * to. A message to a plugin key nothing answers to is inert, which is what makes it
         * safe to send without first asking whether the field was given the bar.
         */
        onCreate() {
            const { editor } = this
            const storage = this.storage
            const owner = editor.view.dom.ownerDocument

            const press = (event) => {
                storage.pressed = event.target instanceof Node ? event.target : null
            }

            // Let go of once the click is over. A flag left standing would hold the bar open
            // long after the press that set it, and every later question would answer with
            // where the pointer last happened to be.
            const release = () => {
                storage.pressed = null
            }

            owner.addEventListener('pointerdown', press, true)
            owner.addEventListener('pointerup', release, true)
            owner.addEventListener('pointercancel', release, true)

            storage.release = () => {
                owner.removeEventListener('pointerdown', press, true)
                owner.removeEventListener('pointerup', release, true)
                owner.removeEventListener('pointercancel', release, true)
            }

            editor.view.dispatch(
                editor.state.tr
                    .setMeta('addToHistory', false)
                    .setMeta(`floatingToolbar::${TOOLBAR}`, {
                        type: 'updateOptions',
                        options: {
                            shouldShow: textToolbarVisibility({ pressed: () => storage.pressed }),
                        },
                    }),
            )
        },

        onDestroy() {
            this.storage.release?.()
        },
    })
}
