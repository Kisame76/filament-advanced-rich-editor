import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import linkPreviewExtension, {
    linkPreview,
    previewHref,
    previewIcon,
    previewSite,
    previewText,
    STYLES,
} from '../../resources/js/link-preview.js'

/**
 * A link card, on the editor's side. Every rule here mirrors `Nodes/LinkPreview` - the PHP test
 * pins the styles and lengths against this file - and the two halves have to draw the same card:
 * this one while somebody writes, that one for a reader.
 */

const PNG =
    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='

describe('the address', () => {
    it('takes a page on the web', () => {
        expect(previewHref(' https://example.com/page ')).toBe('https://example.com/page')
        expect(previewHref('http://example.com')).toBe('http://example.com')
    })

    it('refuses anything a browser would run, and anything that is not a page', () => {
        expect(previewHref('javascript:alert(1)')).toBeNull()
        expect(previewHref('java\nscript:alert(1)')).toBeNull()
        // What the browser repairs and PHP does not: the two have to agree on what a card is.
        expect(previewHref('https:example.com/page')).toBeNull()
        expect(previewHref('https:\\\\example.com/page')).toBeNull()
        expect(previewHref('HTTPS://example.com/page')).toBe('HTTPS://example.com/page')
        expect(previewHref('data:text/html;base64,PHNjcmlwdD4=')).toBeNull()
        expect(previewHref('/about')).toBeNull()
        expect(previewHref('mailto:a@example.com')).toBeNull()
        expect(previewHref(null)).toBeNull()
    })
})

describe('the parts', () => {
    it('keeps a part as one line, cut to its length', () => {
        expect(previewText('  A\n  title  ', 200)).toBe('A title')
        expect(previewText('   ', 200)).toBeNull()
        expect(previewText('abcdef', 3)).toBe('abc…')
    })

    it('names the site by the host where it was given no name', () => {
        expect(previewSite(null, 'https://www.example.com/a')).toBe('example.com')
        expect(previewSite('Example', 'https://www.example.com/a')).toBe('Example')
    })

    it('keeps an icon only as a small picture in a data address', () => {
        expect(previewIcon(PNG)).toBe(PNG)
        expect(previewIcon('data:image/svg+xml;base64,PHN2Zy8+')).not.toBeNull()
        expect(previewIcon('https://example.com/favicon.ico')).toBeNull()
        expect(previewIcon('data:text/html;base64,PHNjcmlwdD4=')).toBeNull()
        expect(previewIcon('data:image/svg+xml,<svg onload="alert(1)"/>')).toBeNull()
        expect(previewIcon(`data:image/png;base64,${'A'.repeat(16384)}`)).toBeNull()
    })

    it('falls back to the address for a card with no title', () => {
        expect(linkPreview({ href: 'https://example.com/a' })).toMatchObject({
            title: 'https://example.com/a',
            description: null,
            site: 'example.com',
            icon: null,
        })
        expect(linkPreview({ href: 'javascript:alert(1)', title: 'Click' })).toBeNull()
    })
})

describe('the node', () => {
    let extension

    beforeEach(() => {
        window.FilamentRichEditor = {
            tiptap: { core: { Node: { create: (definition) => definition }, mergeAttributes: Object.assign } },
        }

        extension = linkPreviewExtension()
    })

    afterEach(() => {
        delete window.FilamentRichEditor
    })

    const attrs = {
        href: 'https://laravel.com/docs',
        title: 'Laravel',
        description: 'A framework.',
        site: 'Laravel',
        icon: PNG,
    }

    it('is read as a card before the link the same markup also is', () => {
        const [rule] = extension.parseHTML()

        expect(rule.tag).toBe('a[data-type="link-preview"]')
        expect(rule.priority).toBeGreaterThan(50)
    })

    it('writes the card the PHP half writes', () => {
        const [tag, attributes, title, , description, , site] = extension.renderHTML({ node: { attrs } })

        expect(tag).toBe('a')
        expect(attributes).toEqual({
            class: 'fi-arte-link-preview',
            'data-type': 'link-preview',
            href: 'https://laravel.com/docs',
            style: STYLES.card,
        })
        expect(title).toEqual(['span', { class: 'fi-arte-link-preview-title', style: STYLES.title }, 'Laravel'])
        expect(description[2]).toBe('A framework.')
        expect(site[2][0]).toBe('img')
        expect(site[2][1]).toMatchObject({ src: PNG, role: 'presentation', alt: '' })
        expect(site.at(-1)).toBe('Laravel')
    })

    it('reads back what it wrote', () => {
        const element = document.createElement('div')
        element.innerHTML = `<a data-type="link-preview" href="https://laravel.com/docs"><span class="fi-arte-link-preview-title">Laravel</span> <span class="fi-arte-link-preview-description">A framework.</span> <span class="fi-arte-link-preview-site"><img class="fi-arte-link-preview-icon" src="${PNG}"> Laravel</span></a>`

        const card = element.firstElementChild
        const read = Object.fromEntries(
            Object.entries(extension.addAttributes()).map(([name, spec]) => [name, spec.parseHTML(card)]),
        )

        expect(read).toEqual(attrs)
    })

    it('draws a card in the editor that cannot be typed into or followed', () => {
        const { dom } = extension.addNodeView()({ node: { attrs } })

        expect(dom.contentEditable).toBe('false')
        expect(dom.getAttribute('href')).toBe('https://laravel.com/docs')
        expect(dom.querySelector('.fi-arte-link-preview-title').textContent).toBe('Laravel')
        expect(dom.querySelector('.fi-arte-link-preview-icon').getAttribute('role')).toBe('presentation')

        const click = new MouseEvent('click', { cancelable: true })
        dom.dispatchEvent(click)

        expect(click.defaultPrevented).toBe(true)
    })
})
