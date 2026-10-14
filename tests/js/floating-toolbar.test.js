import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, expect, it } from 'vitest'

/**
 * The bars that float over a picture, a table or a selection, before anybody has opened one.
 *
 * Filament renders every one of them into the field up front and hides them with
 * `visibility: hidden` - which hides a box without taking it out of the layout. The browser
 * still works out the style and the size of everything in it, on every recalculation, for a
 * bar nobody can see. Filament has one such bar; this package has eight, and on a page of
 * editors they were where nearly all of the long tasks of starting them went.
 *
 * TipTap takes a bar out of the document when it hides it, and puts it back with an inline
 * `visibility: visible` when it shows it - before measuring it. So "no inline visibility yet"
 * is exactly "never shown", and that is the state the stylesheet takes out of the layout.
 *
 * jsdom applies the cascade, which is all this needs: the package's own sheet as shipped,
 * and Filament's rule for the bar after it, the way the panel orders them.
 */

const FILAMENT_BAR_RULE = '.fi-fo-rich-editor .fi-fo-rich-editor-floating-toolbar { visibility: hidden; display: flex; position: absolute; }'

// From the root Vitest runs in: under jsdom neither `URL` nor `import.meta.url` is the one `fs` takes.
const sheet = readFileSync(resolve(process.cwd(), 'resources/css/filament-advanced-rich-editor.css'), 'utf8')

beforeEach(() => {
    document.head.innerHTML = `<style>${sheet}</style><style>${FILAMENT_BAR_RULE}</style>`
})

afterEach(() => {
    document.head.innerHTML = ''
    document.body.innerHTML = ''
})

function bar(fieldClasses = 'fi-fo-rich-editor fi-arte') {
    document.body.innerHTML = `
        <div class="${fieldClasses}">
            <div x-ref="editor">
                <div class="ProseMirror"></div>
                <div x-ref="floatingToolbar::image" class="fi-fo-rich-editor-floating-toolbar fi-not-prose"></div>
            </div>
        </div>`

    return document.querySelector('.fi-fo-rich-editor-floating-toolbar')
}

it('keeps a bar nobody has opened out of the layout', () => {
    expect(getComputedStyle(bar()).display).toBe('none')
})

it('lays a bar out again the moment TipTap shows it', () => {
    const element = bar()

    // What `BubbleMenuView.show()` writes, before `updatePosition()` measures the bar.
    element.style.visibility = 'visible'
    element.style.opacity = '1'

    expect(getComputedStyle(element).display).toBe('flex')
})

it('keeps a bar laid out that TipTap hid while it stayed in the document', () => {
    // Only the floating-ui `hide` middleware does this, and Filament does not turn it on -
    // but a bar hidden that way is measured again before it is shown, so it has to keep
    // its size.
    const element = bar()

    element.style.visibility = 'hidden'

    expect(getComputedStyle(element).display).toBe('flex')
})

it('leaves the bars of a plain Filament editor alone', () => {
    expect(getComputedStyle(bar('fi-fo-rich-editor')).display).toBe('flex')
})
