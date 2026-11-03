import { afterEach, describe, expect, it, vi } from 'vitest'
import shortcutsExtension, { keymapOf, readBindings, runTool } from '../../resources/js/shortcuts.js'

/**
 * The keys a project moved, on the editor's side. Which key runs what arrives finished from
 * `Shortcuts::bindings()`; what is tested here is that the script binds exactly that - a key
 * runs its tool's own handler, a key taken away is swallowed - and only while it may.
 */

const elementWith = (bindings) => {
    const element = document.createElement('div')

    if (bindings !== undefined) {
        element.dataset.arteShortcuts = typeof bindings === 'string' ? bindings : JSON.stringify(bindings)
    }

    return element
}

afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
})

describe('reading the bindings', () => {
    it('reads what the field handed over', () => {
        const bindings = [{ keys: 'Mod-k', tool: 'link', handler: 'mountToolAction(...)' }]

        expect(readBindings(elementWith(bindings))).toEqual(bindings)
    })

    it('binds nothing where there is nothing, or nothing it can read', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(readBindings(elementWith())).toEqual([])
        expect(readBindings(elementWith('{not json'))).toEqual([])
        expect(readBindings(elementWith({ keys: 'Mod-k' }))).toEqual([])
        expect(readBindings(elementWith([{ tool: 'link' }, null]))).toEqual([])
        expect(readBindings(undefined)).toEqual([])
    })
})

describe('the keymap', () => {
    const bindings = [
        { keys: 'Mod-k', tool: 'link', handler: 'open()' },
        { keys: 'Mod-b', tool: 'bold', handler: null },
    ]

    it('runs the tool a key was given to, and answers it', () => {
        const run = vi.fn()
        const element = elementWith()
        const keymap = keymapOf(bindings, element, () => true, run)

        expect(Object.keys(keymap)).toEqual(['Mod-k', 'Mod-b'])
        expect(keymap['Mod-k']()).toBe(true)
        expect(run).toHaveBeenCalledWith(element, bindings[0])
    })

    it('swallows a key that was taken away, so the extension that shipped it never hears it', () => {
        const run = vi.fn()

        expect(keymapOf(bindings, elementWith(), () => true, run)['Mod-b']()).toBe(true)
        expect(run).not.toHaveBeenCalled()
    })

    it('leaves every key alone while the field cannot be written in', () => {
        const run = vi.fn()
        const keymap = keymapOf(bindings, elementWith(), () => false, run)

        expect(keymap['Mod-k']()).toBe(false)
        expect(keymap['Mod-b']()).toBe(false)
        expect(run).not.toHaveBeenCalled()
    })
})

describe('running a tool', () => {
    it("evaluates the button's own handler in the editor's Alpine scope", () => {
        const evaluate = vi.fn()
        const element = elementWith()

        vi.stubGlobal('Alpine', { evaluate })

        runTool(element, { tool: 'link', handler: 'mountToolAction("link")' })

        expect(evaluate).toHaveBeenCalledWith(element, 'mountToolAction("link")', {
            scope: { $event: { currentTarget: element } },
        })
    })

    it('says which tool failed rather than throwing into the keystroke', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        vi.stubGlobal('Alpine', {
            evaluate: () => {
                throw new Error('nope')
            },
        })

        expect(() => runTool(elementWith(), { tool: 'link', handler: 'x' })).not.toThrow()
        expect(error.mock.calls[0][0]).toContain('[link]')
    })
})

describe('the extension', () => {
    afterEach(() => {
        delete window.FilamentRichEditor
    })

    it('reads the bindings off the element the editor was mounted on, ahead of every other extension', () => {
        window.FilamentRichEditor = { tiptap: { core: { Extension: { create: (definition) => definition } } } }

        const extension = shortcutsExtension()
        const element = elementWith([{ keys: 'Mod-k', tool: 'link', handler: 'open()' }])

        extension.editor = { options: { element }, isEditable: true }

        expect(extension.priority).toBeGreaterThan(1000)
        expect(Object.keys(extension.addKeyboardShortcuts.call(extension))).toEqual(['Mod-k'])
    })

    it('says so when Filament did not expose TipTap', () => {
        vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(shortcutsExtension()).toBeNull()
    })
})
