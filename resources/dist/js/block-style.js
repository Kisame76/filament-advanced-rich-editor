/*
 * The named style a block carries.
 *
 * The key travels, the classes do not. A style is a label mapped to a set of CSS classes,
 * and the map lives in the project's PHP configuration - which is where it has to live,
 * because the classes belong to the front end's design system and are applied when the
 * document is stored and when it is rendered. This side only ever holds the key, in
 * `data-style`, exactly as it is written into the database.
 *
 * A project that wants to see the style inside the editor as well as on the page styles
 * `[data-style="…"]` in its panel theme. The classes themselves would not help here: the
 * admin panel does not load the front end's stylesheet, so writing them into the editor's
 * DOM would produce markup that looks styled and renders plain.
 *
 * Registered as a GLOBAL attribute rather than by redefining the paragraph and the heading,
 * so Filament's own nodes keep their definition - the same shape `arteTextDirection` uses,
 * and the PHP half mirrors it with the same mechanism.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor block style extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteBlockStyle',

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
                        arteStyle: {
                            default: null,
                            parseHTML: (element) =>
                                element.getAttribute('data-style') || null,
                            renderHTML: (attributes) =>
                                attributes.arteStyle
                                    ? { 'data-style': attributes.arteStyle }
                                    : {},
                        },
                    },
                },
            ]
        },

        addCommands() {
            const write =
                (key) =>
                ({ state, tr, dispatch }) => {
                    const types = new Set(
                        this.options.types.filter((type) => state.schema.nodes[type]),
                    )

                    const { from, to } = state.selection

                    let changed = false

                    state.doc.nodesBetween(from, to, (node, pos) => {
                        if (!types.has(node.type.name) || node.attrs.arteStyle === key) {
                            return
                        }

                        changed = true

                        if (dispatch) {
                            tr.setNodeMarkup(pos, undefined, {
                                ...node.attrs,
                                arteStyle: key,
                            })
                        }
                    })

                    return changed
                }

            return {
                setBlockStyle: (key) => write(key),

                unsetBlockStyle: () => write(null),

                // Picking the style a block already has takes it back off, the way picking
                // a heading level returns a heading to a paragraph. One style at a time is
                // the whole model: a project that wants two of its classes together writes
                // one entry holding both.
                toggleBlockStyle:
                    (key) =>
                    ({ editor, commands }) =>
                        editor.isActive({ arteStyle: key })
                            ? commands.unsetBlockStyle()
                            : commands.setBlockStyle(key),
            }
        },
    })
}
