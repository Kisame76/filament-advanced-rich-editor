/*
 * Line spacing on the block the caret sits in.
 *
 * The spacing is a unitless `line-height` in the block's inline `style`, which is the one
 * declaration that scales with whatever font size the block ends up at - a heading keeps
 * its own proportions, and the same document reads the same in the editor, in a saved page
 * and in a print stylesheet. Filament's sanitiser allows `style` on every element, so
 * nothing has to be added to an allow list.
 *
 * Only bare numbers are accepted, in both directions. The sanitiser does not look inside
 * CSS, so a value taken from a document could otherwise smuggle a second declaration in
 * behind a semicolon; a number cannot. Anything else - `150%`, `24px`, a pasted `inherit` -
 * is read as no spacing at all rather than carried through.
 *
 * Registered as a GLOBAL attribute rather than by redefining the paragraph and the heading,
 * for the same reason the direction extension is: TipTap merges global attributes into the
 * types they name, so Filament's own nodes keep their definition, styles written by several
 * attributes are merged into one `style`, and the PHP half can mirror this exactly.
 */
const MIN = 0.5

const MAX = 5

const normalise = (value) => {
    if (typeof value === 'number') {
        value = String(value)
    }

    if (typeof value !== 'string') {
        return null
    }

    const trimmed = value.trim()

    if (!/^\d+(\.\d+)?$/.test(trimmed)) {
        return null
    }

    const number = Number(trimmed)

    if (!Number.isFinite(number) || number < MIN || number > MAX) {
        return null
    }

    // `1.50` and `1.5` are the same spacing, and the toolbar compares the stored value
    // against the one its button carries - so only one of the two spellings may be stored.
    return String(number)
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor line height extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteLineHeight',

        addOptions() {
            return {
                types: ['paragraph', 'heading', 'blockquote', 'listItem'],
            }
        },

        addGlobalAttributes() {
            return [
                {
                    types: this.options.types,
                    attributes: {
                        lineHeight: {
                            default: null,
                            parseHTML: (element) =>
                                normalise(
                                    element.style?.lineHeight ||
                                        element.getAttribute('data-line-height'),
                                ),
                            renderHTML: (attributes) =>
                                attributes.lineHeight
                                    ? { style: `line-height: ${attributes.lineHeight}` }
                                    : {},
                        },
                    },
                },
            ]
        },

        addCommands() {
            // Written over the declared types only. TipTap's own attribute commands walk
            // every node in the selection, including the ones that never declared the
            // attribute, where ProseMirror then throws.
            const write =
                (lineHeight) =>
                ({ state, tr, dispatch }) => {
                    const types = new Set(
                        this.options.types.filter((type) => state.schema.nodes[type]),
                    )

                    const { from, to } = state.selection

                    let changed = false

                    state.doc.nodesBetween(from, to, (node, pos) => {
                        if (!types.has(node.type.name) || node.attrs.lineHeight === lineHeight) {
                            return
                        }

                        changed = true

                        if (dispatch) {
                            tr.setNodeMarkup(pos, undefined, { ...node.attrs, lineHeight })
                        }
                    })

                    return changed
                }

            return {
                setLineHeight: (lineHeight) => write(normalise(lineHeight)),

                unsetLineHeight: () => write(null),

                // Picking the spacing a block already has takes it back off, the way the
                // headings dropdown returns a heading to a paragraph. Without it there
                // would be no way back to whatever the theme sets, only to another number.
                toggleLineHeight:
                    (lineHeight) =>
                    ({ editor, commands }) =>
                        editor.isActive({ lineHeight: normalise(lineHeight) })
                            ? commands.unsetLineHeight()
                            : commands.setLineHeight(lineHeight),
            }
        },
    })
}
