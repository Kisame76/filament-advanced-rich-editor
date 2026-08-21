/*
 * The attributes a link in a published document carries, on the editor's side.
 *
 * Filament's link mark declares `href`, `target`, `rel` and `class`. TipTap drops every
 * attribute nothing declares while parsing, so without this the dialog would write a
 * `hreflang` that the editor forgets before the field is even saved - and the PHP half
 * would never see it.
 *
 * Added as GLOBAL attributes rather than by redefining the link mark: Filament's own mark
 * keeps its definition, its input rules and its paste handler, and the PHP half mirrors
 * this with the same mechanism.
 *
 * The values are validated on both sides of the round trip. `rel`, `hreflang` and
 * `referrerpolicy` all reach the page untouched by the sanitiser, so a policy that is not
 * one would be written out in full and quietly do nothing.
 */
export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor link attributes extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Extension } = tiptap

    const REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'origin',
        'origin-when-cross-origin',
        'same-origin',
        'strict-origin',
        'strict-origin-when-cross-origin',
        'unsafe-url',
    ]

    // Deliberately narrower than HTML allows: what this package generates is a slug, and
    // what a person types by hand ends up in a URL, where anything else has to be
    // percent-encoded to survive the trip.
    const ID_PATTERN = /^[A-Za-z0-9_\-.:]+$/

    const plain = (name) => ({
        default: null,
        parseHTML: (element) => element.getAttribute(name) || null,
        renderHTML: (attributes) =>
            attributes[name] ? { [name]: attributes[name] } : {},
    })

    const checked = (name, isValid) => ({
        default: null,
        parseHTML: (element) => {
            const value = element.getAttribute(name)?.trim()

            return value && isValid(value) ? value : null
        },
        renderHTML: (attributes) => {
            const value = attributes[name]?.trim?.()

            return value && isValid(value) ? { [name]: value } : {}
        },
    })

    return Extension.create({
        name: 'arteLinkAttributes',

        addGlobalAttributes() {
            return [
                {
                    types: ['link'],
                    attributes: {
                        hreflang: plain('hreflang'),
                        referrerpolicy: checked('referrerpolicy', (value) =>
                            REFERRER_POLICIES.includes(value.toLowerCase()),
                        ),
                        id: checked('id', (value) => ID_PATTERN.test(value)),
                    },
                },
            ]
        },
    })
}
