/*
 * Letting the text run beside a picture.
 *
 * A GLOBAL attribute on the image node rather than a replacement for it, exactly as the
 * rotation is and for the reasons written there: replacing the node would mean reproducing
 * Filament's own image extension, its resize node view and its attachment id handling.
 *
 * The side travels inside the inline `style`, because Filament's sanitiser keeps `style`
 * and drops anything not on its short allow list. Nothing validates CSS on the way, so the
 * side is whitelisted to two words before it is written.
 *
 * What actually floats in the editor is not this element. Filament draws an image through
 * a resize node view, which puts the picture inside a wrapper, and a float on a picture
 * inside a block wrapper moves nothing. The stylesheet floats the wrapper instead, keyed
 * on the style this writes - see `[data-resize-wrapper]:has(> img[style*='float'])`.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor image float needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // The image has to stay selected after a side is chosen, and re-selecting it needs the
    // class the selection is made of. Missing only on a build that does not expose
    // ProseMirror's own state module, where the float still works and the toolbar blinks.
    const NodeSelection = window.FilamentRichEditor?.tiptap?.pmState?.NodeSelection ?? null

    const PLACEMENTS = ['left', 'center', 'right']

    const normalise = (value) => {
        const placement = typeof value === 'string' ? value.trim().toLowerCase() : null

        return PLACEMENTS.includes(placement) ? placement : null
    }

    /*
     * The placement and nothing else. The gap beside a floated picture is written by the
     * PHP half, and only by it: what a save stores is rendered from the document in PHP,
     * not from this DOM, so a second copy of the margins here would reach nobody. Inside
     * the editor the gap comes from the stylesheet, which reads the same configured value
     * off a custom property on the field.
     */
    const styleFor = (placement) => {
        if (!placement) {
            return {}
        }

        return {
            style:
                placement === 'center'
                    ? 'display: block; margin-inline: auto'
                    : `float: ${placement}`,
        }
    }

    return Extension.create({
        name: 'arteImageFloat',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        float: {
                            default: null,

                            parseHTML: (element) => {
                                const style = element.getAttribute('style') ?? ''
                                const match = /(?:^|[;\s])float\s*:\s*(left|right)/i.exec(style)

                                if (match) {
                                    return normalise(match[1])
                                }

                                // The automatic margin is what centres the picture, so it
                                // is what says it was centred.
                                return /(?:^|[;\s])margin-inline\s*:\s*auto/i.test(style) ? 'center' : null
                            },

                            renderHTML: (attributes) => styleFor(normalise(attributes.float)),
                        },
                    },
                },
            ]
        },

        addCommands() {
            return {
                /*
                 * Reads the side off the node at the selection rather than through
                 * `getAttributes()`, and writes it with `setNodeMarkup` rather than
                 * `updateAttributes`. Both matter for the same reason the rotation gives:
                 * focusing the editor collapses the node selection a click on an image
                 * produced, and once it is a caret the attribute lookup comes back empty.
                 *
                 * Choosing the placement an image already has takes it off, back to the
                 * ordinary flow. That is the idiom the callouts already use - the button
                 * that put something there is the one that takes it away - and it means
                 * three buttons cover four states.
                 */
                setImageFloat:
                    (placement) =>
                    ({ state, tr, dispatch }) => {
                        const position = state.selection.from
                        const node = state.doc.nodeAt(position)

                        if (node?.type.name !== 'image') {
                            return false
                        }

                        const wanted = normalise(placement)
                        const current = normalise(node.attrs.float)

                        if (dispatch) {
                            tr.setNodeMarkup(position, undefined, {
                                ...node.attrs,
                                float: current === wanted ? null : wanted,
                            })

                            /*
                             * `setNodeMarkup` writes a new node over the old one, and the
                             * selection that survives is a caret beside it rather than a
                             * selection OF it - which takes the floating toolbar away,
                             * along with the button that was just pressed.
                             */
                            if (NodeSelection?.isSelectable(tr.doc.nodeAt(position))) {
                                tr.setSelection(NodeSelection.create(tr.doc, position))
                            }

                            dispatch(tr)
                        }

                        return true
                    },
            }
        },
    })
}
