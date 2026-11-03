import { afterEach, describe, expect, it, vi } from 'vitest'
import imageLoadingExtension, { loadingHint } from '../../resources/js/image-loading.js'

/**
 * The loading hint a picture carries, on the editor's side.
 *
 * Filament's image node declares `id`, `width` and `height` on top of TipTap's own three and
 * nothing else, and TipTap drops what nothing declares - so a hint written on insert, or
 * stored by an earlier save, was gone the moment the editor sent its document back. The
 * declaration is what these tests hold on to; `loadingHint` is the half with a twin in PHP,
 * `ImageAttributes::loadingHint()`, and the two have to agree on what a hint is.
 */

const build = () => {
    window.FilamentRichEditor = {
        tiptap: {
            core: { Extension: { create: (definition) => definition } },
        },
    }

    return imageLoadingExtension()
}

const loadingAttribute = () => {
    const [global] = build().addGlobalAttributes()

    return { types: global.types, attribute: global.attributes.loading }
}

const imageWith = (loading) => {
    const element = document.createElement('img')

    if (loading !== undefined) {
        element.setAttribute('loading', loading)
    }

    return element
}

afterEach(() => {
    delete window.FilamentRichEditor
})

describe('what counts as a loading hint', () => {
    it('keeps the two a browser knows', () => {
        expect(loadingHint('lazy')).toBe('lazy')
        expect(loadingHint('eager')).toBe('eager')
    })

    it('folds case and space the way PHP does', () => {
        expect(loadingHint('  LAZY ')).toBe('lazy')
        expect(loadingHint('Eager')).toBe('eager')
    })

    it('drops what is not one of them', () => {
        // `auto` is what a browser does without the attribute, not a value it has.
        expect(loadingHint('auto')).toBeNull()
        expect(loadingHint('lasy')).toBeNull()
        expect(loadingHint('')).toBeNull()
        expect(loadingHint(null)).toBeNull()
        expect(loadingHint(undefined)).toBeNull()
        expect(loadingHint(7)).toBeNull()
    })
})

describe('the hint on the image node', () => {
    it('is declared on the image and on nothing else', () => {
        const { types, attribute } = loadingAttribute()

        expect(types).toEqual(['image'])
        expect(attribute.default).toBeNull()
    })

    it('is read off a stored picture', () => {
        const { attribute } = loadingAttribute()

        expect(attribute.parseHTML(imageWith('lazy'))).toBe('lazy')
        expect(attribute.parseHTML(imageWith('EAGER'))).toBe('eager')
    })

    it('reads nothing where there is no hint to keep', () => {
        const { attribute } = loadingAttribute()

        expect(attribute.parseHTML(imageWith())).toBeNull()
        expect(attribute.parseHTML(imageWith('auto'))).toBeNull()
    })

    it('is written back only when it is one of the two', () => {
        const { attribute } = loadingAttribute()

        expect(attribute.renderHTML({ loading: 'lazy' })).toEqual({ loading: 'lazy' })
        expect(attribute.renderHTML({ loading: 'eager' })).toEqual({ loading: 'eager' })
        // An attribute set through a command rather than parsed is not checked on the way
        // in, so the check is made again on the way out.
        expect(attribute.renderHTML({ loading: 'auto' })).toEqual({})
        expect(attribute.renderHTML({ loading: null })).toEqual({})
    })

    it('says so and gives up when Filament exposed no TipTap', () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {})

        expect(imageLoadingExtension()).toBeNull()
        expect(error).toHaveBeenCalledOnce()
    })
})
