import { afterEach, describe, expect, it, vi } from 'vitest'
import listPropertiesExtension, {
    TOOLBARS,
    listStart,
    listToolbarVisibility,
    listType,
} from '../../resources/js/list-properties.js'

/**
 * The two functions the list attributes are guarded by. Both mirror `ListProperties` in
 * PHP, and the two halves have to agree - one writes what the other reads.
 */

describe('the marker a list may draw', () => {
    it('tells the two kinds apart', () => {
        // `square` is a bullet and `a` is a numbering, and neither belongs on the other.
        expect(listType('a', 'orderedList')).toBe('a')
        expect(listType('a', 'bulletList')).toBeNull()
        expect(listType('square', 'bulletList')).toBe('square')
        expect(listType('square', 'orderedList')).toBeNull()
    })

    it('keeps case, because a and A are different alphabets', () => {
        // The one place this package does not fold case: lowercasing would turn the four
        // choices `a`, `A`, `i`, `I` into two.
        expect(listType('A', 'orderedList')).toBe('A')
        expect(listType('I', 'orderedList')).toBe('I')
    })

    it('refuses a marker nothing draws', () => {
        expect(listType('javascript:alert(1)', 'orderedList')).toBeNull()
        expect(listType('', 'bulletList')).toBeNull()
        expect(listType(null, 'orderedList')).toBeNull()
        expect(listType('disc', 'somethingElse')).toBeNull()
    })
})

describe('the number a list starts counting at', () => {
    it('reads a number written either way', () => {
        expect(listStart(7)).toBe(7)
        expect(listStart('7')).toBe(7)
        expect(listStart(' 12 ')).toBe(12)
    })

    it('answers null for the number a list would count from anyway', () => {
        // `start="1"` on every list would be an attribute saying exactly what its absence
        // says.
        expect(listStart(1)).toBeNull()
        expect(listStart(0)).toBeNull()
        expect(listStart(-5)).toBeNull()
    })

    it('refuses what is not a number, and what is too big to be one', () => {
        expect(listStart('twelve')).toBeNull()
        expect(listStart(null)).toBeNull()
        expect(listStart(100001)).toBeNull()
    })
})

/**
 * The rule that decides whether the panel is on screen at all.
 *
 * This is the half that broke: Filament shows a floating toolbar while
 * `editor.isFocused && editor.isActive(<its key>)`, which is right for a bar of buttons and
 * wrong for one holding a panel. Clicking anything inside the panel takes the focus off the
 * editor, the transaction the click dispatches finds `isFocused` false, and the bubble
 * menu answers by removing its element from the DOM - which takes the panel, and the Alpine
 * state saying it was open, with it. The image toolbar already widens the rule for exactly
 * this reason; the two list toolbars did not.
 */

const editorIn = (listType, { focused = true, empty = true, paragraph = true } = {}) => ({
    isFocused: focused,
    isActive: (type) => (type === 'paragraph' ? paragraph : type === listType),
    state: { selection: { empty } },
})

const toolbarInside = (container) => {
    const element = document.createElement('div')
    const button = document.createElement('button')

    element.appendChild(button)
    container.appendChild(element)

    return { element, button }
}

describe('whether the list toolbar stays on screen', () => {
    it('shows while the caret is in the list it belongs to, and not in the other one', () => {
        const shouldShow = listToolbarVisibility('bulletList')

        expect(shouldShow({ editor: editorIn('bulletList'), element: null })).toBe(true)
        expect(shouldShow({ editor: editorIn('orderedList'), element: null })).toBe(false)
    })

    it('stays while the focus is inside the toolbar, which is where a panel puts it', () => {
        // The regression, stated: a click on a marker focuses the button it landed on, so
        // the editor is no longer focused by the time the command it ran dispatches.
        const { element, button } = toolbarInside(document.body)

        button.focus()

        const shouldShow = listToolbarVisibility('orderedList')

        expect(shouldShow({ editor: editorIn('orderedList', { focused: false }), element })).toBe(true)
    })

    it('stays while a press is landing inside it, which is where a blur commits from', () => {
        // Typing a start number and then clicking the checkbox: the input's `blur` runs the
        // command, and during a blur the focus is on nothing yet. What says the interaction
        // is still in the panel is the pointer that caused it.
        const { element, button } = toolbarInside(document.body)

        document.body.focus()

        const shouldShow = listToolbarVisibility('orderedList', { pressed: () => button })

        expect(shouldShow({ editor: editorIn('orderedList', { focused: false }), element })).toBe(true)
    })

    it('goes away when the focus and the press are both somewhere else', () => {
        const { element } = toolbarInside(document.body)
        const elsewhere = document.createElement('button')

        document.body.appendChild(elsewhere)
        elsewhere.focus()

        const shouldShow = listToolbarVisibility('bulletList', { pressed: () => elsewhere })

        expect(shouldShow({ editor: editorIn('bulletList', { focused: false }), element })).toBe(false)
    })

    it('leaves a selection to the text toolbar, the way Filament does', () => {
        // A list item holds a paragraph, so somebody who has selected words inside one
        // wants the bar that formats them.
        const editor = editorIn('bulletList', { empty: false })

        expect(listToolbarVisibility('bulletList', { hasTextToolbar: true })({ editor, element: null })).toBe(false)
        expect(listToolbarVisibility('bulletList', { hasTextToolbar: false })({ editor, element: null })).toBe(true)
    })
})

