/*
 * The language a passage is written in.
 *
 * A mark rather than a global attribute on the block, and that is the whole feature: WCAG
 * 3.1.2 is about a *passage*, which is usually a phrase inside a sentence. A `lang` on the
 * paragraph cannot say "these three words are French", and those three words are exactly
 * the case a screen reader gets wrong without it.
 *
 * The attribute needs nothing added to a sanitiser allow list: `lang` is safe by
 * specification, the same way `dir` is, so what is written here reaches the rendered page
 * untouched. It is still validated - being allowed through is not the same as being a
 * language.
 *
 * This package ships no bundler, so the file must stay free of `import` statements:
 * Filament loads it verbatim through a dynamic `import()`, and TipTap is read from the
 * global its own bundle publishes.
 */

/**
 * The part of BCP 47 anybody types. Mirrors `Languages::CODE` in PHP; both halves have to
 * agree, because one writes what the other reads.
 */
const CODE = /^[a-z]{2,8}(-[a-z0-9]{1,8})*$/

/**
 * Lowercased throughout. `lang` is case-insensitive by specification, so `fr-CA` and
 * `fr-ca` are one language - and kept apart they would be two, with a document stored
 * under one spelling lighting up no button for the other.
 */
export function languageCode(value) {
    const code = String(value ?? '')
        .trim()
        .toLowerCase()

    return CODE.test(code) ? code : null
}

export default () => {
    const tiptap = window.FilamentRichEditor?.tiptap?.core

    if (!tiptap) {
        console.error(
            'The advanced rich editor language extension needs window.FilamentRichEditor.tiptap, which Filament did not expose.',
        )

        return null
    }

    const { Mark, mergeAttributes } = tiptap

    return Mark.create({
        name: 'language',

        addOptions() {
            return {
                HTMLAttributes: {},
            }
        },

        addAttributes() {
            return {
                code: {
                    default: null,
                    parseHTML: (element) => languageCode(element.getAttribute('lang')),
                    renderHTML: (attributes) => {
                        const code = languageCode(attributes.code)

                        return code ? { lang: code } : {}
                    },
                },
            }
        },

        parseHTML() {
            return [
                {
                    tag: 'span[lang]',
                    // Only spans that carry something that is a language, so this rule
                    // never competes with Filament's own span based marks.
                    getAttrs: (element) =>
                        languageCode(element.getAttribute('lang')) ? {} : false,
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
            /**
             * Without a selection the run under the caret is meant, which is what somebody
             * expects when they click into a word they already marked and pick a different
             * language for it. The style picker reaches for `extendMarkRange` for the same
             * reason.
             */
            const range = (chain, editor) =>
                editor.state.selection.empty ? chain.extendMarkRange(this.name) : chain

            return {
                setLanguage:
                    (code) =>
                    ({ chain, editor }) => {
                        const value = languageCode(code)

                        return value
                            ? range(chain(), editor).setMark(this.name, { code: value }).run()
                            : false
                    },

                unsetLanguage:
                    () =>
                    ({ chain, editor }) =>
                        range(chain(), editor).unsetMark(this.name).run(),

                // One language at a time: picking the one the passage already carries takes
                // the marking back off, which is what a lit-up button pressed again has to
                // do.
                toggleLanguage:
                    (code) =>
                    ({ editor, commands }) =>
                        editor.isActive(this.name, { code: languageCode(code) })
                            ? commands.unsetLanguage()
                            : commands.setLanguage(code),
            }
        },
    })
}
