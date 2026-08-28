/**
 * Typing straight quotes and getting the ones the language uses.
 *
 * Filament loads this verbatim through a dynamic `import()`, so there are no `import`
 * statements here and TipTap is read from the global the editor publishes. See
 * `task-list.js` for the whole reasoning.
 *
 * Nothing is stored beyond the characters themselves: a typographic quote is a character,
 * so it travels through the sanitiser, the save and `RichContentRenderer` like any letter.
 * No node, no mark, no PHP extension - the same deal the characters picker has.
 *
 * TipTap's own Typography extension is not reachable from here, and would not have been
 * enough if it were: it writes the English pair. German opens with `„` and closes with `“`,
 * which is the shape English uses to *open*, and its dash is the shorter one. Which
 * characters are correct is a question about a language, so the table is the feature and the
 * input rules are the plumbing.
 */

/**
 * What each language opens and closes with, and which dash it sets.
 *
 * Shipped for the two languages this package itself is translated into. Anything else falls
 * back to `default`, which is the English convention - the same one every editor applies
 * today - and a project whose language is not here says so in the config rather than
 * accepting a guess. Naming a language's typography is a claim about that language, and
 * three of them written from memory would be three claims nobody checked.
 */
export const TYPOGRAPHY = {
    de: {
        open: '„',
        close: '“',
        openSingle: '‚',
        closeSingle: '‘',
        // Halbgeviertstrich. The long one is an English convention and reads as a mistake in
        // German text.
        dash: '–',
    },
    en: {
        open: '“',
        close: '”',
        openSingle: '‘',
        closeSingle: '’',
        dash: '—',
    },
}

TYPOGRAPHY.default = TYPOGRAPHY.en

/** Every language writes an elision with the same mark, whichever quotes it uses. */
const APOSTROPHE = '’'

const ELLIPSIS = '…'

// Where a quotation may begin: nothing before it, whitespace, or something that opens.
const BEFORE_AN_OPENING = /[\s([{<„“‘‚«‹—–]$/u

// What a single quote follows when it is shortening a word rather than closing a quotation:
// `geht's`, `don't`, `the 90's`, `'89`.
const A_SHORTENED_WORD = /[\p{L}\p{N}]$/u

/**
 * The table for a locale, however it is spelled. `app()->getLocale()` answers `de_DE` as
 * readily as `de`, and a browser says `de-AT`; only the language in front matters here.
 */
export function typographyFor(language) {
    if (! language) {
        return TYPOGRAPHY.default
    }

    const tag = String(language).toLowerCase().split(/[-_]/)[0]

    return TYPOGRAPHY[tag] ?? TYPOGRAPHY.default
}

export function doubleQuoteFor(before, table) {
    return BEFORE_AN_OPENING.test(before) || before === '' ? table.open : table.close
}

/**
 * Three answers rather than two, and the third is the reason this reads the whole line rather
 * than the character in front of the caret.
 *
 * German closes a single quotation with `‘` and apostrophises with `’`, so `geht's` and a
 * closed quotation are not the same character - and both follow a letter. The character
 * before decides nothing here. What does is whether a single quotation is still open: if one
 * was opened earlier in this text and never closed, the quote being typed closes it;
 * otherwise a letter in front means a word is being shortened.
 *
 * English never notices, because its closing single quote and its apostrophe are the same
 * character. That is exactly why a rule written against English gets German wrong.
 */
export function singleQuoteFor(before, table) {
    if (hasAnUnclosedQuotation(before, table)) {
        return table.closeSingle
    }

    if (A_SHORTENED_WORD.test(before)) {
        return APOSTROPHE
    }

    return BEFORE_AN_OPENING.test(before) || before === '' ? table.openSingle : table.closeSingle
}

function hasAnUnclosedQuotation(before, table) {
    let depth = 0

    for (const character of before) {
        if (character === table.openSingle) {
            depth++
        } else if (character === table.closeSingle && depth > 0) {
            depth--
        }
    }

    return depth > 0
}

/*
 * The extension.
 *
 * Everything above is a function over plain data and is tested as such. What follows is the
 * part only an editor can prove: reading what stands before the caret and handing the rules
 * to TipTap.
 */

const SETTINGS = 'arteTypography'

export default () => {
    const { Extension, InputRule } = window.FilamentRichEditor.tiptap.core

    return Extension.create({
        name: 'arteTypography',

        addStorage() {
            // The table PHP resolved for this field. Until `onCreate()` runs it is the
            // fallback, which matters because `addInputRules()` is called first: the rules
            // are built once and must therefore read the table when they fire rather than
            // when they are made.
            return { table: TYPOGRAPHY.default }
        },

        onCreate() {
            const element = this.editor.options.element

            try {
                const settings = JSON.parse(element.dataset[SETTINGS] ?? 'null')

                if (settings) {
                    this.storage.table = settings
                }
            } catch (error) {
                console.error('The advanced rich editor could not read its typography settings:', error)
            }
        },

        addInputRules() {
            // The runner hands the whole text before the caret to the pattern, which is what
            // lets the decision read the line rather than the character in front of it. It
            // also steps aside inside `code` and never runs on a paste - three of the four
            // things this feature was expected to have to solve turn out to be the
            // framework's problem rather than ours.
            const quote = (character, choose) =>
                new InputRule({
                    find: new RegExp(`(.*)${character}$`, 'u'),
                    handler: ({ state, range, match }) => {
                        // Inserted at the caret, replacing nothing. The typed character is
                        // not in the document yet - `range.to` is where it would go - so a
                        // range ending there and one character wide covers the character
                        // *before* it, and the rule would eat whatever was typed last. It ate
                        // a full stop before this was written down.
                        //
                        // Nothing else may be rewritten either. The pattern spans the line
                        // because the decision needs to read it, but writing that span back
                        // would put plain text where marked text was: a bold word in the
                        // sentence would come back unbold for having a quote typed after it.
                        state.tr.insertText(
                            choose(match[1], this.storage.table),
                            range.to,
                            range.to,
                        )
                    },
                })

            const replaceWith = (find, character) =>
                new InputRule({
                    find,
                    handler: ({ state, range }) => {
                        state.tr.insertText(character(), range.from, range.to)
                    },
                })

            return [
                quote('"', doubleQuoteFor),
                quote("'", singleQuoteFor),
                replaceWith(/\.\.\.$/, () => ELLIPSIS),
                // Two hyphens with something in front of them, so a line opening with `--`
                // is left alone.
                replaceWith(/(?<=\S)--$/u, () => this.storage.table.dash),
            ]
        },
    })
}
