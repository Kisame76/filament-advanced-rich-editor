/*
 * Left-to-right and right-to-left blocks.
 *
 * The direction is a `dir` attribute on the block itself, which is the HTML that means
 * this and the one thing the browser, the sanitiser and a screen reader all already
 * understand. Symfony's sanitiser - the one Filament configures - counts `dir` among the
 * safe attributes, so it survives a save without anything being added to an allow list.
 *
 * Registered as a GLOBAL attribute rather than by redefining the paragraph and the
 * heading: TipTap merges global attributes into the schema of the types they name, so
 * Filament's own nodes keep their definition and the PHP half can mirror this with the
 * same mechanism.
 *
 * TipTap's own `setTextDirection` is bundled with Filament's editor, and it writes `dir`
 * onto every node in the selection - including the ones that never declared the attribute,
 * where ProseMirror then throws. The commands here do the same job over the declared types
 * only, which is why they carry their own names instead of shadowing that one.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor text direction extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteTextDirection',

        addOptions() {
            return {
                types: ['paragraph', 'heading', 'blockquote', 'listItem', 'codeBlock'],
            }
        },

        addGlobalAttributes() {
            return [
                {
                    types: this.options.types,
                    attributes: {
                        dir: {
                            default: null,
                            parseHTML: (element) => {
                                const dir = element.getAttribute('dir')?.toLowerCase()

                                return dir === 'ltr' || dir === 'rtl' ? dir : null
                            },
                            renderHTML: (attributes) =>
                                attributes.dir ? { dir: attributes.dir } : {},
                        },
                    },
                },
            ]
        },

        addCommands() {
            const write =
                (dir) =>
                ({ state, tr, dispatch }) => {
                    const types = new Set(
                        this.options.types.filter((type) => state.schema.nodes[type]),
                    )

                    const { from, to } = state.selection

                    let changed = false

                    state.doc.nodesBetween(from, to, (node, pos) => {
                        if (!types.has(node.type.name) || node.attrs.dir === dir) {
                            return
                        }

                        changed = true

                        if (dispatch) {
                            tr.setNodeMarkup(pos, undefined, { ...node.attrs, dir })
                        }
                    })

                    return changed
                }

            return {
                setBlockDirection: (dir) => write(dir),

                unsetBlockDirection: () => write(null),

                // Picking the direction a block already has takes it back off, the way the
                // headings dropdown returns a heading to a paragraph. Without it the only
                // way back to the field's own direction would be the other button, which
                // says something different.
                toggleBlockDirection:
                    (dir) =>
                    ({ editor, commands }) =>
                        editor.isActive({ dir })
                            ? commands.unsetBlockDirection()
                            : commands.setBlockDirection(dir),
            }
        },
    })
}
