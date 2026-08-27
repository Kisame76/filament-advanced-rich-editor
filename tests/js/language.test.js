import { describe, expect, it } from 'vitest'
import { languageCode } from '../../resources/js/language.js'

/**
 * The one function the language mark is built on: what a language tag is allowed to be.
 *
 * It mirrors `Languages::code()` in PHP, and the two have to agree - one half writes what
 * the other reads. The ProseMirror wiring around it is declarative and is asserted from
 * PHP, through the round trip a save actually goes through.
 */

describe('the tag a passage may be marked with', () => {
    it('accepts a primary subtag on its own', () => {
        expect(languageCode('fr')).toBe('fr')
        expect(languageCode('gsw')).toBe('gsw')
    })

    it('accepts the subtags after it', () => {
        expect(languageCode('fr-CA')).toBe('fr-ca')
        expect(languageCode('zh-Hant-HK')).toBe('zh-hant-hk')
    })

    it('folds case, because lang is case-insensitive by specification', () => {
        // Kept apart, `fr-CA` and `fr-ca` would be two languages - and a passage stored
        // under one spelling would light up no button for the other.
        expect(languageCode('  FR ')).toBe('fr')
    })

    it('refuses anything that could not be a tag or an argument', () => {
        // A code is interpolated into a tool name and into the command a button runs.
        expect(languageCode("fr') || alert('")).toBeNull()
        expect(languageCode('f')).toBeNull()
        expect(languageCode('1x')).toBeNull()
        expect(languageCode('fr_CA')).toBeNull()
        expect(languageCode('de de')).toBeNull()
        expect(languageCode('')).toBeNull()
        expect(languageCode(null)).toBeNull()
    })
})
