import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import slashExtension from '../../resources/js/slash-menu.js'

/**
 * The slash menu: when a slash opens it, what typing narrows it to, how it is driven from the
 * keyboard, and what picking an entry does.
 *
 * The module exports nothing but the extension, so all of it is reached the way ProseMirror
 * reaches it - through the plugin's `view.update()` and `handleKeyDown` - and what is checked
 * is what lands in the page. The ProseMirror side is reduced to what the menu asks of it: the
 * selection and the text before the caret, the coordinates of a position, and a chain that
 * records what it was told to do.
 */

const MENU = {
    char: '/',
    empty: 'Nothing found',
    groups: [
        {
            key: 'style',
            label: 'Style',
            items: [
                { name: 'paragraph', label: 'Text', icon: '<svg data-icon="text"></svg>', aliases: ['body', 'absatz'], handler: 'setParagraph()' },
                { name: 'bulletList', label: 'Bullet list', icon: '<svg></svg>', aliases: ['ul', 'bullets'], handler: 'toggleBulletList()' },
                { name: 'codeBlock', label: 'Code block', icon: '', aliases: ['pre', 'snippet'], handler: 'toggleCodeBlock()' },
            ],
        },
        {
            key: 'insert',
            label: 'Insert',
            items: [
                { name: 'table', label: 'Table', icon: '', aliases: ['grid'], handler: 'insertTable()' },
                { name: 'toc', label: 'Table of contents', icon: '', handler: 'insertToc()' },
                { name: 'image', label: 'Image', icon: '', aliases: ['img', 'UPLOAD'], handler: 'openImage()' },
            ],
        },
    ],
}

const START = 1

const build = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: { Extension: { create: (definition) => definition } },
            pmState: {
                Plugin: class {
                    constructor(spec) {
                        this.spec = spec
                    }
                },
                PluginKey: class {
                    constructor(name) {
                        this.name = name
                    }
                },
            },
        },
    }

    return slashExtension()
}

const views = []

/**
 * A field with the menu mounted on it, and the caret wherever `type()` puts it.
 */
const mount = ({ menu = MENU, code = false } = {}) => {
    // One field at a time, as on a page a person is writing in: a second one puts the
    // first away, so `panel()` cannot find somebody else's.
    for (const earlier of views.splice(0)) {
        earlier.destroy()
    }

    const element = document.createElement('div')

    if (menu !== null) {
        element.dataset.arteSlash = typeof menu === 'string' ? menu : JSON.stringify(menu)
    }

    document.body.append(element)

    const calls = []
    const chain = {
        focus: vi.fn(() => chain),
        deleteRange: vi.fn(() => chain),
        run: vi.fn(() => calls.push('delete')),
    }
    const textBetween = vi.fn()
    const editor = {
        options: { element },
        state: { selection: null },
        view: { coordsAtPos: vi.fn(() => ({ left: 100, top: 200, bottom: 220 })) },
        chain: vi.fn(() => chain),
    }

    const type = (before, { empty = true, inCode = code } = {}) => {
        textBetween.mockReturnValue(before)
        editor.state.selection = {
            empty,
            $from: {
                parent: { type: { spec: { code: inCode } }, textBetween },
                parentOffset: before.length,
                start: () => START,
                pos: START + before.length,
            },
        }
    }

    const [plugin] = build().addProseMirrorPlugins.call({ editor })
    const view = plugin.spec.view()

    views.push(view)

    const menuPanel = () => document.querySelector('.fi-arte-slash')

    return {
        element,
        editor,
        chain,
        calls,
        textBetween,
        plugin,
        view,
        type,
        /** Types and lets the plugin see the new state, as a transaction would. */
        show(before, options) {
            type(before, options)
            view.update()
        },
        press: (key) => plugin.spec.props.handleKeyDown(null, { key }),
        panel: menuPanel,
        labels: () => [...document.querySelectorAll('.fi-arte-slash-item-label')].map((node) => node.textContent),
        groups: () => [...document.querySelectorAll('.fi-arte-slash-group-label')].map((node) => node.textContent),
        active: () => document.querySelector('.fi-arte-slash-item-active')?.textContent ?? null,
    }
}

beforeEach(() => {
    // jsdom does not lay anything out, so it has no way of scrolling to anything.
    Element.prototype.scrollIntoView = vi.fn()
})

afterEach(() => {
    for (const view of views.splice(0)) {
        view.destroy()
    }

    delete window.FilamentRichEditor
    delete window.Alpine
    delete Element.prototype.scrollIntoView
    delete HTMLElement.prototype.offsetHeight
    vi.useRealTimers()
    vi.unstubAllGlobals()
    document.body.replaceChildren()
})

