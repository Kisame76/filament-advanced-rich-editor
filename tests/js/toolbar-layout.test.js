import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'

/**
 * The toolbar in a column narrower than it.
 *
 * Filament draws the bar as a wrapping row of groups and never lets a group wrap, so the
 * narrowest a bar can get is its widest group. In a narrow column - a repeater in a grid, a
 * side panel - that group is wider than the field, and a centred bar then hangs over both
 * edges of it: buttons cut off on the left where nobody can reach them.
 *
 * jsdom applies the cascade but lays nothing out, so this holds the rules that decide the
 * layout, with the package's sheet as shipped; what they draw was checked in Chrome at six
 * widths. A container query is never evaluated by jsdom, so the narrow case is staged by
 * appending the rules inside it the way a matching query would apply them - which also
 * checks that they win against the rules for a wide bar.
 */

// From the root Vitest runs in: under jsdom neither `URL` nor `import.meta.url` is the one `fs` takes.
const sheet = readFileSync(resolve(process.cwd(), 'resources/css/filament-advanced-rich-editor.css'), 'utf8')

// What Filament's theme says about the bar and its groups, after this package's sheet.
const FILAMENT = `
    .fi-fo-rich-editor .fi-fo-rich-editor-toolbar { display: flex; flex-wrap: wrap; }
    .fi-fo-rich-editor .fi-fo-rich-editor-toolbar-group { display: flex; }
`

afterEach(() => {
    document.head.innerHTML = ''
    document.body.innerHTML = ''
})

function bar({ split = true, align = 'center', pin = 'end', narrow = false } = {}) {
    document.head.innerHTML = `<style>${sheet}</style><style>${FILAMENT}</style>`

    if (narrow) {
        const style = document.createElement('style')

        style.textContent = narrowRules().map((rule) => rule.cssText).join('\n')
        document.head.append(style)
    }

    const classes = ['fi-fo-rich-editor-toolbar', `fi-arte-toolbar-align-${align}`]

    if (split) {
        classes.push('fi-arte-toolbar-split', `fi-arte-toolbar-pin-${pin}`)
    }

    document.body.innerHTML = split
        ? `<div class="fi-fo-rich-editor fi-arte"><div class="${classes.join(' ')}">
               <div class="fi-arte-toolbar-flow"><div class="fi-fo-rich-editor-toolbar-group" id="flowing"></div></div>
               <div class="fi-arte-toolbar-pinned"><div class="fi-fo-rich-editor-toolbar-group" id="pinned"></div></div>
           </div></div>`
        : `<div class="fi-fo-rich-editor fi-arte"><div class="${classes.join(' ')}">
               <div class="fi-fo-rich-editor-toolbar-group" id="flowing"></div>
           </div></div>`

    return {
        toolbar: document.querySelector('.fi-fo-rich-editor-toolbar'),
        flow: document.querySelector('.fi-arte-toolbar-flow'),
        pinned: document.querySelector('.fi-arte-toolbar-pinned'),
        flowing: document.getElementById('flowing'),
        pinnedGroup: document.getElementById('pinned'),
    }
}

// The rules inside the query that rearranges a split bar too narrow for its two halves.
function narrowRules() {
    document.head.innerHTML = `<style>${sheet}</style>`

    const query = [...document.styleSheets[0].cssRules].find(
        (rule) => rule.constructor.name === 'CSSContainerRule' && /^@container\s+fi-arte-toolbar\b/.test(rule.cssText),
    )

    return query ? [...query.cssRules] : []
}

describe('a group', () => {
    it('wraps in the half of a split bar that flows, so the bar is never wider than its column', () => {
        expect(getComputedStyle(bar().flowing).flexWrap).toBe('wrap')
    })

    it('wraps on a bar with nothing pinned', () => {
        expect(getComputedStyle(bar({ split: false }).flowing).flexWrap).toBe('wrap')
    })

    it('stays on one line in the pinned half, which is a corner and not a row', () => {
        // A pinned half that could wrap would report its widest button as its minimum, and
        // the column it sits in would let the flow run into it.
        expect(getComputedStyle(bar().pinnedGroup).flexWrap).toBe('nowrap')
    })
})

