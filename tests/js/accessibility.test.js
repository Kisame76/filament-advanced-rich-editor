import { describe, expect, it } from 'vitest'
import {
    RULES,
    contrastRatio,
    findingsFor,
    isWeakLinkText,
    mergeRuns,
    sizeInPixels,
    parseColor,
    relativeLuminance,
    resolveColor,
} from '../../resources/js/accessibility.js'

/**
 * The accessibility check.
 *
 * The judging is separate from the looking, and this is the half that judges: a heading
 * sequence is a list of numbers, a contrast ratio is two colours, and a weak link is a
 * string and a list of phrases. None of it has ever seen a document, which is why all of it
 * can be argued about here rather than in a browser. What is left over is the walk that
 * writes the list down, and only an editor can prove that.
 *
 * The contrast numbers are not this file's opinion: they are WCAG's own worked examples,
 * so a change to the formula fails here rather than quietly making everything pass.
 */

const subject = (kind, extra = {}) => ({ kind, from: 1, to: 2, ...extra })

describe('reading a colour', () => {
    it('takes both lengths of hex and both spellings of rgb', () => {
        expect(parseColor('#fff')).toEqual({ r: 255, g: 255, b: 255 })
        expect(parseColor('#18181B')).toEqual({ r: 24, g: 24, b: 27 })
        expect(parseColor('rgb(220, 38, 38)')).toEqual({ r: 220, g: 38, b: 38 })
        expect(parseColor('rgb(220 38 38)')).toEqual({ r: 220, g: 38, b: 38 })
        expect(parseColor('rgba(220, 38, 38, 1)')).toEqual({ r: 220, g: 38, b: 38 })
    })

    it('refuses anything translucent rather than guessing at it', () => {
        // A ratio worked out from half a colour is not a cautious answer, it is a wrong
        // one - and a wrong finding is the finding that teaches people to stop reading.
        expect(parseColor('rgba(220, 38, 38, 0.5)')).toBeNull()
        expect(parseColor('rgb(220 38 38 / 50%)')).toBeNull()
    })

    it('refuses what it does not understand', () => {
        expect(parseColor('rebeccapurple')).toBeNull()
        expect(parseColor('var(--brand)')).toBeNull()
        expect(parseColor('#ff')).toBeNull()
        expect(parseColor(null)).toBeNull()
    })

    it('looks a palette name up, and takes a colour as it stands', () => {
        // Filament stores `data-color="ink"` rather than the colour, which is the right way
        // round and leaves the browser with a name it cannot measure.
        expect(resolveColor('ink', { ink: '#18181b' })).toEqual({ r: 24, g: 24, b: 27 })
        expect(resolveColor('#dc2626', { ink: '#18181b' })).toEqual({ r: 220, g: 38, b: 38 })
        expect(resolveColor('unlisted', {})).toBeNull()
    })
})

describe('the contrast between two colours', () => {
    const ratio = (one, two) => Math.round(contrastRatio(parseColor(one), parseColor(two)) * 100) / 100

    it('agrees with the numbers WCAG publishes', () => {
        expect(ratio('#000000', '#ffffff')).toBe(21)
        expect(ratio('#ffffff', '#ffffff')).toBe(1)
        // The darkest grey that still passes AA on white, and the lightest that does not.
        expect(ratio('#767676', '#ffffff')).toBeGreaterThanOrEqual(4.5)
        expect(ratio('#777777', '#ffffff')).toBeLessThan(4.5)
    })

    it('does not care which way round they are given', () => {
        expect(ratio('#000000', '#ffffff')).toBe(ratio('#ffffff', '#000000'))
    })

    it('weighs green far above blue, the way the eye does', () => {
        expect(relativeLuminance({ r: 0, g: 255, b: 0 }))
            .toBeGreaterThan(relativeLuminance({ r: 0, g: 0, b: 255 }))
    })

    it('has nothing to say where a colour could not be read', () => {
        expect(contrastRatio(null, { r: 0, g: 0, b: 0 })).toBeNull()
    })
})

describe('a link that says nothing', () => {
    const phrases = ['here', 'click here', 'read more', 'hier klicken']

    it('is the whole text and not part of it', () => {
        expect(isWeakLinkText('Click here', phrases)).toBe(true)
        expect(isWeakLinkText('CLICK HERE', phrases)).toBe(true)
        // Says what it is, so it is left alone: a check that is wrong about things people
        // did correctly is a check people switch off.
        expect(isWeakLinkText('click here for the report', phrases)).toBe(false)
        expect(isWeakLinkText('the report', phrases)).toBe(false)
    })

    it('sees past the punctuation and the spacing around it', () => {
        expect(isWeakLinkText('  read more…  ', phrases)).toBe(true)
        expect(isWeakLinkText('Read more.', phrases)).toBe(true)
    })

    it('answers in whatever language the list is in', () => {
        // "Click here" is a fact about English, not about the web: a list shipped in one
        // language finds nothing in another and calls the document fine.
        expect(isWeakLinkText('Hier klicken', phrases)).toBe(true)
    })

    it('says nothing about an empty text, which is a different finding', () => {
        expect(isWeakLinkText('', phrases)).toBe(false)
    })
})

