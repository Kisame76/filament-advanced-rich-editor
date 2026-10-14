/**
 * Showing what a document holds and does not draw: the spaces that may not break, the
 * hyphen that only appears at the end of a line, the characters with no width at all, the
 * line breaks and the ends of paragraphs - and, as a second view, the outline of every block.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Nothing here is stored. Both views are decorations and a class on the editor, which
 * ProseMirror draws and never serialises: the saved HTML, the clipboard and the undo history
 * are the same with them on as off - the reason there is no PHP half, as for the search.
 *
 * The signs themselves are the stylesheet's. Every element this draws is empty, so there is
 * nothing in it to be read out, selected or copied, and a project can change `°` for
 * something else without touching a script.
 */

/**
 * The characters that are there and cannot be seen, and how each is drawn.
 *
 * `space` takes room like a space and must not break; it is drawn over itself, so turning
 * the view on moves no line. `hyphen` is the soft hyphen, which takes no room until a line
 * breaks at it; it is drawn as the `¬` Word uses. `zero-width` never takes room; it is drawn
 * as a hairline.
 *
 * The zero-width joiner and non-joiner are deliberately absent. They hold every family emoji
 * together and keep Persian letters apart, and neither is a stray character to be found.
 */
export const INVISIBLE_CHARACTERS = {
    // No-break space.
    ' ': 'space',
    // Narrow no-break space, before French punctuation and inside numbers.
    ' ': 'space',
    // Figure space, as wide as a digit.
    ' ': 'space',
    // Soft hyphen.
    '­': 'hyphen',
    // Zero-width space.
    '​': 'zero-width',
    // Word joiner.
    '⁠': 'zero-width',
    // Zero-width no-break space: the byte order mark, carried in by a paste.
    '﻿': 'zero-width',
}

const PATTERN = new RegExp(`[${Object.keys(INVISIBLE_CHARACTERS).join('')}]`, 'g')

/**
 * Everything the character view draws in one document.
 *
 * Positions are ProseMirror's: a character is `from` to `to`, a line break is the position in
 * front of it, and the end of a paragraph is the end of its content - where the caret sits
 * after the last letter.
 *
 * @returns {Array<{type: 'character', kind: string, from: number, to: number} | {type: 'break' | 'paragraph', pos: number}>}
 */
export function invisiblesIn(doc) {
    const found = []

    doc.descendants((node, pos) => {
        if (!node.isTextblock) {
            return true
        }

        found.push(...invisiblesInTextblock(node, pos))

        return false
    })

    return found
}

/**
 * The same for one textblock starting at `pos` - the unit an edit is redrawn in, since inline
 * content lives only in textblocks and a sign never reaches past the one it is in.
 *
 * Code gets no paragraph sign: a `¶` after a command would read as part of it. Its
 * characters are marked like any others, since a no-break space pasted into a shell command
 * is exactly the thing worth finding.
 */
export function invisiblesInTextblock(node, pos) {
    const found = []

    node.forEach((child, offset) => {
        const start = pos + 1 + offset

        if (child.isText) {
            for (const match of child.text.matchAll(PATTERN)) {
                found.push({
                    type: 'character',
                    kind: INVISIBLE_CHARACTERS[match[0]],
                    from: start + match.index,
                    to: start + match.index + match[0].length,
                })
            }
        } else if (child.type.name === 'hardBreak') {
            found.push({ type: 'break', pos: start })
        }
    })

    if (!node.type.spec.code) {
        found.push({ type: 'paragraph', pos: pos + node.nodeSize - 1 })
    }

    return found
}

/**
 * The textblocks an edit touched, by where they start in the document after it.
 *
 * Each step of the transaction says which stretch it replaced; that stretch is carried
 * through the steps after it, so every range is in the final document's positions. One
 * position either side, so that a paragraph that lost its neighbour - or was joined to it -
 * is read again too.
 */
