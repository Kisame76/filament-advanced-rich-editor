/*
 * The address a picture points at.
 *
 * Schema only. There is no command here and no anchor either: what the editor holds is two
 * attributes on the image node, and the `<a>` around it is built when the page is rendered,
 * by the PHP half. That is the same bargain the caption makes, and it is made for the same
 * reason - an attribute cannot build a structure, and rebuilding Filament's image node to
 * get one would mean owning its resizing, its uploads and its node view for the sake of a
 * link.
 *
 * Which means a linked picture is not clickable inside the editor, and should not be:
 * clicking a picture there selects it so it can be resized, turned, placed or described.
 *
 * The writing is done by the dialog, through `updateAttributes` on a Livewire round trip,
 * the same way the alt text and the media browser write.
 */
// The schemes a picture may point at, and the reasoning behind the list is in the PHP
// half: `javascript:` and `data:` are refused rather than escaped, because there is no
// correct escaping for "this was meant to be an address".
//
// Checked here as well as there because a document can be edited in the source view and
// saved without ever passing through the dialog that asks for the address.
export const SCHEMES = ['http', 'https', 'mailto', 'tel']

// Whitespace and control characters come out before the scheme is read: `java\nscript:`
// is the oldest trick against a check that reads the string as it arrives, because a
// browser strips them before resolving an address, so a check that does not strip them
// first is reading a different string than the browser will.
const withoutControlCharacters = (href) => href.replace(/[\u0000-\u0020\u007F]/g, '')

export const imageHref = (value) => {
    if (typeof value !== 'string') {
        return null
    }

    const href = value.trim()

    if (href === '') {
        return null
    }

    const colon = href.indexOf(':')
    const slash = href.indexOf('/')

    // No scheme at all: a path, a fragment or a query, which a browser resolves against
    // the page it is on. The colon is looked for before the first slash on purpose -
    // `/a:b` is a path with a colon in it, `javascript:alert(1)` is not a path at all.
    if (colon === -1 || (slash !== -1 && slash < colon)) {
        return withoutControlCharacters(href)
    }

    return SCHEMES.includes(href.slice(0, colon).toLowerCase())
        ? withoutControlCharacters(href)
        : null
}


export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor image link needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    return Extension.create({
        name: 'arteImageLink',

        addGlobalAttributes() {
            return [
                {
                    types: ['image'],
                    attributes: {
                        href: {
                            default: null,

                            parseHTML: (element) => imageHref(element.getAttribute('data-href')),

                            renderHTML: (attributes) => {
                                const href = imageHref(attributes.href)

                                return href === null ? {} : { 'data-href': href }
                            },
                        },

                        // Beside the address rather than folded into it: a link that opens in
                        // a new tab is the same link, and clearing the tab setting is not
                        // clearing the link.
                        hrefNewTab: {
                            default: false,

                            parseHTML: (element) => element.getAttribute('data-href-new-tab') === 'true',

                            renderHTML: (attributes) =>
                                attributes.hrefNewTab === true ? { 'data-href-new-tab': 'true' } : {},
                        },
                    },
                },
            ]
        },
    })
}
