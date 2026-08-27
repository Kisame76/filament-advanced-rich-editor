import { describe, expect, it } from 'vitest'
import { calloutVariant, variantFromClassList } from '../../resources/js/callout.js'

/**
 * The two functions the callout extension is built on.
 *
 * Both of them decide what a piece of text is allowed to become: one guards the name that
 * ends up in a class and in a command argument, the other reads that name back out of the
 * markup a rendered page carries. The ProseMirror wiring around them is declarative and is
 * asserted from PHP, through the round trip a save actually goes through.
 *
 * `calloutVariant` mirrors `Callouts::name()` in PHP. The two have to agree: one half
 * writes what the other reads.
 */

describe('the name a kind of callout is allowed to have', () => {
    it('accepts a lowercase word', () => {
        expect(calloutVariant('warning')).toBe('warning')
    })

    it('accepts a hyphenated one, because a project may name its own', () => {
        expect(calloutVariant('legal-notice')).toBe('legal-notice')
    })

    it('forgives case and surrounding space, which name the same box', () => {
        expect(calloutVariant('  Tip ')).toBe('tip')
    })

    it('refuses anything that could not be a class or an argument', () => {
        // A variant is interpolated into a class name and into the command a button runs.
        // Names that do not fit are refused rather than escaped.
        expect(calloutVariant("note') || alert('")).toBeNull()
        expect(calloutVariant('9lives')).toBeNull()
        expect(calloutVariant('legal notice')).toBeNull()
        expect(calloutVariant('')).toBeNull()
        expect(calloutVariant(null)).toBeNull()
        expect(calloutVariant(undefined)).toBeNull()
    })
})

describe('reading the kind back out of the markup', () => {
    it('finds it among the other classes', () => {
        expect(variantFromClassList('fi-arte-callout fi-arte-callout-danger')).toBe('danger')
    })

    it('does not care what order they are in, or what else is there', () => {
        expect(variantFromClassList('prose fi-arte-callout-tip fi-arte-callout mine')).toBe('tip')
    })

    it('answers null for a callout that only says it is one', () => {
        // Which is what the node then draws as the default kind: a box in the wrong colour
        // beats a box that is not drawn at all.
        expect(variantFromClassList('fi-arte-callout')).toBeNull()
        expect(variantFromClassList('')).toBeNull()
        expect(variantFromClassList(null)).toBeNull()
    })

    it('refuses a prefixed class whose remainder could not be a name', () => {
        expect(variantFromClassList('fi-arte-callout-')).toBeNull()
        expect(variantFromClassList('fi-arte-callout-9lives')).toBeNull()
    })

    it('reads a capitalised class as the kind it names', () => {
        // The same forgiveness the name itself gets: nothing downstream can tell
        // `fi-arte-callout-Note` and `fi-arte-callout-note` apart as intentions.
        expect(variantFromClassList('fi-arte-callout-Note')).toBe('note')
    })
})
