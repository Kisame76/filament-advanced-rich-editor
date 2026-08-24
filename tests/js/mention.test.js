import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { MentionMenu, SEARCH_DEBOUNCE, filterItems, normalizeItems, queryInText, renderRow } from '../../resources/js/mention.js'

/**
 * The mention menu.
 *
 * The parts worth testing are the ones that decide what a row says and whether the menu
 * opens at all - the ProseMirror wiring around them is three lines and is asserted by
 * using the editor. Everything here is a plain function over plain data for exactly that
 * reason.
 */

describe('finding the trigger the caret sits in', () => {
    it('opens on a trigger at the start of a block', () => {
        expect(queryInText('@ann', ['@'])).toEqual({ char: '@', query: 'ann', start: 0 })
    })

    it('opens on a trigger after a space', () => {
        expect(queryInText('hello @ann', ['@'])).toEqual({ char: '@', query: 'ann', start: 6 })
    })

    it('opens on the trigger alone, before anything is typed', () => {
        expect(queryInText('@', ['@'])).toEqual({ char: '@', query: '', start: 0 })
    })

    it('stays shut inside a word, because an email is not a mention', () => {
        expect(queryInText('ann@example.test', ['@'])).toBeNull()
    })

    it('stays shut once the name is finished with a space', () => {
        expect(queryInText('@ann wrote', ['@'])).toBeNull()
    })

    it('knows one trigger from another', () => {
        expect(queryInText('see #laravel', ['@', '#'])).toEqual({ char: '#', query: 'laravel', start: 4 })
        expect(queryInText('see #laravel', ['@'])).toBeNull()
    })

    it('reads the trigger nearest the caret when two are typed', () => {
        expect(queryInText('@ann #lara', ['@', '#'])).toEqual({ char: '#', query: 'lara', start: 5 })
    })

    it('escapes a trigger that means something in a regular expression', () => {
        expect(queryInText('a $usd', ['$'])).toEqual({ char: '$', query: 'usd', start: 2 })
        expect(queryInText('a +one', ['+'])).toEqual({ char: '+', query: 'one', start: 2 })
    })

    it('has nothing to open on an empty line or without triggers', () => {
        expect(queryInText('', ['@'])).toBeNull()
        expect(queryInText('@ann', [])).toBeNull()
    })
})

describe('reading what the server sent', () => {
    it("takes the map Filament's own providers return", () => {
        expect(normalizeItems({ 1: 'Ada', 2: 'Grace' })).toEqual([
            { id: '1', label: 'Ada', avatar: null, hint: null },
            { id: '2', label: 'Grace', avatar: null, hint: null },
        ])
    })

    it('takes a list of rows carrying more than a label', () => {
        expect(normalizeItems([
            { id: 7, label: 'Ada Lovelace', avatar: '/ada.jpg', hint: 'Mathematician' },
        ])).toEqual([
            { id: '7', label: 'Ada Lovelace', avatar: '/ada.jpg', hint: 'Mathematician' },
        ])
    })

    it('reads a name where there is no label, the way Filament does', () => {
        expect(normalizeItems([{ id: 7, name: 'Ada' }])[0].label).toBe('Ada')
    })

    it('falls back to the id rather than drawing an empty row', () => {
        expect(normalizeItems([{ id: 7 }])[0].label).toBe('7')
    })

    it('takes a bare string as its own id and label', () => {
        expect(normalizeItems(['Ada'])).toEqual([
            { id: 'Ada', label: 'Ada', avatar: null, hint: null },
        ])
    })

    it('drops a row that identifies nobody', () => {
        expect(normalizeItems([{ label: 'Ada' }, null, {}])).toEqual([])
    })

    it('has nothing to read in nothing', () => {
        expect(normalizeItems(null)).toEqual([])
        expect(normalizeItems(undefined)).toEqual([])
    })
})