/**
 * Installing that rule. Done through the bubble menu plugin's own `updateOptions` message,
 * the way the image toolbar does it, rather than by touching Filament's component.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: { core: { Extension: { create: (definition) => definition } } },
    }

    return listPropertiesExtension()
}

const fakeEditor = (root) => {
    const metas = {}
    const transaction = {
        setMeta(key, value) {
            metas[key] = value

            return transaction
        },
    }

    const dom = document.createElement('div')

    root.appendChild(dom)

    return {
        metas,
        editor: {
            state: { tr: transaction },
            view: { dom, dispatch: vi.fn() },
        },
    }
}

const created = (extension, editor) =>
    extension.onCreate.call({ editor, storage: extension.addStorage() })

afterEach(() => {
    delete window.FilamentRichEditor
    document.body.innerHTML = ''
})

describe('the toolbars it widens', () => {
    it('names one per kind of list, and only those', () => {
        expect(TOOLBARS).toEqual(['bulletList', 'orderedList'])
    })

    it('hands each bubble menu a rule of its own', () => {
        const root = document.createElement('div')

        document.body.appendChild(root)

        const { editor, metas } = fakeEditor(root)

        created(build(), editor)

        expect(editor.view.dispatch).toHaveBeenCalledOnce()

        TOOLBARS.forEach((listType) => {
            expect(metas[`floatingToolbar::${listType}`]).toMatchObject({ type: 'updateOptions' })
            expect(typeof metas[`floatingToolbar::${listType}`].options.shouldShow).toBe('function')
        })
    })

    it('keeps the message out of the undo history, because it is not an edit', () => {
        const root = document.createElement('div')

        document.body.appendChild(root)

        const { editor, metas } = fakeEditor(root)

        created(build(), editor)

        expect(metas.addToHistory).toBe(false)
    })

    it('defers to a text toolbar only where the field has one', () => {
        const withText = document.createElement('div')
        const withoutText = document.createElement('div')
        const bar = document.createElement('div')

        bar.setAttribute('x-ref', 'floatingToolbar::paragraph')
        withText.appendChild(bar)
        document.body.append(withText, withoutText)

        const selecting = editorIn('bulletList', { empty: false })

        const ruleFrom = (root) => {
            const { editor, metas } = fakeEditor(root)

            created(build(), editor)

            return metas['floatingToolbar::bulletList'].options.shouldShow
        }

        expect(ruleFrom(withText)({ editor: selecting, element: null })).toBe(false)
        expect(ruleFrom(withoutText)({ editor: selecting, element: null })).toBe(true)
    })

    it('follows the pointer, so a press inside the panel holds the bar open', () => {
        const root = document.createElement('div')

        document.body.appendChild(root)

        const { editor, metas } = fakeEditor(root)
        const extension = build()
        const storage = extension.addStorage()

        extension.onCreate.call({ editor, storage })

        const { element, button } = toolbarInside(root)
        const shouldShow = metas['floatingToolbar::bulletList'].options.shouldShow
        const blurred = { editor: editorIn('bulletList', { focused: false }), element }

        expect(shouldShow(blurred)).toBe(false)

        button.dispatchEvent(new Event('pointerdown', { bubbles: true }))

        expect(shouldShow(blurred)).toBe(true)

        button.dispatchEvent(new Event('pointerup', { bubbles: true }))

        // The press is let go of once it is over: a flag left standing would hold the bar
        // open long after the click that set it.
        expect(shouldShow(blurred)).toBe(false)
    })

    it('takes its listeners away with it', () => {
        const root = document.createElement('div')

        document.body.appendChild(root)

        const { editor, metas } = fakeEditor(root)
        const extension = build()
        const storage = extension.addStorage()

        extension.onCreate.call({ editor, storage })
        extension.onDestroy.call({ editor, storage })

        const { element, button } = toolbarInside(root)

        button.dispatchEvent(new Event('pointerdown', { bubbles: true }))

        expect(
            metas['floatingToolbar::bulletList'].options.shouldShow({
                editor: editorIn('bulletList', { focused: false }),
                element,
            }),
        ).toBe(false)
    })
})
