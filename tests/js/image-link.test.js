import { describe, expect, it } from 'vitest'
import { imageHref, SCHEMES } from '../../resources/js/image-link.js'
import { isPresentational } from '../../resources/js/image-decorative.js'

/**
 * The two functions the image attributes are built on, and the reason they are tested here
 * rather than only in PHP: each one has a twin on the other side of the wire.
 *
 * `imageHref` mirrors `ImageLink::normalise()` and `isPresentational` mirrors
 * `ImageDecorative::isPresentational()`. One half writes what the other reads, and a
 * document is parsed by both - by the browser when it is opened and by PHP when it is
 * rendered. Where the two disagree, an address is written that the page then refuses, or
 * refused here and written there. The ProseMirror wiring around them is declarative and is
 * asserted from PHP, through the round trip a save actually goes through.
 */

describe('the address a picture may point at', () => {
    it('keeps what a document is actually written with', () => {
        expect(imageHref('https://example.com/a')).toBe('https://example.com/a')
        expect(imageHref('http://example.com')).toBe('http://example.com')
        expect(imageHref('mailto:someone@example.com')).toBe('mailto:someone@example.com')
        expect(imageHref('tel:+491234')).toBe('tel:+491234')
    })

    it('keeps an address inside the site, which carries no scheme to check', () => {
        expect(imageHref('/articles/7')).toBe('/articles/7')
        expect(imageHref('../up')).toBe('../up')
        expect(imageHref('#section')).toBe('#section')
    })

    it('reads a colon after a slash as part of a path rather than a scheme', () => {
        // `/a:b` is a path with a colon in it. Deciding on the colon alone would make it a
        // scheme called `/a`, and refuse a perfectly ordinary address.
        expect(imageHref('/a:b')).toBe('/a:b')
    })

    it('refuses a scheme that runs code', () => {
        expect(imageHref('javascript:alert(1)')).toBeNull()
        expect(imageHref('JavaScript:alert(1)')).toBeNull()
        expect(imageHref('vbscript:msgbox(1)')).toBeNull()
        expect(imageHref('data:text/html;base64,PHNjcmlwdD4=')).toBeNull()
    })

    it('is not fooled by a scheme with a newline in it', () => {
        // The oldest trick against a check that reads the string as it arrives: a browser
        // strips these characters before resolving an address.
        expect(imageHref('java\nscript:alert(1)')).toBeNull()
        expect(imageHref('java\tscript:alert(1)')).toBeNull()
    })

    it('has nothing to say about nothing', () => {
        expect(imageHref('')).toBeNull()
        expect(imageHref('   ')).toBeNull()
        expect(imageHref(null)).toBeNull()
        expect(imageHref(undefined)).toBeNull()
        expect(imageHref(7)).toBeNull()
    })

    it('lists the same four schemes PHP does', () => {
        // Written out rather than derived, so a change on one side fails here rather than
        // quietly letting through what the other half refuses.
        expect(SCHEMES).toEqual(['http', 'https', 'mailto', 'tel'])
    })
})

describe('whether a role says the picture carries nothing', () => {
    it('takes both words ARIA has for it', () => {
        expect(isPresentational('presentation')).toBe(true)
        expect(isPresentational('none')).toBe(true)
        expect(isPresentational('  PRESENTATION  ')).toBe(true)
    })

    it('says nothing about a role that means something else', () => {
        // `role="button"` on a picture is somebody building a control out of it, which is a
        // different claim entirely.
        expect(isPresentational('button')).toBe(false)
        expect(isPresentational('img')).toBe(false)
        expect(isPresentational('')).toBe(false)
        expect(isPresentational(null)).toBe(false)
    })
})
