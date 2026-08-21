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

    // The image has to stay selected after a turn, and re-selecting it needs the class the
    // selection is made of. Missing only on a build that does not expose ProseMirror's own
    // state module, where the turn still works and only the toolbar blinks out.
    const NodeSelection = window.FilamentRichEditor?.tiptap?.pmState?.NodeSelection ?? null

    const normalise = (value) => {
        const angle = Number.parseFloat(value)

        if (!Number.isFinite(angle)) {
            return null
        }

        const quarters = Math.round(angle / 90) * 90
        const wrapped = ((quarters % 360) + 360) % 360

        return wrapped === 0 ? null : wrapped
    }

    /**
     * The width and height an image is actually drawn at, for the moment it is turned and
     * has none of its own. Returns nothing when the picture cannot be measured - a size
     * guessed from an image that has not loaded would be worse than no size at all.
     */
    const measure = (view, position, attributes) => {
        // Only when the node has none of its own: overwriting a stored size with the one
        // that happens to be on screen would let a picture drift smaller every time it was
        // turned in a container narrow enough to be holding it back.
        if (
            Number.isFinite(Number.parseFloat(attributes?.width)) &&
            Number.isFinite(Number.parseFloat(attributes?.height))
        ) {
            return {}
        }

        const dom = view?.nodeDOM?.(position)
        const image = dom?.querySelector?.('img') ?? (dom?.tagName === 'IMG' ? dom : null)

        if (!image || !image.offsetWidth || !image.offsetHeight) {
            return {}
        }

        return { width: image.offsetWidth, height: image.offsetHeight }
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
                    ({ state, tr, dispatch, view }) => {
                        const position = state.selection.from
                        const node = state.doc.nodeAt(position)

                        if (node?.type.name !== 'image') {
                            return false
                        }

                        const current = Number.parseFloat(node.attrs.rotate) || 0

                        if (dispatch) {
                            /*
                             * The margins that make a turned image's layout box match what
                             * is drawn can only be worked out from a width and a height, and
                             * an image that has never been resized carries neither - it is
                             * simply drawn at its natural size. Turning it would then leave
                             * the box unturned: the picture would lie across the lines
                             * around it, and the resize handles - which sit on that box -
                             * would no longer be on its corners.
                             *
                             * So a turn pins the size it is turning, read off the picture as
                             * it is on screen right now. It is also what someone doing it
                             * would expect: the picture keeps the size it had.
                             */
                            const size = measure(view, position, node.attrs)

                            tr.setNodeMarkup(position, undefined, {
                                ...node.attrs,
                                ...size,
                                rotate: normalise(current + delta),
                            })

                            /*
                             * `setNodeMarkup` writes a new node over the old one, and the
                             * selection that survives that is a caret beside it rather than
                             * a selection OF it. The floating toolbar is shown while the
                             * image is active, so without putting the node selection back
                             * the bar vanishes on the first turn - taking the button that
                             * was just pressed with it.
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
