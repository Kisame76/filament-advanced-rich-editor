/*
 * The loading hint a picture carries, on the editor's side.
 *
 * Schema only. Filament's image node declares `id`, `width` and `height` on top of TipTap's
 * `src`, `alt` and `title` and nothing else, and TipTap drops what nothing declares: a hint
 * written on insert was gone before the dialog closed, and one already stored was gone the
 * first time the editor sent its document back. The PHP half needs nothing of this package -
 * Filament's `ImageExtension` declares `loading` there itself, which is why a record nobody
 * typed into kept it and one somebody touched did not.
 *
 * A GLOBAL attribute on the image node rather than a replacement for it, for the reasons
 * `image-float.js` gives: replacing the node would mean reproducing Filament's own image
 * extension, its resize node view and its attachment id handling.
 */

// Exported so it can be checked against `ImageAttributes::loadingHint()` in PHP: one half
// writes what the other reads, and the two have to agree on what a hint is. Anything else
// is dropped rather than kept - `loading` is on the sanitiser's allow list, so whatever is
// kept here reaches the page.
export const loadingHint = (value) => {
    const loading = typeof value === 'string' ? value.trim().toLowerCase() : null

    return ['lazy', 'eager'].includes(loading) ? loading : null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor image loading hint needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteImageLoading',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        loading: {
                            default: null,

                            parseHTML: (element) => loadingHint(element.getAttribute('loading')),

                            // Checked here too: a value handed to a command as data is
                            // never parsed on its way in.
                            renderHTML: (attributes) => {
                                const loading = loadingHint(attributes.loading)

                                return loading === null ? {} : { loading }
                            },
                        },
                    },
                },
            ]
        },
    })
}