describe('narrowing the list', () => {
    const items = normalizeItems([
        { id: 1, label: 'Grace Hopper', hint: 'Rear admiral' },
        { id: 2, label: 'Ada Lovelace', hint: 'Mathematician' },
        { id: 3, label: 'Alan Turing', hint: 'Grace under fire' },
    ])

    it('keeps everything when nothing is typed', () => {
        expect(filterItems(items, '')).toHaveLength(3)
    })

    it('puts what starts with the query before what merely contains it', () => {
        expect(filterItems(items, 'grace').map((item) => item.id)).toEqual(['1', '3'])
    })

    it('searches the second line as well as the name', () => {
        expect(filterItems(items, 'mathem').map((item) => item.id)).toEqual(['2'])
    })

    it('ignores case', () => {
        expect(filterItems(items, 'ADA').map((item) => item.id)).toEqual(['2'])
    })

    it('answers nothing when nothing matches', () => {
        expect(filterItems(items, 'zzz')).toEqual([])
    })
})

describe('drawing a row', () => {
    it('is a button carrying the label', () => {
        const row = renderRow(normalizeItems([{ id: 1, label: 'Ada' }])[0], false)

        expect(row.tagName).toBe('BUTTON')
        expect(row.type).toBe('button')
        expect(row.textContent).toContain('Ada')
        expect(row.className).toContain('fi-arte-mention-item')
    })

    it('says which row is selected, for the eye and for a screen reader', () => {
        const item = normalizeItems([{ id: 1, label: 'Ada' }])[0]

        expect(renderRow(item, true).getAttribute('aria-selected')).toBe('true')
        expect(renderRow(item, true).className).toContain('fi-arte-mention-item-active')
        expect(renderRow(item, false).getAttribute('aria-selected')).toBe('false')
    })

    it('draws an avatar where there is one', () => {
        const row = renderRow(normalizeItems([{ id: 1, label: 'Ada', avatar: '/ada.jpg' }])[0], false)
        const avatar = row.querySelector('img')

        expect(avatar?.getAttribute('src')).toBe('/ada.jpg')
        // Decoration rather than information: the label beside it already names the person,
        // and a screen reader reading the name twice is worse than one that does not.
        expect(avatar?.getAttribute('alt')).toBe('')
        expect(avatar?.getAttribute('loading')).toBe('lazy')
    })

    it('draws initials where there is no avatar, rather than a hole in the row', () => {
        const row = renderRow(normalizeItems([{ id: 1, label: 'Ada Lovelace' }])[0], false)

        expect(row.querySelector('img')).toBeNull()
        expect(row.querySelector('.fi-arte-mention-avatar')?.textContent).toBe('AL')
    })

    it('draws a second line where there is one, and no empty one where there is not', () => {
        const withHint = renderRow(normalizeItems([{ id: 1, label: 'Ada', hint: 'Mathematician' }])[0], false)
        const without = renderRow(normalizeItems([{ id: 1, label: 'Ada' }])[0], false)

        expect(withHint.querySelector('.fi-arte-mention-hint')?.textContent).toBe('Mathematician')
        expect(without.querySelector('.fi-arte-mention-hint')).toBeNull()
    })

    it("writes text rather than markup, because the label is somebody else's data", () => {
        const row = renderRow(normalizeItems([{ id: 1, label: '<img src=x onerror=alert(1)>' }])[0], false)

        expect(row.querySelector('img')).toBeNull()
        expect(row.textContent).toContain('<img src=x onerror=alert(1)>')
    })
})