export function touchedTextblocks(tr, doc) {
    const blocks = new Map()

    tr.mapping.maps.forEach((stepMap, index) => {
        const rest = tr.mapping.slice(index + 1)

        stepMap.forEach((_oldStart, _oldEnd, newStart, newEnd) => {
            const from = Math.max(0, rest.map(newStart, -1) - 1)
            const to = Math.min(doc.content.size, rest.map(newEnd, 1) + 1)

            doc.nodesBetween(from, to, (node, pos) => {
                if (!node.isTextblock) {
                    return true
                }

                blocks.set(pos, node)

                return false
            })
        })
    })

    return blocks
}

function sign(type) {
    const element = document.createElement('span')

    element.className = `fi-arte-invisible-${type}`
    element.setAttribute('aria-hidden', 'true')

    return element
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState
    const pmView = window.FilamentRichEditor?.tiptap?.pmView

    if (!tiptap || !pmState || !pmView) {
        console.error(
            'The advanced rich editor invisibles extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState
    const { Decoration, DecorationSet } = pmView

    const key = new PluginKey('arteInvisibles')

    const decorationOf = (found) =>
        found.type === 'character'
            ? Decoration.inline(found.from, found.to, {
                  class: `fi-arte-invisible fi-arte-invisible-${found.kind}`,
              })
            : // A caret at a break sits after its `↵`, and one at the end of a paragraph
              // before its `¶`. The key lets ProseMirror keep the element it already drew
              // rather than drawing every sign again, and no marks keep a `¶` after bold
              // text from coming out bold.
              Decoration.widget(found.pos, () => sign(found.type), {
                  side: found.type === 'break' ? -1 : 1,
                  key: found.type,
                  marks: [],
              })

    const decorationsFor = (doc) => DecorationSet.create(doc, invisiblesIn(doc).map(decorationOf))

    // Measured on 3,000 paragraphs: walking the whole document for each keystroke took typing
    // from 6 to 33 ms. Everything else is moved along with the edit, and only what it touched
    // is read again.
    const redrawn = (decorations, tr, doc) => {
        let next = decorations.map(tr.mapping, doc)

        for (const [pos, node] of touchedTextblocks(tr, doc)) {
            next = next.remove(next.find(pos, pos + node.nodeSize))
            next = next.add(doc, invisiblesInTextblock(node, pos).map(decorationOf))
        }

        return next
    }

    // Toggled through the command so that a transaction follows: one without steps is what
    // makes the view draw again, and what Filament moves the button's pressed state with.
    const toggle = (storage, view) => ({ tr, dispatch }) => {
        if (dispatch) {
            storage[view] = !storage[view]
            tr.setMeta(key, view)
        }

        return true
    }

    return Extension.create({
        name: 'arteInvisibles',

        addStorage() {
            return {
                characters: false,
                blocks: false,
            }
        },

        addCommands() {
            return {
                toggleInvisibleCharacters: () => toggle(this.storage, 'characters'),
                toggleBlockOutlines: () => toggle(this.storage, 'blocks'),
            }
        },

        addProseMirrorPlugins() {
            const storage = this.storage

            return [
                new Plugin({
                    key,
                    state: {
                        init: (_config, state) =>
                            storage.characters ? decorationsFor(state.doc) : DecorationSet.empty,
                        // Every click and arrow key is a transaction too, and none of them
                        // changes a sign - nor does switching the outlines.
                        apply(tr, decorations, _oldState, state) {
                            if (!storage.characters) {
                                return DecorationSet.empty
                            }

                            if (tr.getMeta(key) === 'characters') {
                                return decorationsFor(state.doc)
                            }

                            return tr.docChanged ? redrawn(decorations, tr, state.doc) : decorations
                        },
                    },
                    props: {
                        decorations: (state) => key.getState(state),
                        // The outlines and their labels are the stylesheet's, so they cost the
                        // same in a document of ten paragraphs as in one of ten thousand.
                        attributes: () => (storage.blocks ? { class: 'fi-arte-show-blocks' } : {}),
                    },
                }),
            ]
        },
    })
}