describe('a centred bar', () => {
    it('centres only what fits, so nothing hangs over the start of the field', () => {
        // Plain `center` splits an overflow between both edges, and the half on the left is
        // out of reach. `safe` falls back to the start edge instead.
        const { toolbar, flow } = bar()

        expect(getComputedStyle(toolbar).justifyContent).toBe('safe center')
        expect(getComputedStyle(flow).justifyContent).toBe('safe center')
        expect(getComputedStyle(bar({ split: false }).toolbar).justifyContent).toBe('safe center')
    })

    it('ends only what fits on a bar aligned to the end, for the same reason', () => {
        expect(getComputedStyle(bar({ align: 'end' }).flow).justifyContent).toBe('safe flex-end')
        expect(getComputedStyle(bar({ split: false, align: 'end' }).toolbar).justifyContent).toBe('safe flex-end')
    })
})

describe('a split bar too narrow for both halves side by side', () => {
    it('measures itself, because the column it sits in is what decides', () => {
        // A media query reads the window, and a narrow column on a wide screen is exactly
        // the case this is for.
        expect(getComputedStyle(bar().toolbar).getPropertyValue('container-type')).toBe('inline-size')
        expect(getComputedStyle(bar().toolbar).getPropertyValue('container-name')).toBe('fi-arte-toolbar')
        expect(narrowRules()).not.toHaveLength(0)
    })

    it('gives the pinned half the first row, in its corner, and the flow the rows below', () => {
        for (const pin of ['end', 'start']) {
            for (const align of ['center', 'start']) {
                const { pinned, flow } = bar({ narrow: true, pin, align })

                expect(getComputedStyle(pinned).gridRow, `${align}, pinned to the ${pin}`).toBe('1')
                expect(getComputedStyle(pinned).gridColumn, `${align}, pinned to the ${pin}`).toBe('1 / -1')
                expect(getComputedStyle(pinned).justifySelf).toBe(pin)
                expect(getComputedStyle(flow).gridRow, `${align}, pinned to the ${pin}`).toBe('2')
                expect(getComputedStyle(flow).gridColumn, `${align}, pinned to the ${pin}`).toBe('1 / -1')
            }
        }
    })

    it('keeps both halves where they are on a bar wide enough for them', () => {
        const { pinned, flow } = bar()

        expect(getComputedStyle(pinned).gridColumn).toBe('3')
        expect(getComputedStyle(flow).gridColumn).toBe('2')
    })
})

describe('a pinned bar', () => {
    // Filament's own sticky bar, from 5.9: rounded like ours, and loaded after this sheet.
    const FILAMENT_STICKY = `
        .fi-fo-rich-editor .fi-fo-rich-editor-toolbar.fi-fo-rich-editor-sticky-toolbar {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            position: sticky;
        }
    `

    const pinned = (classes) => {
        document.head.innerHTML = `<style>${sheet}</style><style>${FILAMENT}${FILAMENT_STICKY}</style>`
        document.body.innerHTML = `<div class="fi-fo-rich-editor fi-arte"><div class="fi-fo-rich-editor-toolbar ${classes}"></div></div>`

        return getComputedStyle(document.querySelector('.fi-fo-rich-editor-toolbar'))
    }

    // jsdom hands a custom property back unresolved, so a round corner is "not zero" here.
    const SQUARE = /^0(px)?$/

    it('keeps the round corners of its field while it rests at the top of it', () => {
        expect(pinned('fi-arte-sticky').borderStartStartRadius).not.toMatch(SQUARE)
        expect(pinned('fi-arte-sticky').borderStartEndRadius).not.toMatch(SQUARE)
        expect(pinned('fi-fo-rich-editor-sticky-toolbar').borderTopLeftRadius).not.toMatch(SQUARE)
    })

    it('squares them while it is stuck under the page header, where the field edge is gone', () => {
        expect(pinned('fi-arte-sticky fi-arte-stuck').borderStartStartRadius).toMatch(SQUARE)
        expect(pinned('fi-arte-sticky fi-arte-stuck').borderStartEndRadius).toMatch(SQUARE)
    })

    it('squares Filament’s own sticky bar the same way', () => {
        const style = pinned('fi-fo-rich-editor-sticky-toolbar fi-arte-stuck')

        expect(style.borderTopLeftRadius).toMatch(SQUARE)
        expect(style.borderTopRightRadius).toMatch(SQUARE)
    })
})
