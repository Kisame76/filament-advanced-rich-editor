/*
 * Quarter-turn rotation for images.
 *
 * Added as a GLOBAL attribute on the image node rather than by replacing the node: both
 * TipTap and `ueberdosis/tiptap-php` merge global attributes into the attribute list their
 * parser and serialiser walk (the PHP side does it in `Tiptap\Core\Schema`, with
 * `Tiptap\Extensions\TextAlign` as the in-tree precedent). Replacing the node instead would
 * mean reproducing Filament's own image extension, its resize node view and its attachment
 * id handling - and, on the PHP side, a second extension of the same name renders a second
 * tag on every save.
 *
 * The angle travels inside the inline `style`, because Filament's sanitiser keeps `style`
 * but drops any attribute that is not on its short allow list. Nothing else in the stack
 * validates CSS, so the angle is whitelisted here to quarter turns before it is written.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor image rotation needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    const normalise = (value) => {
        const angle = Number.parseFloat(value)

        if (!Number.isFinite(angle)) {
            return null
        }

        const quarters = Math.round(angle / 90) * 90
        const wrapped = ((quarters % 360) + 360) % 360

        return wrapped === 0 ? null : wrapped
    }

    return Extension.create({
        name: 'arteImageRotate',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        rotate: {
                            default: null,

                            parseHTML: (element) => {
                                const match = /rotate\(\s*(-?[\d.]+)deg\s*\)/i.exec(
                                    element.getAttribute('style') ?? '',
                                )

                                return match ? normalise(match[1]) : null
                            },

                            renderHTML: (attributes) => {
                                const angle = normalise(attributes.rotate)

                                if (!angle) {
                                    return {}
                                }

                                const declarations = [`transform: rotate(${angle}deg)`]

                                const width = Number.parseFloat(attributes.width)
                                const height = Number.parseFloat(attributes.height)

                                // A transform does not change the layout box, so a quarter
                                // turned image would keep reserving its old footprint and
                                // overlap the lines around it. The margins make the box
                                // match what is actually drawn. Half turns need nothing.
                                if (
                                    angle % 180 !== 0 &&
                                    Number.isFinite(width) &&
                                    Number.isFinite(height)
                                ) {
                                    declarations.push(
                                        `margin-block: ${(width - height) / 2}px`,
                                        `margin-inline: ${(height - width) / 2}px`,
                                    )
                                }

                                return { style: declarations.join('; ') }
                            },
                        },
                    },
                },
            ]
        },

        addCommands() {
            return {
                /*
                 * Reads the angle off the node at the selection rather than through
                 * `getAttributes()`, and writes it with `setNodeMarkup` rather than
                 * `updateAttributes`. Both matter: focusing the editor collapses the node
                 * selection an image click produced, and once it is a caret the attribute
                 * lookup comes back empty - every turn would then compute from zero and
                 * the image would sit at 90 degrees for ever.
                 */
                rotateImage:
                    (delta) =>
                    ({ state, tr, dispatch }) => {
                        const position = state.selection.from
                        const node = state.doc.nodeAt(position)

                        if (node?.type.name !== 'image') {
                            return false
                        }

                        const current = Number.parseFloat(node.attrs.rotate) || 0

                        if (dispatch) {
                            dispatch(
                                tr.setNodeMarkup(position, undefined, {
                                    ...node.attrs,
                                    rotate: normalise(current + delta),
                                }),
                            )
                        }

                        return true
                    },
            }
        },
    })
}
