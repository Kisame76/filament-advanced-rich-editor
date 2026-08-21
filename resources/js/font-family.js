/*
 * A TipTap mark carrying a typeface on a text selection.
 *
 * Filament's editor build ships no `fontFamily` extension, so this is the package's own -
 * written against the TipTap instance on `window.FilamentRichEditor.tiptap`, like the font
 * size mark beside it, and for the same reason: no bundler, and the editor's own
 * ProseMirror instance rather than a second one.
 *
 * The family travels in the inline `style` attribute, which Filament's sanitiser allows,
 * where a `data-font` would be stripped. The PHP half of the mark reads the same property
 * back, so a typeface survives the save and the server side renderer.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor font family extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Mark, mergeAttributes } = tiptap

    // A font stack and nothing else: this value is written into an attribute nothing
    // downstream validates. The PHP half applies the same rule to what comes back in.
    const clean = (family) =>
        typeof family === 'string' &&
        family.length <= 200 &&
        /^[\p{L}\p{N} ,'"\-_]+$/u.test(family.trim())
            ? family.trim()
            : null

    return Mark.create({
        name: 'fontFamily',

        addOptions() {
            return { HTMLAttributes: {} }
        },

        addAttributes() {
            return {
                family: {
                    default: null,
                    parseHTML: (element) => clean(element.style?.fontFamily),
                    renderHTML: (attributes) => {
                        const family = clean(attributes.family)

                        return family ? { style: `font-family: ${family}` } : {}
                    },
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'span',
                    // Spans without a family are left to whichever mark does claim them.
                    getAttrs: (element) => (clean(element.style?.fontFamily) ? {} : false),
                },
            ]
        },

        renderHTML({ HTMLAttributes }) {
            return ['span', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
        },

        addCommands() {
            return {
                setFontFamily:
                    (family) =>
                    ({ commands }) =>
                        commands.setMark(this.name, { family }),

                unsetFontFamily:
                    () =>
                    ({ commands }) =>
                        commands.unsetMark(this.name),
            }
        },
    })
}
