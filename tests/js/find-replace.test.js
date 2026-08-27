import { describe, expect, it } from 'vitest'
import {
    BREAK,
    descending,
    flatten,
    indexAfter,
    matchesIn,
    segmentsOf,
    segmentsToRanges,
    stepIndex,
    withinDoc,
} from '../../resources/js/find-replace.js'

/**
 * Finding and replacing.
 *
 * Everything worth testing here is a function over plain data, and that is the design
 * rather than a convenience: a hit that runs across `<strong>` is several text nodes in the
 * document and one hit on the page, so the search happens on a flat string and the results
 * are mapped back onto document positions afterwards. Both halves are pure, so both can be
 * asserted without a ProseMirror instance. What is left over - the decorations and the
 * commands - is wiring that only an editor can prove.
 *
 * A segment is one text node: where it starts in the document, and what it says. Between
 * two blocks sits a break, which is a segment with no position, and that is what stops a
 * search for "hello world" from matching the end of one paragraph and the start of the next.
 */

const segment = (from, text) => ({ from, text })

const brk = () => ({ from: null, text: BREAK })

describe('finding hits in the flat text', () => {
    it('finds every occurrence', () => {
        expect(matchesIn('one two one', 'one')).toEqual([
            { start: 0, end: 3 },
            { start: 8, end: 11 },
        ])
    })

    it('ignores case unless asked to mind it', () => {
        expect(matchesIn('Otto and otto', 'otto')).toHaveLength(2)
        expect(matchesIn('Otto and otto', 'otto', { caseSensitive: true })).toEqual([
            { start: 9, end: 13 },
        ])
    })

    it('matches inside a word until whole words are asked for', () => {
        expect(matchesIn('cat concatenate', 'cat')).toHaveLength(2)
        expect(matchesIn('cat concatenate', 'cat', { wholeWord: true })).toEqual([
            { start: 0, end: 3 },
        ])
    })

    it('counts letters outside ASCII as part of a word', () => {
        // `\b` would call the boundary between `Müller` and `s` a word end, so a whole word
        // search for `Mü` would match inside the name.
        expect(matchesIn('Müller Mü', 'Mü', { wholeWord: true })).toEqual([
            { start: 7, end: 9 },
        ])
    })

    it('reads the query as text, not as a pattern', () => {
        expect(matchesIn('a.b axb', 'a.b')).toEqual([{ start: 0, end: 3 }])
        expect(matchesIn('price (net)', '(net)')).toEqual([{ start: 6, end: 11 }])
    })

    it('has nothing to find without a query', () => {
        expect(matchesIn('one two', '')).toEqual([])
        expect(matchesIn('', 'one')).toEqual([])
    })

    it('does not run away on a query that could match nothing', () => {
        // A pattern able to match an empty string would otherwise return one hit per
        // character, or never finish at all.
        expect(matchesIn('aaa', ' '.trim())).toEqual([])
    })
})

describe('the flat text a search runs on', () => {
    it('joins the text nodes and separates the blocks', () => {
        expect(flatten([segment(1, 'hello'), brk(), segment(8, 'world')])).toBe(
            `hello${BREAK}world`,
        )
    })

    it('joins the runs of one paragraph without a seam, so a mark cannot hide a hit', () => {
        // `he<strong>ll</strong>o` is three text nodes and one word.
        const segments = [segment(1, 'he'), segment(3, 'll'), segment(5, 'o')]

        expect(matchesIn(flatten(segments), 'hello')).toEqual([{ start: 0, end: 5 }])
    })

    it('keeps a hit from running across two blocks', () => {
        const segments = [segment(1, 'hello'), brk(), segment(8, 'world')]

        expect(matchesIn(flatten(segments), 'hello world')).toEqual([])
    })
})

describe('mapping a hit back onto the document', () => {
    it('turns an offset into a position', () => {
        const segments = [segment(1, 'one two')]

        expect(segmentsToRanges(segments, [{ start: 4, end: 7 }])).toEqual([{ from: 5, to: 8 }])
    })

    it('spans a hit that runs across two text nodes', () => {
        const segments = [segment(1, 'he'), segment(3, 'll'), segment(5, 'o')]

        expect(segmentsToRanges(segments, [{ start: 0, end: 5 }])).toEqual([{ from: 1, to: 6 }])
    })

    it('counts the position from the block a node sits in, not from the one before it', () => {
        // The two paragraphs are not next to each other: `</p><p>` is two positions the
        // text does not have, which is exactly what makes the offset and the position
        // different numbers.
        const segments = [segment(1, 'hello'), brk(), segment(8, 'world')]

        expect(segmentsToRanges(segments, [{ start: 6, end: 11 }])).toEqual([{ from: 8, to: 13 }])
    })

    it('drops a hit that would start on a break', () => {
        const segments = [segment(1, 'hello'), brk(), segment(8, 'world')]

        expect(segmentsToRanges(segments, [{ start: 5, end: 6 }])).toEqual([])
    })
})