describe('the extension', () => {
    it('is one plugin, under its own key', () => {
        const definition = build()
        const [plugin] = definition.addProseMirrorPlugins.call({ editor: { options: { element: document.createElement('div') } } })

        expect(definition.name).toBe('arteSlashMenu')
        expect(plugin.spec.key.name).toBe('arteSlashMenu')
    })

    it('says so rather than throwing when TipTap is not there', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})

        delete window.FilamentRichEditor

        expect(slashExtension()).toBeNull()
        expect(complaint).toHaveBeenCalled()
    })
})

describe('what the field hands over', () => {
    it('does nothing where the field configured no menu, and does not call that an error', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const field = mount({ menu: null })

        field.show('/')

        expect(field.panel()).toBeNull()
        expect(field.press('ArrowDown')).toBe(false)
        expect(complaint).not.toHaveBeenCalled()
    })

    it('says so, once, where what it was handed cannot be read', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const field = mount({ menu: '{not json' })

        field.show('/')

        expect(field.panel()).toBeNull()
        expect(complaint).toHaveBeenCalledTimes(1)
    })
})

describe('when a slash opens it', () => {
    it('opens on a slash at the start of a block, with everything listed', () => {
        const field = mount()

        field.show('/')

        expect(field.panel()).not.toBeNull()
        expect(field.groups()).toEqual(['Style', 'Insert'])
        expect(field.labels()).toEqual(['Text', 'Bullet list', 'Code block', 'Table', 'Table of contents', 'Image'])
    })

    it('opens on a slash after a space', () => {
        const field = mount()

        field.show('Some words /')

        expect(field.panel()).not.toBeNull()
    })

    it('stays shut for a slash inside a word', () => {
        // `and/or` is a word somebody is writing.
        const field = mount()

        field.show('and/')
        expect(field.panel()).toBeNull()

        field.show('https://')
        expect(field.panel()).toBeNull()
    })

    it('stays shut in code, where a slash is nearly always code', () => {
        const field = mount({ code: true })

        field.show('/')

        expect(field.panel()).toBeNull()
    })

    it('stays shut while something is selected', () => {
        const field = mount()

        field.show('/', { empty: false })

        expect(field.panel()).toBeNull()
    })

    it('counts every leaf in the block as one character, so the offsets stay true', () => {
        const field = mount()

        field.show('/')

        expect(field.textBetween).toHaveBeenCalledWith(0, 1, undefined, '￼')
    })

    it('closes when the query is ended by a space, or by another slash', () => {
        const field = mount()

        field.show('/co')
        expect(field.panel()).not.toBeNull()

        field.show('/co ')
        expect(field.panel()).toBeNull()

        field.show('/co')
        field.show('/co/de')
        expect(field.panel()).toBeNull()
    })

    it('closes when the slash is deleted', () => {
        const field = mount()

        field.show('/')
        field.show('')

        expect(field.panel()).toBeNull()
    })

    it('goes by the character the field was configured with', () => {
        // `+` means something to a regular expression, and here it is a character.
        const field = mount({ menu: { ...MENU, char: '+' } })

        field.show('a +li')

        expect(field.panel()).not.toBeNull()
        expect(field.labels()).toEqual(['Bullet list'])
    })

    it('does not let that character be read as a pattern', () => {
        // Unescaped, `.` would match the `x` and open the menu on it.
        const field = mount({ menu: { ...MENU, char: '.' } })

        field.show('x')

        expect(field.panel()).toBeNull()
    })

    it('does not open a second panel for a second update', () => {
        const field = mount()

        field.show('/')
        field.show('/c')

        expect(document.querySelectorAll('.fi-arte-slash')).toHaveLength(1)
    })
})

