/*
 * The named style a piece of text carries.
 *
 * The inline twin of `arteBlockStyle`, and the same division of labour: the key travels in
 * `data-style`, the classes are added in PHP when the document is stored and when it is
 * rendered. See `block-style.js` for why the classes are deliberately not written here.
 *
 * A mark rather than a global attribute, because a selection is not a node. The attribute
 * is called `name` and needs no prefix: it lives inside this mark's own attributes, where
 * nothing else can collide with it.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor style extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Mark, mergeAttributes } = tiptap

    return Mark.create({
        name: 'styleClass',

        addOptions() {
            return {
                HTMLAttributes: {},
            }
        },

        addAttributes() {
            return {
                name: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('data-style') || null,
                    renderHTML: (attributes) =>
                        attributes.name ? { 'data-style': attributes.name } : {},
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'span[data-style]',
                    // Only spans that actually carry a style, so this rule never competes
                    // with Filament's own span based marks.
                    getAttrs: (element) =>
                        element.getAttribute('data-style') ? {} : false,
                },
            ]
        },

        renderHTML({ HTMLAttributes }) {
            return [
                'span',
                mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
                0,
            ]
        },

        addCommands() {
            return {
                setStyleClass:
                    (name) =>
                    ({ commands }) =>
                        commands.setMark(this.name, { name }),

                unsetStyleClass:
                    () =>
                    ({ commands }) =>
                        commands.unsetMark(this.name),

                // One style at a time, the same as the block half: picking the one the text
                // already has takes it back off.
                toggleStyleClass:
                    (name) =>
                    ({ editor, commands }) =>
                        editor.isActive(this.name, { name })
                            ? commands.unsetStyleClass()
                            : commands.setStyleClass(name),
            }
        },
    })
}
