import { afterEach, describe, expect, it, vi } from 'vitest'
import pageBreakExtension, { isPageBreakElement, NAME, STYLE, settingsOf } from '../../resources/js/page-break.js'

/**
 * The page break on the editor's side: what it reads off the field, which pasted markup it
 * takes for a break, and how it is written back. Where it lands in a stored document and a
 * PDF is asserted from PHP - see `PageBreakTest`.
 */

afterEach(() => {
    vi.unstubAllGlobals()
    delete window.FilamentRichEditor
})

const element = (html) => {
    const wrapper = document.createElement('div')
    wrapper.innerHTML = html

    return wrapper.firstElementChild
}

describe('the settings the field hands over', () => {
    it('are the label of the line', () => {
        const editor = document.createElement('div')
        editor.dataset.artePageBreak = JSON.stringify({ label: 'Seitenumbruch' })

        expect(settingsOf(editor)).toEqual({ label: 'Seitenumbruch' })
    })

    it('leave the line without a label rather than breaking, when they are missing or unreadable', () => {
        const editor = document.createElement('div')

        expect(settingsOf(editor)).toEqual({ label: '' })

        editor.dataset.artePageBreak = '{not json'
        expect(settingsOf(editor)).toEqual({ label: '' })
        expect(settingsOf(null)).toEqual({ label: '' })
    })
})

describe('what is taken for a break', () => {
    it('is the package\'s own', () => {
        expect(isPageBreakElement(element(`<div data-type="${NAME}" style="${STYLE}"></div>`))).toBe(true)
    })

    it('is the one another editor wrote: empty, with a forced break as its style', () => {
        // CKEditor's, with the hidden space it keeps inside.
        expect(isPageBreakElement(element('<div class="page-break" style="page-break-after:always;"><span style="display:none;">&nbsp;</span></div>'))).toBe(true)
        expect(isPageBreakElement(element('<div style="break-before: page"></div>'))).toBe(true)
    })

    it('is never a block with text in it, or one that only avoids a break', () => {
        expect(isPageBreakElement(element('<div style="page-break-after: always"><p>Text</p></div>'))).toBe(false)
        expect(isPageBreakElement(element('<div style="page-break-inside: avoid"></div>'))).toBe(false)
        expect(isPageBreakElement(element('<div></div>'))).toBe(false)
        expect(isPageBreakElement(null)).toBe(false)
    })
})

describe('the node', () => {
    const extension = (settings) => {
        window.FilamentRichEditor = {
            tiptap: {
                core: { Node: { create: (definition) => definition }, mergeAttributes: Object.assign },
                pmState: { NodeSelection: class {}, TextSelection: class {} },
            },
        }

        const node = pageBreakExtension()
        const editor = document.createElement('div')

        if (settings) {
            editor.dataset.artePageBreak = JSON.stringify(settings)
        }

        node.editor = { options: { element: editor } }
        node.options = node.addOptions()

        return node
    }

    it('is a leaf block that can be selected and dragged', () => {
        const node = extension()

        expect(node.name).toBe('pageBreak')
        expect(node.group).toBe('block')
        expect(node.atom).toBe(true)
        expect(node.selectable).toBe(true)
        expect(node.draggable).toBe(true)
    })

    it('is written the way the PHP half reads it', () => {
        expect(extension().renderHTML({ HTMLAttributes: {} })).toEqual([
            'div',
            { class: 'fi-arte-page-break', 'data-type': 'pageBreak', style: 'break-after: page; page-break-after: always;' },
        ])
    })

    it('reads its own markup and a foreign break, and no other div', () => {
        const [own, foreign] = extension().parseHTML()

        expect(own.tag).toBe('div[data-type="pageBreak"]')
        expect(foreign.getAttrs(element('<div style="page-break-after: always"></div>'))).toBeNull()
        expect(foreign.getAttrs(element('<div class="note"><p>x</p></div>'))).toBe(false)
    })

    it('draws a labelled line nobody types into', () => {
        const { dom } = extension({ label: 'Page break' }).addNodeView()()

        expect(dom.className).toBe('fi-arte-page-break')
        expect(dom.dataset.type).toBe('pageBreak')
        expect(dom.dataset.label).toBe('Page break')
        expect(dom.contentEditable).toBe('false')
    })

    it('says what is missing instead of breaking the editor without TipTap', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(pageBreakExtension()).toBeNull()
        expect(error).toHaveBeenCalledOnce()
    })
})
