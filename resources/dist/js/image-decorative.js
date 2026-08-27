/*
 * Marking a picture as one that carries no meaning.
 *
 * A divider, a texture, a flourish beside a heading the words already say. Such a picture
 * wants an empty `alt` AND `role="presentation"`, and the pair is the point: an empty `alt`
 * on its own is indistinguishable from a description somebody forgot, which is exactly what
 * the accessibility check in this package has to report as a fault. Saying it deliberately
 * is what takes it off that list.
 *
 * A GLOBAL attribute on the image node rather than a replacement for it, for the reasons
 * `image-float.js` gives: replacing the node would mean reproducing Filament's own image
 * extension, its resize node view and its attachment id handling.
 *
 * `role` rides as itself rather than inside the `style` the placement uses. Symfony's
 * sanitiser keeps it on its own safe list - which was checked rather than assumed, because
 * Filament's own additions name `width` and `height` for images and say nothing about it.
 */
// `none` is ARIA's own synonym for `presentation`, and the one a document pasted from
// another editor is as likely to spell.
//
// Exported so it can be checked against `ImageDecorative::isPresentational()` in PHP: one
// half writes what the other reads, and the two have to agree.
export const isPresentational = (role) =>
    ['presentation', 'none'].includes(typeof role === 'string' ? role.trim().toLowerCase() : '')

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor decorative image needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // Same reason as the float: `setNodeMarkup` leaves a caret where a node selection was,
    // and the floating toolbar goes away with it - along with the button just pressed.
    const NodeSelection = window.FilamentRichEditor?.tiptap?.pmState?.NodeSelection ?? null

    return Extension.create({
        name: 'arteImageDecorative',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        decorative: {
                            default: false,

                            parseHTML: (element) => isPresentational(element.getAttribute('role')),

                            renderHTML: (attributes) =>
                                attributes.decorative === true
                                    ? // Both halves, always. The role without the empty alt
                                      // is the case screen readers still read out loud.
                                      { role: 'presentation', alt: '' }
                                    : {},
                        },
                    },
                },
            ]
        },

        addCommands() {
            return {
                /*
                 * Reads the node at the selection rather than through `getAttributes()`, and
                 * writes with `setNodeMarkup`, for the reason the float and the rotation both
                 * give: focusing the editor collapses the node selection a click on a picture
                 * produced, and once it is a caret the attribute lookup comes back empty.
                 *
                 * Pressing it again takes the mark off, the idiom the placement and the
                 * callouts already use.
                 *
                 * Turning it on clears the alt text, which is the one destructive thing here
                 * and is meant: a description and "there is nothing to describe" cannot both
                 * be true, and leaving the words behind would have them come back the moment
                 * somebody unmarked it. Turning it off leaves the empty alt alone rather than
                 * inventing one - the picture then wants a description, and the accessibility
                 * check is about to say so.
                 */
                toggleImageDecorative:
                    () =>
                    ({ state, tr, dispatch }) => {
                        const position = state.selection.from
                        const node = state.doc.nodeAt(position)

                        if (node?.type.name !== 'image') {
                            return false
                        }

                        const decorative = node.attrs.decorative === true

                        if (dispatch) {
                            tr.setNodeMarkup(position, undefined, {
                                ...node.attrs,
                                decorative: !decorative,
                                alt: decorative ? node.attrs.alt : '',
                            })

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
