import { afterEach, describe, expect, it, vi } from 'vitest'
import sharedSettingsExtension, { STORE, share } from '../../resources/js/shared-settings.js'

/**
 * The settings a page's editors share, put back on every editor that only names them.
 *
 * The server writes the slash menu, both pickers' strings and the find bar's in full on the
 * first editor of a render and only a key on the rest (`SharedSettingsTest` holds that half).
 * What is tested here is the other half: every editor ends up carrying all of them under the
 * attribute it always read, whichever of the editors starts first and whether or not the one
 * that carried them is still on the page.
 */

const SLASH = JSON.stringify({ char: '/', groups: [] })
const FIND = JSON.stringify({ label: 'Find and replace' })

/** An editor element as the server writes it, carrying its settings or only naming them. */
const editorElement = ({ carries = false, keys = { arteSlash: 'k-slash', arteFind: 'k-find' } } = {}) => {
    const element = document.createElement('div')

    element.dataset.arteShared = JSON.stringify(keys)

    if (carries) {
        element.dataset.arteSlash = SLASH
        element.dataset.arteFind = FIND
        element.dataset.arteCarries = Object.values(keys).join(' ')
    }

    document.body.append(element)

    return element
}

afterEach(() => {
    document.body.innerHTML = ''
    delete window[STORE]
    delete window.FilamentRichEditor
    vi.restoreAllMocks()
})

describe('sharing', () => {
    it('keeps what an editor carries, for the editors that start after it', () => {
        const store = {}

        share(editorElement({ carries: true }), store)

        expect(store).toEqual({ 'k-slash': SLASH, 'k-find': FIND })
    })

    it('puts the settings on an editor that only names them, under the attribute it always read', () => {
        const store = { 'k-slash': SLASH, 'k-find': FIND }
        const element = editorElement()

        share(element, store)

        expect(element.dataset.arteSlash).toBe(SLASH)
        expect(element.dataset.arteFind).toBe(FIND)
    })

    it('finds them on the editor that carries them when that one has not started yet', () => {
        // Editors do not start in the order they stand in: each waits for its own scripts. The
        // one carrying the settings arrived in the same response, so it is in the page.
        editorElement({ carries: true })
        const later = editorElement()
        const store = {}

        share(later, store)

        expect(later.dataset.arteSlash).toBe(SLASH)
        expect(store['k-find']).toBe(FIND)
    })

    it('still finds them after the editor that carried them has gone', () => {
        // A repeater item deleted, a modal closed: the store is what outlives the carrier.
        const carrier = editorElement({ carries: true })
        const store = {}

        share(carrier, store)
        carrier.remove()

        const added = editorElement()

        share(added, store)

        expect(added.dataset.arteSlash).toBe(SLASH)
    })

    it('reads a carried setting over the one in the store', () => {
        // A key is a hash of what it names, so the two are the same - but what is on the
        // element is what the server sent with it, and it is the one that is kept.
        const store = { 'k-slash': 'stale' }

        share(editorElement({ carries: true }), store)

        expect(store['k-slash']).toBe(SLASH)
    })

    it('says so when a setting is nowhere to be found, and leaves the attribute missing', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})
        const element = editorElement()

        share(element, {})

        expect(element.dataset.arteSlash).toBeUndefined()
        expect(error).toHaveBeenCalledWith(expect.stringContaining('arteSlash'))
    })

    it('leaves an editor alone that shares nothing', () => {
        const element = document.createElement('div')
        const store = {}

        share(element, store)

        expect(store).toEqual({})
        expect({ ...element.dataset }).toEqual({})
    })

    it('says so when the list of what is shared cannot be read', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})
        const element = document.createElement('div')

        element.dataset.arteShared = '{'

        share(element, {})

        expect(error).toHaveBeenCalled()
    })

    it('keeps its store on the window, one for the page', () => {
        share(editorElement({ carries: true }))

        expect(window[STORE]['k-slash']).toBe(SLASH)
    })
})

describe('as an extension', () => {
    it('shares before the editor builds its view, which is where the readers start', () => {
        // TipTap calls `onBeforeCreate` before any plugin's view exists: the slash menu reads
        // its list as its view is made, the find bar when the editor has been created.
        window.FilamentRichEditor = { tiptap: { core: { Extension: { create: (definition) => definition } } } }

        const extension = sharedSettingsExtension()
        const element = editorElement()

        window[STORE] = { 'k-slash': SLASH, 'k-find': FIND }

        extension.onBeforeCreate.call({ editor: { options: { element } } })

        expect(extension.name).toBe('arteSharedSettings')
        expect(element.dataset.arteSlash).toBe(SLASH)
    })

    it('says so and registers nothing without the TipTap Filament exposes', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(sharedSettingsExtension()).toBeNull()
        expect(error).toHaveBeenCalled()
    })
})
