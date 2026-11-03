import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'

/**
 * A field with `maxHeight()`, where the document scrolls inside the field.
 *
 * The document is the box that scrolls, and it had no room of its own: the scrollbar sat
 * flush against the text column, a card running the full width ran into it, and the outline
 * of a selected block - drawn outside the block - was cut off on both sides. jsdom lays
 * nothing out, so this holds the rules that give it that room, with the sheet as shipped;
 * what they draw was checked in Chrome, light and dark, with cards, a wide table, a long line
 * of code and a picture wider than the field.
 */

// From the root Vitest runs in: under jsdom neither `URL` nor `import.meta.url` is the one `fs` takes.
const sheet = readFileSync(resolve(process.cwd(), 'resources/css/filament-advanced-rich-editor.css'), 'utf8')

afterEach(() => {
    document.head.innerHTML = ''
    document.body.innerHTML = ''
})

function field({ fullscreen = false } = {}) {
    document.head.innerHTML = `<style>${sheet}</style>`
    document.body.innerHTML = `
        <div class="fi-fo-rich-editor fi-arte fi-arte-max-height${fullscreen ? ' fi-arte-fullscreen' : ''}">
            <div class="fi-fo-rich-editor-content" id="box"><div class="tiptap" id="document"></div></div>
        </div>`

    return {
        box: getComputedStyle(document.getElementById('box')),
        document: getComputedStyle(document.getElementById('document')),
    }
}

// The two lengths of a logical shorthand, in rem - start, then end.
const rems = (value) => {
    const [start, end = start] = value.split(' ').map((length) => (length === '0' ? 0 : parseFloat(length)))

    return [start, end]
}

describe('the document of a capped field', () => {
    it('scrolls, with a thin bar whose room is kept whether it shows or not', () => {
        const { document: style } = field()

        expect(style.overflowY).toBe('auto')
        expect(style.getPropertyValue('scrollbar-width')).toBe('thin')
        expect(style.getPropertyValue('scrollbar-gutter')).toBe('stable')
    })

    it('reaches into the padding around it and takes the same back, so the text does not move', () => {
        const { document: style } = field()
        const margin = rems(style.getPropertyValue('margin-inline'))
        const padding = rems(style.getPropertyValue('padding-inline'))

        // Room on both sides: the outline of a selected block is three pixels outside it.
        expect(padding[0]).toBeGreaterThan(0)
        expect(padding[1]).toBeGreaterThan(padding[0])
        expect(margin[0] + padding[0]).toBe(0)
        expect(margin[1] + padding[1]).toBe(0)
    })

    it('gives the room back in fullscreen, where the box around it is what scrolls', () => {
        const { box, document: style } = field({ fullscreen: true })

        expect(style.overflowY).toBe('visible')
        expect(rems(style.getPropertyValue('margin-inline'))).toEqual([0, 0])
        expect(rems(style.getPropertyValue('padding-inline'))).toEqual([0, 0])
        expect(box.overflowY).toBe('auto')
        expect(box.getPropertyValue('scrollbar-width')).toBe('thin')
    })
})

describe('a code block in the editor', () => {
    it('keeps a strip above its first line for the language picker, which a page does not have', () => {
        document.head.innerHTML = `<style>${sheet}</style>`
        document.body.innerHTML = `
            <div class="fi-arte-code-block"><select class="fi-arte-code-block-language"></select><pre id="editor"></pre></div>
            <pre id="page"></pre>`

        expect(getComputedStyle(document.getElementById('editor')).getPropertyValue('padding-block-start')).toBe('34px')
        expect(getComputedStyle(document.getElementById('page')).getPropertyValue('padding-block-start')).not.toBe('34px')
    })
})