describe('putting runs back together', () => {
    it('joins two touching runs that say the same thing', () => {
        // `click <strong>here</strong>` is two text nodes and one link, and judging them
        // apart would find a link reading "here" that nobody wrote.
        const merged = mergeRuns(
            [
                { from: 1, to: 7, href: '/a', text: 'click ' },
                { from: 7, to: 11, href: '/a', text: 'here' },
            ],
            (run) => run.href,
        )

        expect(merged).toEqual([{ from: 1, to: 11, href: '/a', text: 'click here' }])
    })

    it('keeps two runs apart when something sits between them', () => {
        expect(mergeRuns(
            [
                { from: 1, to: 5, href: '/a', text: 'one' },
                { from: 9, to: 13, href: '/a', text: 'two' },
            ],
            (run) => run.href,
        )).toHaveLength(2)
    })

    it('keeps two runs apart when they go to different places', () => {
        expect(mergeRuns(
            [
                { from: 1, to: 5, href: '/a', text: 'one' },
                { from: 5, to: 9, href: '/b', text: 'two' },
            ],
            (run) => run.href,
        )).toHaveLength(2)
    })
})

describe('the seven rules', () => {
    const rules = (findings) => findings.map((found) => found.rule)

    it('finds a picture nobody described', () => {
        expect(rules(findingsFor([subject('image', { alt: '', src: 'cat.png' })]))).toEqual(['missing_alt'])
        expect(rules(findingsFor([subject('image', { alt: '   ' })]))).toEqual(['missing_alt'])
        expect(findingsFor([subject('image', { alt: 'A cat asleep on a keyboard' })])).toEqual([])
    })

    it('says nothing about a picture that was marked as carrying nothing', () => {
        // The whole point of the mark. An empty alt on its own is indistinguishable from a
        // description somebody forgot, which is why it is reported; an empty alt beside
        // `role="presentation"` is a decision, and reporting a decision is how a check
        // teaches people to switch it off.
        expect(findingsFor([subject('image', { alt: '', decorative: true, src: 'divider.png' })])).toEqual([])
        expect(findingsFor([subject('image', { alt: '   ', decorative: true })])).toEqual([])
    })

    it('still asks for a description where the mark was taken off', () => {
        expect(rules(findingsFor([subject('image', { alt: '', decorative: false })]))).toEqual(['missing_alt'])
    })

    it('reports a link whose only content is a picture that says nothing', () => {
        // The mark tells a screen reader to skip the picture, and the picture is the whole
        // of the link - so the link is announced with no name at all. Each half is right on
        // its own, which is exactly why nothing else catches the pair.
        expect(rules(findingsFor([subject('image', { alt: '', decorative: true, href: '/somewhere' })])))
            .toEqual(['decorative_link'])
    })

    it('leaves a linked picture alone once it has a description', () => {
        // The alt text is what names the link, so there is nothing left to report.
        expect(findingsFor([subject('image', { alt: 'Eine Katze', decorative: false, href: '/somewhere' })]))
            .toEqual([])
    })

    it('says nothing about a decorative picture that is not a link', () => {
        expect(findingsFor([subject('image', { alt: '', decorative: true })])).toEqual([])
    })

    it('finds a link with nothing in it, and calls it that rather than weak', () => {
        // A link with no text also says nothing about where it goes, and one finding is the
        // useful number.
        expect(rules(findingsFor([subject('link', { text: ' ', href: '/a' })]))).toEqual(['empty_link'])
    })

    it('finds a link whose text says nothing', () => {
        const findings = findingsFor([subject('link', { text: 'read more', href: '/a' })], {
            weakPhrases: ['read more'],
        })

        expect(rules(findings)).toEqual(['weak_link_text'])
        expect(findings[0].text).toBe('read more')
    })

    it('finds a heading level jumped over, and not the level a document starts at', () => {
        // An article whose page already carries the `<h1>` starts at two, and one inside a
        // card may reasonably start at three. What no document can defend is two, then four.
        const skipped = findingsFor([
            subject('heading', { level: 2, text: 'Two' }),
            subject('heading', { level: 4, text: 'Four' }),
        ])

        expect(rules(skipped)).toEqual(['skipped_heading'])
        expect(skipped[0].text).toBe('Four')

        expect(findingsFor([subject('heading', { level: 3, text: 'Three' })])).toEqual([])
        expect(findingsFor([
            subject('heading', { level: 2 }),
            subject('heading', { level: 3 }),
            subject('heading', { level: 2 }),
        ])).toEqual([])
    })

    it('finds a table with no header row', () => {
        expect(rules(findingsFor([subject('table', { headerCells: 0 })]))).toEqual(['table_without_header'])
        expect(findingsFor([subject('table', { headerCells: 3 })])).toEqual([])
    })

    it('finds a colour that cannot be read on the page it is going to', () => {
        const findings = findingsFor(
            [subject('colour', { color: 'sand', text: 'A heading', large: false })],
            { palette: { sand: '#e8dcc8' }, background: '#ffffff' },
        )

        expect(rules(findings)).toEqual(['weak_contrast'])
        // The number is the whole of the answer: "not enough" is not something anybody can
        // act on, and 1.4 against 4.5 is.
        expect(findings[0].ratio).toBeLessThan(2)
        expect(findings[0].needed).toBe(4.5)
    })

    it('holds large text to the easier level, which is what WCAG does', () => {
        const grey = { color: '#949494', text: 'Big' }

        expect(findingsFor([subject('colour', { ...grey, large: false })])).toHaveLength(1)
        expect(findingsFor([subject('colour', { ...grey, large: true })])).toEqual([])
    })

    it('measures against the colour it is actually sitting on', () => {
        // White on white is unreadable and white on near-black is not; the background it was
        // given beats the one the page was assumed to have.
        expect(findingsFor([subject('colour', { color: '#ffffff', background: '#18181b' })])).toEqual([])
        expect(findingsFor([subject('colour', { color: '#ffffff' })])).toHaveLength(1)
    })

    it('measures a chosen background against the colour the page writes in', () => {
        // Highlighting a sentence and leaving the text at its default is the same failure
        // as choosing a colour, and it is the more common one.
        const findings = findingsFor(
            [subject('colour', { color: null, background: '#3b0764', text: 'Highlighted' })],
            { text: '#18181b' },
        )

        expect(findings.map((found) => found.rule)).toEqual(['weak_contrast'])

        // And a light background under the same default text is fine.
        expect(findingsFor(
            [subject('colour', { color: null, background: '#fef9c3' })],
            { text: '#18181b' },
        )).toEqual([])
    })

    it('measures against the colour a project says its pages are', () => {
        // The editor cannot know what colour the page will be, so it is told.
        expect(findingsFor([subject('colour', { color: '#ffffff' })], { background: '#18181b' })).toEqual([])
    })

    it('says nothing about a colour it could not read', () => {
        expect(findingsFor([subject('colour', { color: 'var(--brand)' })])).toEqual([])
        expect(findingsFor([subject('colour', { color: 'rgba(0, 0, 0, 0.4)' })])).toEqual([])
    })
})