describe('what typing narrows it to', () => {
    const RANKED = {
        char: '/',
        empty: 'Nothing found',
        groups: [
            {
                key: 'g',
                label: 'G',
                items: [
                    { name: 'a', label: 'Subtable', icon: '', handler: '' },
                    { name: 'b', label: 'Table', icon: '', handler: '' },
                    { name: 'c', label: 'Other', icon: '', aliases: ['tabular'], handler: '' },
                    { name: 'd', label: 'Nope', icon: '', handler: '' },
                ],
            },
        ],
    }

    it('keeps what contains the query, in the group it came from', () => {
        const field = mount()

        field.show('/tab')

        expect(field.groups()).toEqual(['Insert'])
        expect(field.labels()).toEqual(['Table', 'Table of contents'])
    })

    it('puts what starts with the query before what merely contains it', () => {
        // Somebody typing `/co` means the code block far more often than a table of contents.
        const field = mount({ menu: RANKED })

        field.show('/tab')

        expect(field.labels()).toEqual(['Table', 'Other', 'Subtable'])
    })

    it('finds an entry by the words somebody types instead of its name', () => {
        const field = mount()

        field.show('/ul')

        expect(field.labels()).toEqual(['Bullet list'])
    })

    it('finds an entry by its tool name', () => {
        const field = mount()

        field.show('/codeb')

        expect(field.labels()).toEqual(['Code block'])
    })

    it('does not mind the case, on either side', () => {
        const field = mount()

        field.show('/CODE')
        expect(field.labels()).toEqual(['Code block'])

        // The alias is written in capitals in the menu.
        field.show('/upl')
        expect(field.labels()).toEqual(['Image'])
    })

    it('copes with an entry that has no aliases', () => {
        const field = mount()

        field.show('/contents')

        expect(field.labels()).toEqual(['Table of contents'])
    })

    it('says so where nothing matches, and offers nothing to pick', () => {
        const field = mount()

        field.show('/zzz')

        expect(field.panel().querySelector('.fi-arte-slash-empty').textContent).toBe('Nothing found')
        expect(field.labels()).toEqual([])
        expect(field.press('Enter')).toBe(false)
        expect(field.press('Tab')).toBe(false)
    })

    it('starts at the top again with every letter', () => {
        const field = mount()

        field.show('/')
        field.press('ArrowDown')
        field.press('ArrowDown')
        expect(field.active()).toBe('Code block')

        field.show('/t')

        expect(field.active()).toBe('Text')
    })
})

describe('what is drawn', () => {
    it('is a listbox on the page itself, so that no scrolling field can clip it', () => {
        const field = mount()

        field.show('/')

        expect(field.panel().parentElement).toBe(document.body)
        expect(field.panel().querySelector('.fi-arte-slash-list').getAttribute('role')).toBe('listbox')
    })

    it('numbers the entries across the groups, and makes each an option', () => {
        const field = mount()

        field.show('/')

        const options = [...document.querySelectorAll('.fi-arte-slash-item')]

        expect(options.map((node) => node.dataset.index)).toEqual(['0', '1', '2', '3', '4', '5'])
        expect(options.every((node) => node.getAttribute('role') === 'option')).toBe(true)
        expect(options.every((node) => node.type === 'button')).toBe(true)
    })

    it('puts the icon in as the markup PHP made, and the name in as text', () => {
        const hostile = {
            ...MENU,
            groups: [{ key: 'g', label: 'G', items: [{ name: 'x', label: '<img src=x onerror=alert(1)>', icon: '<svg data-icon="x"></svg>', handler: '' }] }],
        }
        const field = mount({ menu: hostile })

        field.show('/')

        expect(document.querySelector('.fi-arte-slash-item-icon svg[data-icon="x"]')).not.toBeNull()
        expect(document.querySelector('.fi-arte-slash-item img')).toBeNull()
        expect(field.labels()).toEqual(['<img src=x onerror=alert(1)>'])
    })

    it('marks the first entry, and only that one, as the chosen one', () => {
        const field = mount()

        field.show('/')

        const options = [...document.querySelectorAll('.fi-arte-slash-item')]

        expect(options.map((node) => node.getAttribute('aria-selected'))).toEqual(['true', 'false', 'false', 'false', 'false', 'false'])
        expect(field.active()).toBe('Text')
        // Only the chosen one is brought into view, and not every entry as it is drawn.
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(1)
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' })
    })

    it('draws an entry that has no icon with an empty place for one', () => {
        const bare = { ...MENU, groups: [{ key: 'g', label: 'G', items: [{ name: 'x', label: 'Bare', handler: '' }] }] }
        const field = mount({ menu: bare })

        field.show('/')

        expect(document.querySelector('.fi-arte-slash-item-icon').innerHTML).toBe('')
    })

    it('follows the mouse', () => {
        const field = mount()

        field.show('/')
        document.querySelectorAll('.fi-arte-slash-item')[3].dispatchEvent(new MouseEvent('mouseenter'))

        expect(field.active()).toBe('Table')
    })
})

