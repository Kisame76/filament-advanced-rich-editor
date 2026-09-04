import { describe, expect, it } from 'vitest'
import {
    DEFAULT_TINT,
    fileCard,
    fileLabel,
    fileName,
    fileSize,
    fileSrc,
    fileTint,
} from '../../resources/js/file-card.js'

/**
 * An uploaded document, on the editor's side. Every one of these mirrors a static in PHP -
 * `Media/FileTypes`, `Media/ByteSize` and `Nodes/FileCard` - and the two halves have to
 * agree: this one decides what a person sees while they write, that one decides what a
 * reader gets. A card that changes shape on save is a card nobody trusts.
 */

describe('the address', () => {
    it('takes a path, and a link a browser fetches a file over', () => {
        expect(fileSrc('/storage/q3.pdf')).toBe('/storage/q3.pdf')
        expect(fileSrc('  files/q3.pdf  ')).toBe('files/q3.pdf')
        expect(fileSrc('https://cdn.test/q3.pdf')).toBe('https://cdn.test/q3.pdf')
    })

    it('refuses a scheme that is not a way of fetching a file', () => {
        expect(fileSrc('javascript:alert(1)')).toBeNull()
        expect(fileSrc('data:text/html;base64,PHNjcmlwdD4=')).toBeNull()
    })

    it('refuses a hidden scheme rather than stripping it', () => {
        // A browser reads this as `javascript:`; a check that looks at the front of the
        // string does not.
        expect(fileSrc('java\nscript:alert(1)')).toBeNull()
        expect(fileSrc('/files/a b.pdf')).toBeNull()
    })
})

describe('the tile', () => {
    it('reads the ending off the name', () => {
        expect(fileLabel('Bericht.pdf')).toBe('PDF')
        expect(fileLabel('Tabelle.xlsx')).toBe('XLSX')
        expect(fileLabel('/files/report.pdf?v=2')).toBe('PDF')
    })

    it('falls back to the address where the name has no ending', () => {
        // `<a download>Handbuch</a>` names a file the way a person would, and the ending is
        // in the address it points at.
        expect(fileLabel('Handbuch', '/files/a.docx')).toBe('DOCX')
        expect(fileLabel('Handbuch')).toBe('FILE')
    })

    it('colours by what the file is for, not by its format', () => {
        expect(fileTint('a.pdf')).toBe('#dc2626')
        expect(fileTint('a.docx')).toBe('#2563eb')
        expect(fileTint('a.csv')).toBe('#16a34a')
        expect(fileTint('a.xlsx')).toBe(fileTint('a.csv'))
        expect(fileTint('a.unknown')).toBe(DEFAULT_TINT)
    })
})

describe('the name and the size', () => {
    it('takes the name it was given', () => {
        expect(fileName('Bericht Q3.pdf', '/storage/abc.pdf')).toBe('Bericht Q3.pdf')
    })

    it('falls back to the last part of the address', () => {
        expect(fileName(null, '/storage/01J9/abc.pdf')).toBe('abc.pdf')
        expect(fileName('   ', '/storage/a%20b.pdf')).toBe('a b.pdf')
        expect(fileName(null, '/storage/q3.pdf?v=2#page=4')).toBe('q3.pdf')
    })

    it('keeps a size only as the short label it is', () => {
        expect(fileSize(' 1,2  MB ')).toBe('1,2 MB')
        expect(fileSize('')).toBeNull()
        expect(fileSize('x'.repeat(40))).toBeNull()
        expect(fileSize(null)).toBeNull()
    })
})

describe('the card', () => {
    it('is nothing where there is no address to point at', () => {
        expect(fileCard({ src: 'javascript:alert(1)' })).toBeNull()
        expect(fileCard({ src: null })).toBeNull()
    })

    it('carries the attachment id the save walks, and only where there is one', () => {
        // An id nothing walks is a file deleted by the next save of the record pointing at
        // it. See `Media/FileAttachments.php`.
        expect(fileCard({ src: '/a.pdf', id: 'doc-1' }).attributes['data-id']).toBe('doc-1')
        expect(fileCard({ src: '/a.pdf' }).attributes).not.toHaveProperty('data-id')
    })

    it('writes the name into `download`, so the file is saved under it', () => {
        const card = fileCard({ src: '/storage/abc.pdf', name: 'Bericht Q3.pdf' })

        expect(card.attributes.download).toBe('Bericht Q3.pdf')
        expect(card.attributes.href).toBe('/storage/abc.pdf')
        expect(card.attributes['data-type']).toBe('file')
    })

    it('carries its own shape inline, because the page it lands on has no stylesheet', () => {
        const card = fileCard({ src: '/a.pdf', name: 'a.pdf' })

        expect(card.attributes.style).toContain('display: inline-flex')
        expect(card.kindStyle).toContain('background-color: #dc2626')
    })

    it('sits inline so two cards share a line', () => {
        // A block gave every attachment a line of its own and 400 pixels of nothing beside
        // it. Inline they sit together and wrap; a card on its own line is a paragraph of
        // its own, which is what pressing return already does.
        const card = fileCard({ src: '/a.pdf', name: 'a.pdf' })

        expect(card.attributes.style).toContain('display: inline-flex')
        expect(card.attributes.style).toContain('vertical-align: top')
        expect(card.attributes.style).toContain('max-width: 100%')
        expect(card.attributes.style).not.toContain('max-width: 28rem')
    })

    it('says box-sizing, because a panel and a page disagree about it', () => {
        // Measured: the same card came out 448px wide in the panel and 478 on the page,
        // because a panel sets `border-box` for everything and a plain page does not.
        expect(fileCard({ src: '/a.pdf' }).attributes.style).toContain(
            'box-sizing: border-box',
        )
    })

    it('zeroes every margin prose styles would hand it', () => {
        // Prose styles give a document's children a top margin - measured at 16px on the
        // column and 16 more on the size, which turned a 50px card into a 78px one. An
        // inline style only beats a stylesheet for the properties it names.
        const card = fileCard({ src: '/a.pdf', name: 'a.pdf', size: '88 KB' })

        expect(card.textStyle).toContain('margin: 0')
        expect(card.nameStyle).toContain('margin: 0')
        expect(card.sizeStyle).toContain('margin: 0')
        expect(card.kindStyle).toContain('margin: 0')
    })

    it('stacks the name over the size, both with a line height of their own', () => {
        // Without them the two lines came to 38px against a 36px tile, so the text decided
        // the card's height instead of the tile - and the card grew for a change meant to
        // make it smaller.
        const card = fileCard({ src: '/a.pdf', name: 'a.pdf', size: '88 KB' })

        expect(card.textStyle).toContain('flex-direction: column')
        expect(card.nameStyle).toContain('line-height: 1.2')
        expect(card.sizeStyle).toContain('line-height: 1.2')
        expect(card.sizeStyle).toContain('align-self: flex-end')
    })

    it('has no size where nobody knows the size', () => {
        expect(fileCard({ src: '/a.pdf' }).size).toBeNull()
        expect(fileCard({ src: '/a.pdf', size: '1,2 MB' }).size).toBe('1,2 MB')
    })
})