describe('which rules are asked', () => {
    it('asks all seven unless a project says otherwise', () => {
        expect(RULES).toHaveLength(7)
    })

    it('leaves out what a project left out', () => {
        const subjects = [
            subject('image', { alt: '' }),
            subject('table', { headerCells: 0 }),
        ]

        expect(findingsFor(subjects)).toHaveLength(2)
        expect(findingsFor(subjects, { rules: ['missing_alt'] }).map((found) => found.rule))
            .toEqual(['missing_alt'])
        expect(findingsFor(subjects, { rules: [] })).toEqual([])
    })

    it('still follows the heading levels while the rule is switched off', () => {
        // The sequence is what the rule is about, so it has to be kept whether or not
        // anybody is being told about it - otherwise switching the rule on and off again
        // would change what it finds next.
        const subjects = [
            subject('heading', { level: 2 }),
            subject('heading', { level: 4 }),
            subject('heading', { level: 5 }),
        ]

        expect(findingsFor(subjects, { rules: ['skipped_heading'] })).toHaveLength(1)
    })

    it('reads a font size in whatever unit it was written in', () => {
        // Eighteen point is exactly the size WCAG calls large. Reading the number and
        // ignoring the unit calls it 18, holds it to the stricter ratio, and reports text
        // that passes.
        expect(sizeInPixels('24px')).toBe(24)
        expect(sizeInPixels('18pt')).toBe(24)
        expect(sizeInPixels('1.5rem')).toBe(24)
        expect(sizeInPixels('24')).toBe(24)
        expect(sizeInPixels(null)).toBe(0)
        expect(sizeInPixels('inherit')).toBe(0)
    })

    it('carries the position of everything it finds, so a row can be clicked', () => {
        const findings = findingsFor([{ kind: 'image', from: 12, to: 13, node: true, alt: '' }])

        expect(findings[0]).toMatchObject({ from: 12, to: 13, node: true })
    })
})