describe('where it is put', () => {
    const near = (coords) => (field) => {
        field.editor.view.coordsAtPos.mockReturnValue(coords)
    }

    const at = (coords, size = {}) => {
        const field = mount()

        if (size.width !== undefined) {
            vi.stubGlobal('innerWidth', size.width)
        }

        if (size.height !== undefined) {
            vi.stubGlobal('innerHeight', size.height)
        }

        near(coords)(field)
        field.show('/')

        return {
            field,
            left: Number.parseFloat(field.panel().style.left),
            top: Number.parseFloat(field.panel().style.top),
        }
    }

    it('asks where the slash is, and opens below that line', () => {
        const { field, left, top } = at({ left: 100, top: 200, bottom: 220 }, { width: 1024, height: 768 })

        expect(field.editor.view.coordsAtPos).toHaveBeenCalledWith(START)
        expect(left).toBe(100)
        expect(top).toBe(226)
    })

    it('opens above the line where there is no room below', () => {
        // The panel has no measured height under jsdom, which is taken as its tallest.
        const { top } = at({ left: 100, top: 680, bottom: 700 }, { width: 1024, height: 768 })

        expect(top).toBe(680 - 6 - 320)
    })

    it('keeps a margin between the panel and the bottom of the window', () => {
        // 320 high, under a line whose bottom is at 434: the panel ends at 760, exactly the
        // margin short of a window 768 high, which still counts as room.
        expect(at({ left: 100, top: 414, bottom: 434 }, { width: 1024, height: 768 }).top).toBe(440)

        // One pixel lower it ends inside the margin, and the panel goes above instead.
        expect(at({ left: 100, top: 415, bottom: 435 }, { width: 1024, height: 768 }).top).toBe(415 - 6 - 320)
    })

    it('never opens above the top of the window', () => {
        const { top } = at({ left: 100, top: 10, bottom: 30 }, { width: 1024, height: 300 })

        expect(top).toBe(8)
    })

    it('goes by the height the panel really has, up to the most it is allowed', () => {
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
            configurable: true,
            get() {
                return this.classList.contains('fi-arte-slash') ? 100 : 0
            },
        })

        // 100 high fits under a line at 600; the 320 it is taken to be without a measurement
        // does not.
        expect(at({ left: 100, top: 580, bottom: 600 }, { width: 1024, height: 768 }).top).toBe(606)

        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
            configurable: true,
            get() {
                return this.classList.contains('fi-arte-slash') ? 900 : 0
            },
        })

        // Taller than the cap: it is 320 that has to fit, and 320 does under a line at 400.
        expect(at({ left: 100, top: 380, bottom: 400 }, { width: 1024, height: 768 }).top).toBe(406)
    })

    it('keeps clear of the left edge and, while there is room, of the right one', () => {
        expect(at({ left: 2, top: 200, bottom: 220 }, { width: 1024, height: 768 }).left).toBe(8)
        expect(at({ left: 1000, top: 200, bottom: 220 }, { width: 1024, height: 768 }).left).toBe(1024 - 256 - 8)
    })

    it('stays on the screen on a window narrower than the panel', () => {
        // The right-hand limit goes negative there, and clamping to it would push the menu off
        // the left of the screen instead.
        expect(at({ left: 100, top: 200, bottom: 220 }, { width: 200, height: 768 }).left).toBe(8)
    })
})

