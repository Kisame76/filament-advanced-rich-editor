import { describe, expect, it } from 'vitest'
import {
    TYPOGRAPHY,
    doubleQuoteFor,
    singleQuoteFor,
    typographyFor,
} from '../../resources/js/typography.js'

/**
 * Typing straight quotes and getting the ones the language actually uses.
 *
 * The whole point of this file is that none of it is universal. German opens with `„` and
 * closes with `“` - which is the shape English uses to *open* - and its dash is the shorter
 * one. An editor that hard-codes the English pair writes wrong German, and TipTap's own
 * Typography extension does exactly that, which is why it would not have been enough even
 * if this package could reach it.
 *
 * Everything here is a function over a string and a table, so the decisions can be asserted
 * without an editor: which of the two quotes a position calls for, and which table a locale
 * resolves to.
 */

const de = TYPOGRAPHY.de
const en = TYPOGRAPHY.en

describe('picking the table for a locale', () => {
    it('knows the two languages the package itself speaks', () => {
        expect(typographyFor('de')).toBe(de)
        expect(typographyFor('en')).toBe(en)
    })

    it('reads a region off a tag rather than failing on it', () => {
        // `app()->getLocale()` answers `de_DE` as readily as `de`, and a browser says `de-AT`.
        expect(typographyFor('de_DE')).toBe(de)
        expect(typographyFor('de-AT')).toBe(de)
        expect(typographyFor('EN_GB')).toBe(en)
    })

    it('falls back rather than guessing at a language it was never told about', () => {
        expect(typographyFor('pl')).toBe(TYPOGRAPHY.default)
        expect(typographyFor(null)).toBe(TYPOGRAPHY.default)
        expect(typographyFor('')).toBe(TYPOGRAPHY.default)
    })

    it('opens and closes differently in the two languages', () => {
        // The one assertion that says why this is not a single pair of characters.
        expect(de.open).toBe('„')
        expect(de.close).toBe('“')
        expect(en.open).toBe('“')
        expect(en.close).toBe('”')

        // And German's opening quote is not English's, but German's *closing* one is.
        expect(de.close).toBe(en.open)
    })

    it('uses the dash the language uses', () => {
        expect(de.dash).toBe('–')
        expect(en.dash).toBe('—')
    })
})

describe('which double quote a position calls for', () => {
    it('opens at the start of a line', () => {
        expect(doubleQuoteFor('', de)).toBe(de.open)
    })

    it('opens after a space', () => {
        expect(doubleQuoteFor('er sagte ', de)).toBe(de.open)
    })

    it('opens after an opening bracket, which is not a word', () => {
        expect(doubleQuoteFor('(', de)).toBe(de.open)
        expect(doubleQuoteFor('[', en)).toBe(en.open)
    })

    it('closes after a word', () => {
        expect(doubleQuoteFor('hallo', de)).toBe(de.close)
    })

    it('closes after punctuation, because the sentence ended inside the quote', () => {
        expect(doubleQuoteFor('hallo.', de)).toBe(de.close)
        expect(doubleQuoteFor('wirklich?', en)).toBe(en.close)
    })
})

describe('which single quote a position calls for', () => {
    it('opens and closes like the double one', () => {
        expect(singleQuoteFor('', de)).toBe(de.openSingle)
        expect(singleQuoteFor('wort ', de)).toBe(de.openSingle)
        expect(singleQuoteFor('‚wort', de)).toBe(de.closeSingle)
    })

    it('writes an apostrophe after a letter rather than a closing quote', () => {
        // The case that makes this three answers rather than two. German closes a single
        // quotation with `‘` and apostrophises with `’`, so `geht's` and a closed
        // quotation are not the same character - and getting it wrong is invisible until it
        // is printed.
        expect(singleQuoteFor('geht', de)).toBe('’')
        expect(singleQuoteFor('don', en)).toBe('’')
    })

    it('writes an apostrophe after a digit too', () => {
        // Decades and abbreviated years: `the 90's`, `'89`.
        expect(singleQuoteFor('90', en)).toBe('’')
    })

    it('still closes after punctuation, where no word is being shortened', () => {
        expect(singleQuoteFor('wort.', de)).toBe(de.closeSingle)
    })
})

describe('the case that needs more than the character in front', () => {
    it('closes a German quotation rather than apostrophising it', () => {
        // `‚wort` then `'`: a letter precedes, which on its own says "apostrophe" - but a
        // single quotation is open, so this closes it. Both characters follow a letter and
        // only the line as a whole tells them apart.
        expect(singleQuoteFor('er sagte ‚wort', de)).toBe(de.closeSingle)
    })

    it('apostrophises once that quotation has been closed again', () => {
        expect(singleQuoteFor('er sagte ‚wort‘ und es geht', de)).toBe('’')
    })

    it('does not mistake an apostrophe earlier in the line for an open quotation', () => {
        expect(singleQuoteFor('geht’s und dann wort', de)).toBe('’')
    })
})