describe('walking the hits', () => {
    it('steps forward and wraps at the end', () => {
        expect(stepIndex(3, 0, 1)).toBe(1)
        expect(stepIndex(3, 2, 1)).toBe(0)
    })

    it('steps back and wraps at the start', () => {
        expect(stepIndex(3, 1, -1)).toBe(0)
        expect(stepIndex(3, 0, -1)).toBe(2)
    })

    it('has nowhere to step without hits', () => {
        expect(stepIndex(0, -1, 1)).toBe(-1)
    })

    it('starts at the first hit after the caret, so the bar opens where the reader is', () => {
        const ranges = [
            { from: 3, to: 6 },
            { from: 20, to: 23 },
        ]

        expect(indexAfter(ranges, 10)).toBe(1)
        expect(indexAfter(ranges, 1)).toBe(0)
    })

    it('wraps to the first hit when the caret sits past the last one', () => {
        expect(indexAfter([{ from: 3, to: 6 }], 40)).toBe(0)
    })

    it('has no hit to start on when there are none', () => {
        expect(indexAfter([], 10)).toBe(-1)
    })
})

describe('replacing every hit', () => {
    it('works from the back, so replacing one does not move the next', () => {
        const ranges = [
            { from: 1, to: 4 },
            { from: 10, to: 13 },
            { from: 20, to: 23 },
        ]

        expect(descending(ranges)).toEqual([
            { from: 20, to: 23 },
            { from: 10, to: 13 },
            { from: 1, to: 4 },
        ])
    })

    it('leaves the list it was given alone', () => {
        const ranges = [
            { from: 1, to: 4 },
            { from: 10, to: 13 },
        ]

        descending(ranges)

        expect(ranges[0]).toEqual({ from: 1, to: 4 })
    })
})

describe('reading the segments off a document', () => {
    /*
     * A stand-in for a ProseMirror document, faithful in the two things this function
     * depends on: `descendants` hands over every node with the position it sits at and the
     * node holding it, and positions count an opening token, a closing token and a leaf as
     * one each while text counts its own length. Building it here rather than booting an
     * editor keeps the arithmetic visible - the whole point of these assertions is that a
     * position is not an offset.
     */
    const text = (value) => ({ isText: true, isLeaf: true, text: value })

    const leaf = () => ({ isText: false, isLeaf: true })

    const doc = (blocks) => {
        const visits = []

        let pos = 0

        for (const children of blocks) {
            const parent = { isText: false, isLeaf: false }

            pos += 1

            for (const child of children) {
                visits.push([child, pos, parent])
                pos += child.isText ? child.text.length : 1
            }

            pos += 1
        }

        return {
            descendants(callback) {
                for (const visit of visits) {
                    callback(...visit)
                }
            },
        }
    }

    it('lays the runs of one paragraph end to end, so a mark leaves no seam', () => {
        // `he<strong>ll</strong>o`
        expect(segmentsOf(doc([[text('he'), text('ll'), text('o')]]))).toEqual([
            { from: 1, text: 'he' },
            { from: 3, text: 'll' },
            { from: 5, text: 'o' },
        ])
    })

    it('puts a break between two blocks, and counts their tokens', () => {
        expect(segmentsOf(doc([[text('hello')], [text('world')]]))).toEqual([
            { from: 1, text: 'hello' },
            { from: null, text: BREAK },
            { from: 8, text: 'world' },
        ])
    })

    it('puts a break either side of a leaf, so a hit does not run through an image', () => {
        expect(segmentsOf(doc([[text('one'), leaf(), text('two')]]))).toEqual([
            { from: 1, text: 'one' },
            { from: null, text: BREAK },
            { from: 5, text: 'two' },
        ])
    })

    it('opens with no break, so nothing is prepended to the first hit', () => {
        expect(segmentsOf(doc([[text('one')]]))).toEqual([{ from: 1, text: 'one' }])
    })

    it('has nothing to say about an empty document', () => {
        expect(segmentsOf(doc([[]]))).toEqual([])
    })
})

describe('hits that the document has outgrown', () => {
    /*
     * Between a replacement being applied and the search being run again, the hits belong
     * to the document as it was. ProseMirror redraws the decorations in between, against
     * the document as it now is, and a position past its end is not a hit that is drawn
     * wrong - it throws, and takes the editor with it.
     */
    it('keeps the hits that still fit', () => {
        expect(withinDoc([{ from: 1, to: 4 }], 20)).toEqual([{ from: 1, to: 4 }])
    })

    it('drops a hit that ends past the document', () => {
        expect(withinDoc([{ from: 1, to: 4 }, { from: 18, to: 24 }], 20)).toEqual([
            { from: 1, to: 4 },
        ])
    })

    it('drops a hit that ends exactly on the end, only when it does not', () => {
        expect(withinDoc([{ from: 15, to: 20 }], 20)).toEqual([{ from: 15, to: 20 }])
        expect(withinDoc([{ from: 15, to: 21 }], 20)).toEqual([])
    })
})
