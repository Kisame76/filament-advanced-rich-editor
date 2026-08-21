/*
 * The caption an image carries, on the editor's side.
 *
 * Two halves. The attribute keeps the text through the schema: TipTap drops what nothing
 * declares, so without this a caption typed into the panel is gone before the record is
 * saved. And the mirror below copies it onto the box Filament draws around a resizable
 * image, so the stylesheet can show it under the picture - `attr()` reads the element's own
 * attributes, and an `<img>` is void and cannot carry a `::after` of its own.
 *
 * What is stored is `data-caption` on the image, not a `<figure>` around it. The structure
 * is not something an attribute can build, and rebuilding Filament's image node to get one
 * would mean owning its resizing, its uploads and its node view for the sake of one line of
 * text. The figure is built in PHP when the page is rendered.
 */

const WRAPPER = '[data-resize-wrapper]'
const MIRROR = 'data-arte-caption'

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core
    const pmState = window.FilamentRichEditor?.tiptap?.pmState

    if (!tiptap || !pmState) {
        console.error(
            'The advanced rich editor image caption extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap
    const { Plugin, PluginKey } = pmState

    const normalise = (value) => {
        const caption = typeof value === 'string' ? value.trim() : ''

        return caption === '' ? null : caption
    }

    /**
     * Copies each image's caption onto the box drawn around it, and clears the ones whose
     * image no longer has any. Runs on every editor update, which is also when an image is
     * inserted, replaced or deleted.
     */
    const mirror = (element) => {
        if (!element) {
            return
        }

        for (const box of element.querySelectorAll(`${WRAPPER}[${MIRROR}]`)) {
            if (!box.querySelector('img[data-caption]')) {
                box.removeAttribute(MIRROR)
            }
        }

        for (const image of element.querySelectorAll('img[data-caption]')) {
            const caption = normalise(image.getAttribute('data-caption'))
            const box = image.closest(WRAPPER)

            if (!box) {
                continue
            }

            if (caption === null) {
                box.removeAttribute(MIRROR)
            } else if (box.getAttribute(MIRROR) !== caption) {
                box.setAttribute(MIRROR, caption)
            }
        }
    }

    return Extension.create({
        name: 'arteImageCaption',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        caption: {
                            default: null,
                            parseHTML: (element) => normalise(element.getAttribute('data-caption')),
                            renderHTML: (attributes) => {
                                const caption = normalise(attributes.caption)

                                return caption === null ? {} : { 'data-caption': caption }
                            },
                        },
                    },
                },
            ]
        },

        addProseMirrorPlugins() {
            const editor = this.editor

            return [
                new Plugin({
                    key: new PluginKey('arteImageCaptionMirror'),
                    view: () => ({
                        update: () => mirror(editor.options.element),
                    }),
                }),
            ]
        },
    })
}