describe('driving it from the keyboard', () => {
    it('is left alone by every key while it is shut', () => {
        const field = mount()

        for (const key of ['Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'a']) {
            expect(field.press(key)).toBe(false)
        }
    })

    it('moves down and up the entries, and round the end', () => {
        const field = mount()

        field.show('/')

        expect(field.press('ArrowDown')).toBe(true)
        expect(field.active()).toBe('Bullet list')

        expect(field.press('ArrowUp')).toBe(true)
        expect(field.active()).toBe('Text')

        // Up from the first is the last, and down from the last is the first.
        field.press('ArrowUp')
        expect(field.active()).toBe('Image')

        field.press('ArrowDown')
        expect(field.active()).toBe('Text')
    })

    it('leaves every other key to the editor', () => {
        const field = mount()

        field.show('/')

        expect(field.press('a')).toBe(false)
        expect(field.press('ArrowLeft')).toBe(false)
        expect(field.panel()).not.toBeNull()
    })

    it('closes on Escape, and stays closed for the rest of that slash', () => {
        const field = mount()

        field.show('/co')
        expect(field.press('Escape')).toBe(true)
        expect(field.panel()).toBeNull()

        // Still typing the same slash: it does not come back.
        field.show('/cod')
        expect(field.panel()).toBeNull()
    })

    it('comes back for the next slash after an Escape', () => {
        const field = mount()

        field.show('/co')
        field.press('Escape')
        // The caret leaves the slash: the menu is finished with it.
        field.show('done')
        field.show('done /')

        expect(field.panel()).not.toBeNull()
    })

    it('picks the chosen entry on Enter and on Tab', () => {
        vi.useFakeTimers({ toFake: ['setTimeout'] })
        window.Alpine = { evaluate: vi.fn() }

        const field = mount()

        field.show('/')
        field.press('ArrowDown')

        expect(field.press('Enter')).toBe(true)
        expect(field.panel()).toBeNull()

        field.show('/')

        expect(field.press('Tab')).toBe(true)
        expect(field.panel()).toBeNull()

        vi.runAllTimers()

        expect(window.Alpine.evaluate).toHaveBeenNthCalledWith(1, field.element, 'toggleBulletList()', expect.anything())
        expect(window.Alpine.evaluate).toHaveBeenNthCalledWith(2, field.element, 'setParagraph()', expect.anything())
    })
})

describe('picking an entry', () => {
    const pick = () => {
        vi.useFakeTimers({ toFake: ['setTimeout'] })
        window.Alpine = { evaluate: vi.fn() }

        const field = mount()

        window.Alpine.evaluate.mockImplementation(() => field.calls.push('run'))

        return field
    }

    it('takes the typed word out first, and puts the menu away', () => {
        const field = pick()

        field.show('Some words /bul')
        field.press('Enter')

        expect(field.panel()).toBeNull()
        expect(field.chain.focus).toHaveBeenCalled()
        // From the slash to the caret: the word that was typed to get here.
        expect(field.chain.deleteRange).toHaveBeenCalledWith({ from: START + 11, to: START + 15 })
        expect(field.chain.run).toHaveBeenCalledTimes(1)
    })

    it('runs the entry\'s own handler in the editor\'s scope, a tick after the word is gone', () => {
        const field = pick()

        field.show('/bul')
        field.press('Enter')

        // Not yet: the handler reads a selection Alpine has not caught up with.
        expect(window.Alpine.evaluate).not.toHaveBeenCalled()

        vi.runAllTimers()

        expect(window.Alpine.evaluate).toHaveBeenCalledWith(field.element, 'toggleBulletList()', {
            $event: { currentTarget: field.element },
        })
        expect(field.calls).toEqual(['delete', 'run'])
    })

    it('picks with the mouse without taking the focus from the text', () => {
        const field = pick()
        const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true })

        field.show('/')
        document.querySelectorAll('.fi-arte-slash-item')[2].dispatchEvent(event)
        vi.runAllTimers()

        expect(event.defaultPrevented).toBe(true)
        expect(window.Alpine.evaluate).toHaveBeenCalledWith(field.element, 'toggleCodeBlock()', expect.anything())
    })

    it('says so, and does not throw, where Alpine is not there to run it', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const field = pick()

        delete window.Alpine
        field.show('/')
        field.press('Enter')

        expect(() => vi.runAllTimers()).not.toThrow()
        expect(complaint).toHaveBeenCalledTimes(1)
        // Said as what is missing, and not as some entry's handler having failed.
        expect(complaint).toHaveBeenCalledWith(expect.stringContaining('needs Alpine'))
    })

    it('names the entry, and does not throw, where its handler fails', () => {
        const complaint = vi.spyOn(console, 'error').mockImplementation(() => {})
        const field = pick()
        const failure = new Error('boom')

        window.Alpine.evaluate.mockImplementation(() => {
            throw failure
        })
        field.show('/')
        field.press('Enter')

        expect(() => vi.runAllTimers()).not.toThrow()
        expect(complaint).toHaveBeenCalledWith(expect.stringContaining('[paragraph]'), failure)
    })
})

describe('putting it away', () => {
    it('closes on a press outside it, and not on one inside it', () => {
        const add = vi.spyOn(document, 'addEventListener')
        const field = mount()
        const outside = document.createElement('div')

        document.body.append(outside)
        field.show('/')

        // In the capture phase, so that a page that stops the event on its way down still
        // closes the menu.
        expect(add).toHaveBeenCalledWith('mousedown', expect.any(Function), true)

        field.panel().querySelector('.fi-arte-slash-group-label').dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
        expect(field.panel()).not.toBeNull()

        outside.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
        expect(field.panel()).toBeNull()
    })

    it('stops listening to the page once it is closed', () => {
        const remove = vi.spyOn(document, 'removeEventListener')
        const field = mount()

        field.show('/')
        field.press('Escape')

        expect(remove).toHaveBeenCalledWith('mousedown', expect.any(Function), true)
    })

    it('goes with the editor', () => {
        const field = mount()

        field.show('/')
        field.view.destroy()

        expect(field.panel()).toBeNull()
    })
})
