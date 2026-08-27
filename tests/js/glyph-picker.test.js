import { describe, expect, it } from 'vitest'
import { matches, search } from '../../resources/js/glyph-picker.js'
import characters from '../../resources/js/character-data.js'

/**
 * The searching half of the popup the emoji and special character pickers share.
 *
 * What is tested here is what decides which rows a query paints. The popup around it is
 * DOM assembly with no branches worth pinning, and it is checked by using it.
 */

describe('matching a name against a query', () => {
    it('wants every word, in any order', () => {
        expect(matches('grinning cat face', ['cat', 'face'])).toBe(true)
        expect(matches('grinning cat face', ['face', 'cat'])).toBe(true)
        expect(matches('grinning cat face', ['cat', 'dog'])).toBe(false)
    })

    it('matches inside a word, so "dash" finds the en dash', () => {
        expect(matches('en dash', ['das'])).toBe(true)
    })
})

describe('searching the groups', () => {
    const groups = [
        ['one', [['a', 'alpha letter'], ['b', 'beta letter']]],
        ['two', [['c', 'gamma letter']]],
    ]

    it('answers null for an empty query, which means "paint the tab instead"', () => {
        expect(search(groups, '')).toBeNull()
        expect(search(groups, '   ')).toBeNull()
        expect(search(groups, null)).toBeNull()
    })

    it('looks across every group rather than only the open one', () => {
        expect(search(groups, 'letter').map(([value]) => value)).toEqual(['a', 'b', 'c'])
        expect(search(groups, 'gamma').map(([value]) => value)).toEqual(['c'])
    })

    it('stops at the limit, so a single letter does not paint the whole list', () => {
        expect(search(groups, 'letter', 2)).toHaveLength(2)
    })
})

describe('the shipped character list', () => {
    it('names every group the picker draws a tab for', () => {
        // Mirrors `CharactersPlugin::TABS` minus `recent`, which is the reader's own
        // history rather than a group of the list.
        expect(characters.map(([group]) => group)).toEqual([
            'punctuation', 'currency', 'math', 'arrows', 'symbols', 'latin', 'greek',
        ])
    })

    it('carries no character twice', () => {
        // A repeat would be two buttons doing the same thing, and the recent tab looks a
        // character up by itself to find the name it is drawn with.
        const all = characters.flatMap(([, entries]) => entries.map(([value]) => value))

        expect(new Set(all).size).toBe(all.length)
    })

    it('gives every row a name to search on', () => {
        for (const [, entries] of characters) {
            for (const [value, name] of entries) {
                expect(typeof name, `name for ${JSON.stringify(value)}`).toBe('string')
                expect(name.length).toBeGreaterThan(0)
            }
        }
    })

    it('draws a stand-in for the character that would draw nothing', () => {
        // A non-breaking space is worth offering and a blank button is one nobody can aim
        // at, which is what the optional third element is for.
        const nbsp = characters
            .flatMap(([, entries]) => entries)
            .find(([value]) => value === ' ')

        expect(nbsp).toBeDefined()
        expect(nbsp[2]).toBe('␣')
    })
})
