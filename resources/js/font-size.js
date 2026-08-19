/*
 * A TipTap mark that carries an explicit font size on a text selection.
 *
 * Written against the TipTap instance Filament exposes on
 * `window.FilamentRichEditor.tiptap`, so the package needs no bundler and shares the
 * editor's own ProseMirror instance.
 *
 * The size travels in the inline `style` attribute rather than a data attribute or a
 * class: Filament sanitises rich content before rendering it on a page and `style` is on
 * its allow list, while an attribute such as `data-font-size` would be stripped. The
 * `parseHTML` rule reads the same property back, so the mark round-trips through the
 * database and through the PHP renderer.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor font size extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Mark, mergeAttributes } = tiptap

    return Mark.create({
        name: 'fontSize',

        addOptions() {
            return {
                HTMLAttributes: {},
                min: 8,
                max: 96,
                step: 1,
                defaultSize: 16,
                unit: 'px',
            }
        },

        addAttributes() {
            return {
                size: {
                    default: null,
                    parseHTML: (element) => element.style?.fontSize || null,
                    renderHTML: (attributes) =>
                        attributes.size
                            ? { style: `font-size: ${attributes.size}` }
                            : {},
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'span',
                    // Only spans that actually carry a size, so this rule never competes
                    // with Filament's own span based marks.
                    getAttrs: (element) => (element.style?.fontSize ? {} : false),
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
            const clamp = (value) =>
                Math.min(this.options.max, Math.max(this.options.min, value))

            const toNumber = (value) => {
                const parsed = Number.parseFloat(value)

                return Number.isFinite(parsed) ? parsed : null
            }

            return {
                setFontSize:
                    (size) =>
                    ({ chain }) => {
                        const value = toNumber(size)

                        if (value === null) {
                            return chain().unsetMark(this.name).run()
                        }

                        return chain()
                            .setMark(this.name, {
                                size: `${clamp(value)}${this.options.unit}`,
                            })
                            .run()
                    },

                unsetFontSize:
                    () =>
                    ({ chain }) =>
                        chain().unsetMark(this.name).run(),

                /* Steps relative to whatever the cursor currently sits in. */
                adjustFontSize:
                    (delta) =>
                    ({ editor, chain }) => {
                        const current =
                            toNumber(editor.getAttributes(this.name)?.size) ??
                            this.options.defaultSize

                        return chain()
                            .setMark(this.name, {
                                size: `${clamp(current + delta)}${this.options.unit}`,
                            })
                            .run()
                    },
            }
        },
    })
}
