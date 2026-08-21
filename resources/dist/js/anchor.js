/*
 * The `id` on a heading, on the editor's side.
 *
 * TipTap's heading declares `level` and nothing else, so an anchor typed into the source
 * code view - or carried in by pasting markup - is dropped the moment the document is
 * parsed, and the link pointing at it stops working on the next save. The PHP half of this
 * package declares the same attribute for rendering; this is the half that keeps a
 * hand-written anchor alive while the document is being edited.
 *
 * A global attribute rather than a redefined heading node, so Filament's own node keeps
 * its definition.
 *
 * Only headings carry one. Every other block is addressable through the heading above it,
 * and an `id` on a paragraph is a promise the editor cannot keep stable while the text
 * around it is rewritten.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor anchor extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    // Mirrors the PHP half: an id with a space in it survives the sanitiser and is still
    // not a fragment any browser will jump to, so it is dropped rather than stored.
    const ID_PATTERN = /^[A-Za-z0-9_\-.:]+$/

    const normalise = (value) => {
        const id = typeof value === 'string' ? value.trim() : ''

        return id && ID_PATTERN.test(id) ? id : null
    }

    return Extension.create({
        name: 'arteAnchor',

        addOptions() {
            return {
                types: ['heading'],
            }
        },

        addGlobalAttributes() {
            return [
                {
                    types: this.options.types,
                    attributes: {
                        id: {
                            default: null,
                            parseHTML: (element) =>
                                normalise(element.getAttribute('id')),
                            renderHTML: (attributes) => {
                                const id = normalise(attributes.id)

                                return id ? { id } : {}
                            },
                        },
                    },
                },
            ]
        },
    })
}
