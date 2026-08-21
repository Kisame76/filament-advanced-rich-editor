/*
 * A background colour for a text selection.
 *
 * Deliberately NOT named `highlight`, even though it also renders a `<mark>`: Filament
 * registers TipTap's Highlight without `multicolor`, so its mark type declares no
 * attributes and a colour handed to `setHighlight()` is silently dropped. Replacing that
 * extension by name would fix the browser side but not the server side, where
 * `RichContentRenderer` adds its own colourless Highlight unconditionally - two PHP marks
 * of the same name both open a tag, so every save would nest another `<mark>`.
 *
 * A separate name avoids all of it: this mark claims `mark[data-color]` at a higher parse
 * priority, Filament's plain `highlight` tool keeps working on bare `<mark>` elements, and
 * neither side sees a duplicate.
 *
 * The colour rides in `data-color` and in the inline `style`, which are both on Filament's
 * sanitiser allow list.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor text background extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Mark, mergeAttributes } = tiptap

    return Mark.create({
        name: 'textBackground',

        addOptions() {
            return { HTMLAttributes: {} }
        },

        addAttributes() {
            return {
                color: {
                    default: null,
                    parseHTML: (element) =>
                        element.getAttribute('data-color') ||
                        element.style?.backgroundColor ||
                        null,
                    renderHTML: (attributes) =>
                        attributes.color
                            ? {
                                  'data-color': attributes.color,
                                  style: `background-color: ${attributes.color}`,
                              }
                            : {},
                },
            }
        },

        parseHTML() {
            return [
                {
                    // Outranks the bundled highlight, which claims every `mark` at the
                    // default priority of 50.
                    tag: 'mark[data-color]',
                    priority: 51,
                },
            ]
        },

        renderHTML({ HTMLAttributes }) {
            return [
                'mark',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
                0,
            ]
        },

        addCommands() {
            return {
                setTextBackground:
                    (color) =>
                    ({ commands }) =>
                        commands.setMark(this.name, { color }),

                unsetTextBackground:
                    () =>
                    ({ commands }) =>
                        commands.unsetMark(this.name),
            }
        },
    })
}