describe('asking the server', () => {
    const editor = { options: { element: document.createElement('div') } }

    const menuFor = (trigger, wire = null) => {
        window.Alpine = { evaluate: () => wire }

        const menu = new MentionMenu(editor)
        menu.config = { key: 'editor-key', triggers: [trigger] }
        menu.trigger = trigger
        menu.range = { from: 1, to: 2 }
        // The panel positions itself against the caret, which needs a real editor view.
        menu.position = () => {}

        return menu
    }

    const searchable = {
        char: '@',
        items: [],
        isSearchable: true,
        searchingMessage: 'Searching…',
        noSearchResultsMessage: 'Nobody by that name.',
        searchPrompt: 'Type to search.',
    }

    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
        delete window.Alpine
        document.body.innerHTML = ''
    })

    it('filters a list it was handed without asking anyone', () => {
        const menu = menuFor({
            char: '#',
            items: { 1: 'Backend', 2: 'Frontend' },
            isSearchable: false,
        })

        menu.query = 'back'
        menu.load()

        expect(menu.items.map((item) => item.label)).toEqual(['Backend'])
    })

    it('waits out a burst of typing before it asks', async () => {
        const wire = { callSchemaComponentMethod: vi.fn(async () => ({ 1: 'Ada' })) }
        const menu = menuFor(searchable, wire)

        menu.query = 'a'
        menu.load()
        vi.advanceTimersByTime(200)
        menu.query = 'ad'
        menu.load()
        vi.advanceTimersByTime(200)

        expect(wire.callSchemaComponentMethod).not.toHaveBeenCalled()

        vi.advanceTimersByTime(100)

        expect(wire.callSchemaComponentMethod).toHaveBeenCalledOnce()
        expect(wire.callSchemaComponentMethod).toHaveBeenCalledWith(
            'editor-key',
            'getMentionSearchResultsForJs',
            { search: 'ad', char: '@' },
        )
    })

    it('narrows what is on screen while the next answer is on its way', async () => {
        const wire = { callSchemaComponentMethod: vi.fn(async () => ({ 1: 'Ada Lovelace', 2: 'Alan Turing' })) }
        const menu = menuFor(searchable, wire)

        menu.query = 'a'
        menu.load()
        await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE)

        expect(menu.items).toHaveLength(2)

        // The second keystroke must not empty the panel: a menu that goes blank between two
        // letters reads as a menu that found nobody.
        menu.query = 'ada'
        menu.load()

        expect(menu.items.map((item) => item.label)).toEqual(['Ada Lovelace'])
        expect(menu.loading).toBe(false)
    })

    it('says it is searching only while there is nothing to show', async () => {
        const wire = { callSchemaComponentMethod: vi.fn(async () => ({})) }
        const menu = menuFor(searchable, wire)

        menu.query = 'zz'
        menu.load()
        vi.advanceTimersByTime(SEARCH_DEBOUNCE)

        expect(menu.loading).toBe(true)
        expect(menu.panel?.textContent).toBe('Searching…')

        await vi.runAllTimersAsync()

        expect(menu.loading).toBe(false)
        expect(menu.panel?.textContent).toBe('Nobody by that name.')
    })

    it('ignores an answer to a name that has since been typed further', async () => {
        let release
        const wire = {
            callSchemaComponentMethod: vi.fn(() => new Promise((resolve) => {
                release = resolve
            })),
        }
        const menu = menuFor(searchable, wire)

        menu.query = 'a'
        menu.load()
        vi.advanceTimersByTime(SEARCH_DEBOUNCE)

        menu.query = 'ada'
        menu.load()
        vi.advanceTimersByTime(SEARCH_DEBOUNCE)

        release({ 1: 'Stale' })
        await vi.runAllTimersAsync()

        expect(menu.items.map((item) => item.label)).not.toContain('Stale')
    })

    it('forgets what it found once the menu is closed', async () => {
        const wire = { callSchemaComponentMethod: vi.fn(async () => ({ 1: 'Ada' })) }
        const menu = menuFor(searchable, wire)

        menu.query = 'a'
        menu.load()
        await vi.advanceTimersByTimeAsync(SEARCH_DEBOUNCE)

        expect(menu.items).toHaveLength(1)

        menu.close()

        expect(menu.items).toEqual([])
        expect(menu.results).toEqual([])
    })
})
